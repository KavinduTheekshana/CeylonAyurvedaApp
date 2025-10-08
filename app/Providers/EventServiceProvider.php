<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use App\Events\BookingCreated;
use App\Events\BookingCancelled;
use App\Listeners\NotifyTherapistOfNewBooking;
use App\Listeners\SendBookingConfirmationEmail;
use App\Listeners\SendBookingCancellationEmail;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        // ... existing events ...

        BookingCreated::class => [
            NotifyTherapistOfNewBooking::class,   // Send push notification to therapist
            SendBookingConfirmationEmail::class,      // Send email to customer
        ],

         BookingCancelled::class => [
            SendBookingCancellationEmail::class,      // Send cancellation email to customer
            // You can add more listeners here, e.g.:
            // NotifyTherapistOfCancellation::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}