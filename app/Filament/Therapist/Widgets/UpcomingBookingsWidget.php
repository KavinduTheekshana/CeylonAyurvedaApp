<?php

namespace App\Filament\Therapist\Widgets;

use App\Models\Booking;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;

class UpcomingBookingsWidget extends BaseWidget
{
    protected static ?string $heading = 'Upcoming Appointments';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 3;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Booking::query()
                    ->where('therapist_id', Auth::guard('therapist')->id())
                    ->where('date', '>=', today())
                    ->whereIn('status', ['confirmed', 'pending'])
                    ->with(['service', 'user'])
                    ->orderBy('date')
                    ->orderBy('time')
                    ->limit(10)
            )
            ->columns([
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
                    ->limit(30),

                Tables\Columns\TextColumn::make('name')
                    ->label('Customer')
                    ->searchable()
                    ->description(fn(Booking $record): string => $record->phone),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'confirmed' => 'success',
                        'pending' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('price')
                    ->money('GBP'),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->icon('heroicon-o-eye')
                    ->url(fn(Booking $record) => "/therapist/therapist-bookings/{$record->id}"),
                Tables\Actions\Action::make('complete')
                    ->label('Complete')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(function (Booking $record) {
                        $record->update(['status' => 'completed']);
                    })
                    ->requiresConfirmation()
                    ->visible(
                        fn(Booking $record): bool =>
                        $record->status === 'confirmed' && $record->date <= today()
                    ),
            ]);
    }
}
