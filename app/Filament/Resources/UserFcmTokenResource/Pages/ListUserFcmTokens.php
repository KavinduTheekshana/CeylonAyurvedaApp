<?php

namespace App\Filament\Resources\UserFcmTokenResource\Pages;

use App\Filament\Resources\UserFcmTokenResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUserFcmTokens extends ListRecords
{
    protected static string $resource = UserFcmTokenResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            UserFcmTokenResource\Widgets\DeviceStatsWidget::class,
        ];
    }
}
