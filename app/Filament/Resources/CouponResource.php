<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CouponResource\Pages;
use App\Filament\Resources\CouponResource\RelationManagers;
use App\Models\Coupon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Support\Enums\FontWeight;
use Filament\Forms\Get;
use Filament\Forms\Set;

class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';
    
    protected static ?string $navigationGroup = 'Promotions';
    
    protected static ?string $navigationLabel = 'Coupons';
    
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Coupon Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('code')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(255)
                                    ->default(fn() => Coupon::generateUniqueCode())
                                    ->helperText('Unique coupon code that customers will use'),

                                Forms\Components\Select::make('type')
                                    ->options([
                                        'percentage' => 'Percentage',
                                        'fixed' => 'Fixed Amount',
                                    ])
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                        if ($state === 'percentage') {
                                            $currentValue = $get('value');
                                            if ($currentValue > 100) {
                                                $set('value', 100);
                                            }
                                        }
                                    }),
                            ]),

                        Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('value')
                                    ->label(fn(Get $get) => $get('type') === 'percentage' ? 'Percentage (%)' : 'Amount (£)')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0)
                                    ->maxValue(fn(Get $get) => $get('type') === 'percentage' ? 100 : null)
                                    ->step(0.01)
                                    ->prefix(fn(Get $get) => $get('type') === 'fixed' ? '£' : null)
                                    ->suffix(fn(Get $get) => $get('type') === 'percentage' ? '%' : null)
                                    ->live()
                                    ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                        if ($get('type') === 'percentage' && $state > 100) {
                                            $set('value', 100);
                                        }
                                    }),

                                Forms\Components\TextInput::make('minimum_amount')
                                    ->label('Minimum Purchase Amount (£)')
                                    ->numeric()
                                    ->prefix('£')
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->helperText('Leave empty for no minimum'),
                            ]),

                        Forms\Components\Textarea::make('description')
                            ->maxLength(500)
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Section::make('Usage Limits')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('usage_limit')
                                    ->label('Total Usage Limit')
                                    ->numeric()
                                    ->minValue(1)
                                    ->helperText('Leave empty for unlimited usage'),

                                Forms\Components\TextInput::make('usage_limit_per_user')
                                    ->label('Usage Limit Per User')
                                    ->numeric()
                                    ->minValue(1)
                                    ->helperText('How many times each user can use this coupon'),

                                Forms\Components\Placeholder::make('usage_count')
                                    ->label('Current Usage Count')
                                    ->content(fn($record) => $record ? $record->usage_count : 0),
                            ]),
                    ]),

                Section::make('Validity Period')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Forms\Components\DateTimePicker::make('valid_from')
                                    ->label('Valid From')
                                    ->required()
                                    ->native(false)
                                    ->default(now()),

                                Forms\Components\DateTimePicker::make('valid_until')
                                    ->label('Valid Until')
                                    ->native(false)
                                    ->after('valid_from')
                                    ->helperText('Leave empty for no expiration'),
                            ]),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Deactivate to temporarily disable this coupon'),
                    ]),

                Section::make('Applicable Services')
                    ->schema([
                        Forms\Components\Select::make('services')
                            ->relationship('services', 'title')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->columnSpanFull()
                            ->helperText('Leave empty to apply to all services')
                            ->options(function () {
                                return \App\Models\Service::with('treatment')
                                    ->get()
                                    ->mapWithKeys(function ($service) {
                                        return [$service->id => "{$service->treatment->name} - {$service->title}"];
                                    });
                            }),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight(FontWeight::Bold),

                Tables\Columns\BadgeColumn::make('type')
                    ->colors([
                        'primary' => 'percentage',
                        'success' => 'fixed',
                    ]),

                Tables\Columns\TextColumn::make('value')
                    ->label('Discount')
                    ->formatStateUsing(fn($state, $record) => 
                        $record->type === 'percentage' 
                            ? "{$state}%" 
                            : "£" . number_format($state, 2)
                    )
                    ->sortable(),

                Tables\Columns\TextColumn::make('minimum_amount')
                    ->label('Min. Amount')
                    ->money('GBP')
                    ->placeholder('No minimum')
                    ->sortable(),

                Tables\Columns\TextColumn::make('usage_display')
                    ->label('Usage')
                    ->state(function ($record) {
                        $usage = $record->usage_count;
                        $limit = $record->usage_limit;
                        return $limit ? "{$usage}/{$limit}" : "{$usage}/∞";
                    })
                    ->badge()
                    ->color(function ($record) {
                        if (!$record->usage_limit) return 'success';
                        $percentage = ($record->usage_count / $record->usage_limit) * 100;
                        if ($percentage >= 100) return 'danger';
                        if ($percentage >= 80) return 'warning';
                        return 'success';
                    }),

                Tables\Columns\TextColumn::make('services_count')
                    ->label('Services')
                    ->counts('services')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn($state) => $state > 0 ? $state : 'All'),

                Tables\Columns\TextColumn::make('valid_from')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('valid_until')
                    ->dateTime('M j, Y H:i')
                    ->placeholder('No expiration')
                    ->sortable()
                    ->color(fn($record) => 
                        $record->valid_until && $record->valid_until->isPast() 
                            ? 'danger' 
                            : 'success'
                    ),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->state(function ($record) {
                        if (!$record->is_active) return 'Inactive';
                        if ($record->valid_until && $record->valid_until->isPast()) return 'Expired';
                        if ($record->valid_from && $record->valid_from->isFuture()) return 'Scheduled';
                        if ($record->usage_limit && $record->usage_count >= $record->usage_limit) return 'Exhausted';
                        return 'Active';
                    })
                    ->badge()
                    ->color(fn($state) => match($state) {
                        'Active' => 'success',
                        'Inactive' => 'gray',
                        'Expired' => 'danger',
                        'Scheduled' => 'info',
                        'Exhausted' => 'warning',
                        default => 'gray'
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'percentage' => 'Percentage',
                        'fixed' => 'Fixed Amount',
                    ]),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),

                Tables\Filters\Filter::make('valid')
                    ->label('Currently Valid')
                    ->query(fn (Builder $query): Builder => $query->active()),

                Tables\Filters\Filter::make('expired')
                    ->label('Expired')
                    ->query(fn (Builder $query): Builder => 
                        $query->where('valid_until', '<', now())
                    ),

                Tables\Filters\Filter::make('exhausted')
                    ->label('Usage Limit Reached')
                    ->query(fn (Builder $query): Builder => 
                        $query->whereNotNull('usage_limit')
                            ->whereColumn('usage_count', '>=', 'usage_limit')
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                
                Tables\Actions\Action::make('duplicate')
                    ->label('Duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('info')
                    ->action(function (Coupon $record) {
                        $newCoupon = $record->replicate();
                        $newCoupon->code = Coupon::generateUniqueCode();
                        $newCoupon->usage_count = 0;
                        $newCoupon->save();
                        
                        // Copy service relationships
                        $newCoupon->services()->sync($record->services->pluck('id'));
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Coupon Duplicated')
                            ->body("New coupon created with code: {$newCoupon->code}")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('toggle_active')
                    ->label(fn(Coupon $record) => $record->is_active ? 'Deactivate' : 'Activate')
                    ->icon(fn(Coupon $record) => $record->is_active ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->color(fn(Coupon $record) => $record->is_active ? 'warning' : 'success')
                    ->action(function (Coupon $record) {
                        $record->update(['is_active' => !$record->is_active]);
                    }),

                Tables\Actions\DeleteAction::make()
                    ->before(function (Coupon $record) {
                        if ($record->usage_count > 0) {
                            \Filament\Notifications\Notification::make()
                                ->title('Cannot Delete')
                                ->body('This coupon has been used and cannot be deleted.')
                                ->danger()
                                ->send();
                            return false;
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-check')
                        ->color('success')
                        ->action(fn($records) => $records->each->update(['is_active' => true]))
                        ->requiresConfirmation(),

                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate Selected')
                        ->icon('heroicon-o-x-mark')
                        ->color('warning')
                        ->action(fn($records) => $records->each->update(['is_active' => false]))
                        ->requiresConfirmation(),

                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function ($records) {
                            $usedCoupons = $records->filter(fn($record) => $record->usage_count > 0);
                            if ($usedCoupons->count() > 0) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Cannot Delete')
                                    ->body("{$usedCoupons->count()} coupon(s) have been used and cannot be deleted.")
                                    ->danger()
                                    ->send();
                                return false;
                            }
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\UsagesRelationManager::class,
            RelationManagers\ServicesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCoupons::route('/'),
            'create' => Pages\CreateCoupon::route('/create'),
            'view' => Pages\ViewCoupon::route('/{record}'),
            'edit' => Pages\EditCoupon::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::active()->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }
}