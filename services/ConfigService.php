<?php
declare(strict_types=1);

class ConfigService {
    private array $settings = [];
    private array $versionData = [];
    private string $configPath;

    public function __construct() {
        $basePath = dirname(__DIR__) . '/';
        $this->configPath = $basePath . 'data/config.json';
        
        // 1. Config laden (Sichere Prüfung)
        if (file_exists($this->configPath)) {
            $content = file_get_contents($this->configPath);
            if ($content !== false) {
                $decoded = json_decode($content, true);
                $this->settings = is_array($decoded) ? $decoded : [];
            }
        }

        // 2. Version laden
        $versionFile = $basePath . 'version.php';
        if (file_exists($versionFile)) {
            $this->versionData = include $versionFile;
            if (!is_array($this->versionData)) {
                $this->versionData = [];
            }
        }
    }

    /**
     * Holt eine Einstellung mit einem Fallback-Standardwert
     */
    public function get(string $key, $default = null) {
        return $this->settings[$key] ?? $default;
    }

    /**
     * Setzt einen Wert im Speicher (ohne sofort zu speichern)
     */
    public function set(string $key, $value): void {
        $this->settings[$key] = $value;
    }

    /**
     * Hilfsmethode für Timeouts (zentrale Verwaltung)
     */
    public function getTimeout(string $type): int {
        $defaults = [
            'api'           => 10,
            'site_check'    => 15,
            'session'       => 3600,
            'rate_limit'    => 300
        ];
        
        // Sucht in config.json nach 'timeout_api' etc., sonst Standardwert
        return (int)$this->get("timeout_$type", $defaults[$type] ?? 10);
    }

    /**
     * Gibt die App-Version zurück
     */
    public function getVersion(): string {
        return (string)($this->versionData['version'] ?? '0.0.0');
    }

    /**
     * Gibt alle Einstellungen zurück
     */
    public function getAll(): array {
        return $this->settings;
    }

    /**
     * Speichert die aktuellen Einstellungen permanent (Atomares Schreiben)
     */
    public function save(): bool {
        $jsonContent = json_encode($this->settings, JSON_PRETTY_PRINT);
        if ($jsonContent === false) {
            return false;
        }

        // Atomares Speichern: In Temp-Datei schreiben und dann umbenennen
        $tempFile = $this->configPath . '.tmp.' . bin2hex(random_bytes(8));
        if (file_put_contents($tempFile, $jsonContent, LOCK_EX) !== false) {
            return rename($tempFile, $this->configPath);
        }
        
        return false;
    }

    /**
     * Gibt die für das Profil relevanten Daten zurück
     */
    public function getUserData(): array {
        return [
            'username'    => (string)$this->get('username', ''),
            'email'       => (string)$this->get('email', ''),
            '2fa_enabled' => (bool)$this->get('2fa_enabled', false)
        ];
    }

    /**
     * Aktualisiert Username und Email mit Sanitizing
     */
    public function updateUser(string $username, string $email): bool {
        // Trimmen und Tags entfernen für den Usernamen
        $this->settings['username'] = htmlspecialchars(strip_tags(trim($username)), ENT_QUOTES, 'UTF-8');
        
        // E-Mail Validierung
        $sanitizedEmail = filter_var($email, FILTER_SANITIZE_EMAIL);
        $this->settings['email'] = $sanitizedEmail !== false ? $sanitizedEmail : '';
        
        return $this->save();
    }

    /**
     * Aktualisiert den Passwort-Hash
     */
    public function updatePassword(string $hash): bool {
        $this->settings['password_hash'] = $hash;
        return $this->save();
    }

    /**
     * 2FA Status und Secret setzen
     */
    public function update2FA(bool $enabled, ?string $secret = null): bool {
        $this->settings['2fa_enabled'] = $enabled;
        if ($secret !== null) {
            $this->settings['2fa_secret'] = $secret;
        }
        return $this->save();
    }

    /**
     * Aktualisiert das Cron-Secret
     */
    public function updateCronSecret(string $token): bool {
        $this->set('cron_secret', $token);
        return $this->save();
    }
	
/**
 * Erzeugt einen Reset-Token-Hash und setzt das Ablaufdatum
 */
public function setResetToken(string $token): bool {
    $this->set('reset_token', hash('sha256', $token));
    $this->set('reset_expires', time() + 1800); // 30 Minuten Gültigkeit
    return $this->save();
}

/**
 * Verifiziert den Token gegen den gespeicherten Hash
 */
public function verifyResetToken(string $token): bool {
    $storedHash = $this->get('reset_token');
    $expires = (int)$this->get('reset_expires', 0);
    
    if (!$storedHash || time() > $expires) {
        return false;
    }
    
    return hash_equals($storedHash, hash('sha256', $token));
}

/**
 * Löscht Reset-Daten nach Verwendung oder Fehler
 */
public function clearResetToken(): void {
    $this->set('reset_token', null);
    $this->set('reset_expires', null);
    $this->save();
}
public function getSmtpConfig(): array {
    $config = $this->loadConfig(); // Deine bestehende Methode zum Laden der JSON
    return $config['smtp'] ?? [
        'host' => '',
        'user' => '',
        'pass' => '',
        'port' => 587,
        'from_email' => '',
        'from_name' => 'VantixDash'
    ];
}

public function updateSmtpConfig(array $newData): bool {
    $config = $this->loadConfig();
    
    // Bestehende Daten behalten, nur 'smtp' überschreiben/erstellen
    $config['smtp'] = [
        'host'       => (string)($newData['host'] ?? ''),
        'user'       => (string)($newData['user'] ?? ''),
        'pass'       => (string)($newData['pass'] ?? ''),
        'port'       => (int)($newData['port'] ?? 587),
        'from_email' => (string)($newData['from_email'] ?? ''),
        'from_name'  => (string)($newData['from_name'] ?? 'VantixDash')
    ];

    return $this->saveConfig($config); // Deine bestehende Methode zum Speichern der JSON
}
}
