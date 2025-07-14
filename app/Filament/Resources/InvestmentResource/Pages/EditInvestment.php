<?php

namespace App\Filament\Resources\InvestmentResource\Pages;

use App\Filament\Resources\InvestmentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use Filament\Forms;
use Illuminate\Support\Facades\Auth;

class EditInvestment extends EditRecord
{
    protected static string $resource = InvestmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('confirm_bank_transfer')
                ->label('Confirm Bank Transfer')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool => $this->record->payment_method === 'bank_transfer' && $this->record->status === 'pending')
                ->form([
                    Forms\Components\Textarea::make('bank_transfer_details')
                        ->label('Bank Transfer Details')
                        ->placeholder('Enter details about the bank transfer confirmation...')
                        ->required()
                        ->maxLength(1000),
                    Forms\Components\Toggle::make('confirmed')
                        ->label('Confirm this bank transfer')
                        ->default(true)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $this->record->update([
                        'status' => $data['confirmed'] ? 'completed' : 'failed',
                        'bank_transfer_details' => $data['bank_transfer_details'],
                        'bank_transfer_confirmed_at' => $data['confirmed'] ? now() : null,
                        'confirmed_by_admin_id' => Auth::id(),
                        'invested_at' => $data['confirmed'] ? ($this->record->invested_at ?? now()) : $this->record->invested_at,
                    ]);

                    if ($data['confirmed']) {
                        $this->updateLocationInvestmentTotals();
                    }

                    $this->refreshFormData([
                        'status',
                        'bank_transfer_details',
                        'bank_transfer_confirmed_at',
                        'confirmed_by_admin_id',
                        'invested_at',
                    ]);

                    Notification::make()
                        ->title('Bank Transfer Updated')
                        ->body($data['confirmed'] ? 'Bank transfer confirmed successfully.' : 'Bank transfer rejected.')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('resend_stripe_webhook')
                ->label('Retry Stripe Processing')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(fn (): bool => $this->record->payment_method === 'card' && 
                    $this->record->status === 'pending' && 
                    !empty($this->record->stripe_payment_intent_id))
                ->requiresConfirmation()
                ->modalHeading('Retry Stripe Processing')
                ->modalDescription('This will attempt to re-verify the payment status with Stripe. Only use this if you believe the payment was successful but not properly recorded.')
                ->action(function (): void {
                    try {
                        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
                        $paymentIntent = \Stripe\PaymentIntent::retrieve($this->record->stripe_payment_intent_id);
                        
                        if ($paymentIntent->status === 'succeeded') {
                            $this->record->update([
                                'status' => 'completed',
                                'invested_at' => $this->record->invested_at ?? now(),
                            ]);
                            
                            $this->updateLocationInvestmentTotals();
                            
                            Notification::make()
                                ->title('Payment Verified')
                                ->body('Stripe payment was successfully verified and investment completed.')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Payment Not Completed')
                                ->body("Stripe payment status is: {$paymentIntent->status}")
                                ->warning()
                                ->send();
                        }
                    } catch (\Exception $e) {
                        Notification::make()
                            ->title('Stripe Error')
                            ->body("Failed to verify payment: {$e->getMessage()}")
                            ->danger()
                            ->send();
                    }
                }),

            Actions\ViewAction::make(),
            
            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->modalHeading('Delete Investment')
                ->modalDescription('Are you sure you want to delete this investment? This will affect location totals and cannot be undone.')
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
        $originalPaymentMethod = $this->record->payment_method;

        // If status changed to completed and no invested_at date, set it to now
        if ($data['status'] === 'completed' && $originalStatus !== 'completed' && empty($data['invested_at'])) {
            $data['invested_at'] = now();
        }

        // If payment method changed from bank_transfer to card, clear bank transfer fields
        if ($originalPaymentMethod === 'bank_transfer' && $data['payment_method'] === 'card') {
            $data['bank_transfer_details'] = null;
            $data['bank_transfer_confirmed_at'] = null;
            $data['confirmed_by_admin_id'] = null;
        }

        // If payment method changed from card to bank_transfer, clear Stripe fields
        if ($originalPaymentMethod === 'card' && $data['payment_method'] === 'bank_transfer') {
            $data['stripe_payment_intent_id'] = null;
            $data['stripe_payment_method_id'] = null;
            $data['stripe_metadata'] = null;
        }

        // If bank transfer is being confirmed manually through form
        if ($data['payment_method'] === 'bank_transfer' && 
            $data['status'] === 'completed' && 
            $originalStatus !== 'completed' &&
            empty($this->record->confirmed_by_admin_id)) {
            $data['confirmed_by_admin_id'] = Auth::id();
            $data['bank_transfer_confirmed_at'] = now();
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $investment = $this->record;
        $originalStatus = $this->record->getOriginal('status');
        $originalAmount = $this->record->getOriginal('amount');

        // Update location totals if status changed to/from completed or amount changed
        if ($investment->status === 'completed' || 
            $originalStatus === 'completed' || 
            ($investment->status === 'completed' && $originalAmount !== $investment->amount)) {
            $this->updateLocationInvestmentTotals();
        }

        // Create notification based on changes
        $statusChanged = $originalStatus !== $investment->status;
        $amountChanged = $originalAmount !== $investment->amount;

        $notificationBody = "Investment {$investment->reference} has been updated successfully.";

        if ($statusChanged && $investment->status === 'completed') {
            $notificationBody = "Investment {$investment->reference} has been marked as completed.";
            
            if ($investment->payment_method === 'bank_transfer') {
                $notificationBody .= " Bank transfer confirmed.";
            }
        } elseif ($statusChanged && $investment->status === 'failed') {
            $notificationBody = "Investment {$investment->reference} has been marked as failed.";
        } elseif ($amountChanged) {
            $notificationBody = "Investment {$investment->reference} amount has been updated to £{$investment->amount}.";
        }

        Notification::make()
            ->title('Investment Updated')
            ->body($notificationBody)
            ->success()
            ->send();

        // Send additional notification for bank transfer confirmations
        if ($statusChanged && 
            $investment->status === 'completed' && 
            $investment->payment_method === 'bank_transfer' && 
            $investment->bank_transfer_confirmed_at) {
            
            Notification::make()
                ->title('Bank Transfer Confirmed')
                ->body("Bank transfer for investment {$investment->reference} has been confirmed and location totals updated.")
                ->success()
                ->duration(5000)
                ->send();
        }
    }

    /**
     * Update location investment totals
     */
    private function updateLocationInvestmentTotals(): void
    {
        $location = $this->record->location;
        
        if ($location->locationInvestment) {
            // Calculate totals from all completed investments
            $totalInvested = $location->investments()
                ->where('status', 'completed')
                ->sum('amount');
                
            $totalInvestors = $location->investments()
                ->where('status', 'completed')
                ->distinct('user_id')
                ->count();

            // Update the location investment record
            $location->locationInvestment->update([
                'total_invested' => $totalInvested,
                'total_investors' => $totalInvestors,
            ]);

            // Check if investment limit is reached and close if necessary
            if ($totalInvested >= $location->locationInvestment->investment_limit) {
                $location->locationInvestment->update([
                    'is_open_for_investment' => false
                ]);
                
                Notification::make()
                    ->title('Investment Limit Reached')
                    ->body("Location {$location->name} has reached its investment limit and has been closed for new investments.")
                    ->warning()
                    ->send();
            }
        }
    }

    /**
     * Validate form data before saving
     */
    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->submit(null)
                ->action(function () {
                    $data = $this->form->getState();
                    
                    // Additional validation for bank transfers
                    if ($data['payment_method'] === 'bank_transfer' && 
                        $data['status'] === 'completed' && 
                        empty($data['bank_transfer_details'])) {
                        
                        Notification::make()
                            ->title('Validation Error')
                            ->body('Bank transfer details are required when marking a bank transfer as completed.')
                            ->danger()
                            ->send();
                        
                        return;
                    }
                    
                    // Additional validation for card payments
                    if ($data['payment_method'] === 'card' && 
                        $data['status'] === 'completed' && 
                        empty($data['stripe_payment_intent_id'])) {
                        
                        Notification::make()
                            ->title('Validation Warning')
                            ->body('Card payments typically require a Stripe Payment Intent ID. Please verify this is correct.')
                            ->warning()
                            ->send();
                    }
                    
                    $this->save();
                }),
            $this->getCancelFormAction(),
        ];
    }
}