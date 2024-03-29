<?php

namespace Eightbitsnl\NovaReports;

use Eightbitsnl\NovaReports\Console\Commands\ReportsOutput;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Eightbitsnl\NovaReports\Http\Middleware\Authorize;
use Eightbitsnl\NovaReports\Nova\Resources\Report;
use Laravel\Nova\Events\ServingNova;
use Laravel\Nova\Nova;

class NovaReportsServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->registerPublishables();

        $this->loadMigrationsFrom(__DIR__ . "/database/migrations");

        $this->loadViewsFrom(__DIR__ . "/resources/views", "NovaReports");

        $this->mergeConfigFrom(__DIR__ . "/../config/nova-reports.php", "nova-reports");

        $this->commands([ReportsOutput::class]);

        $this->app->booted(function () {
            $this->routes();
        });

        Nova::serving(function (ServingNova $event) {
            Nova::script("querybuilder-field", __DIR__ . "/../dist/js/field.js");
            Nova::style("querybuilder-field", __DIR__ . "/../dist/css/field.css");
        });

        Nova::resources([Report::class]);
    }

    protected function registerPublishables(): void
    {
        $this->publishes(
            [
                __DIR__ . "/../dist/css/webview.css" => public_path("vendor/nova-reports/css/webview.css"),
            ],
            "nova-reports/assets"
        );

        $this->publishes(
            [
                __DIR__ . "/../config/nova-reports.php" => config_path("nova-reports.php"),
            ],
            "nova-reports/config"
        );
    }

    protected function routes()
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        Route::middleware(["nova", Authorize::class])
            ->prefix( config('nova-reports.routes.prefix.api') )
            ->group(__DIR__ . "/routes/api.php");

        Route::middleware(["nova", Authorize::class])
            ->prefix( config('nova-reports.routes.prefix.web') )
            ->group(__DIR__ . "/routes/web.php");
    }
}
