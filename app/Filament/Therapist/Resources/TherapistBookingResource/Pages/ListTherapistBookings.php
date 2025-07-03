<?php

// app/Filament/Therapist/Resources/TherapistBookingResource/Pages/ListTherapistBookings.php
namespace App\Filament\Therapist\Resources\TherapistBookingResource\Pages;

use App\Filament\Therapist\Resources\TherapistBookingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListTherapistBookings extends ListRecords
{
    protected static string $resource = TherapistBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Therapists can't create bookings, only view/manage them
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Bookings'),
            'today' => Tab::make('Today')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereDate('date', today()))
                ->badge(fn () => $this->getResource()::getEloquentQuery()->whereDate('date', today())->count()),
            'upcoming' => Tab::make('Upcoming')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('date', '>', today()))
                ->badge(fn () => $this->getResource()::getEloquentQuery()->where('date', '>', today())->count()),
            'completed' => Tab::make('Completed')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'completed')),
        ];
    }
}