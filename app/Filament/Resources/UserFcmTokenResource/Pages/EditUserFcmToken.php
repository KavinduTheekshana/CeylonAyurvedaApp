<?php

namespace App\Filament\Resources\UserFcmTokenResource\Pages;

use App\Filament\Resources\UserFcmTokenResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUserFcmToken extends EditRecord
{
    protected static string $resource = UserFcmTokenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
