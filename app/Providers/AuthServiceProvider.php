<?php

namespace App\Providers;

use Illuminate\Support\Facades\Log;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // Rejestrujemy polityki, aby Laravel wiedział, jak ich używać.
        \App\Models\Ticket::class => \App\Policies\TicketPolicy::class,
        \App\Models\User::class => \App\Policies\UserPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Definiuje, kto ma dostęp do funkcji panelu administracyjnego (IT i Admin)
        Gate::define('access-admin-features', function (User $user) {
            $allowed = in_array(strtolower($user->role), ['admin', 'it']);
            Log::info('Gate access-admin-features', ['user_id' => $user->id, 'role' => $user->role, 'allowed' => $allowed]);
            return $allowed;
        });

        Gate::define('access-it-features', function (User $user) {
            $allowed = in_array(strtolower($user->role), ['admin', 'it']);
            Log::info('Gate access-it-features', ['user_id' => $user->id, 'role' => $user->role, 'allowed' => $allowed]);
            return $allowed;
        });

    }
}
