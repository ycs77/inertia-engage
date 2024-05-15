<?php

namespace Inertia\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Composer;
use Illuminate\Support\Facades\Process;
use Inertia\Support\NodePackageManager;

class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inertia:install
                            {--npm : Use npm as the package manager}
                            {--yarn : Use yarn as the package manager}
                            {--pnpm : Use pnpm as the package manager}
                            {--force : Overwrite existing files by default}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install Inertia application scaffold';

    /**
     * The Composer instance.
     */
    protected Composer $composer;

    /**
     * The Node package manager instance.
     */
    protected NodePackageManager $npm;

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $cwd = getcwd();

        $this->composer = new Composer(new Filesystem(), $cwd);

        $this->npm = $this->createNpm($cwd);

        $this->info('    Initialize laravel application');
        $this->updateEditorConfig();
        $this->updateTimezoneConfig();
        $this->updateLocaleConfig();
        $this->clearDefaultJsFiles();
        $this->publishViteConfig();

        $this->components->info('Laravel application initialize successfully.');

        $this->installInertiaLaravel();
        $this->publishInertiaMiddleware();
        $this->importInertiaMiddleware();
        $this->importInertiaErrorHandler();
        $this->publishInertiaAppLayout();

        $this->components->info('Inertia.js laravel installs successfully.');

        $this->installVue();
        $this->installInertiaVue();
        $this->installTailwindCss();

        $this->installNodeDependencies();

        $this->components->info('Inertia.js application ready.  You can start your local development using:');
        $this->line('  <fg=gray>➜</>  <options=bold>'.$this->npm->buildCommand().'</>');
        $this->line('  <fg=gray>➜</>  <options=bold>php artisan serve</>');
    }

    /**
     * Get the composer command for the environment.
     */
    protected function findComposer(): string
    {
        return implode(' ', $this->composer->findComposer());
    }

    /**
     * Update the editor config.
     */
    protected function updateEditorConfig(): void
    {
        $editorConfig = file_get_contents(base_path('.editorconfig'));

        if (str_contains($editorConfig, '[*.{yml,yaml}]')) {
            $editorConfig = str_replace(
                '[*.{yml,yaml}]',
                '[*.{css,js,cjs,mjs,json,ts,vue,yml,yaml}]',
                $editorConfig
            );
        }

        if (! str_contains($editorConfig, '[composer.json]')) {
            $editorConfig = str_replace(
                "yml,yaml}]\nindent_size = 2\n",
                "yml,yaml}]\nindent_size = 2\n\n[composer.json]\nindent_size = 4\n",
                $editorConfig
            );
        }

        file_put_contents(base_path('.editorconfig'), $editorConfig);

        $this->components->info('Updated .editorconfig');
    }

    /**
     * Update the timezone configuration.
     */
    protected function updateTimezoneConfig(): void
    {
        file_put_contents(base_path('.env.example'), str_replace(
            'APP_TIMEZONE=UTC',
            'APP_TIMEZONE=Asia/Taipei',
            file_get_contents(base_path('.env.example'))
        ));

        file_put_contents(base_path('.env'), str_replace(
            'APP_TIMEZONE=UTC',
            'APP_TIMEZONE=Asia/Taipei',
            file_get_contents(base_path('.env'))
        ));

        $this->components->info('Updated timezone config');
    }

    /**
     * Update the locale configuration.
     */
    protected function updateLocaleConfig(): void
    {
        $env = file_get_contents(base_path('.env.example'));
        $env = str_replace('APP_LOCALE=en', 'APP_LOCALE=zh_TW', $env);
        $env = str_replace('APP_FAKER_LOCALE=en_US', 'APP_FAKER_LOCALE=zh_TW', $env);
        file_put_contents(base_path('.env.example'), $env);

        $env = file_get_contents(base_path('.env'));
        $env = str_replace('APP_LOCALE=en', 'APP_LOCALE=zh_TW', $env);
        $env = str_replace('APP_FAKER_LOCALE=en_US', 'APP_FAKER_LOCALE=zh_TW', $env);
        file_put_contents(base_path('.env'), $env);

        $this->components->info('Updated locale config');
    }

    /**
     * Publish the Vite configuration file.
     */
    protected function publishViteConfig(): void
    {
        copy(
            __DIR__.'/../../stubs/initialize/vite.config.js',
            base_path('vite.config.js')
        );

        $this->components->info('Published vite config');
    }

    /**
     * Clear default js files.
     */
    protected function clearDefaultJsFiles(): void
    {
        file_put_contents(resource_path('js/app.js'), '');

        @unlink(resource_path('js/bootstrap.js'));

        $this->components->info('Cleared default js files');
    }

    /**
     * Install the "inertia/laravel" package.
     */
    protected function installInertiaLaravel(): void
    {
        $this->info('    Installing inertia/laravel package');

        $this->runProcessCommand(
            $this->findComposer().' require inertiajs/inertia-laravel',
            workingPath: base_path()
        );

        // update inertia home route
        $routes = file_get_contents(base_path('routes/web.php'));

        if (str_contains($routes, 'view(\'welcome\');')) {
            $routes = str_replace(
                "use Illuminate\Support\Facades\Route;",
                "use Illuminate\Support\Facades\Route;\nuse Inertia\Inertia;",
                $routes
            );

            $routes = str_replace(
                "Route::get('/', function () {\n    return view('welcome');\n});",
                "Route::get('/', function () {\n    return Inertia::render('Home');\n});",
                $routes
            );

            file_put_contents(base_path('routes/web.php'), $routes);
        }
    }

    /**
     * Publish the Inertia middleware file.
     */
    protected function publishInertiaMiddleware(): void
    {
        $this->call('inertia:middleware', [
            '--force' => $this->option('force'),
        ]);
    }

    /**
     * Import the Inertia middleware.
     */
    protected function importInertiaMiddleware(): void
    {
        $bootatrapApp = file_get_contents(base_path('bootstrap/app.php'));

        if (str_contains($bootatrapApp, 'HandleInertiaRequests::class')) {
            $this->components->error('Inertia middleware already imported');

            return;
        }

        // import middleware class
        if (! str_contains($bootatrapApp, 'use App\\Http\\Middleware\\HandleInertiaRequests;')) {
            $bootatrapApp = str_replace(
                'use Illuminate\\Foundation\\Application;',
                "use App\\Http\\Middleware\\HandleInertiaRequests;\nuse Illuminate\\Foundation\\Application;",
                $bootatrapApp
            );
        }

        // register middleware class
        $bootatrapApp = str_replace(
            "->withMiddleware(function (Middleware \$middleware) {\n        //",
            "->withMiddleware(function (Middleware \$middleware) {\n        \$middleware->web(append: [\n            HandleInertiaRequests::class,\n        ]);",
            $bootatrapApp
        );

        file_put_contents(base_path('bootstrap/app.php'), $bootatrapApp);

        $this->components->info('Imported inertia middleware');
    }

    /**
     * Import the Inertia error handler.
     */
    protected function importInertiaErrorHandler(): void
    {
        $bootatrapApp = file_get_contents(base_path('bootstrap/app.php'));

        if (str_contains($bootatrapApp, 'Inertia::exception()')) {
            $this->components->error('Inertia error handler already imported');

            return;
        }

        // import classes
        $bootatrapApp = str_replace(
            'use Illuminate\\Foundation\\Configuration\\Middleware;',
            "use Illuminate\\Foundation\\Configuration\\Middleware;\nuse Illuminate\\Http\\Request;\nuse Inertia\\Inertia;\nuse Symfony\\Component\\HttpFoundation\\Response;",
            $bootatrapApp
        );

        // register error handler
        $bootatrapApp = str_replace(
            "->withExceptions(function (Exceptions \$exceptions) {\n        //",
            "->withExceptions(function (Exceptions \$exceptions) {\n        \$exceptions->respond(function (Response \$response, Throwable \$e, Request \$request) {\n            return Inertia::exception()->handle(\$request, \$response, \$e);\n        });",
            $bootatrapApp
        );

        file_put_contents(base_path('bootstrap/app.php'), $bootatrapApp);

        $this->components->info('Imported inertia error handler');
    }

    /**
     * Publish the Inertia app layout.
     */
    protected function publishInertiaAppLayout(): void
    {
        if (! $this->option('force') && file_exists(resource_path('views/app.blade.php'))) {
            if (! $this->components->confirm('The [resources/views/app.blade.php] file already exists. Do you want to replace it?')) {
                return;
            }
        }

        copy(
            __DIR__.'/../../stubs/laravel/app.stub',
            resource_path('views/app.blade.php')
        );

        $this->components->info('Published inertia app layout');
    }

    /**
     * Install the Vue package.
     */
    protected function installVue(): void
    {
        $this->info('    Installing vue');

        $this->npm->addDev('vue', '^3.4.0');
        $this->npm->addDev('@vitejs/plugin-vue', '^5.0.0');

        // update vite.config.js
        $viteConfig = file_get_contents(base_path('vite.config.js'));

        if (! str_contains($viteConfig, 'import Vue from \'@vitejs/plugin-vue\'')) {
            $viteConfig = str_replace(
                'import Laravel from \'laravel-vite-plugin\'',
                "import Laravel from 'laravel-vite-plugin'\nimport Vue from '@vitejs/plugin-vue'",
                $viteConfig
            );

            $viteConfig = preg_replace(
                '/(refresh: true,\n +}\),)/',
                "$1\n    Vue({\n      template: {\n        transformAssetUrls: {\n          base: null,\n          includeAbsolute: false,\n        },\n      },\n    }),",
                $viteConfig
            );

            file_put_contents(base_path('vite.config.js'), $viteConfig);
        }

        $this->components->info('Installed vue successfully.');
    }

    /**
     * Install the Inertia Vue package.
     */
    protected function installInertiaVue(): void
    {
        $this->info('    Installing @inertiajs/vue3');

        $this->npm->addDev('@inertiajs/vue3', '^1.0.15');

        // copy app.js
        copy(
            __DIR__.'/../../stubs/vue/app.js',
            resource_path('js/app.js')
        );

        // copy pages
        if (! is_dir($directory = resource_path('js/pages'))) {
            mkdir($directory, 0755, true);
        }

        copy(
            __DIR__.'/../../stubs/vue/pages/Home.vue',
            resource_path('js/pages/Home.vue')
        );

        $this->components->info('Installed inertia.js for vue.');
    }

    /**
     * Install the Tailwind CSS package.
     */
    protected function installTailwindCss(): void
    {
        $this->info('    Installing tailwindcss');

        $this->npm->addDev('tailwindcss', '^3.4.0');
        $this->npm->addDev('postcss', '^8.4.0');
        $this->npm->addDev('postcss-import', '^16.0.0');
        $this->npm->addDev('autoprefixer', '^10.0.0');

        if (! $this->option('force') && file_exists(base_path('tailwind.config.js'))) {
            if (! $this->components->confirm('The [tailwind.config.js] file already exists. Do you want to replace it?')) {
                return;
            }
        }

        // copy tailwind.config.js
        copy(
            __DIR__.'/../../stubs/tailwindcss/tailwind.config.js',
            base_path('tailwind.config.js')
        );

        if (! $this->option('force') && file_exists(base_path('postcss.config.js'))) {
            if (! $this->components->confirm('The [postcss.config.js] file already exists. Do you want to replace it?')) {
                return;
            }
        }

        // copy postcss.config.js
        copy(
            __DIR__.'/../../stubs/tailwindcss/postcss.config.js',
            base_path('postcss.config.js')
        );

        // import css in app.js
        $appJs = file_get_contents(resource_path('js/app.js'));

        if (! str_contains($appJs, 'import \'./index.css\';')) {
            $appJs = str_replace(
                '} from \'@inertiajs/vue3\'',
                "} from '@inertiajs/vue3'\nimport '../css/index.css'",
                $appJs
            );

            file_put_contents(resource_path('js/app.js'), $appJs);
        }

        // remove empty app.css file
        if (file_exists(resource_path('css/app.css'))) {
            if (file_get_contents(resource_path('css/app.css'))) {
                if ($this->components->confirm('The [resources/css/app.css] file already exists. Do you want to remove it?')) {
                    @unlink(resource_path('css/app.css'));
                }
            } else {
                @unlink(resource_path('css/app.css'));
            }
        }

        // copy index.css
        if (! file_exists(resource_path('css/index.css')) || $this->option('force')) {
            file_put_contents(
                resource_path('css/index.css'),
                file_get_contents(__DIR__.'/../../stubs/tailwindcss/index.css')
            );
        }

        $this->components->info('Installed tailwindcss successfully.');
    }

    /**
     * Create a new Node package manager instance.
     */
    protected function createNpm(string $workingPath): NodePackageManager
    {
        $npm = new NodePackageManager($workingPath);

        $npm->runningProcessWith(function ($command) use ($workingPath) {
            Process::path($workingPath)
                ->run($command, function (string $type, string $output) {
                    $this->output->write($output);
                });
        });

        $npm->format();

        return $npm;
    }

    /**
     * Install the node dependencies.
     */
    protected function installNodeDependencies(): void
    {
        $this->info('    Installing node dependencies');

        $this->npm->commit();
        $this->npm->install();
    }

    /**
     * Run the given command.
     */
    protected function runProcessCommand($command, string $workingPath): void
    {
        $command = is_array($command) ? $command : [$command];

        Process::path($workingPath)
            ->run(implode(' && ', $command), function (string $type, string $output) {
                $this->output->write($output);
            });

        $this->newLine();
    }
}
