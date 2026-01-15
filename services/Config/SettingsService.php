<?php
declare(strict_types=1);

namespace VantixDash\Config;

/**
 * SettingsService - Application-Level Settings
 * 
 * Verantwortlichkeit: Timeouts, Version, Feature Flags
 */
class SettingsService {
    private ConfigService $config;
    private array $versionData = [];

    public function __construct(ConfigService $config) {
        $this->config = $config;
        $this->loadVersion();
    }

    // ==================== VERSION ====================

    private function loadVersion(): void {
        $versionFile = dirname(__DIR__, 2) . '/version.php';
        if (file_exists($versionFile)) {
            $data = include $versionFile;
            $this->versionData = is_array($data) ? $data : [];
        }
    }

    public function getVersion(): string {
        return (string)($this->versionData['version'] ?? '0.0.0');
    }

    public function getLastUpdate(): string {
        return (string)($this->versionData['last_update'] ?? 'unknown');
    }

    // ==================== TIMEOUTS ====================

    private const DEFAULT_TIMEOUTS = [
        'api'        => 10,
        'site_check' => 15,
        'session'    => 3600,
        'rate_limit' => 300
    ];

    public function getTimeout(string $type): int {
        $key = "timeout_{$type}";
        return $this->config->getInt($key, self::DEFAULT_TIMEOUTS[$type] ?? 10);
    }

    public function setTimeout(string $type, int $seconds): bool {
        $this->config->set("timeout_{$type}", $seconds);
        return $this->config->save();
    }

    public function getAllTimeouts(): array {
        $timeouts = [];
        foreach (self::DEFAULT_TIMEOUTS as $type => $default) {
            $timeouts[$type] = $this->getTimeout($type);
        }
        return $timeouts;
    }

    // ==================== CRON SECRET ====================

    public function getCronSecret(): string {
        return $this->config->getString('cron_secret');
    }

    public function updateCronSecret(string $token): bool {
        $this->config->set('cron_secret', $token);
        return $this->config->save();
    }

    public function generateCronSecret(): string {
        $token = bin2hex(random_bytes(32));
        $this->updateCronSecret($token);
        return $token;
    }
}
