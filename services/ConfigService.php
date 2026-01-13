<?php
declare(strict_types=1);

class ConfigService {
    private array $settings = [];
    private array $versionData = [];
    private string $configPath;

    public function __construct() {
        $basePath = dirname(__DIR__) . '/';
        $this->configPath = $basePath . 'data/config.json';
        
        // 1. Config laden (Ohne @, mit expliziter Prüfung)
        if (file_exists($this->configPath)) {
            $content = file_get_contents($this->configPath);
            if ($content !== false) {
                $decoded = json_decode($content, true);
                $this->settings = is_array($decoded) ? $decoded : [];
            }
        }

        // 2. Version laden (include ist für Arrays in PHP völlig legitim)
        $versionFile = $basePath . 'version.php';
        if (file_exists($versionFile)) {
            $this->versionData = include $versionFile;
            if (!is_array($this->versionData)) {
                $this->versionData = [];
            }
        }
    }

    /**
     * Gibt einen Wert aus den Einstellungen zurück
     */
    public function get(string $key, $default = null) {
        return $this->settings[$key] ?? $default;
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
     * Speichert die aktuellen Einstellungen zurück in die config.json
     */
    public function save(): bool {
        $jsonContent = json_encode($this->settings, JSON_PRETTY_PRINT);
        if ($jsonContent === false) {
            return false;
        }
        $result = file_put_contents($this->configPath, $jsonContent);
        return $result !== false;
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
        
        // E-Mail Validierung / Sanitizing
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
 * Setzt einen Wert im Speicher (ohne sofort zu speichern)
 */
public function set(string $key, $value): void {
    $this->settings[$key] = $value;
}

/**
 * Aktualisiert das Cron-Secret und schreibt es sofort in die Datei
 */
public function updateCronSecret(string $token): bool {
    $this->set('cron_secret', $token); // Nutzt intern die set-Methode
    return $this->save();              // Schreibt es permanent fest
}
}
