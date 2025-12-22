<?php

namespace App\Providers;

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
        //
    }
}
