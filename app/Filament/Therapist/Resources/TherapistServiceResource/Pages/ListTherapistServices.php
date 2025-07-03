<?php

namespace App\Filament\Therapist\Resources\TherapistServiceResource\Pages;

use App\Filament\Therapist\Resources\TherapistServiceResource;
use Filament\Resources\Pages\ListRecords;

class ListTherapistServices extends ListRecords
{
    protected static string $resource = TherapistServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Therapists can't create services, only view them
        ];
    }
}
