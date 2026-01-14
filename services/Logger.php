<?php
declare(strict_types=1);

namespace VantixDash;

/**
 * Logger - Verwaltet System-Logs mit automatischer Rotation
 */
class Logger {
    private string $logFile;
    private int $maxLogSize = 5242880; // 5 MB Standard-Limit

    public function __construct(string $logFile = null) {
        $this->logFile = $logFile ?? dirname(__DIR__) . '/data/app.log';
        
        // Ordner erstellen, falls nicht vorhanden
        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }

    public function error(string $message, array $context = []): void {
        $this->log('ERROR', $message, $context);
    }

    public function info(string $message, array $context = []): void {
        $this->log('INFO', $message, $context);
    }

    /**
     * Schreibt einen Eintrag und prüft auf Rotation
     */
    private function log(string $level, string $message, array $context): void {
        $this->rotateIfNeeded();

        $timestamp = date('Y-m-d H:i:s');
        $contextJson = !empty($context) ? json_encode($context, JSON_UNESCAPED_SLASHES) : '';
        $line = sprintf("[%s] [%s] %s %s%s", $timestamp, $level, $message, $contextJson, PHP_EOL);

        // LOCK_EX verhindert korrupte Zeilen bei gleichzeitigem Zugriff
        @file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Verhindert, dass die Log-Datei unendlich groß wird
     */
    private function rotateIfNeeded(): void {
        if (file_exists($this->logFile) && filesize($this->logFile) > $this->maxLogSize) {
            $backupFile = $this->logFile . '.old';
            @rename($this->logFile, $backupFile);
        }
    }

    /**
     * Liest die letzten Log-Einträge aus (für das Dashboard)
     */
    public function getEntries(int $limit = 100): array {
        if (!file_exists($this->logFile)) {
            return [];
        }

        $lines = file($this->logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) return [];

        $results = [];
        // Die neuesten Einträge zuerst (umkehren)
        foreach (array_reverse($lines) as $line) {
            if (count($results) >= $limit) break;
            
            // Parsen der Zeile: [Timestamp] [Level] Nachricht {Context}
            if (preg_match('/^\[(.*?)\] \[(.*?)\] (.*)$/', $line, $matches)) {
                $results[] = [
                    'timestamp' => $matches[1],
                    'level'     => $matches[2],
                    'message'   => $matches[3]
                ];
            } else {
                $results[] = ['raw' => $line];
            }
        }

        return $results;
    }

    /**
     * Leert die Log-Datei (für api.php action=clear_logs)
     */
    public function clear(): bool {
        if (file_exists($this->logFile)) {
            return @file_put_contents($this->logFile, '', LOCK_EX) !== false;
        }
        return true;
    }
}
