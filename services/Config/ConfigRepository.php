<?php
declare(strict_types=1);

namespace VantixDash\Config;

/**
 * ConfigRepository - Low-Level File I/O für JSON Config
 * 
 * Verantwortlichkeit: Nur Lesen/Schreiben der config.json
 */
class ConfigRepository {
    private string $configPath;
    private static ?array $cache = null;

    public function __construct(string $configPath = null) {
        $this->configPath = $configPath ?? dirname(__DIR__, 2) . '/data/config.json';
    }

    /**
     * Lädt komplette Config mit Static Caching
     */
    public function load(): array {
        if (self::$cache !== null) {
            return self::$cache;
        }

        if (!file_exists($this->configPath)) {
            self::$cache = [];
            return [];
        }

        $content = file_get_contents($this->configPath);
        if ($content === false) {
            self::$cache = [];
            return [];
        }

        $decoded = json_decode($content, true);
        self::$cache = is_array($decoded) ? $decoded : [];
        
        return self::$cache;
    }

    /**
     * Speichert komplette Config atomar
     */
    public function save(array $data): bool {
        $jsonContent = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($jsonContent === false) {
            return false;
        }

        // Atomic write
        $tempFile = $this->configPath . '.tmp.' . bin2hex(random_bytes(8));
        
        if (file_put_contents($tempFile, $jsonContent, LOCK_EX) === false) {
            return false;
        }

        if (!rename($tempFile, $this->configPath)) {
            @unlink($tempFile);
            return false;
        }

        // Cache invalidieren
        self::$cache = $data;
        return true;
    }

    /**
     * Cache manuell invalidieren (für Tests)
     */
    public static function clearCache(): void {
        self::$cache = null;
    }
}
