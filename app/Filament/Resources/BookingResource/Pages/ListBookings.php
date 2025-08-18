<?php

namespace App\Filament\Resources\BookingResource\Pages;

use App\Filament\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Therapist;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListBookings extends ListRecords
{
    protected static string $resource = BookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New Booking')
                ->icon('heroicon-o-plus'),

            Action::make('bulk_assign_therapist')
                ->label('Bulk Assign Therapist')
                ->icon('heroicon-o-users')
                ->color('info')
                ->form([
                    Select::make('therapist_id')
                        ->label('Select Therapist')
                        ->options(Therapist::where('status', true)->pluck('name', 'id'))
                        ->searchable()
                        ->required(),
                    
                    Select::make('booking_ids')
                        ->label('Select Bookings')
                        ->multiple()
                        ->options(function () {
                            return Booking::whereNull('therapist_id')
                                ->orWhere('therapist_id', '')
                                ->get()
                                ->mapWithKeys(function ($booking) {
                                    return [$booking->id => "{$booking->reference} - {$booking->name} ({$booking->date->format('M d, Y')})"];
                                });
                        })
                        ->searchable()
                        ->required(),
                ])
                ->action(function (array $data) {
                    $therapist = Therapist::find($data['therapist_id']);
                    $updatedCount = 0;
                    
                    foreach ($data['booking_ids'] as $bookingId) {
                        $booking = Booking::find($bookingId);
                        if ($booking) {
                            $booking->update(['therapist_id' => $data['therapist_id']]);
                            $updatedCount++;
                        }
                    }
                    
                    Notification::make()
                        ->title('Therapist Assigned')
                        ->body("{$updatedCount} booking(s) assigned to {$therapist->name}")
                        ->success()
                        ->send();
                }),

            Action::make('export_bookings')
                ->label('Export Bookings')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->form([
                    DatePicker::make('date_from')
                        ->label('From Date')
                        ->default(now()->startOfMonth()),
                    
                    DatePicker::make('date_to')
                        ->label('To Date')
                        ->default(now()->endOfMonth()),
                    
                    Select::make('status')
                        ->label('Status Filter')
                        ->options([
                            'all' => 'All Statuses',
                            'pending' => 'Pending',
                            'confirmed' => 'Confirmed',
                            'completed' => 'Completed',
                            'cancelled' => 'Cancelled',
                        ])
                        ->default('all'),
                ])
                ->action(function (array $data) {
                    // Here you would implement export logic
                    // For now, just show a notification
                    Notification::make()
                        ->title('Export Started')
                        ->body('Your booking export is being prepared and will be emailed to you shortly.')
                        ->info()
                        ->send();
                }),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Bookings')
                ->badge(Booking::count()),

            'today' => Tab::make('Today')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereDate('date', today()))
                ->badge(Booking::whereDate('date', today())->count())
                ->badgeColor('info'),

            'pending' => Tab::make('Pending')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending'))
                ->badge(Booking::where('status', 'pending')->count())
                ->badgeColor('warning'),

            'confirmed' => Tab::make('Confirmed')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'confirmed'))
                ->badge(Booking::where('status', 'confirmed')->count())
                ->badgeColor('success'),

            'upcoming' => Tab::make('Upcoming')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('date', '>=', today())->whereIn('status', ['pending', 'confirmed']))
                ->badge(Booking::where('date', '>=', today())->whereIn('status', ['pending', 'confirmed'])->count())
                ->badgeColor('primary'),

            'no_therapist' => Tab::make('Unassigned')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('therapist_id'))
                ->badge(Booking::whereNull('therapist_id')->count())
                ->badgeColor('danger'),

            'completed' => Tab::make('Completed')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'completed'))
                ->badge(Booking::where('status', 'completed')->count())
                ->badgeColor('gray'),
        ];
    }

    protected function getTableEmptyStateIcon(): ?string
    {
        return 'heroicon-o-calendar-days';
    }

    protected function getTableEmptyStateHeading(): ?string
    {
        return 'No bookings found';
    }

    protected function getTableEmptyStateDescription(): ?string
    {
        return 'Create your first booking to get started managing appointments.';
    }

    protected function getTableEmptyStateActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Create Booking')
                ->icon('heroicon-o-plus'),
        ];
    }
}