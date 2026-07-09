<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Starts and monitors the local Python recognition node (recognize.py).
 * Only used on the school PC where the camera is attached.
 */
class RecognitionProcessService
{
    public function isEnabled(): bool
    {
        if (! config('recognition.manage_enabled')) {
            return false;
        }

        return is_dir($this->serviceDir()) && is_file($this->pythonExecutable());
    }

    public function status(): string
    {
        if (! $this->isEnabled()) {
            return 'unavailable';
        }

        $pid = $this->lockPid();
        if ($pid !== null && $this->isProcessRunning($pid)) {
            return 'running';
        }

        return 'stopped';
    }

    public function ensureRunning(): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        if ($this->status() === 'running') {
            return true;
        }

        return $this->start();
    }

    public function start(): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $this->stopStale();

        if (PHP_OS_FAMILY === 'Windows') {
            $python = $this->pythonExecutable();
            $cwd = $this->serviceDir();
            $log = $cwd.DIRECTORY_SEPARATOR.'recognition-runner.log';
            // Redirect stdout/stderr — hidden starts have no console and print() crashes on Windows.
            $command = sprintf(
                'start /B "" cmd /c "cd /d "%s" && "%s" -u recognize.py >> "%s" 2>&1"',
                $cwd,
                $python,
                $log
            );
            pclose(popen($command, 'r'));
        } else {
            $process = new Process(
                [$this->pythonExecutable(), '-u', 'recognize.py'],
                $this->serviceDir(),
                null,
                null,
                null
            );
            $process->setOptions(['create_new_console' => false]);
            $process->start();
        }

        for ($attempt = 0; $attempt < 12; $attempt++) {
            usleep(500_000);
            if ($this->status() === 'running') {
                return true;
            }
        }

        Log::warning('Recognition process did not report running after start attempt.');

        return $this->status() === 'running';
    }

    public function stop(): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $this->stopStale();

        return $this->status() === 'stopped';
    }

    /**
     * @return array{enabled: bool, status: string}
     */
    public function snapshot(): array
    {
        return [
            'enabled' => $this->isEnabled(),
            'status' => $this->status(),
        ];
    }

    private function stopStale(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $ps = "Get-CimInstance Win32_Process -Filter \"Name='python.exe'\" | "
                ."Where-Object { \$_.CommandLine -match 'recognize\\.py|stream_server\\.py' } | "
                .'ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }';
            Process::fromShellCommandline('powershell -ExecutionPolicy Bypass -Command "'.$ps.'"')->run();
        } else {
            Process::fromShellCommandline('pkill -f recognize.py 2>/dev/null || true')->run();
        }

        $lock = $this->lockPath();
        if (is_file($lock)) {
            @unlink($lock);
        }
    }

    private function lockPid(): ?int
    {
        $lock = $this->lockPath();
        if (! is_file($lock)) {
            return null;
        }

        $pid = (int) trim((string) file_get_contents($lock));

        return $pid > 0 ? $pid : null;
    }

    private function lockPath(): string
    {
        return $this->serviceDir().DIRECTORY_SEPARATOR.'.recognize.lock';
    }

    private function serviceDir(): string
    {
        return rtrim((string) config('recognition.service_dir'), '/\\');
    }

    private function pythonExecutable(): string
    {
        $configured = config('recognition.python');
        if (is_string($configured) && $configured !== '' && is_file($configured)) {
            return $configured;
        }

        $venv = $this->serviceDir().DIRECTORY_SEPARATOR.'.venv'.DIRECTORY_SEPARATOR;

        if (PHP_OS_FAMILY === 'Windows') {
            return $venv.'Scripts'.DIRECTORY_SEPARATOR.'python.exe';
        }

        return $venv.'bin'.DIRECTORY_SEPARATOR.'python';
    }

    private function isProcessRunning(int $pid): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $process = Process::fromShellCommandline("tasklist /FI \"PID eq {$pid}\" /NH");
            $process->run();

            return str_contains($process->getOutput(), (string) $pid);
        }

        return is_dir("/proc/{$pid}");
    }
}
