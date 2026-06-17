<?php

namespace App\Providers;

use App\Events\BookingCancelled;
use App\Events\BookingConfirmed;
use App\Events\BookingCreated;
use App\Listeners\SendBookingConfirmation;
use App\Listeners\SendBookingCreatedNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],

        BookingCreated::class => [
            SendBookingCreatedNotification::class,
        ],

        BookingConfirmed::class => [
            SendBookingConfirmation::class,
        ],

        BookingCancelled::class => [
            \App\Listeners\HandleBookingCancellation::class,
        ],
    ];

    public function boot(): void {}
}
