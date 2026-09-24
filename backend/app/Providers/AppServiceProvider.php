<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\User;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Shift;
use App\Models\Payment;
use App\Policies\UserPolicy;
use App\Policies\OrderPolicy;
use App\Policies\ShiftPolicy;
use App\Observers\OrderObserver;
use App\Observers\OrderItemObserver;
use App\Observers\PaymentObserver;
use App\Observers\UserObserver;

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
        // Register policies
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Shift::class, ShiftPolicy::class);

        // Register observers for audit logging
        Order::observe(OrderObserver::class);
        OrderItem::observe(OrderItemObserver::class);
        Payment::observe(PaymentObserver::class);
        User::observe(UserObserver::class);
    }
}