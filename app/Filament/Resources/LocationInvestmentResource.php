<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LocationInvestmentResource\Pages;
use App\Models\LocationInvestment;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LocationInvestmentResource extends Resource
{
    protected static ?string $model = LocationInvestment::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';
    
    protected static ?string $navigationGroup = 'Investment Management';
    
    protected static ?string $navigationLabel = 'Location Investment Settings';
    
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Location Investment Configuration')
                    ->schema([
                        Forms\Components\Select::make('location_id')
                            ->label('Location')
                            ->relationship('location', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->live()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $location = \App\Models\Location::find($state);
                                    if ($location) {
                                        // Auto-calculate current totals
                                        $totalInvested = $location->investments()
                                            ->where('status', 'completed')
                                            ->sum('amount');
                                        $totalInvestors = $location->investments()
                                            ->where('status', 'completed')
                                            ->distinct('user_id')
                                            ->count();
                                        
                                        $set('total_invested', $totalInvested);
                                        $set('total_investors', $totalInvestors);
                                    }
                                }
                            }),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('total_invested')
                                    ->label('Total Invested (£)')
                                    ->numeric()
                                    ->prefix('£')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->helperText('Auto-calculated from completed investments'),

                                Forms\Components\TextInput::make('investment_limit')
                                    ->label('Investment Limit (£)')
                                    ->numeric()
                                    ->prefix('£')
                                    ->required()
                                    ->default(10000)
                                    ->minValue(1)
                                    ->maxValue(1000000)
                                    ->live()
                                    ->helperText('Maximum amount that can be invested in this location'),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('total_investors')
                                    ->label('Total Investors')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->helperText('Auto-calculated from completed investments'),

                                Forms\Components\Toggle::make('is_open_for_investment')
                                    ->label('Open for Investment')
                                    ->default(true)
                                    ->helperText('Whether this location is accepting new investments'),
                            ]),

                        Forms\Components\Placeholder::make('remaining_amount')
                            ->label('Remaining Available')
                            ->content(function (callable $get) {
                                $totalInvested = (float) ($get('total_invested') ?? 0);
                                $investmentLimit = (float) ($get('investment_limit') ?? 0);
                                $remaining = max(0, $investmentLimit - $totalInvested);
                                return '£' . number_format($remaining, 2);
                            })
                            ->extraAttributes(['class' => 'text-lg font-bold text-success-600']),

                        Forms\Components\Placeholder::make('progress_percentage')
                            ->label('Investment Progress')
                            ->content(function (callable $get) {
                                $totalInvested = (float) ($get('total_invested') ?? 0);
                                $investmentLimit = (float) ($get('investment_limit') ?? 0);
                                if ($investmentLimit == 0) return '0%';
                                $percentage = ($totalInvested / $investmentLimit) * 100;
                                return number_format($percentage, 1) . '%';
                            })
                            ->extraAttributes(['class' => 'text-lg font-bold text-primary-600']),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('location.name')
                    ->label('Location')
                    ->searchable()
                    ->sortable()
                    ->description(fn (LocationInvestment $record): string => $record->location->city . ', ' . $record->location->postcode),

                Tables\Columns\TextColumn::make('total_invested')
                    ->label('Total Invested')
                    ->money('GBP')
                    ->sortable()
                    ->alignEnd()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('investment_limit')
                    ->label('Investment Limit')
                    ->money('GBP')
                    ->sortable()
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('remaining_amount')
                    ->label('Remaining')
                    ->state(function (LocationInvestment $record): float {
                        return max(0, $record->investment_limit - $record->total_invested);
                    })
                    ->money('GBP')
                    ->alignEnd()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger'),

                Tables\Columns\TextColumn::make('progress_percentage')
                    ->label('Progress')
                    ->state(function (LocationInvestment $record): string {
                        if ($record->investment_limit == 0) return '0%';
                        $percentage = ($record->total_invested / $record->investment_limit) * 100;
                        return number_format($percentage, 1) . '%';
                    })
                    ->badge(),

                Tables\Columns\TextColumn::make('total_investors')
                    ->label('Investors')
                    ->alignCenter()
                    ->badge()
                    ->color('primary'),

                Tables\Columns\IconColumn::make('is_open_for_investment')
                    ->label('Open')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_open_for_investment')
                    ->label('Investment Status')
                    ->trueLabel('Open for Investment')
                    ->falseLabel('Closed for Investment'),

                Tables\Filters\Filter::make('investment_progress')
                    ->form([
                        Forms\Components\Select::make('progress_level')
                            ->label('Investment Progress Level')
                            ->options([
                                'under_25' => 'Under 25%',
                                '25_to_50' => '25% - 50%',
                                '50_to_75' => '50% - 75%',
                                '75_to_100' => '75% - 100%',
                                'fully_funded' => 'Fully Funded (100%+)',
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (!isset($data['progress_level'])) {
                            return $query;
                        }

                        return $query->where(function ($query) use ($data) {
                            switch ($data['progress_level']) {
                                case 'under_25':
                                    $query->whereRaw('(total_invested / investment_limit) < 0.25');
                                    break;
                                case '25_to_50':
                                    $query->whereRaw('(total_invested / investment_limit) >= 0.25')
                                          ->whereRaw('(total_invested / investment_limit) < 0.50');
                                    break;
                                case '50_to_75':
                                    $query->whereRaw('(total_invested / investment_limit) >= 0.50')
                                          ->whereRaw('(total_invested / investment_limit) < 0.75');
                                    break;
                                case '75_to_100':
                                    $query->whereRaw('(total_invested / investment_limit) >= 0.75')
                                          ->whereRaw('(total_invested / investment_limit) < 1.0');
                                    break;
                                case 'fully_funded':
                                    $query->whereRaw('total_invested >= investment_limit');
                                    break;
                            }
                        });
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('refresh_totals')
                    ->label('Refresh Totals')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->action(function (LocationInvestment $record) {
                        $location = $record->location;
                        
                        $totalInvested = $location->investments()
                            ->where('status', 'completed')
                            ->sum('amount');

                        $totalInvestors = $location->investments()
                            ->where('status', 'completed')
                            ->distinct('user_id')
                            ->count();

                        $record->update([
                            'total_invested' => $totalInvested,
                            'total_investors' => $totalInvestors,
                        ]);
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Refresh Investment Totals')
                    ->modalDescription('This will recalculate the total invested amount and investor count based on completed investments.'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('refresh_all_totals')
                        ->label('Refresh All Totals')
                        ->icon('heroicon-o-arrow-path')
                        ->color('info')
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                $location = $record->location;
                                
                                $totalInvested = $location->investments()
                                    ->where('status', 'completed')
                                    ->sum('amount');

                                $totalInvestors = $location->investments()
                                    ->where('status', 'completed')
                                    ->distinct('user_id')
                                    ->count();

                                $record->update([
                                    'total_invested' => $totalInvested,
                                    'total_investors' => $totalInvestors,
                                ]);
                            }
                        })
                        ->requiresConfirmation(),

                    Tables\Actions\BulkAction::make('close_for_investment')
                        ->label('Close for Investment')
                        ->icon('heroicon-o-lock-closed')
                        ->color('danger')
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                $record->update(['is_open_for_investment' => false]);
                            }
                        })
                        ->requiresConfirmation(),

                    Tables\Actions\BulkAction::make('open_for_investment')
                        ->label('Open for Investment')
                        ->icon('heroicon-o-lock-open')
                        ->color('success')
                        ->action(function ($records) {
                            foreach ($records as $record) {
                                $record->update(['is_open_for_investment' => true]);
                            }
                        })
                        ->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('total_invested', 'desc')
            ->striped();
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLocationInvestments::route('/'),
            'create' => Pages\CreateLocationInvestment::route('/create'),
            'view' => Pages\ViewLocationInvestment::route('/{record}'),
            'edit' => Pages\EditLocationInvestment::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $fullyFunded = static::getModel()::whereRaw('total_invested >= investment_limit')->count();
        return $fullyFunded > 0 ? (string) $fullyFunded : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }
}