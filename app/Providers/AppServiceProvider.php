<?php

namespace App\Providers;

use App\Models\Message;
use App\Models\Ticket;
use App\Models\User;
use App\Observers\MessageObserver;
use App\Observers\TicketObserver;
use App\Observers\UserObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('app.env') === 'production') {
            \URL::forceScheme('https');
        }

      

    if (request()->is('admin*') || request()->is('filament*') || request()->is('livewire*')) {
        config([
            'session.domain' => null,
            'session.same_site' => 'lax',
        ]);
    }


        User::observe(UserObserver::class);
        Ticket::observe(TicketObserver::class);
        Message::observe(MessageObserver::class);
    }
}
