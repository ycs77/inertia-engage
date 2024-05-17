<?php

namespace Inertia\Commands;

use Illuminate\Console\Command;
use InvalidArgumentException;

class UiCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inertia:ui
                            {type : The preset type}
                            {--ts : Scaffolded with TypeScript support}
                            {--force : Overwrite existing files by default}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scaffold Inertia UI page and components';

    /**
     * The views that need to be exported.
     */
    protected array $resources = [
        'error' => [
            'error/Error.vue' => 'resources/js/pages/Error.vue',
        ],
        'error-ts' => [
            'error/Error-ts.vue' => 'resources/js/pages/Error.vue',
        ],
        'pagination' => [
            'pagination/Pagination.vue' => 'resources/js/components/Pagination.vue',
            'pagination/pagination.css' => 'resources/css/pagination.css',
        ],
        'pagination-ts' => [
            'pagination/Pagination-ts.vue' => 'resources/js/components/Pagination.vue',
            'pagination/pagination.css' => 'resources/css/pagination.css',
        ],
    ];

    /**
     * Execute the console command.
     *
     * @throws \InvalidArgumentException
     */
    public function handle(): void
    {
        $type = $this->resolveTypeArgument();

        if (! in_array($type, array_keys($this->resources))) {
            throw new InvalidArgumentException('Invalid preset.');
        }

        $this->exportResources($type);

        $this->components->info('Inertia UI scaffolding generated successfully.');
    }

    /**
     * Resolve the type argument.
     *
     * @return string|null
     */
    protected function resolveTypeArgument()
    {
        $type = $this->argument('type');

        if ($this->option('ts')) {
            $type .= '-ts';
        }

        return $type;
    }

    protected function exportResources(string $type): void
    {
        foreach ($this->resources[$type] as $source => $destination) {
            if (! $this->option('force') && file_exists($file = base_path($destination))) {
                if (! $this->components->confirm("The [$destination] file already exists. Do you want to replace it?")) {
                    continue;
                }
            }

            if (str_contains($destination, '/') &&
                ! is_dir($directory = base_path(dirname($destination)))
            ) {
                mkdir($directory, 0755, true);
            }

            copy(
                __DIR__.'/../../stubs/'.$source,
                $file,
            );
        }
    }
}
