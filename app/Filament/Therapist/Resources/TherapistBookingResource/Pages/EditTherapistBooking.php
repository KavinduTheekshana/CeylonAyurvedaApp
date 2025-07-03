<?php

namespace App\Filament\Therapist\Resources\TherapistBookingResource\Pages;

use App\Filament\Therapist\Resources\TherapistBookingResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTherapistBooking extends EditRecord
{
    protected static string $resource = TherapistBookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
