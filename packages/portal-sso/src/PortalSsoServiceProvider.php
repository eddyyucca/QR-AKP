<?php

namespace Akp\PortalSso;

use Akp\PortalSso\Console\CheckCommand;
use Akp\PortalSso\Console\InstallCommand;
use Akp\PortalSso\Http\Middleware\RequirePortalLevel;
use Akp\PortalSso\Http\Middleware\RequirePortalLogin;
use Akp\PortalSso\Http\Middleware\RequirePortalRole;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

class PortalSsoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/sso.php', 'sso');
    }

    public function boot(Router $router): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'portal-sso');

        $this->publishes([__DIR__.'/../config/sso.php' => config_path('sso.php')], 'portal-sso-config');
        $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/portal-sso')], 'portal-sso-views');

        if ($this->app->runningInConsole()) {
            $this->commands([InstallCommand::class, CheckCommand::class]);
        }

        $router->aliasMiddleware('portal.login', RequirePortalLogin::class);
        $router->aliasMiddleware('portal.level', RequirePortalLevel::class);
        $router->aliasMiddleware('portal.role', RequirePortalRole::class);

        // Lewat Kernel (kontrak, bukan kelas konkret atau Router): Kernel menyinkronkan ulang grup middleware ke Router,
        // jadi penambahan lewat Router bisa tertimpa.
        if (config('sso.protect_all')) {
            $this->app->make(HttpKernel::class)->appendMiddlewareToGroup('web', RequirePortalLogin::class);
        }
    }
}