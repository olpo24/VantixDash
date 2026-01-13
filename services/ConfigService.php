<?php

class ConfigService {
    private $settings = [];
    private $versionData = [];
    private $configPath;

    public function __construct() {
        $basePath = dirname(__DIR__) . '/';
        $this->configPath = $basePath . 'data/config.json';
        
        // 1. Config laden
        if (file_exists($this->configPath)) {
            $this->settings = json_decode(file_get_contents($this->configPath), true) ?? [];
        }

        // 2. Version laden
        if (file_exists($basePath . 'version.php')) {
            $this->versionData = include $basePath . 'version.php';
        }
    }

    public function get($key, $default = null) {
        return $this->settings[$key] ?? $default;
    }

    public function getVersion() {
        return $this->versionData['version'] ?? '0.0.0';
    }

    public function getAll() {
        return $this->settings;
    }

    /**
     * Speichert die aktuellen Einstellungen zurück in die config.json
     */
    public function save() {
        return file_put_contents($this->configPath, json_encode($this->settings, JSON_PRETTY_PRINT));
    }

    /**
     * Gibt die für das Profil relevanten Daten zurück
     */
    public function getUserData() {
        return [
            'username'    => $this->get('username', ''),
            'email'       => $this->get('email', ''),
            '2fa_enabled' => $this->get('2fa_enabled', false)
        ];
    }

    /**
     * Aktualisiert Username und Email
     */
    public function updateUser($username, $email) {
        $this->settings['username'] = htmlspecialchars(strip_tags(trim($username)));
        $this->settings['email']    = filter_var($email, FILTER_SANITIZE_EMAIL);
        return $this->save();
    }

    /**
     * Aktualisiert den Passwort-Hash
     */
    public function updatePassword($hash) {
        $this->settings['password_hash'] = $hash;
        return $this->save();
    }

    /**
     * 2FA Status und Secret setzen
     */
    public function update2FA($enabled, $secret = null) {
        $this->settings['2fa_enabled'] = (bool)$enabled;
        if ($secret !== null) {
            $this->settings['2fa_secret'] = $secret;
        }
        return $this->save();
    }
}
