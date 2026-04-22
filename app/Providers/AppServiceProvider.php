<?php

namespace App\Providers;

use App\Services\NotificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
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
        View::composer(['partials.header', 'partials.sidebar'], function ($view) {
            if (!Auth::check()) {
                $view->with([
                    'headerNotifications' => ['count' => 0, 'items' => [], 'latest_ts' => 0],
                    'sidebarNotificationCounts' => ['leave_requests' => 0, 'apply_leave' => 0],
                ]);
                return;
            }

            $user = Auth::user();
            /** @var NotificationService $notifications */
            $notifications = app(NotificationService::class);
            $view->with([
                'headerNotifications' => $notifications->headerNotifications($user, 8),
                'sidebarNotificationCounts' => $notifications->sidebarCounts($user),
            ]);
        });
    }
}
