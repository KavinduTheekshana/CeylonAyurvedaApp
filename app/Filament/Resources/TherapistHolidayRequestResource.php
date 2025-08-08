<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TherapistHolidayRequestResource\Pages;
use App\Models\TherapistHolidayRequest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;

class TherapistHolidayRequestResource extends Resource
{
    protected static ?string $model = TherapistHolidayRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar';
    
    protected static ?string $navigationGroup = 'Therapist Management';
    
    protected static ?string $navigationLabel = 'Holiday Requests';
    
    protected static ?string $modelLabel = 'Holiday Request';
    
    protected static ?string $pluralModelLabel = 'Holiday Requests';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Holiday Request Details')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('therapist_id')
                                    ->label('Therapist')
                                    ->relationship('therapist', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->required()
                                    ->disabled(fn($context) => $context === 'edit'),

                                Forms\Components\DatePicker::make('date')
                                    ->label('Holiday Date')
                                    ->required()
                                    ->native(false)
                                    ->disabled(fn($context) => $context === 'edit'),
                            ]),

                        Forms\Components\Textarea::make('reason')
                            ->label('Reason for Holiday')
                            ->required()
                            ->maxLength(500)
                            ->rows(3)
                            ->disabled(fn($context) => $context === 'edit')
                            ->columnSpanFull(),

                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                            ])
                            ->default('pending')
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($state, $record, Forms\Set $set) {
                                if ($record && in_array($state, ['approved', 'rejected'])) {
                                    $set('reviewed_by', Auth::id());
                                    $set('reviewed_at', now());
                                }
                            }),
                    ]),

                Forms\Components\Section::make('Admin Review')
                    ->schema([
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Admin Notes')
                            ->placeholder('Add notes about this decision...')
                            ->maxLength(1000)
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Hidden::make('reviewed_by')
                                    ->default(Auth::id()),
                                
                                Forms\Components\Hidden::make('reviewed_at')
                                    ->default(now()),
                            ]),
                    ])
                    ->visible(fn($record) => $record && $record->status !== 'pending'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('therapist.name')
                    ->label('Therapist')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('date')
                    ->label('Holiday Date')
                    ->date('M d, Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('reason')
                    ->label('Reason')
                    ->limit(50)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 50 ? $state : null;
                    }),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'approved',
                        'danger' => 'rejected',
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Requested On')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('reviewed_at')
                    ->label('Reviewed On')
                    ->dateTime('M d, Y H:i')
                    ->placeholder('Not reviewed')
                    ->sortable(),

                Tables\Columns\TextColumn::make('reviewedBy.name')
                    ->label('Reviewed By')
                    ->placeholder('Not reviewed')
                    ->sortable(),

                Tables\Columns\TextColumn::make('admin_notes')
                    ->label('Admin Notes')
                    ->limit(30)
                    ->placeholder('No notes')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ])
                    ->default('pending'),

                Tables\Filters\SelectFilter::make('therapist_id')
                    ->label('Therapist')
                    ->relationship('therapist', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('date_from')
                            ->label('From Date'),
                        Forms\Components\DatePicker::make('date_to')
                            ->label('To Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['date_from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('date', '>=', $date),
                            )
                            ->when(
                                $data['date_to'],
                                fn(Builder $query, $date): Builder => $query->whereDate('date', '<=', $date),
                            );
                    }),

                Tables\Filters\Filter::make('upcoming')
                    ->label('Upcoming Holidays')
                    ->query(fn(Builder $query): Builder => $query->where('date', '>=', today())),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn($record) => $record->status === 'pending'),

                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn($record) => $record->status === 'pending')
                    ->form([
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Approval Notes (Optional)')
                            ->placeholder('Add any notes about this approval...')
                            ->maxLength(500),
                    ])
                    ->action(function (TherapistHolidayRequest $record, array $data) {
                        $record->update([
                            'status' => 'approved',
                            'admin_notes' => $data['admin_notes'] ?? null,
                            'reviewed_by' => Auth::id(),
                            'reviewed_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Holiday Approved')
                            ->body("Holiday request for {$record->therapist->name} on {$record->date->format('M d, Y')} has been approved.")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn($record) => $record->status === 'pending')
                    ->form([
                        Forms\Components\Textarea::make('admin_notes')
                            ->label('Rejection Reason')
                            ->placeholder('Explain why this request is being rejected...')
                            ->required()
                            ->maxLength(500),
                    ])
                    ->action(function (TherapistHolidayRequest $record, array $data) {
                        $record->update([
                            'status' => 'rejected',
                            'admin_notes' => $data['admin_notes'],
                            'reviewed_by' => Auth::id(),
                            'reviewed_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Holiday Rejected')
                            ->body("Holiday request for {$record->therapist->name} on {$record->date->format('M d, Y')} has been rejected.")
                            ->warning()
                            ->send();
                    }),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn($record) => $record->status === 'pending'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('approve_selected')
                        ->label('Approve Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function ($records) {
                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->status === 'pending') {
                                    $record->update([
                                        'status' => 'approved',
                                        'reviewed_by' => Auth::id(),
                                        'reviewed_at' => now(),
                                    ]);
                                    $count++;
                                }
                            }

                            Notification::make()
                                ->title('Holiday Requests Approved')
                                ->body("{$count} holiday requests have been approved.")
                                ->success()
                                ->send();
                        })
                        ->requiresConfirmation(),

                    Tables\Actions\DeleteBulkAction::make()
                        ->label('Delete Selected'),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTherapistHolidayRequests::route('/'),
            'create' => Pages\CreateTherapistHolidayRequest::route('/create'),
            'view' => Pages\ViewTherapistHolidayRequest::route('/{record}'),
            'edit' => Pages\EditTherapistHolidayRequest::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', 'pending')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}