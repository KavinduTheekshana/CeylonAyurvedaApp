<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvestmentResource\Pages;
use App\Models\Investment;
use App\Models\Location;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class InvestmentResource extends Resource
{
    protected static ?string $model = Investment::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    
    protected static ?string $navigationGroup = 'Investment Management';
    
    protected static ?string $navigationLabel = 'Investments';
    
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Investment Details')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('user_id')
                                    ->label('Investor')
                                    ->relationship('user', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->createOptionForm([
                                        Forms\Components\TextInput::make('name')
                                            ->required()
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('email')
                                            ->email()
                                            ->required()
                                            ->unique(User::class, 'email')
                                            ->maxLength(255),
                                        Forms\Components\TextInput::make('password')
                                            ->password()
                                            ->required()
                                            ->minLength(8),
                                    ]),

                                Forms\Components\Select::make('location_id')
                                    ->label('Location')
                                    ->relationship('location', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        // You could add logic here to show location investment limits
                                        if ($state) {
                                            $location = Location::find($state);
                                            if ($location && $location->locationInvestment) {
                                                $remaining = $location->locationInvestment->remaining_amount;
                                                // Could set a helper text or validation
                                            }
                                        }
                                    }),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('amount')
                                    ->label('Investment Amount (£)')
                                    ->numeric()
                                    ->prefix('£')
                                    ->required()
                                    ->minValue(10)
                                    ->maxValue(10000)
                                    ->step(0.01)
                                    ->live(debounce: 500)
                                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                        // Auto-generate reference when amount is set
                                        if ($state && !$get('reference')) {
                                            $set('reference', Investment::generateReference());
                                        }
                                    }),

                                Forms\Components\Select::make('currency')
                                    ->options([
                                        'GBP' => 'British Pound (£)',
                                        'USD' => 'US Dollar ($)',
                                        'EUR' => 'Euro (€)',
                                    ])
                                    ->default('GBP')
                                    ->required(),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('status')
                                    ->options([
                                        'pending' => 'Pending',
                                        'processing' => 'Processing',
                                        'completed' => 'Completed',
                                        'failed' => 'Failed',
                                        'refunded' => 'Refunded',
                                    ])
                                    ->default('completed') // For manual entries, usually completed
                                    ->required()
                                    ->live(),

                                Forms\Components\TextInput::make('reference')
                                    ->label('Reference Number')
                                    ->unique(ignoreRecord: true)
                                    ->default(fn () => Investment::generateReference())
                                    ->required()
                                    ->maxLength(255),
                            ]),

                        Forms\Components\DateTimePicker::make('invested_at')
                            ->label('Investment Date')
                            ->default(now())
                            ->required()
                            ->native(false),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->placeholder('Any additional notes about this investment...')
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Payment Information')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('stripe_payment_intent_id')
                                    ->label('Stripe Payment Intent ID')
                                    ->placeholder('pi_1234567890...')
                                    ->maxLength(255),

                                Forms\Components\TextInput::make('stripe_payment_method_id')
                                    ->label('Stripe Payment Method ID')
                                    ->placeholder('pm_1234567890...')
                                    ->maxLength(255),
                            ]),

                        Forms\Components\KeyValue::make('stripe_metadata')
                            ->label('Additional Metadata')
                            ->keyLabel('Field')
                            ->valueLabel('Value')
                            ->addActionLabel('Add metadata'),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference')
                    ->label('Reference')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Reference copied!')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Investor')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Investment $record): string => $record->user->email),

                Tables\Columns\TextColumn::make('location.name')
                    ->label('Location')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Investment $record): string => $record->location->city),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->money('GBP')
                    ->sortable()
                    ->alignEnd()
                    ->weight('bold'),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'info' => 'processing',
                        'success' => 'completed',
                        'danger' => 'failed',
                        'gray' => 'refunded',
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('invested_at')
                    ->label('Investment Date')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->description(fn (Investment $record): string => $record->invested_at?->diffForHumans() ?? ''),

                Tables\Columns\TextColumn::make('stripe_payment_intent_id')
                    ->label('Stripe ID')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->copyable()
                    ->placeholder('Manual entry'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'processing' => 'Processing',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                        'refunded' => 'Refunded',
                    ])
                    ->multiple(),

                Tables\Filters\SelectFilter::make('location_id')
                    ->label('Location')
                    ->relationship('location', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('amount_range')
                    ->form([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('amount_from')
                                    ->label('Amount From (£)')
                                    ->numeric()
                                    ->prefix('£'),
                                Forms\Components\TextInput::make('amount_to')
                                    ->label('Amount To (£)')
                                    ->numeric()
                                    ->prefix('£'),
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['amount_from'],
                                fn (Builder $query, $amount): Builder => $query->where('amount', '>=', $amount),
                            )
                            ->when(
                                $data['amount_to'],
                                fn (Builder $query, $amount): Builder => $query->where('amount', '<=', $amount),
                            );
                    }),

                Tables\Filters\Filter::make('invested_date')
                    ->form([
                        Forms\Components\DatePicker::make('invested_from')
                            ->label('Invested From'),
                        Forms\Components\DatePicker::make('invested_until')
                            ->label('Invested Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['invested_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('invested_at', '>=', $date),
                            )
                            ->when(
                                $data['invested_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('invested_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Delete Investment')
                    ->modalDescription('Are you sure you want to delete this investment? This action cannot be undone and will affect location totals.')
                    ->modalSubmitActionLabel('Yes, delete it'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation(),
                    
                    Tables\Actions\BulkAction::make('mark_completed')
                        ->label('Mark as Completed')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $records->each(function ($record) {
                                $record->update([
                                    'status' => 'completed',
                                    'invested_at' => $record->invested_at ?? now(),
                                ]);
                            });
                        }),

                    Tables\Actions\BulkAction::make('export')
                        ->label('Export to CSV')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(function ($records) {
                            // You can implement CSV export logic here
                            return response()->streamDownload(function () use ($records) {
                                $csv = fopen('php://output', 'w');
                                
                                // Headers
                                fputcsv($csv, [
                                    'Reference',
                                    'Investor Name',
                                    'Investor Email',
                                    'Location',
                                    'Amount',
                                    'Currency',
                                    'Status',
                                    'Investment Date',
                                    'Created At'
                                ]);
                                
                                // Data
                                foreach ($records as $record) {
                                    fputcsv($csv, [
                                        $record->reference,
                                        $record->user->name,
                                        $record->user->email,
                                        $record->location->name,
                                        $record->amount,
                                        $record->currency,
                                        $record->status,
                                        $record->invested_at?->format('Y-m-d H:i:s'),
                                        $record->created_at->format('Y-m-d H:i:s'),
                                    ]);
                                }
                                
                                fclose($csv);
                            }, 'investments-' . now()->format('Y-m-d') . '.csv');
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
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
            'index' => Pages\ListInvestments::route('/'),
            'create' => Pages\CreateInvestment::route('/create'),
            'view' => Pages\ViewInvestment::route('/{record}'),
            'edit' => Pages\EditInvestment::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'pending')->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $pendingCount = static::getModel()::where('status', 'pending')->count();
        return $pendingCount > 10 ? 'danger' : ($pendingCount > 0 ? 'warning' : null);
    }
}