<?php
// app/Filament/Resources/NotificationResource.php

namespace App\Filament\Resources;

use App\Filament\Resources\NotificationResource\Pages;
use App\Models\Notification;
use App\Jobs\SendBroadcastNotificationJob;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Support\Facades\Auth;

class NotificationResource extends Resource
{
    protected static ?string $model = Notification::class;
    protected static ?string $navigationIcon = 'heroicon-o-bell';
    protected static ?string $navigationLabel = 'Push Notifications';
    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Notification Details')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->label('Notification Title')
                            ->placeholder('Enter notification title'),

                        Forms\Components\Textarea::make('message')
                            ->required()
                            ->rows(4)
                            ->maxLength(500)
                            ->label('Message Content')
                            ->placeholder('Enter your message here...'),

                        Forms\Components\Select::make('type')
                            ->required()
                            ->options([
                                'promotional' => 'Promotional',
                                'system' => 'System Notification'
                            ])
                            ->label('Notification Type')
                            ->default('promotional'),

                        Forms\Components\FileUpload::make('image_url')
                            ->image()
                            ->directory('notification-images')
                            ->nullable()
                            ->label('Optional Image')
                            ->helperText('Upload an image for the notification (optional)'),

                        Forms\Components\Toggle::make('is_active')
                            ->default(true)
                            ->label('Active Status')
                            ->helperText('Only active notifications can be sent'),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('message')
                    ->limit(50)
                    ->tooltip(function ($record) {
                        return $record->message;
                    }),

                Tables\Columns\BadgeColumn::make('type')
                    ->colors([
                        'success' => 'promotional',
                        'primary' => 'system',
                    ])
                    ->icons([
                        'heroicon-o-megaphone' => 'promotional',
                        'heroicon-o-cog' => 'system',
                    ]),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),

                Tables\Columns\BadgeColumn::make('sent_at')
                    ->label('Status')
                    ->getStateUsing(function ($record) {
                        return $record->sent_at ? 'Sent' : 'Draft';
                    })
                    ->colors([
                        'success' => 'Sent',
                        'warning' => 'Draft',
                    ]),

                Tables\Columns\TextColumn::make('total_sent')
                    ->label('Recipients')
                    ->default(0),

                Tables\Columns\TextColumn::make('sent_at')
                    ->dateTime()
                    ->sortable()
                    ->label('Sent At'),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Created By'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'promotional' => 'Promotional',
                        'system' => 'System',
                    ]),
                Tables\Filters\TernaryFilter::make('sent_at')
                    ->label('Sent Status')
                    ->nullable()
                    ->trueLabel('Sent')
                    ->falseLabel('Draft'),
            ])
            ->actions([
                Action::make('send')
                    ->label('Send to All Users')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->visible(fn ($record) => is_null($record->sent_at) && $record->is_active)
                    ->requiresConfirmation()
                    ->modalHeading('Send Notification to All Users')
                    ->modalDescription('This will send the notification to all registered users. This action cannot be undone.')
                    ->action(function ($record) {
                        // Set created_by if not set
                        if (!$record->created_by) {
                            $record->update(['created_by' => Auth::id()]);
                        }

                        // Dispatch the job
                        SendBroadcastNotificationJob::dispatch($record);

                        FilamentNotification::make()
                            ->title('Notification Queued')
                            ->body('The notification has been queued for delivery to all users.')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\EditAction::make()
                    ->visible(fn ($record) => is_null($record->sent_at)),

                Tables\Actions\ViewAction::make(),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn ($record) => is_null($record->sent_at)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(function ($records) {
                            if (!$records || $records->isEmpty()) {
                                return false;
                            }
                            return $records->every(fn ($record) => is_null($record->sent_at));
                        }),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotifications::route('/'),
            'create' => Pages\CreateNotification::route('/create'),
            'view' => Pages\ViewNotification::route('/{record}'),
            'edit' => Pages\EditNotification::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $draftCount = static::getModel()::whereNull('sent_at')->where('is_active', true)->count();
        return $draftCount > 0 ? (string) $draftCount : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}