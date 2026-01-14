<?php
declare(strict_types=1);
namespace VantixDash;
class Logger {
    private string $logFile;

    public function __construct(string $logFile = null) {
        $this->logFile = $logFile ?? __DIR__ . '/../data/app.log';
        
        // Ordner erstellen, falls nicht vorhanden
        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

    public function error(string $message, array $context = []): void {
        $this->log('ERROR', $message, $context);
    }

    public function info(string $message, array $context = []): void {
        $this->log('INFO', $message, $context);
    }

    private function log(string $level, string $message, array $context): void {
        $timestamp = date('Y-m-d H:i:s');
        $contextJson = !empty($context) ? json_encode($context, JSON_UNESCAPED_SLASHES) : '';
        $line = "[$timestamp] [$level] $message $contextJson" . PHP_EOL;

        // Fehler silent unterdrücken, falls Schreibzugriff fehlt
        @file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
    }
}
