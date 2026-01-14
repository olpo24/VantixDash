<?php
declare(strict_types=1);

namespace VantixDash;

/**
 * ConfigService - Optimierte Verwaltung mit Static Caching
 */
class ConfigService {
    private array $settings = [];
    private array $versionData = [];
    private string $configPath;
    
    // Statischer Cache, um Disk-I/O innerhalb eines Requests zu minimieren
    private static ?array $settingsCache = null;
    private static ?array $versionCache = null;

    public function __construct() {
        $basePath = dirname(__DIR__) . '/';
        $this->configPath = $basePath . 'data/config.json';
        
        $this->loadConfig();
        $this->loadVersion($basePath);
    }

    /**
     * Lädt die Konfiguration mit statischem Caching
     */
    private function loadConfig(): void {
        if (self::$settingsCache !== null) {
            $this->settings = self::$settingsCache;
            return;
        }

        if (file_exists($this->configPath)) {
            $content = file_get_contents($this->configPath);
            if ($content !== false) {
                $decoded = json_decode($content, true);
                $this->settings = is_array($decoded) ? $decoded : [];
                self::$settingsCache = $this->settings;
            }
        }
    }

    /**
     * Lädt die Versionsdaten mit statischem Caching
     */
    private function loadVersion(string $basePath): void {
        if (self::$versionCache !== null) {
            $this->versionData = self::$versionCache;
            return;
        }

        $versionFile = $basePath . 'version.php';
        if (file_exists($versionFile)) {
            $this->versionData = include $versionFile;
            if (!is_array($this->versionData)) {
                $this->versionData = [];
            }
            self::$versionCache = $this->versionData;
        }
    }

    /**
     * Holt eine Einstellung mit Fallback
     */
    public function get(string $key, $default = null) {
        return $this->settings[$key] ?? $default;
    }

    /**
     * Setzt einen Wert im Speicher und aktualisiert den Cache
     */
    public function set(string $key, $value): void {
        $this->settings[$key] = $value;
        self::$settingsCache = $this->settings;
    }

    /**
     * Hilfsmethode für Timeouts
     */
    public function getTimeout(string $type): int {
        $defaults = [
            'api'        => 10,
            'site_check' => 15,
            'session'    => 3600,
            'rate_limit' => 300
        ];
        return (int)$this->get("timeout_$type", $defaults[$type] ?? 10);
    }

    public function getVersion(): string {
        return (string)($this->versionData['version'] ?? '0.0.0');
    }

    public function getAll(): array {
        return $this->settings;
    }

    /**
     * Speichert die Einstellungen und invalidiert/aktualisiert den Cache
     */
    public function save(): bool {
        $jsonContent = json_encode($this->settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($jsonContent === false) return false;

        $tempFile = $this->configPath . '.tmp.' . bin2hex(random_bytes(8));
        if (file_put_contents($tempFile, $jsonContent, LOCK_EX) !== false) {
            if (rename($tempFile, $this->configPath)) {
                self::$settingsCache = $this->settings; // Cache synchron halten
                return true;
            }
        }
        return false;
    }

    public function getUserData(): array {
        return [
            'username'    => (string)$this->get('username', ''),
            'email'       => (string)$this->get('email', ''),
            '2fa_enabled' => (bool)$this->get('2fa_enabled', false)
        ];
    }

    public function updateUser(string $username, string $email): bool {
        $this->settings['username'] = htmlspecialchars(strip_tags(trim($username)), ENT_QUOTES, 'UTF-8');
        $sanitizedEmail = filter_var($email, FILTER_VALIDATE_EMAIL);
        $this->settings['email'] = $sanitizedEmail !== false ? $sanitizedEmail : $this->settings['email'];
        return $this->save();
    }

    public function updatePassword(string $hash): bool {
        $this->settings['password_hash'] = $hash;
        return $this->save();
    }

    public function update2FA(bool $enabled, ?string $secret = null): bool {
        $this->settings['2fa_enabled'] = $enabled;
        if ($secret !== null) $this->settings['2fa_secret'] = $secret;
        return $this->save();
    }

    public function updateCronSecret(string $token): bool {
        $this->set('cron_secret', $token);
        return $this->save();
    }
    
    public function setResetToken(string $token): bool {
        $this->set('reset_token', hash('sha256', $token));
        $this->set('reset_expires', time() + 1800);
        return $this->save();
    }

    public function verifyResetToken(string $token): bool {
        $storedHash = $this->get('reset_token');
        $expires = (int)$this->get('reset_expires', 0);
        if (!$storedHash || time() > $expires) return false;
        return hash_equals((string)$storedHash, hash('sha256', $token));
    }

    public function clearResetToken(): void {
        unset($this->settings['reset_token']);
        unset($this->settings['reset_expires']);
        $this->save();
    }

    public function getSmtpConfig(): array {
        return $this->settings['smtp'] ?? [
            'host' => '', 'user' => '', 'pass' => '', 'port' => 587,
            'from_email' => '', 'from_name' => 'VantixDash'
        ];
    }

    public function updateSmtpConfig(array $newData): bool {
        $this->settings['smtp'] = [
            'host'       => (string)($newData['host'] ?? ''),
            'user'       => (string)($newData['user'] ?? ''),
            'pass'       => (string)($newData['pass'] ?? ''),
            'port'       => (int)($newData['port'] ?? 587),
            'from_email' => (string)($newData['from_email'] ?? ''),
            'from_name'  => (string)($newData['from_name'] ?? 'VantixDash')
        ];
        return $this->save();
    }
}
