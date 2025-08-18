<?php

namespace App\Filament\Resources\BookingResource\Pages;

use App\Filament\Resources\BookingResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

class CreateBooking extends CreateRecord
{
    protected static string $resource = BookingResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Auto-generate reference number if not provided
        if (empty($data['reference'])) {
            $data['reference'] = $this->generateUniqueReference();
        }
        
        // Set original price if not set
        if (empty($data['original_price']) && !empty($data['price'])) {
            $data['original_price'] = $data['price'];
        }
        
        // Calculate discount amount
        if (!empty($data['original_price']) && !empty($data['price'])) {
            $data['discount_amount'] = max(0, $data['original_price'] - $data['price']);
        }
        
        // Set default payment status if not provided
        if (empty($data['payment_status'])) {
            $data['payment_status'] = 'pending';
        }
        
        return $data;
    }

    protected function afterCreate(): void
    {
        // Send success notification
        Notification::make()
            ->title('Booking Created Successfully')
            ->body("Booking {$this->record->reference} has been created for {$this->record->name}")
            ->success()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Generate a unique booking reference
     */
    private function generateUniqueReference(): string
    {
        do {
            $reference = 'BK-' . strtoupper(Str::random(8));
        } while (static::getModel()::where('reference', $reference)->exists());

        return $reference;
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Booking created successfully';
    }
}