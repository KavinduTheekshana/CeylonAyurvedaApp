<?php

namespace App\Filament\Resources\InvestmentResource\Pages;

use App\Filament\Resources\InvestmentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditInvestment extends EditRecord
{
    protected static string $resource = InvestmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->modalHeading('Delete Investment')
                ->modalDescription('Are you sure you want to delete this investment? This will affect location totals.')
                ->after(function () {
                    // Update location totals after deletion
                    $this->updateLocationInvestmentTotals();
                }),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $originalStatus = $this->record->status;
        
        // If status changed to completed and no invested_at date, set it to now
        if ($data['status'] === 'completed' && $originalStatus !== 'completed' && empty($data['invested_at'])) {
            $data['invested_at'] = now();
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $investment = $this->record;
        $originalStatus = $this->record->getOriginal('status');
        
        // Update location totals if status changed to/from completed
        if ($investment->status === 'completed' || $originalStatus === 'completed') {
            $this->updateLocationInvestmentTotals();
        }

        Notification::make()
            ->title('Investment Updated')
            ->body("Investment {$investment->reference} has been updated successfully.")
            ->success()
            ->send();
    }

    /**
     * Update location investment totals
     */
    private function updateLocationInvestmentTotals(): void
    {
        $location = $this->record->location;
        
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