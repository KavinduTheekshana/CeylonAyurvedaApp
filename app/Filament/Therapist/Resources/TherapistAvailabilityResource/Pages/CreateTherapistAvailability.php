<?php

namespace App\Filament\Therapist\Resources\TherapistAvailabilityResource\Pages;

use App\Filament\Therapist\Resources\TherapistAvailabilityResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateTherapistAvailability extends CreateRecord
{
    protected static string $resource = TherapistAvailabilityResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['therapist_id'] = Auth::guard('therapist')->id();
        return $data;
    }
}
