<?php

namespace App\Filament\Resources\LocationInvestmentResource\Pages;

use App\Filament\Resources\LocationInvestmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLocationInvestments extends ListRecords
{
    protected static string $resource = LocationInvestmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Add Location Investment Setting'),
        ];
    }
}
