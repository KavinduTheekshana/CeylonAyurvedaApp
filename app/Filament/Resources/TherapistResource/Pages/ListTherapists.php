<?php

namespace App\Filament\Resources\TherapistResource\Pages;

use App\Filament\Resources\TherapistResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTherapists extends ListRecords
{
    protected static string $resource = TherapistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
