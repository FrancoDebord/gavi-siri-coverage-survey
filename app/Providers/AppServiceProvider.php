<?php

namespace App\Providers;

use App\Services\EligibleKeysService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //

        // On déclare le service comme singleton
        $this->app->singleton(EligibleKeysService::class, function () {
            return new EligibleKeysService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // On partage les clés éligibles dans toutes les vues
        $eligibleService = app(EligibleKeysService::class);
        view()->share('eligibleKeys', $eligibleService->all());
    }
}
