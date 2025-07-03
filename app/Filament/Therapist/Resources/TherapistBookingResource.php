<?php

namespace App\Filament\Therapist\Resources;

use App\Filament\Therapist\Resources\TherapistBookingResource\Pages;
use App\Models\Booking;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Carbon\Carbon;

class TherapistBookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';
    
    protected static ?string $navigationLabel = 'My Bookings';
    
    protected static ?string $modelLabel = 'Booking';
    
    protected static ?string $pluralModelLabel = 'My Bookings';

    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        // Only show bookings for the authenticated therapist
        return parent::getEloquentQuery()
            ->where('therapist_id', Auth::guard('therapist')->id())
            ->with(['service', 'user']);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Booking Details')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('reference')
                                    ->label('Reference')
                                    ->disabled(),
                                
                                Forms\Components\Select::make('status')
                                    ->options([
                                        'confirmed' => 'Confirmed',
                                        'pending' => 'Pending',
                                        'completed' => 'Completed',
                                        'cancelled' => 'Cancelled',
                                    ])
                                    ->required(),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\DatePicker::make('date')
                                    ->required()
                                    ->disabled(),
                                
                                Forms\Components\TimePicker::make('time')
                                    ->required()
                                    ->disabled(),
                            ]),

                  Forms\Components\Select::make('service_id')
                        ->label('Service')
                        ->relationship('service', 'title')
                        ->disabled()
                        ->preload(),
                    ]),

                Forms\Components\Section::make('Customer Information')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->disabled(),
                                
                                Forms\Components\TextInput::make('email')
                                    ->email()
                                    ->disabled(),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('phone')
                                    ->disabled(),
                                
                                Forms\Components\TextInput::make('price')
                                    ->prefix('£')
                                    ->disabled(),
                            ]),

                        Forms\Components\Textarea::make('notes')
                            ->label('Customer Notes')
                            ->disabled()
                            ->rows(3),
                    ]),

                Forms\Components\Section::make('Address')
                    ->schema([
                        Forms\Components\TextInput::make('address_line1')
                            ->label('Address Line 1')
                            ->disabled(),
                        
                        Forms\Components\TextInput::make('address_line2')
                            ->label('Address Line 2')
                            ->disabled(),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('city')
                                    ->disabled(),
                                
                                Forms\Components\TextInput::make('postcode')
                                    ->disabled(),
                            ]),
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
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('date')
                    ->label('Date')
                    ->date('M d, Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('time')
                    ->label('Time')
                    ->time('H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('service.title')
                    ->label('Service')
                    ->searchable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Customer')
                    ->searchable()
                    ->description(fn (Booking $record): string => $record->phone),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'confirmed' => 'success',
                        'pending' => 'warning',
                        'completed' => 'info',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('price')
                    ->money('GBP')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Booked')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'confirmed' => 'Confirmed',
                        'pending' => 'Pending',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),

                Filter::make('date_range')
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
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '>=', $date),
                            )
                            ->when(
                                $data['date_to'],
                                fn (Builder $query, $date): Builder => $query->whereDate('date', '<=', $date),
                            );
                    }),

                Filter::make('today')
                    ->label('Today\'s Bookings')
                    ->query(fn (Builder $query): Builder => $query->whereDate('date', today())),

                Filter::make('upcoming')
                    ->label('Upcoming Bookings')
                    ->query(fn (Builder $query): Builder => $query->where('date', '>=', today())),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (Booking $record): bool => 
                        in_array($record->status, ['confirmed', 'pending'])
                    ),
                Tables\Actions\Action::make('complete')
                    ->label('Mark as Completed')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(function (Booking $record) {
                        $record->update(['status' => 'completed']);
                    })
                    ->requiresConfirmation()
                    ->visible(fn (Booking $record): bool => 
                        $record->status === 'confirmed' && $record->date <= today()
                    ),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('mark_completed')
                        ->label('Mark as Completed')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function ($records) {
                            $records->each(function ($record) {
                                if ($record->status === 'confirmed') {
                                    $record->update(['status' => 'completed']);
                                }
                            });
                        })
                        ->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('date', 'asc')
            ->defaultSort('time', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTherapistBookings::route('/'),
            'view' => Pages\ViewTherapistBooking::route('/{record}'),
            'edit' => Pages\EditTherapistBooking::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()
            ->whereDate('date', today())
            ->whereIn('status', ['confirmed', 'pending'])
            ->count();
        
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }
}