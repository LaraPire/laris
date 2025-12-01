<?php

namespace Laris;

use Laris\Commands\Ai\ConfigCommand as AiConfigCommand;
use Laris\Commands\Ai\GenerateModuleCommand;
use Laris\Commands\Ai\MakeConsoleCommand;
use Laris\Commands\Ai\MakeControllerCommand;
use Laris\Commands\Ai\MakeFactoryCommand;
use Laris\Commands\Ai\MakeJobCommand;
use Laris\Commands\Ai\MakeMailCommand;
use Laris\Commands\Ai\MakeMigrationCommand;
use Laris\Commands\Ai\MakeModelCommand;
use Laris\Commands\Ai\MakeNotificationCommand;
use Laris\Commands\Ai\MakeObserverCommand;
use Laris\Commands\Ai\MakePolicyCommand;
use Laris\Commands\Ai\MakeRequestCommand;
use Laris\Commands\Ai\MakeResourceCommand;
use Laris\Commands\Ai\MakeRuleCommand;
use Laris\Commands\Ai\MakeSeederCommand;
use Laris\Commands\Ai\MakeServiceCommand;
use Laris\Commands\BackupCommand;
use Laris\Commands\ComposerCommand;
use Laris\Commands\ConfigCommand;
use Laris\Commands\DbCommand;
use Laris\Commands\DeployCommand;
use Laris\Commands\DockerCommand;
use Laris\Commands\DocsCommand;
use Laris\Commands\GitCommand;
use Laris\Commands\HookCommand;
use Laris\Commands\NewCommand;
use Laris\Commands\NewCommandLaris;
use Laris\Commands\NpmCommand;
use Laris\Commands\PerformanceCommand;
use Laris\Commands\ProjectSelectCommand;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

class Application extends SymfonyApplication
{
    /**
     * Path to the Laravel project.
     *
     * @var string|null
     */
    private ?string $laravelPath = null;

    /**
     * History of recently accessed projects.
     *
     * @var array
     */
    private array $projectHistory = [];

    /**
     * Path to the history file.
     *
     * @var string
     */
    private string $historyFile;

    /**
     * Path to the projects file.
     *
     * @var string
     */
    private string $projectsFile;

    /**
     * List of saved projects.
     *
     * @var array
     */
    private array $savedProjects = [];

    /**
     * Symfony console application instance.
     *
     * @var SymfonyApplication
     */
    protected SymfonyApplication $cli;

    /**
     * Create a new Laris application instance.
     */
    protected $version;
    public function __construct(string $version)
    {
        parent::__construct('Laris CLI', $version);
        $this->version = $version;
        // Initialize file paths for history and projects
        $home = $_SERVER['HOME']
            ?? getenv('HOME')
            ?? ($_SERVER['HOMEDRIVE'] ?? '') . ($_SERVER['HOMEPATH'] ?? '')
            ?? getenv('USERPROFILE');
        $this->historyFile = $home . '/.laris_history';
        $this->projectsFile = $home . '/.laris_projects';
        $this->loadHistory();
        $this->loadProjects();
        $this->detectLaravelProject();
        $this->add(new GitCommand());
        $this->add(new DockerCommand());
        $this->add(new ComposerCommand());
        $this->add(new DbCommand());
        $this->add(new NpmCommand());
        $this->add(new PerformanceCommand());
        $this->add(new BackupCommand());
        $this->add(new ConfigCommand());
        $this->add(new DeployCommand());
        $this->add(new DocsCommand());
        $this->add(new HookCommand());
        $this->add(new NewCommandLaris());
        $this->add(new ProjectSelectCommand($this->projectHistory));
        $this->add(new NewCommand());
        $this->add(new AiConfigCommand());
        $this->add(new MakeControllerCommand());
        $this->add(new MakeFactoryCommand());
        $this->add(new MakeRequestCommand());
        $this->add(new MakeJobCommand());
        $this->add(new MakeMailCommand());
        $this->add(new MakeRuleCommand());
        $this->add(new MakeModelCommand());
        $this->add(new MakePolicyCommand());
        $this->add(new MakeSeederCommand());
        $this->add(new MakeConsoleCommand());
        $this->add(new MakeServiceCommand());
        $this->add(new MakeObserverCommand());
        $this->add(new MakeResourceCommand());
        $this->add(new MakeMigrationCommand());
        $this->add(new MakeNotificationCommand());
        $this->add(new GenerateModuleCommand());
        $this->loadDynamicCommands();
    }
    private function loadDynamicCommands(): void
    {
        $dir = __DIR__ . '/Commands/Laris'; // مسیر فایل‌های دستورات متنی
        if (!is_dir($dir)) {
            return;
        }

        $files = scandir($dir);
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) !== 'txt') {
                continue;
            }

            $path = $dir . '/' . $file;
            $content = file_get_contents($path);
            $data = $this->parseCommandFile($content);

            if (!$data || empty($data['name']) || strpos($data['name'], 'laris:') !== 0) {
                continue;
            }

            $command = $this->createCommandFromData($data);
            if ($command) {
                $this->add($command);
            }
        }
    }

    private function parseCommandFile(string $content): ?array
    {
        $lines = explode("\n", $content);
        $data = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '=') === false) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $data[trim($key)] = trim($value);
        }
        return $data ?: null;
    }

    private function createCommandFromData(array $data): ?\Symfony\Component\Console\Command\Command
    {
        $name = $data['name'];
        $description = !empty($data['description']) ? $data['description'] : 'No description';

        return new class ($name, $description) extends \Symfony\Component\Console\Command\Command {
            private $desc;

            public function __construct(string $name, string $description)
            {
                parent::__construct($name);
                $this->desc = $description;
            }

            protected function configure(): void
            {
                $this->setDescription($this->desc ?? 'No description');
            }

            protected function execute(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output): int
            {
                $output->writeln("Executing dynamic command: " . $this->getName());
                return self::SUCCESS;
            }
        };
    }


    public function debugCommands(): void
    {
        foreach ($this->all() as $name => $command) {
            var_dump("Command registered:", $name);
        }
    }

    /**
     * Run the application.
     *
     * @param InputInterface|null $input
     * @param OutputInterface|null $output
     * @return int
     */
    public function run(?InputInterface $input = null, ?OutputInterface $output = null): int
    {
        if ($input === null) {
            $input = new ArgvInput();
        }
        if ($output === null) {
            $output = new ConsoleOutput();
        }

        $command = $input->getFirstArgument();

        // If in a Laravel project, handle artisan commands
        if ($this->laravelPath && file_exists($this->laravelPath . '/artisan')) {
            if ($command && str_starts_with($command, 'laris:')) {
                return parent::run($input, $output);
            }
            if (!$command || in_array($command, ['help', '--help', '-h'])) {
                return $this->runArtisanCommand('list', [], new SymfonyStyle($input, $output));
            }
            $args = $input->getArguments();
            $params = array_slice($_SERVER['argv'], 2);
            return $this->runArtisanCommand($command, $params, new SymfonyStyle($input, $output));
        }

        return parent::run($input, $output);
    }

    /**
     * Detect the Laravel project path by looking for artisan file.
     *
     * @return void
     */
    protected function detectLaravelPath(): void
    {
        $cwd = getcwd(); // Current working directory
        $path = $cwd;

        // Traverse up the directory tree to find artisan file
        while ($path !== dirname($path)) {
            if (file_exists($path . '/artisan')) {
                $this->laravelPath = $path;
                return;
            }
            $path = dirname($path);
        }

        // If path not found
        $this->laravelPath = null;
    }

    /**
     * Display the welcome screen with Laris CLI information.
     *
     * @param SymfonyStyle $io
     * @return void
     */
    private function displayWelcomeScreen(SymfonyStyle $io): void
    {
        $io->writeln([
            '',
            '<fg=magenta;bg=black>                                                                          </>',
            '<fg=magenta;bg=black>  ██╗      █████╗ ██████╗ ██╗███████╗    ██████╗██╗     ██╗               </>',
            '<fg=magenta;bg=black>  ██║     ██╔══██╗██╔══██╗██║██╔════╝   ██╔════╝██║     ██║               </>',
            '<fg=magenta;bg=black>  ██║     ███████║██████╔╝██║███████╗   ██║     ██║     ██║               </>',
            '<fg=magenta;bg=black>  ██║     ██╔══██║██╔══██╗██║╚════██║   ██║     ██║     ██║               </>',
            '<fg=magenta;bg=black>  ███████╗██║  ██║██║  ██║██║███████║██╗╚██████╗███████╗███████╗          </>',
            '<fg=magenta;bg=black>  ╚══════╝╚═╝  ╚═╝╚═╝  ╚═╝╚═╝╚══════╝╚═╝ ╚═════╝╚══════╝╚══════╝          </>',
            '<fg=magenta;bg=red>LaraPire                                                                          </>',
            '',
            '<fg=cyan>Laravel Development Environment Manager - LaraPire</>',
            '<fg=yellow>Version ' . $this->version . '</>',
            '',
        ]);
    }

    /**
     * Execute an artisan command in the Laravel project.
     *
     * @param string $command
     * @param array $args
     * @param SymfonyStyle $io
     * @return int
     */
    private function runArtisanCommand(string $command, array $args, SymfonyStyle $io): int
    {
        $io->section("Executing:artisan {$command}");

        $process = new Process(['php', 'artisan', $command, ...$args], $this->laravelPath);
        $process->setTimeout(null);

        if (Process::isTtySupported() && strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            $process->setTty(true);
        }

        $process->run(function ($type, $buffer) use ($io) {
            $io->write($buffer);
        });

        if (!$process->isSuccessful()) {
            $io->error("Command failed with exit code {$process->getExitCode()}");
            return $process->getExitCode();
        }

        // If the command was 'list' or help command, show Laris commands
        if ($command === 'list' || in_array($command, ['help', '--help', '-h'])) {
            $io->newLine(2);
            $io->text([
                '',
                '<fg=magenta;options=bold>  ╔════════════════════════════════════╗  </>',
                '<fg=magenta;options=bold>  ║                                    ║  </>',
                '<fg=magenta;options=bold>  ║    🚀 Laris Custom Commands 🚀     ║  </>',
                '<fg=magenta;options=bold>  ║                                    ║  </>',
                '<fg=magenta;options=bold>  ╚════════════════════════════════════╝  </>',
                '',
            ]);
            foreach ($this->getLarisCommands() as $cmd) {
                $io->writeln("  <info>{$cmd['name']}</info> - {$cmd['description']}");
            }

            $io->newLine();
        }

        return 0;
    }

    /**
     * Get all Laris-specific commands.
     *
     * @return array
     */
    private function getLarisCommands(): array
    {
        $commands = $this->all();
        $larisCommands = [];

        foreach ($commands as $name => $command) {
            if (str_starts_with($name, 'laris:')) {
                $larisCommands[] = [
                    'name' => $name,
                    'description' => $command->getDescription(),
                ];
            }
        }

        return $larisCommands;
    }

    public function printLarisCommands()
    {
        $larisCommands = $this->getLarisCommands();

        if (empty($larisCommands)) {
            return 0;
        }

        foreach ($larisCommands as $command) {
            echo $command['name'] . " - " . $command['description'] . PHP_EOL;
        }
    }


    /**
     * Handle project selection when not in a Laravel project.
     *
     * @param SymfonyStyle $io
     * @return int
     */

    /**
     * Create a new Laravel project.
     *
     * @param SymfonyStyle $io
     * @return int
     */
    private function createNewProject(SymfonyStyle $io): int
    {
        $result = $this->doCreateNewProject($io);

        if ($result === 0 && $this->laravelPath) {
            $io->success([
                'Project created successfully!',
                "Location: {$this->laravelPath}"
            ]);
            return $this->openShellInProject($io);
        }

        return $result;
    }

    /**
     * Execute the new project creation process.
     *
     * @param SymfonyStyle $io
     * @return int
     */
    private function doCreateNewProject(SymfonyStyle $io): int
    {
        $input = new ArrayInput(['command' => 'new']);
        $result = parent::run($input, $io);

        $this->detectLaravelProject();

        if ($this->laravelPath && file_exists($this->laravelPath . '/artisan')) {
            $this->saveProject($this->laravelPath);
        }

        return $result;
    }

    /**
     * Select an existing project from the list.
     *
     * @param SymfonyStyle $io
     * @param int $index
     * @return int
     */
    private function selectExistingProject(SymfonyStyle $io, int $index): int
    {
        $this->laravelPath = $this->savedProjects[$index - 1];
        $io->success([
            'Project selected:',
            $this->laravelPath
        ]);
        return $this->openShellInProject($io);
    }

    /**
     * Open a shell in the project directory.
     *
     * @param SymfonyStyle $io
     * @return int
     */
    private function openShellInProject(SymfonyStyle $io): int
    {
        $io->note('Opening shell in project directory...');

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $shell = getenv('COMSPEC') ?: 'cmd.exe'; // COMSPEC معمولاً مسیر cmd.exe هست
        } else {
            $shell = getenv('SHELL') ?: 'bash';
        }
        $process = new Process([$shell], $this->laravelPath);

        $process->setTimeout(null);

        if (Process::isTtySupported() && strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            $process->setTty(true);
        }

        $process->run(function ($type, $buffer) use ($io) {
            $io->write($buffer);
        });

        return $process->isSuccessful() ? 0 : 1;
    }

    /**
     * Run a shell in the Laravel project directory.
     *
     * @param SymfonyStyle $io
     * @return int
     */
    private function runShell(SymfonyStyle $io): int
    {
        if (!$this->laravelPath) {
            $io->error('No Laravel project detected');
            return 1;
        }
        return $this->openShellInProject($io);
    }

    /**
     * Detect if current directory is a Laravel project.
     *
     * @return void
     */
    private function detectLaravelProject(): void
    {
        $current = getcwd();
        for ($i = 0; $i < 5; $i++) {
            if (file_exists($current . '/artisan')) {
                $this->laravelPath = $current;
                $this->addToHistory($current);
                break;
            }
            $current = dirname($current);
        }
    }

    /**
     * Load project history from file.
     *
     * @return void
     */
    private function loadHistory(): void
    {
        if (file_exists($this->historyFile)) {
            $this->projectHistory = json_decode(file_get_contents($this->historyFile), true) ?: [];
        }
    }

    /**
     * Add a project to history.
     *
     * @param string $path
     * @return void
     */
    private function addToHistory(string $path): void
    {
        $name = $this->getLaravelAppName($path);
        $this->projectHistory[$name] = $path;
        file_put_contents($this->historyFile, json_encode($this->projectHistory));
    }

    /**
     * Get the Laravel application name from .env file.
     *
     * @param string $path
     * @return string
     */
    private function getLaravelAppName(string $path): string
    {
        $envFile = $path . '/.env';
        if (!file_exists($envFile)) {
            return basename($path);
        }

        $lines = file($envFile);
        foreach ($lines as $line) {
            if (str_starts_with(trim($line), 'APP_NAME=')) {
                $value = trim(explode('=', $line, 2)[1]);
                return trim($value, "\"'");
            }
        }

        return basename($path);
    }

    /**
     * Load saved projects from file.
     *
     * @return void
     */
    private function loadProjects(): void
    {
        if (file_exists($this->projectsFile)) {
            $content = file_get_contents($this->projectsFile);
            $this->savedProjects = array_filter(array_map('trim', explode(PHP_EOL, $content)));
        }
    }

    /**
     * Save a project path to the projects file.
     *
     * @param string $path
     * @return void
     */
    private function saveProject(string $path): void
    {
        if (!in_array($path, $this->savedProjects)) {
            $this->savedProjects[] = $path;
            file_put_contents($this->projectsFile, implode(PHP_EOL, $this->savedProjects) . PHP_EOL);
        }
    }
}
