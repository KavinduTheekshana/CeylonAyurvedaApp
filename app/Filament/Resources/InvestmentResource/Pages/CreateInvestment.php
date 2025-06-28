<?php

namespace App\Filament\Resources\InvestmentResource\Pages;

use App\Filament\Resources\InvestmentResource;
use App\Models\Investment;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

class CreateInvestment extends CreateRecord
{
    protected static string $resource = InvestmentResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Ensure we have a reference number
        if (empty($data['reference'])) {
            $data['reference'] = Investment::generateReference();
        }

        // Set invested_at to now if status is completed and no date is set
        if ($data['status'] === 'completed' && empty($data['invested_at'])) {
            $data['invested_at'] = now();
        }

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $investment = parent::handleRecordCreation($data);

        // Update location investment totals if the investment is completed
        if ($investment->status === 'completed') {
            $this->updateLocationInvestmentTotals($investment);
        }

        return $investment;
    }

    protected function afterCreate(): void
    {
        $investment = $this->record;
        
        Notification::make()
            ->title('Investment Created Successfully')
            ->body("Investment {$investment->reference} for £{$investment->amount} has been created.")
            ->success()
            ->send();
    }

    /**
     * Update location investment totals
     */
    private function updateLocationInvestmentTotals($investment): void
    {
        $location = $investment->location;
        
        if ($location->locationInvestment) {
            $totalInvested = $location->investments()
                ->where('status', 'completed')
                ->sum('amount');

            $totalInvestors = $location->investments()
                ->where('status', 'completed')
                ->distinct('user_id')
                ->count();

            $location->locationInvestment->update([
                'total_invested' => $totalInvested,
                'total_investors' => $totalInvestors,
            ]);
        }
    }
}