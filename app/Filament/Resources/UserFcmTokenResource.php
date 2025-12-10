<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserFcmTokenResource\Pages;
use App\Models\UserFcmToken;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class UserFcmTokenResource extends Resource
{
    protected static ?string $model = UserFcmToken::class;

    protected static ?string $navigationIcon = 'heroicon-o-device-phone-mobile';

    protected static ?string $navigationLabel = 'Registered Devices';

    protected static ?string $navigationGroup = 'Push Notifications';

    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),

                Forms\Components\TextInput::make('fcm_token')
                    ->required()
                    ->maxLength(500)
                    ->columnSpanFull(),

                Forms\Components\Select::make('device_type')
                    ->options([
                        'android' => 'Android',
                        'ios' => 'iOS',
                    ])
                    ->required(),

                Forms\Components\TextInput::make('device_id')
                    ->maxLength(255),

                Forms\Components\Toggle::make('is_active')
                    ->default(true)
                    ->label('Active'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('device_type')
                    ->label('Device Type')
                    ->colors([
                        'success' => 'android',
                        'primary' => 'ios',
                    ])
                    ->icons([
                        'heroicon-o-device-phone-mobile' => 'android',
                        'heroicon-o-device-phone-mobile' => 'ios',
                    ]),

                Tables\Columns\TextColumn::make('device_id')
                    ->label('Device ID')
                    ->limit(20)
                    ->tooltip(function ($record) {
                        return $record->device_id;
                    })
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active')
                    ->sortable(),

                Tables\Columns\TextColumn::make('last_used_at')
                    ->dateTime()
                    ->sortable()
                    ->label('Last Used')
                    ->since()
                    ->description(fn ($record) => $record->last_used_at?->format('M d, Y H:i')),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->label('Registered')
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('fcm_token')
                    ->label('FCM Token')
                    ->limit(30)
                    ->tooltip(function ($record) {
                        return $record->fcm_token;
                    })
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('device_type')
                    ->options([
                        'android' => 'Android',
                        'ios' => 'iOS',
                    ]),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('All devices')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),

                Tables\Filters\Filter::make('recently_used')
                    ->query(fn ($query) => $query->where('last_used_at', '>=', now()->subDays(7)))
                    ->label('Used in last 7 days'),
            ])
            ->actions([
                Tables\Actions\Action::make('deactivate')
                    ->label('Deactivate')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => $record->is_active)
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update(['is_active' => false]);
                    })
                    ->successNotificationTitle('Device deactivated'),

                Tables\Actions\Action::make('activate')
                    ->label('Activate')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => !$record->is_active)
                    ->action(function ($record) {
                        $record->update(['is_active' => true]);
                    })
                    ->successNotificationTitle('Device activated'),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('deactivate')
                        ->label('Deactivate Selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $records->each->update(['is_active' => false]);
                        })
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\BulkAction::make('activate')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function ($records) {
                            $records->each->update(['is_active' => true]);
                        })
                        ->deselectRecordsAfterCompletion(),

                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Device Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('user.name')
                            ->label('User'),
                        Infolists\Components\TextEntry::make('user.email')
                            ->label('Email'),
                        Infolists\Components\TextEntry::make('device_type')
                            ->label('Device Type')
                            ->badge()
                            ->color(fn ($state) => $state === 'android' ? 'success' : 'primary'),
                        Infolists\Components\TextEntry::make('device_id')
                            ->label('Device ID'),
                        Infolists\Components\IconEntry::make('is_active')
                            ->label('Status')
                            ->boolean(),
                    ])
                    ->columns(2),

                Infolists\Components\Section::make('FCM Token')
                    ->schema([
                        Infolists\Components\TextEntry::make('fcm_token')
                            ->label('Token')
                            ->copyable()
                            ->columnSpanFull(),
                    ]),

                Infolists\Components\Section::make('Timeline')
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Registered At')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('last_used_at')
                            ->label('Last Used At')
                            ->dateTime()
                            ->placeholder('Never used'),
                        Infolists\Components\TextEntry::make('updated_at')
                            ->label('Last Updated')
                            ->dateTime(),
                    ])
                    ->columns(3),
            ]);
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
            'index' => Pages\ListUserFcmTokens::route('/'),
            'create' => Pages\CreateUserFcmToken::route('/create'),
            'view' => Pages\ViewUserFcmToken::route('/{record}'),
            'edit' => Pages\EditUserFcmToken::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $activeCount = static::getModel()::where('is_active', true)->count();
        return $activeCount > 0 ? (string) $activeCount : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function getWidgets(): array
    {
        return [
            UserFcmTokenResource\Widgets\DeviceStatsWidget::class,
        ];
    }
}
