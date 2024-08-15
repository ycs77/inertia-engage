<?php

namespace Inertia\Console\Concerns;

use Illuminate\Support\Facades\Process;

trait HasProcess
{
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
