<?php

namespace Inertia\Support;

use Ergebnis\Json\Printer\Printer as JsonPrinter;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class NodePackageManager
{
    /**
     * The package manager type.
     */
    protected NodePackageManagerType $type = NodePackageManagerType::yarn;

    /**
     * The number of spaces for indentation.
     */
    protected int $indentation = 2;

    /**
     * The packages that should be added to the "package.json" file as dependencies.
     */
    protected array $pendingAdditions = [];

    /**
     * The packages that should be added to the "package.json" file as dev dependencies.
     */
    protected array $pendingDevAdditions = [];

    /**
     * The packages that should be removed from the "package.json" file.
     */
    protected array $pendingRemovals = [];

    /**
     * The packages that should be added to the "package.json" file as scripts.
     */
    protected array $pendingScripts = [];

    /**
     * @var callable|null
     */
    protected $runningProcessWithCallback;

    /**
     * Create a new Node package manager instance.
     *
     * @return void
     */
    public function __construct(
        protected ?string $workingPath = null,
    ) {
        $this->workingPath ??= getcwd();
    }

    /**
     * Add the given package to the "package.json" file as a dependency.
     *
     * @return $this
     */
    public function add(string $package, string $version = '*')
    {
        $this->pendingAdditions[$package] = $version;

        return $this;
    }

    /**
     * Add the given package to the "package.json" file as a dev dependency.
     *
     * @return $this
     */
    public function addDev(string $package, string $version = '*')
    {
        $this->pendingDevAdditions[$package] = $version;

        return $this;
    }

    /**
     * Remove the given package from the "package.json" file.
     *
     * @return $this
     */
    public function remove(string $package)
    {
        $this->pendingRemovals[] = $package;

        return $this;
    }

    /**
     * Add the given script to the "package.json" file.
     *
     * @return $this
     */
    public function script(string $name, string $command)
    {
        $this->pendingScripts[$name] = $command;

        return $this;
    }

    /**
     * Change the package manager to use NPM.
     *
     * @return $this
     */
    public function useNpm()
    {
        $this->type = NodePackageManagerType::npm;

        return $this;
    }

    /**
     * Change the package manager to use Yarn.
     *
     * @return $this
     */
    public function useYarn()
    {
        $this->type = NodePackageManagerType::yarn;

        return $this;
    }

    /**
     * Change the package manager to use pnpm.
     *
     * @return $this
     */
    public function usePnpm()
    {
        $this->type = NodePackageManagerType::pnpm;

        return $this;
    }

    /**
     * Set the working path used by the class.
     *
     * @return $this
     */
    public function setWorkingPath(string $path)
    {
        $this->workingPath = realpath($path);

        return $this;
    }

    /**
     * Save the changes to the "package.json" file.
     */
    public function save(array $packageJsonData): void
    {
        $packageJsonFile = $this->findPackageJsonFile();
        $packageJsonData = $this->sortDependencies($packageJsonData);

        $packageJson = json_encode(
            $packageJsonData,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        $packageJson = (new JsonPrinter)->print($packageJson, str_repeat(' ', $this->indentation));

        file_put_contents($packageJsonFile, $packageJson.PHP_EOL);
    }

    /**
     * Commit the changes to the "package.json" file.
     */
    public function commit(): void
    {
        $this->modify(function (array $packageJson) {
            foreach ($this->pendingRemovals as $package) {
                unset($packageJson['dependencies'][$package]);
                unset($packageJson['devDependencies'][$package]);
            }

            foreach ($this->pendingAdditions as $package => $version) {
                $packageJson['dependencies'][$package] = $version;
            }

            foreach ($this->pendingDevAdditions as $package => $version) {
                $packageJson['devDependencies'][$package] = $version;
            }

            foreach ($this->pendingScripts as $name => $command) {
                $packageJson['scripts'][$name] = $command;
            }

            return $packageJson;
        });

        $this->pendingAdditions = [];
        $this->pendingDevAdditions = [];
        $this->pendingRemovals = [];
    }

    /**
     * Modify the "package.json" file contents using the given callback.
     *
     * @throws \RuntimeException
     */
    public function modify(callable $callback): void
    {
        $packageJsonFile = $this->findPackageJsonFile();

        /** @var array */
        $packageJsonData = json_decode(
            file_get_contents($packageJsonFile), true, 512, JSON_THROW_ON_ERROR
        );

        /** @var array */
        $packageJsonData = call_user_func($callback, $packageJsonData);

        $this->save($packageJsonData);
    }

    /**
     * Format the "package.json" file.
     *
     * @return $this
     */
    public function format(): void
    {
        $this->modify(fn (array $packageJson) => $packageJson);
    }

    /**
     * Callback that determines how the process runs.
     *
     * @param  callable  $callback
     * @return $this
     */
    public function runningProcessWith($callback)
    {
        $this->runningProcessWithCallback = $callback;

        return $this;
    }

    /**
     * Run the given command.
     *
     * @param  array|string  $command
     */
    protected function runProcessCommand($command): void
    {
        if ($this->runningProcessWithCallback) {
            call_user_func($this->runningProcessWithCallback, $command);
        } else {
            Process::path($this->workingPath)->run($command);
        }
    }

    /**
     * Install the dependencies using the package manager.
     */
    public function install(): void
    {
        $type = $this->guessManagerType();

        if ($type === NodePackageManagerType::yarn) {
            $command = 'yarn';
        } elseif ($type === NodePackageManagerType::pnpm) {
            $command = 'pnpm install';
        } else {
            $command = 'npm install';
        }

        $this->runProcessCommand($command);
    }

    /**
     * Get the build command for the package manager.
     */
    public function buildCommand(): string
    {
        $type = $this->guessManagerType();

        if ($type === NodePackageManagerType::yarn) {
            $command = 'yarn build';
        } elseif ($type === NodePackageManagerType::pnpm) {
            $command = 'pnpm run build';
        } else {
            $command = 'npm run build';
        }

        return $command;
    }

    /**
     * Get the path to the "package.json" file.
     *
     * @throws \RuntimeException
     */
    protected function findPackageJsonFile(): string
    {
        $packageJsonFile = "{$this->workingPath}/package.json";

        if (! file_exists($packageJsonFile)) {
            throw new RuntimeException("Unable to locate `package.json` file at [{$this->workingPath}].");
        }

        return $packageJsonFile;
    }

    /**
     * Sort the dependencies in the "package.json" file.
     */
    protected function sortDependencies(array $packageJson): array
    {
        if (array_key_exists('dependencies', $packageJson)) {
            ksort($packageJson['dependencies'], SORT_NATURAL);
        }

        if (array_key_exists('devDependencies', $packageJson)) {
            ksort($packageJson['devDependencies'], SORT_NATURAL);
        }

        if (array_key_exists('peerDependencies', $packageJson)) {
            ksort($packageJson['peerDependencies'], SORT_NATURAL);
        }

        return $packageJson;
    }

    /**
     * Guess the package manager type based on the working path.
     */
    protected function guessManagerType(): NodePackageManagerType
    {
        if (file_exists($this->workingPath.'/package-lock.json')) {
            return NodePackageManagerType::npm;
        } elseif (file_exists($this->workingPath.'/yarn.lock')) {
            return NodePackageManagerType::yarn;
        } elseif (file_exists($this->workingPath.'/pnpm-lock.yaml')) {
            return NodePackageManagerType::pnpm;
        }

        return $this->type;
    }
}
