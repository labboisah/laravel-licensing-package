<?php

namespace KernelBridge\LicensingClient;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use KernelBridge\LicensingClient\Console\ReprovisionDeploymentProfileCommand;
use KernelBridge\LicensingClient\Console\ResetLicenseCacheCommand;
use KernelBridge\LicensingClient\Http\Middleware\FeatureMiddleware;
use KernelBridge\LicensingClient\Http\Middleware\LicenseMiddleware;
use KernelBridge\LicensingClient\Http\Middleware\LimitMiddleware;
use KernelBridge\LicensingClient\Jobs\VerifyLicenseJob;
use KernelBridge\LicensingClient\Services\DeploymentProfile;
use KernelBridge\LicensingClient\Services\DeviceIdentity;

final class KernelBridgeLicensingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/kernelbridge-licensing.php', 'kernelbridge-licensing');
        $this->app->singleton(KernelBridgeLicenseClient::class);
        $this->app->singleton(DeviceIdentity::class);
        $this->app->singleton(DeploymentProfile::class);
    }

    public function boot(Router $router): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'kernelbridge-licensing');

        if ((bool) config('kernelbridge-licensing.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        }

        $this->publishes([__DIR__.'/../config/kernelbridge-licensing.php' => config_path('kernelbridge-licensing.php')], 'kernelbridge-licensing-config');
        $this->publishes([__DIR__.'/../database/migrations' => database_path('migrations')], 'kernelbridge-licensing-migrations');
        $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/kernelbridge-licensing')], 'kernelbridge-licensing-views');
        $this->publishes([__DIR__.'/../public' => public_path('vendor/kernelbridge-licensing')], 'kernelbridge-licensing-assets');
        $this->publishes([__DIR__.'/../routes/web.php' => base_path('routes/kernelbridge-licensing.php')], 'kernelbridge-licensing-routes');

        $router->aliasMiddleware('kernelbridge.subscription.active', LicenseMiddleware::class);
        $router->aliasMiddleware('kernelbridge.feature', FeatureMiddleware::class);
        $router->aliasMiddleware('kernelbridge.limit', LimitMiddleware::class);

        if ((bool) config('kernelbridge-licensing.protection.web', false)) {
            $this->app->make(HttpKernel::class)->pushMiddleware(LicenseMiddleware::class);
        }

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            ResetLicenseCacheCommand::class,
            ReprovisionDeploymentProfileCommand::class,
        ]);

        $this->app->booted(function (): void {
            $minutes = min(59, max(1, (int) config('kernelbridge-licensing.verification_interval_minutes', 15)));
            app(Schedule::class)->job(new VerifyLicenseJob)->cron("*/$minutes * * * *")
                ->name('kernelbridge-license-verification')->withoutOverlapping()->onOneServer();
        });
    }
}
