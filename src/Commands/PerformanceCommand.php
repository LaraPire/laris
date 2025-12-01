<?php

namespace Laris\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

class PerformanceCommand extends Command
{
    protected static $defaultName = 'laris:performance';
    protected static $defaultDescription = 'Monitor and analyze Laravel application performance';

    protected function configure(): void
    {
        $this
            ->setName('laris:performance')
            ->setDescription('Monitor and analyze Laravel application performance')
            ->setHelp('This command provides comprehensive performance monitoring for your Laravel application including database queries, memory usage, response times, and optimization recommendations.')
            ->addOption('detailed', 'd', InputOption::VALUE_NONE, 'Show detailed performance metrics')
            ->addOption('database', null, InputOption::VALUE_NONE, 'Focus on database performance analysis')
            ->addOption('memory', 'm', InputOption::VALUE_NONE, 'Show memory usage analysis')
            ->addOption('routes', 'r', InputOption::VALUE_NONE, 'Analyze route performance')
            ->addOption('export', 'e', InputOption::VALUE_OPTIONAL, 'Export results to file (json|csv)', false);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Check if we're in a Laravel project
        if (!file_exists(getcwd() . '/artisan')) {
            $io->error('This command must be run from a Laravel project directory.');
            return Command::FAILURE;
        }

        $io->title('🚀 Laris Performance Monitor');
        $io->text('Analyzing your Laravel application performance...');
        $io->newLine();

        $performanceData = [];

        // System Information
        $systemInfo = $this->getSystemInfo();
        $performanceData['system'] = $systemInfo;
        $this->displaySystemInfo($io, $systemInfo);

        // Laravel Application Analysis
        $appInfo = $this->getLaravelAppInfo();
        $performanceData['application'] = $appInfo;
        $this->displayAppInfo($io, $appInfo);

        // Database Performance (if requested or in detailed mode)
        if ($input->getOption('database') || $input->getOption('detailed')) {
            $dbInfo = $this->getDatabaseInfo();
            $performanceData['database'] = $dbInfo;
            $this->displayDatabaseInfo($io, $dbInfo);
        }

        // Memory Analysis (if requested or in detailed mode)
        if ($input->getOption('memory') || $input->getOption('detailed')) {
            $memoryInfo = $this->getMemoryInfo();
            $performanceData['memory'] = $memoryInfo;
            $this->displayMemoryInfo($io, $memoryInfo);
        }

        // Route Analysis (if requested or in detailed mode)
        if ($input->getOption('routes') || $input->getOption('detailed')) {
            $routeInfo = $this->getRouteInfo();
            $performanceData['routes'] = $routeInfo;
            $this->displayRouteInfo($io, $routeInfo);
        }

        // Performance Recommendations
        $recommendations = $this->generateRecommendations($performanceData);
        $this->displayRecommendations($io, $recommendations);

        // Export results if requested
        if ($input->getOption('export') !== false) {
            $this->exportResults($io, $performanceData, $input->getOption('export'));
        }

        $io->success('Performance analysis completed!');
        return Command::SUCCESS;
    }

    private function getSystemInfo(): array
    {
        $info = [
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'opcache_enabled' => extension_loaded('opcache') && opcache_get_status()['opcache_enabled'] ?? false,
            'extensions' => $this->getImportantExtensions(),
        ];

        // Get system load if available (Unix systems)
        if (function_exists('sys_getloadavg')) {
            $info['system_load'] = sys_getloadavg();
        }

        return $info;
    }

    private function getImportantExtensions(): array
    {
        $important = ['pdo', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'json', 'bcmath', 'fileinfo', 'redis', 'memcached'];
        $loaded = [];
        
        foreach ($important as $ext) {
            $loaded[$ext] = extension_loaded($ext);
        }
        
        return $loaded;
    }

    private function getLaravelAppInfo(): array
    {
        $info = [];
        
        // Get Laravel version
        $process = new Process(['php', 'artisan', '--version']);
        $process->run();
        $info['laravel_version'] = trim($process->getOutput());

        // Check environment
        if (file_exists('.env')) {
            $envContent = file_get_contents('.env');
            preg_match('/APP_ENV=(.*)/', $envContent, $matches);
            $info['environment'] = $matches[1] ?? 'unknown';
            
            preg_match('/APP_DEBUG=(.*)/', $envContent, $matches);
            $info['debug_mode'] = ($matches[1] ?? 'false') === 'true';
        }

        // Check config cache
        $info['config_cached'] = file_exists('bootstrap/cache/config.php');
        $info['routes_cached'] = file_exists('bootstrap/cache/routes-v7.php');
        $info['views_cached'] = is_dir('storage/framework/views') && count(glob('storage/framework/views/*.php')) > 0;

        // Count files
        $info['controllers_count'] = $this->countFiles('app/Http/Controllers', '*.php');
        $info['models_count'] = $this->countFiles('app/Models', '*.php');
        $info['migrations_count'] = $this->countFiles('database/migrations', '*.php');

        return $info;
    }

    private function getDatabaseInfo(): array
    {
        $info = [];
        
        try {
            // Get database configuration
            $process = new Process(['php', 'artisan', 'tinker', '--execute=echo config("database.default")']);
            $process->run();
            $info['default_connection'] = trim($process->getOutput());

            // Check for slow query log (MySQL)
            $info['slow_query_log_enabled'] = 'Check manually in database settings';
            
            // Migration status
            $process = new Process(['php', 'artisan', 'migrate:status']);
            $process->run();
            $migrationOutput = $process->getOutput();
            $info['pending_migrations'] = strpos($migrationOutput, 'No') === false;

        } catch (\Exception $e) {
            $info['error'] = 'Could not analyze database: ' . $e->getMessage();
        }

        return $info;
    }

    private function getMemoryInfo(): array
    {
        return [
            'current_usage' => $this->formatBytes(memory_get_usage()),
            'peak_usage' => $this->formatBytes(memory_get_peak_usage()),
            'current_real_usage' => $this->formatBytes(memory_get_usage(true)),
            'peak_real_usage' => $this->formatBytes(memory_get_peak_usage(true)),
            'memory_limit' => ini_get('memory_limit'),
        ];
    }

    private function getRouteInfo(): array
    {
        $info = [];
        
        try {
            $process = new Process(['php', 'artisan', 'route:list', '--json']);
            $process->run();
            $routes = json_decode($process->getOutput(), true);
            
            if ($routes) {
                $info['total_routes'] = count($routes);
                $info['methods'] = array_count_values(array_column($routes, 'method'));
                $info['middleware_usage'] = $this->analyzeMiddleware($routes);
            }
        } catch (\Exception $e) {
            $info['error'] = 'Could not analyze routes: ' . $e->getMessage();
        }

        return $info;
    }

    private function analyzeMiddleware(array $routes): array
    {
        $middleware = [];
        foreach ($routes as $route) {
            if (isset($route['middleware'])) {
                foreach (explode(',', $route['middleware']) as $mw) {
                    $mw = trim($mw);
                    if ($mw) {
                        $middleware[$mw] = ($middleware[$mw] ?? 0) + 1;
                    }
                }
            }
        }
        arsort($middleware);
        return array_slice($middleware, 0, 10); // Top 10 most used middleware
    }

    private function generateRecommendations(array $data): array
    {
        $recommendations = [];

        // System recommendations
        if (!$data['system']['opcache_enabled']) {
            $recommendations[] = [
                'type' => 'critical',
                'category' => 'System',
                'message' => 'Enable OPcache for better PHP performance',
                'action' => 'Configure opcache.enable=1 in php.ini'
            ];
        }

        // Laravel recommendations
        if (isset($data['application']['debug_mode']) && $data['application']['debug_mode'] && $data['application']['environment'] === 'production') {
            $recommendations[] = [
                'type' => 'critical',
                'category' => 'Security',
                'message' => 'Debug mode is enabled in production',
                'action' => 'Set APP_DEBUG=false in .env file'
            ];
        }

        if (!$data['application']['config_cached']) {
            $recommendations[] = [
                'type' => 'warning',
                'category' => 'Performance',
                'message' => 'Configuration is not cached',
                'action' => 'Run: php artisan config:cache'
            ];
        }

        if (!$data['application']['routes_cached']) {
            $recommendations[] = [
                'type' => 'warning',
                'category' => 'Performance',
                'message' => 'Routes are not cached',
                'action' => 'Run: php artisan route:cache'
            ];
        }

        // Extension recommendations
        foreach (['redis', 'memcached'] as $ext) {
            if (!$data['system']['extensions'][$ext]) {
                $recommendations[] = [
                    'type' => 'info',
                    'category' => 'Performance',
                    'message' => "Consider installing {$ext} extension for better caching",
                    'action' => "Install {$ext} PHP extension"
                ];
            }
        }

        return $recommendations;
    }

    private function displaySystemInfo(SymfonyStyle $io, array $info): void
    {
        $io->section('🖥️  System Information');
        
        $rows = [
            ['PHP Version', $info['php_version']],
            ['Memory Limit', $info['memory_limit']],
            ['Max Execution Time', $info['max_execution_time'] . 's'],
            ['OPcache Enabled', $info['opcache_enabled'] ? '✅ Yes' : '❌ No'],
        ];

        if (isset($info['system_load'])) {
            $load = $info['system_load'];
            $rows[] = ['System Load', sprintf('%.2f, %.2f, %.2f', $load[0], $load[1], $load[2])];
        }

        $io->table(['Metric', 'Value'], $rows);

        // Extensions table
        $io->text('📦 Important PHP Extensions:');
        $extRows = [];
        foreach ($info['extensions'] as $ext => $loaded) {
            $extRows[] = [$ext, $loaded ? '✅ Loaded' : '❌ Missing'];
        }
        $io->table(['Extension', 'Status'], $extRows);
    }

    private function displayAppInfo(SymfonyStyle $io, array $info): void
    {
        $io->section('🚀 Laravel Application');
        
        $rows = [
            ['Laravel Version', $info['laravel_version'] ?? 'Unknown'],
            ['Environment', $info['environment'] ?? 'Unknown'],
            ['Debug Mode', isset($info['debug_mode']) ? ($info['debug_mode'] ? '⚠️  Enabled' : '✅ Disabled') : 'Unknown'],
            ['Config Cached', $info['config_cached'] ? '✅ Yes' : '❌ No'],
            ['Routes Cached', $info['routes_cached'] ? '✅ Yes' : '❌ No'],
            ['Views Cached', $info['views_cached'] ? '✅ Yes' : '❌ No'],
            ['Controllers', $info['controllers_count']],
            ['Models', $info['models_count']],
            ['Migrations', $info['migrations_count']],
        ];

        $io->table(['Metric', 'Value'], $rows);
    }

    private function displayDatabaseInfo(SymfonyStyle $io, array $info): void
    {
        $io->section('🗄️  Database Information');
        
        if (isset($info['error'])) {
            $io->warning($info['error']);
            return;
        }

        $rows = [
            ['Default Connection', $info['default_connection'] ?? 'Unknown'],
            ['Pending Migrations', isset($info['pending_migrations']) ? ($info['pending_migrations'] ? '⚠️  Yes' : '✅ No') : 'Unknown'],
        ];

        $io->table(['Metric', 'Value'], $rows);
    }

    private function displayMemoryInfo(SymfonyStyle $io, array $info): void
    {
        $io->section('💾 Memory Usage');
        
        $rows = [
            ['Current Usage', $info['current_usage']],
            ['Peak Usage', $info['peak_usage']],
            ['Current Real Usage', $info['current_real_usage']],
            ['Peak Real Usage', $info['peak_real_usage']],
            ['Memory Limit', $info['memory_limit']],
        ];

        $io->table(['Metric', 'Value'], $rows);
    }

    private function displayRouteInfo(SymfonyStyle $io, array $info): void
    {
        $io->section('🛣️  Route Analysis');
        
        if (isset($info['error'])) {
            $io->warning($info['error']);
            return;
        }

        $io->text("Total Routes: {$info['total_routes']}");
        
        if (isset($info['methods'])) {
            $io->text('HTTP Methods:');
            foreach ($info['methods'] as $method => $count) {
                $io->text("  {$method}: {$count}");
            }
        }

        if (isset($info['middleware_usage']) && !empty($info['middleware_usage'])) {
            $io->text('Top Middleware Usage:');
            foreach ($info['middleware_usage'] as $middleware => $count) {
                $io->text("  {$middleware}: {$count} routes");
            }
        }
    }

    private function displayRecommendations(SymfonyStyle $io, array $recommendations): void
    {
        if (empty($recommendations)) {
            $io->success('🎉 No performance issues detected! Your application looks great.');
            return;
        }

        $io->section('💡 Performance Recommendations');

        foreach ($recommendations as $rec) {
            $icon = match($rec['type']) {
                'critical' => '🚨',
                'warning' => '⚠️',
                'info' => 'ℹ️',
                default => '💡'
            };

            $io->text("{$icon} [{$rec['category']}] {$rec['message']}");
            $io->text("   Action: {$rec['action']}");
            $io->newLine();
        }
    }

    private function exportResults(SymfonyStyle $io, array $data, ?string $format): void
    {
        $format = $format ?: 'json';
        $filename = 'laris-performance-' . date('Y-m-d-H-i-s') . '.' . $format;

        try {
            if ($format === 'json') {
                file_put_contents($filename, json_encode($data, JSON_PRETTY_PRINT));
            } elseif ($format === 'csv') {
                $this->exportToCsv($data, $filename);
            } else {
                $io->error("Unsupported export format: {$format}");
                return;
            }

            $io->success("Results exported to: {$filename}");
        } catch (\Exception $e) {
            $io->error("Failed to export results: " . $e->getMessage());
        }
    }

    private function exportToCsv(array $data, string $filename): void
    {
        $handle = fopen($filename, 'w');
        fputcsv($handle, ['Category', 'Metric', 'Value']);

        foreach ($data as $category => $metrics) {
            if (is_array($metrics)) {
                foreach ($metrics as $key => $value) {
                    fputcsv($handle, [$category, $key, is_array($value) ? json_encode($value) : $value]);
                }
            }
        }

        fclose($handle);
    }

    private function countFiles(string $directory, string $pattern): int
    {
        if (!is_dir($directory)) {
            return 0;
        }
        return count(glob($directory . '/' . $pattern));
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}