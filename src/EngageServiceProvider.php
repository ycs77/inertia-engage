<?php

namespace Inertia;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\ServiceProvider;
use Inertia\Commands\IdeHelperCommand;
use Inertia\Commands\InstallCommand;
use Inertia\Commands\UiCommand;
use Inertia\Exceptions\Handler as ExceptionHandler;
use Inertia\Pagination\Paginator;

class EngageServiceProvider extends ServiceProvider
{
    /**
     * Register package service.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/inertia-engage.php', 'inertia-engage');

        $this->registerCommands();
        $this->registerPaginator();
        $this->registerExceptionHandler();
    }

    /**
     * Bootstrap package service.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/inertia-engage.php' => config_path('inertia-engage.php'),
            ], 'inertia-engage-config');
        }
    }

    /**
     * Register package commands.
     */
    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                IdeHelperCommand::class,
                InstallCommand::class,
                UiCommand::class,
            ]);
        }
    }

    /**
     * Register package exception handler.
     */
    protected function registerPaginator(): void
    {
        if ($this->app['config']->get('inertia-engage.pagination.register')) {
            $this->app->bind(LengthAwarePaginator::class, Paginator::class);
        }
    }

    /**
     * Register package exception handler.
     */
    protected function registerExceptionHandler(): void
    {
        ResponseFactory::macro('exception', function () {
            /** @var \Inertia\ResponseFactory $this */
            return new ExceptionHandler($this);
        });
    }
}
