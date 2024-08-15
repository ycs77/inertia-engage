<?php

namespace Inertia\Console\Concerns;

trait HasInitializeLaravelApp
{
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

        if (str_contains($editorConfig, '[docker-compose.yml]')) {
            $editorConfig = str_replace(
                "\n[docker-compose.yml]\nindent_size = 4\n",
                '',
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
}
