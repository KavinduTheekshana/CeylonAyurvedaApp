<?php

namespace App\Filament\Therapist\Resources\TherapistServiceResource\Pages;

use App\Filament\Therapist\Resources\TherapistServiceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTherapistService extends EditRecord
{
    protected static string $resource = TherapistServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
