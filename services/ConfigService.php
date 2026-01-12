<?php

class ConfigService {
    private $settings = [];
    private $versionData = [];
 
    public function __construct() {
        $basePath = dirname(__DIR__) . '/';
        
        // 1. Config laden
        if (file_exists($basePath . 'data/config.json')) {
            $this->settings = json_decode(file_get_contents($basePath . 'data/config.json'), true) ?? [];
        }

        // 2. Version laden (WICHTIG!)
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

    // Hilfreich für das Frontend
    public function getAll() {
        return $this->settings;
    }
}
