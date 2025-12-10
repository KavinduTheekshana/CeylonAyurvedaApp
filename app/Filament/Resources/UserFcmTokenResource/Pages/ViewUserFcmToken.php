<?php

namespace App\Filament\Resources\UserFcmTokenResource\Pages;

use App\Filament\Resources\UserFcmTokenResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewUserFcmToken extends ViewRecord
{
    protected static string $resource = UserFcmTokenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
