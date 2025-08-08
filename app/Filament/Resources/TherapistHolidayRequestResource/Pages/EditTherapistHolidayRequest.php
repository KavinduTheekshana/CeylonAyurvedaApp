<?php

namespace App\Filament\Resources\TherapistHolidayRequestResource\Pages;

use App\Filament\Resources\TherapistHolidayRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTherapistHolidayRequest extends EditRecord
{
    protected static string $resource = TherapistHolidayRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}