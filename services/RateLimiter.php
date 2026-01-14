<?php
declare(strict_types=1);

namespace VantixDash;

/**
 * VantixDash - RateLimiter
 * Schützt die API vor Brute-Force und Denial-of-Service Angriffen.
 */
class RateLimiter {
    private string $attemptsFile;

    public function __construct() {
        $this->attemptsFile = dirname(__DIR__) . '/data/rate_limits.json';
    }

    /**
     * Prüft, ob das Limit für eine IP/Kennung überschritten wurde.
     * Konsolidiert die Ablauf-Logik und erhöht den Zähler nur bei Erfolg.
     */
    public function checkLimit(string $identifier, int $maxAttempts = 5, int $timeWindow = 300): bool {
        $attempts = $this->getAttempts();
        $key = hash('sha256', $identifier);
        $now = time();

        // Falls kein Eintrag existiert oder das Fenster abgelaufen ist: Neu initialisieren
        // Hier ist die konsolidierte Ablauf-Logik
        if (!isset($attempts[$key]) || $this->isExpired($attempts[$key]['reset_at'])) {
            $attempts[$key] = [
                'count' => 0, 
                'reset_at' => $now + $timeWindow
            ];
        }

        // Limit-Check
        if ($attempts[$key]['count'] >= $maxAttempts) {
            return false;
        }

        // Zähler erhöhen und speichern
        $attempts[$key]['count']++;
        $this->saveAttempts($attempts);
        
        return true;
    }

    /**
     * Zentrale Hilfsmethode zur Prüfung des Zeitfensters
     * Verhindert Code-Duplizierung in checkLimit und saveAttempts
     */
    private function isExpired(int $resetAt): bool {
        return time() > $resetAt;
    }

    /**
     * Lädt die aktuellen Versuche aus der JSON-Datei
     */
    private function getAttempts(): array {
        if (!file_exists($this->attemptsFile)) {
            return [];
        }
        
        $content = file_get_contents($this->attemptsFile);
        if ($content === false) {
            return [];
        }
        
        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Speichert die Versuche und bereinigt gleichzeitig die Datei
     */
    private function saveAttempts(array $data): void {
        // Bereinigung: Nur Einträge behalten, die noch NICHT abgelaufen sind
        // Nutzt ebenfalls die zentrale isExpired Logik
        $data = array_filter($data, fn($item) => !$this->isExpired($item['reset_at']));
        
        $jsonContent = json_encode($data);
        
        // Atomares Speichern, um Datei-Korruption bei gleichzeitigem Zugriff zu verhindern
        $tempFile = $this->attemptsFile . '.tmp.' . bin2hex(random_bytes(8));
        
        if (file_put_contents($tempFile, $jsonContent, LOCK_EX) !== false) {
            rename($tempFile, $this->attemptsFile);
        }
    }
}
