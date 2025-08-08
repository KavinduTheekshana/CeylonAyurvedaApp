<?php

namespace App\Filament\Resources\TherapistHolidayRequestResource\Pages;

use App\Filament\Resources\TherapistHolidayRequestResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTherapistHolidayRequest extends CreateRecord
{
    protected static string $resource = TherapistHolidayRequestResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}