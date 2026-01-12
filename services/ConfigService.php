<?php

class ConfigService {
    private $settings = [];
    private $versionData = [];
    private static $instance = null;

    public function __construct() {
    $basePath = dirname(__DIR__) . '/data/';
    $configFile = $basePath . 'config.json'; // .json statt .php
    
    if (file_exists($configFile)) {
        $content = file_get_contents($configFile);
        $this->settings = json_decode($content, true) ?? [];
    }

    if (file_exists(dirname(__DIR__) . '/version.php')) {
        $this->versionData = include dirname(__DIR__) . '/version.php';
    }
}

    // Zugriff auf Einstellungen (z.B. get('github_token'))
    public function get($key, $default = null) {
        return $this->settings[$key] ?? $default;
    }

    // Zugriff auf Version (z.B. getVersion())
    public function getVersion() {
        return $this->versionData['version'] ?? '1.0.0';
    }

    // Hilfreich für das Frontend
    public function getAll() {
        return $this->settings;
    }
}
