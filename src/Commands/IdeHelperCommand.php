<?php

namespace Inertia\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'inertia:ide-helper')]
class IdeHelperCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inertia:ide-helper
                            {--force : Overwrite existing files by default}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish Inertia IDE helper file';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        if (! $this->option('force') && file_exists($file = base_path('_ide_helpers.php'))) {
            if (! $this->components->confirm('The [_ide_helpers.php] file already exists. Do you want to replace it?')) {
                return;
            }
        }

        if (! $this->option('force') && file_exists(base_path('vendor/inertiajs/inertia-laravel/_ide_helpers.php'))) {
            if (! $this->components->confirm('The [_ide_helpers.php] file already exists in the Inertia package. Do you want to publish it?')) {
                return;
            }
        }

        copy(
            __DIR__.'/../../stubs/ide-helper/_ide_helpers.stub',
            $file,
        );

        $this->components->info('Inertia IDE helper file published.');
    }
}
