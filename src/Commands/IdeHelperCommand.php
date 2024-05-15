<?php

namespace Inertia\Commands;

use Illuminate\Console\Command;

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
        if (file_exists($file = base_path('_ide_helpers.php')) && ! $this->option('force')) {
            if (! $this->components->confirm('The [_ide_helpers.php] file already exists. Do you want to replace it?')) {
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
