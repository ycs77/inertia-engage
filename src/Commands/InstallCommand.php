<?php

namespace Inertia\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Composer;
use Illuminate\Support\Facades\Process;
use Inertia\Support\NodePackageManager;

use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;

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
                            {--ts : Scaffolded with TypeScript support}
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
     * The javascript file extension.
     */
    protected string $js = 'js';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $cwd = getcwd();

        $this->js = $this->option('ts') ? 'ts' : 'js';

        $this->composer = new Composer(new Filesystem(), $cwd);

        $this->npm = $this->createNpm($cwd);

        if (! $this->option('ts')) {
            collect(multiselect(
                label: 'Would you like any optional features?',
                options: [
                    'ts' => 'TypeScript',
                ],
                default: array_filter([
                    $this->option('ts') ? 'typescript' : null,
                ]),
            ))->each(fn ($option) => $this->input->setOption($option, true));
        }

        if (! $this->option('npm') &&
            ! $this->option('yarn') &&
            ! $this->option('pnpm') &&
            ! $this->npm->lockFileExists()
        ) {
            match (select(
                label: 'Which Node package manager do you want to use?',
                options: [
                    'npm' => 'npm',
                    'yarn' => 'yarn',
                    'pnpm' => 'pnpm',
                ],
                default: 'yarn',
            )) {
                'npm' => $this->input->setOption('npm', true),
                'yarn' => $this->input->setOption('yarn', true),
                'pnpm' => $this->input->setOption('pnpm', true),
                default => null,
            };
        }

        if ($this->option('npm')) {
            $this->npm->useNpm();
        } elseif ($this->option('yarn')) {
            $this->npm->useYarn();
        } elseif ($this->option('pnpm')) {
            $this->npm->usePnpm();
        }

        $this->info('    Initialize laravel application');
        $this->updateEditorConfig();
        $this->updateTimezoneConfig();
        $this->updateLocaleConfig();
        $this->clearDefaultJsFiles();

        $this->components->info('Laravel application initialize successfully.');

        $this->installInertiaLaravel();
        $this->publishInertiaMiddleware();
        $this->importInertiaMiddleware();
        $this->importInertiaErrorHandler();
        $this->publishInertiaAppLayout();

        $this->components->info('Inertia.js laravel installs successfully.');

        $this->installVue();
        $this->installTypescript();
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
     * Clear default js files.
     */
    protected function clearDefaultJsFiles(): void
    {
        if (file_exists(resource_path('js/app.js'))) {
            $js = 'js';
        } elseif (file_exists(resource_path('js/app.ts'))) {
            $js = 'ts';
        } else {
            return;
        }

        $appJs = trim(file_get_contents(resource_path("js/app.$js")));

        if (str_contains($appJs, "import './bootstrap';")) {
            file_put_contents(resource_path("js/app.$js"), '');

            @unlink(resource_path('js/bootstrap.js'));

            $this->components->info('Cleared default js files');
        }
    }

    /**
     * Install the "inertiajs/inertia-laravel" package.
     */
    protected function installInertiaLaravel(): void
    {
        $this->info('    Installing inertiajs/inertia-laravel package');

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

        @unlink(resource_path('views/welcome.blade.php'));

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

        $js = $this->js;

        // update vite.config.js
        if (! $this->option('force') && file_exists(base_path("vite.config.$js"))) {
            if (! $this->components->confirm("The [vite.config.$js] file already exists. Do you want to replace it?")) {
                return;
            }
        }

        if ($this->option('ts') && file_exists(base_path('vite.config.js'))) {
            @unlink(base_path('vite.config.js'));
        }

        copy(
            __DIR__.'/../../stubs/vue/vite.config.js',
            base_path("vite.config.$js")
        );

        $this->components->info('Installed vue successfully.');
    }

    /**
     * Install the TypeScript package.
     */
    protected function installTypescript(): void
    {
        if (! $this->option('ts')) {
            return;
        }

        $this->info('    Installing typescript');

        // install typescript
        $this->npm->addDev('typescript', '~5.4.0');
        $this->npm->addDev('@types/node', '^20.0.0');
        $this->npm->addDev('@tsconfig/node20', '^20.0.0');

        // install typescript for vue
        $this->npm->addDev('vue-tsc', '^2.0.17');
        $this->npm->addDev('@vue/tsconfig', '^0.5.1');
        $this->npm->script('type-check', 'vue-tsc --build --force');

        $tsconfigs = [
            'tsconfig.json',
            'tsconfig.app.json',
            'tsconfig.node.json',
        ];

        foreach ($tsconfigs as $tsconfig) {
            if (! $this->option('force') && file_exists(base_path($tsconfig))) {
                if (! $this->components->confirm("The [$tsconfig] file already exists. Do you want to replace it?")) {
                    return;
                }
            }

            copy(
                __DIR__."/../../stubs/typescript/$tsconfig",
                base_path($tsconfig)
            );
        }

        if (! $this->option('force') && file_exists(resource_path('js/shims/env.d.ts'))) {
            if (! $this->components->confirm('The [resources/js/shims/env.d.ts] file already exists. Do you want to replace it?')) {
                return;
            }
        }

        if (! is_dir($directory = resource_path('js/shims'))) {
            mkdir($directory, 0755, true);
        }

        copy(
            __DIR__.'/../../stubs/typescript/shims/env.d.ts',
            resource_path('js/shims/env.d.ts')
        );

        $this->components->info('Installed typescript successfully.');
    }

    /**
     * Install the Inertia Vue package.
     */
    protected function installInertiaVue(): void
    {
        $this->info('    Installing @inertiajs/vue3');

        $this->npm->addDev('@inertiajs/vue3', '^1.0.15');

        $js = $this->js;

        // copy app.js
        if (! $this->option('force') && file_exists(resource_path("js/app.$js"))) {
            if (! $this->components->confirm("The [resources/js/app.$js] file already exists. Do you want to replace it?")) {
                return;
            }
        }

        if ($this->option('ts') && file_exists(resource_path('js/app.js'))) {
            @unlink(resource_path('js/app.js'));
        }

        copy(
            __DIR__."/../../stubs/vue/app.$js",
            resource_path("js/app.$js")
        );

        if ($this->option('ts')) {
            // update app.js path in vite.config.js
            file_put_contents(base_path('vite.config.ts'), str_replace(
                "'resources/js/app.js'",
                "'resources/js/app.ts'",
                file_get_contents(base_path('vite.config.ts'))
            ));

            // update app.js path in app layout
            file_put_contents(resource_path('views/app.blade.php'), str_replace(
                "'resources/js/app.js'",
                "'resources/js/app.ts'",
                file_get_contents(resource_path('views/app.blade.php'))
            ));
        }

        // copy pages
        if (! is_dir($directory = resource_path('js/pages'))) {
            mkdir($directory, 0755, true);
        }

        if (! $this->option('force') && file_exists(resource_path('js/pages/Home.vue'))) {
            if (! $this->components->confirm('The [resources/js/pages/Home.vue] file already exists. Do you want to replace it?')) {
                return;
            }
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

        $js = $this->js;

        // copy tailwind.config.js
        if (! $this->option('force') && file_exists(base_path("tailwind.config.$js"))) {
            if (! $this->components->confirm("The [tailwind.config.$js] file already exists. Do you want to replace it?")) {
                return;
            }
        }

        if ($this->option('ts') && file_exists(base_path('tailwind.config.js'))) {
            @unlink(base_path('tailwind.config.js'));
        }

        copy(
            __DIR__."/../../stubs/tailwindcss/tailwind.config.$js",
            base_path("tailwind.config.$js")
        );

        // copy postcss.config.js
        if (! $this->option('force') && file_exists(base_path('postcss.config.js'))) {
            if (! $this->components->confirm('The [postcss.config.js] file already exists. Do you want to replace it?')) {
                return;
            }
        }

        copy(
            __DIR__.'/../../stubs/tailwindcss/postcss.config.js',
            base_path('postcss.config.js')
        );

        // import css in app.js
        $appJs = file_get_contents(resource_path("js/app.$js"));

        if (! str_contains($appJs, 'import \'./index.css\';')) {
            $appJs = str_replace(
                '} from \'@inertiajs/vue3\'',
                "} from '@inertiajs/vue3'\nimport '../css/index.css'",
                $appJs
            );

            file_put_contents(resource_path("js/app.$js"), $appJs);
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
                ->timeout(10 * 60) // 10 minutes
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
