<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class TailMt5AutoCloseLog extends Command
{
    protected $signature = 'wolforix:tail-mt5-autoclose
        {--lines=100 : Number of existing lines to show before following the log}
        {--no-follow : Print the latest lines and exit instead of following}';

    protected $description = 'Tail the dedicated MT5 auto-close diagnostic log.';

    public function handle(): int
    {
        $path = $this->currentLogPath();
        $lines = max((int) $this->option('lines'), 1);

        File::ensureDirectoryExists(dirname($path));

        if (! File::exists($path)) {
            File::put($path, '');
        }

        $this->info('Tailing MT5 auto-close log: '.$path);

        $arguments = ['tail', '-n', (string) $lines];

        if (! (bool) $this->option('no-follow')) {
            $arguments[] = '-F';
        }

        $arguments[] = $path;

        $process = new Process($arguments);
        $process->setTimeout(null);
        $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });

        return $process->isSuccessful() ? self::SUCCESS : self::FAILURE;
    }

    private function currentLogPath(): string
    {
        $todayPath = storage_path('logs/mt5-autoclose-'.now()->format('Y-m-d').'.log');

        if (File::exists($todayPath)) {
            return $todayPath;
        }

        $basePath = storage_path('logs/mt5-autoclose.log');

        if (File::exists($basePath)) {
            return $basePath;
        }

        return $todayPath;
    }
}
