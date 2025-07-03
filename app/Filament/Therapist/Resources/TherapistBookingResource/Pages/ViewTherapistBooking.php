<?php

namespace App\Filament\Therapist\Resources\TherapistBookingResource\Pages;

use App\Filament\Therapist\Resources\TherapistBookingResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewTherapistBooking extends ViewRecord
{
    protected static string $resource = TherapistBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn(): bool => in_array($this->record->status, ['confirmed', 'pending'])),
        ];
    }
}
