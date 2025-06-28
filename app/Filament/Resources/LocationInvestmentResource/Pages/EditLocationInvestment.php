<?php

namespace App\Filament\Resources\LocationInvestmentResource\Pages;

use App\Filament\Resources\LocationInvestmentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLocationInvestment extends EditRecord
{
    protected static string $resource = LocationInvestmentResource::class;

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
