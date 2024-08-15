<?php

namespace Inertia\Console;

use Illuminate\Console\Command;
use Inertia\Support\NodePackageManager;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'laravel:initial')]
class LaravelInitialCommand extends Command
{
    use Concerns\HasInitializeLaravelApp;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'laravel:initial';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initialize Laravel application';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $cwd = getcwd();

        $this->info('    Initialize laravel application');
        $this->updateEditorConfig();
        $this->updateTimezoneConfig();
        $this->updateLocaleConfig();
        $this->clearDefaultJsFiles();
        $this->formatPackageJson($cwd);

        $this->components->info('Laravel application initialize successfully.');
    }

    protected function formatPackageJson(string $cwd): void
    {
        (new NodePackageManager($cwd))->format();
    }
}
