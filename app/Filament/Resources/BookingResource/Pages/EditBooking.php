<?php

namespace App\Filament\Resources\BookingResource\Pages;

use App\Filament\Resources\BookingResource;
use App\Models\Therapist;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditBooking extends EditRecord
{
    protected static string $resource = BookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('assign_therapist')
                ->label('Quick Assign Therapist')
                ->icon('heroicon-o-user-plus')
                ->color('info')
                ->form([
                    Select::make('therapist_id')
                        ->label('Select Therapist')
                        ->options(Therapist::where('status', true)->pluck('name', 'id'))
                        ->searchable()
                        ->required()
                        ->default($this->record->therapist_id),
                ])
                ->action(function (array $data) {
                    $oldTherapist = $this->record->therapist->name ?? 'None';
                    $this->record->update(['therapist_id' => $data['therapist_id']]);
                    
                    $newTherapist = Therapist::find($data['therapist_id'])->name;
                    
                    Notification::make()
                        ->title('Therapist Updated')
                        ->body("Therapist changed from '{$oldTherapist}' to '{$newTherapist}'")
                        ->success()
                        ->send();
                        
                    // Refresh the form to show updated therapist
                    $this->refreshFormData(['therapist_id']);
                }),

            Action::make('mark_completed')
                ->label('Mark Completed')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Mark Booking as Completed')
                ->modalDescription('Are you sure you want to mark this booking as completed?')
                ->action(function () {
                    $this->record->update(['status' => 'completed']);
                    
                    Notification::make()
                        ->title('Booking Completed')
                        ->body("Booking {$this->record->reference} has been marked as completed")
                        ->success()
                        ->send();
                        
                    // Refresh the form to show updated status
                    $this->refreshFormData(['status']);
                })
                ->visible(fn() => in_array($this->record->status, ['confirmed', 'in_progress'])),

            Action::make('send_confirmation')
                ->label('Send Confirmation')
                ->icon('heroicon-o-envelope')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Send Booking Confirmation')
                ->modalDescription('Send a confirmation email to the customer?')
                ->action(function () {
                    // Here you would implement email sending logic
                    // For now, just show a notification
                    
                    Notification::make()
                        ->title('Confirmation Sent')
                        ->body("Confirmation email sent to {$this->record->email}")
                        ->success()
                        ->send();
                })
                ->visible(fn() => in_array($this->record->status, ['confirmed', 'pending'])),

            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->modalHeading('Delete Booking')
                ->modalDescription('Are you sure you want to delete this booking? This action cannot be undone.')
                ->successNotificationTitle('Booking deleted successfully'),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Ensure reference number cannot be changed
        unset($data['reference']);
        
        // Auto-calculate discount amount if original price and final price are different
        if (isset($data['original_price']) && isset($data['price'])) {
            $data['discount_amount'] = max(0, $data['original_price'] - $data['price']);
        }
        
        return $data;
    }

    protected function afterSave(): void
    {
        // Send notification about the update
        Notification::make()
            ->title('Booking Updated')
            ->body("Booking {$this->record->reference} has been updated successfully")
            ->success()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    // Add custom method to refresh specific form fields
    // protected function refreshFormData(array $fields = []): void
    // {
    //     $this->record->refresh();
        
    //     if (!empty($fields)) {
    //         foreach ($fields as $field) {
    //             $this->form->fill([$field => $this->record->{$field}]);
    //         }
    //     } else {
    //         $this->form->fill($this->record->toArray());
    //     }
    // }
}