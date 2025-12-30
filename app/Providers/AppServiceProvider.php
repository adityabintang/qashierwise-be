<?php

namespace App\Providers;

use App\Helpers\LocalizationHelper;
use App\Services\BalanceService;
use App\Services\FinancialAuditService;
use App\Services\MediaStorageService;
use App\Services\QrisService;
use App\Services\WithdrawalService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(MediaStorageService::class, function ($app) {
            return new MediaStorageService();
        });

        // Register LocalizationHelper as singleton
        $this->app->singleton(LocalizationHelper::class, function ($app) {
            return new LocalizationHelper();
        });

        // Register FinancialAuditService as singleton
        $this->app->singleton(FinancialAuditService::class, function ($app) {
            $service = new FinancialAuditService();
            
            // Set request context if available
            if ($app->has('request')) {
                $service->setRequest($app->make('request'));
            }
            
            return $service;
        });

        // Extend BalanceService to inject audit service
        $this->app->extend(BalanceService::class, function ($service, $app) {
            if ($app->has(FinancialAuditService::class)) {
                $service->setAuditService($app->make(FinancialAuditService::class));
            }
            return $service;
        });

        // Extend QrisService to inject audit service
        $this->app->extend(QrisService::class, function ($service, $app) {
            if ($app->has(FinancialAuditService::class)) {
                $service->setAuditService($app->make(FinancialAuditService::class));
            }
            return $service;
        });

        // Extend WithdrawalService to inject audit service
        $this->app->extend(WithdrawalService::class, function ($service, $app) {
            if ($app->has(FinancialAuditService::class)) {
                $service->setAuditService($app->make(FinancialAuditService::class));
            }
            return $service;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force HTTPS when behind proxy (Easypanel, Cloudflare, etc.)
        if ($this->app->environment('production') || str_starts_with(config('app.url'), 'https')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        // Handle missing translation keys in development
        if ($this->app->isLocal()) {
            \Illuminate\Support\Facades\Lang::handleMissingKeysUsing(function ($key) {
                \Illuminate\Support\Facades\Log::channel('missing_translations')
                    ->warning('Missing translation key', [
                        'key' => $key,
                        'locale' => app()->getLocale(),
                        'url' => request()->fullUrl(),
                        'timestamp' => now()->toDateTimeString(),
                    ]);
                
                return $key;
            });
        }

        // Register custom Blade directives for localization
        $this->registerBladeDirectives();
    }

    /**
     * Register custom Blade directives for localization
     */
    protected function registerBladeDirectives(): void
    {
        // @trans directive for inline translations
        \Illuminate\Support\Facades\Blade::directive('trans', function ($expression) {
            return "<?php echo __($expression); ?>";
        });

        // @locale directive for current locale
        \Illuminate\Support\Facades\Blade::directive('locale', function () {
            return "<?php echo app()->getLocale(); ?>";
        });
    }
}
