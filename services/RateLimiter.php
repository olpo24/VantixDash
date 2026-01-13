<?php
declare(strict_types=1);

class RateLimiter {
    private string $attemptsFile;

    public function __construct() {
        $this->attemptsFile = dirname(__DIR__) . '/data/rate_limits.json';
    }

    /**
     * Prüft, ob das Limit für eine IP/Kennung überschritten wurde
     */
    public function checkLimit(string $identifier, int $maxAttempts = 5, int $timeWindow = 300): bool {
        $attempts = $this->getAttempts();
        $key = hash('sha256', $identifier);
        $now = time();

        if (!isset($attempts[$key]) || $now > $attempts[$key]['reset_at']) {
            $attempts[$key] = [
                'count' => 0, 
                'reset_at' => $now + $timeWindow
            ];
        }

        if ($attempts[$key]['count'] >= $maxAttempts) {
            return false;
        }

        $attempts[$key]['count']++;
        $this->saveAttempts($attempts);
        return true;
    }

    private function getAttempts(): array {
        if (!file_exists($this->attemptsFile)) {
            return [];
        }
        $content = file_get_contents($this->attemptsFile);
        if ($content === false) return [];
        
        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    private function saveAttempts(array $data): void {
        // Bereinigung alter Einträge, um die Datei klein zu halten
        $now = time();
        $data = array_filter($data, fn($item) => $item['reset_at'] > $now);
        
        $jsonContent = json_encode($data);
        $tempFile = $this->attemptsFile . '.tmp.' . bin2hex(random_bytes(8));
        
        if (file_put_contents($tempFile, $jsonContent, LOCK_EX) !== false) {
            rename($tempFile, $this->attemptsFile);
        }
    }
}
