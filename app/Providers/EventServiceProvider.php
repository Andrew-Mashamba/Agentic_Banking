<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        \App\Events\OrderItemUpdated::class => [
            \App\Listeners\SendPrepStatusFcmNotification::class,
        ],
        \App\Events\OrderItemReady::class => [
            \App\Listeners\NotifyWaiter::class,
        ],
        \App\Events\OrderStatusUpdated::class => [
            \App\Listeners\SendOrderReadyFcmNotification::class,
        ],
        \App\Events\WaiterRequested::class => [
            \App\Listeners\SendWaiterRequestFcm::class,
        ],
        \App\Events\ManagerRequested::class => [
            \App\Listeners\SendManagerRequestFcm::class,
        ],
        \App\Events\WhatsAppCircuitOpened::class => [
            \App\Listeners\LogWhatsAppCircuitOpened::class,
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
