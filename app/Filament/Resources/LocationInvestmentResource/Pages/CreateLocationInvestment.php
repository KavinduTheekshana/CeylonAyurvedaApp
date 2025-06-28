<?php

namespace App\Filament\Resources\LocationInvestmentResource\Pages;

use App\Filament\Resources\LocationInvestmentResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateLocationInvestment extends CreateRecord
{
   protected static string $resource = LocationInvestmentResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Auto-calculate totals when creating
        if (isset($data['location_id'])) {
            $location = \App\Models\Location::find($data['location_id']);
            if ($location) {
                $data['total_invested'] = $location->investments()
                    ->where('status', 'completed')
                    ->sum('amount');
                
                $data['total_investors'] = $location->investments()
                    ->where('status', 'completed')
                    ->distinct('user_id')
                    ->count();
            }
        }

        return $data;
    }
}
