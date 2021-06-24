<?php

namespace Eightbitsnl\NovaReports;


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

        // $this->loadViewsFrom(__DIR__.'/../resources/views', 'NovaReports');

        $this->app->booted(function () {
            $this->routes();
        });

		Nova::serving(function (ServingNova $event) {
            Nova::script('querybuilder-field', __DIR__.'/../dist/js/field.js');
            Nova::style('querybuilder-field', __DIR__.'/../dist/css/field.css');
        });


        Nova::resources([
            Report::class
        ]);
    }

    protected function registerPublishables(): void
    {

        if (! class_exists('CreateReportsTable')) {
            $this->publishes([
                __DIR__.'/../database/migrations/create_reports_table.php.stub' => database_path('migrations/'.date('Y_m_d_His', time()).'_create_reports_table.php'),
            ], 'migrations');
        }
    }

    protected function routes()
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        Route::middleware(['nova', Authorize::class])
            ->prefix('/nova-vendor/eightbitsnl/nova-reports')
            ->group(
                __DIR__.'/../routes/api.php'
            );
    }
}