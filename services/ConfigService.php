<?php
declare(strict_types=1);

class ConfigService {
    private string $configFile;
    private array $config = [];

    public function __construct(string $configFile = __DIR__ . '/../data/config.json') {
        $this->configFile = $configFile;
        $this->load();
    }

    /**
     * Lädt die Konfiguration aus der JSON-Datei
     */
    private function load(): void {
        if (file_exists($this->configFile)) {
            $content = file_get_contents($this->configFile);
            if ($content !== false) {
                $data = json_decode($content, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $this->config = is_array($data) ? $data : [];
                }
            }
        }
    }

    /**
     * Gibt einen Wert aus der Konfiguration zurück
     */
    public function get(string $key, $default = null) {
        return $this->config[$key] ?? $default;
    }

    /**
     * Gibt alle Benutzerdaten zurück
     */
    public function getUserData(): array {
        return [
            'username'     => (string)$this->get('username', 'admin'),
            'email'        => (string)$this->get('email', ''),
            '2fa_enabled'  => (bool)$this->get('2fa_enabled', false),
            'last_login'   => (string)$this->get('last_login', 'Nie')
        ];
    }

    /**
     * Aktualisiert Stammdaten des Benutzers
     */
    public function updateUser(string $username, string $email): bool {
        $this->config['username'] = $username;
        $this->config['email'] = $email;
        return $this->save();
    }

    /**
     * Aktualisiert das Passwort-Hash
     */
    public function updatePassword(string $hash): bool {
        $this->config['password_hash'] = $hash;
        return $this->save();
    }

    /**
     * Aktiviert oder deaktiviert 2FA
     */
    public function update2FA(bool $enabled, ?string $secret): bool {
        $this->config['2fa_enabled'] = $enabled;
        $this->config['2fa_secret'] = $secret;
        return $this->save();
    }

    /**
     * Speichert die aktuelle Konfiguration in die Datei
     */
    public function save(): bool {
        $jsonContent = json_encode($this->config, JSON_PRETTY_PRINT);
        if ($jsonContent === false) {
            return false;
        }
        return file_put_contents($this->configFile, $jsonContent) !== false;
    }
}
