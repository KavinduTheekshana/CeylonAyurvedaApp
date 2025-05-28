<?php

namespace App\Filament\Resources\TherapistAvailabilityResource\Pages;

use App\Filament\Resources\TherapistAvailabilityResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTherapistAvailabilities extends ListRecords
{
    protected static string $resource = TherapistAvailabilityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
