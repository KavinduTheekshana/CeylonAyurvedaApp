<?php

namespace App\Filament\Resources\TherapistAvailabilityResource\Pages;

use App\Filament\Resources\TherapistAvailabilityResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateTherapistAvailability extends CreateRecord
{
    protected static string $resource = TherapistAvailabilityResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
