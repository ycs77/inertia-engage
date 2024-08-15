<?php

namespace Inertia\Console\Concerns;

use Illuminate\Support\Facades\Process;
use Inertia\Support\NodePackageManager;

trait HasNodePackageManager
{
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
}
