<?php

namespace App\Filament\Therapist\Resources\TherapistAvailabilityResource\Pages;

use App\Filament\Therapist\Resources\TherapistAvailabilityResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTherapistAvailabilities extends ListRecords
{
    protected static string $resource = TherapistAvailabilityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Add Availability'),
        ];
    }
}