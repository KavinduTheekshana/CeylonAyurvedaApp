<?php

namespace App\Filament\Therapist\Resources\TherapistAvailabilityResource\Pages;

use App\Filament\Therapist\Resources\TherapistAvailabilityResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTherapistAvailability extends EditRecord
{
    protected static string $resource = TherapistAvailabilityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}