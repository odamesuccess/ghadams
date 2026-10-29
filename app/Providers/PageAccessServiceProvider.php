<?php

namespace App\Providers;

use App\Support\PageAccessCatalog;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\ServiceProvider;

class PageAccessServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->booted(function (): void {
            foreach ($this->app['router']->getRoutes() as $route) {
                app(PageAccessCatalog::class)->apply($route);
            }
        });
        // Also covers routes loaded from a compiled route cache.
        $this->app['events']->listen(RouteMatched::class, function (RouteMatched $event): void {
            app(PageAccessCatalog::class)->apply($event->route);
        });
    }
}
