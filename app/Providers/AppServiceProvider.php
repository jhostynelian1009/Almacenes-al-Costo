<?php

namespace App\Providers;

use App\Models\Product;
use App\Observers\ProductObserver;
use App\Services\CartService;
use App\Services\Payments\PaymentFactory;
use App\Services\Payments\PaymentService;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PaymentFactory::class);

        $this->app->singleton(PaymentService::class, function ($app) {
            return new PaymentService($app->make(PaymentFactory::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Forzar HTTPS si la página se está abriendo desde Ngrok
        if (str_contains(request()->headers->get('X-Forwarded-Host'), 'ngrok')) {
            URL::forceScheme('https');
        }

        Product::observe(ProductObserver::class);

        View::composer('components.public.navbar', function ($view): void {
            $view->with('cartUnitCount', app(CartService::class)->unitCount());
        });

    }
}
