<?php
declare(strict_types=1);

namespace VantixDash;

use Exception;

/**
 * SiteService - Verwaltung und Kommunikation mit WordPress-Instanzen
 */
class SiteService {
    private string $file;
    private ConfigService $config;
    private Logger $logger;
    private array $sites = [];

    /**
     * @param string $file Pfad zur sites.json
     * @param ConfigService $config Instanz des ConfigService
     * @param Logger $logger Instanz des LoggerService
     */
    public function __construct(string $file, ConfigService $config, Logger $logger) {
        $this->file = $file;
        $this->config = $config;
        $this->logger = $logger;
        $this->load();
    }

    /**
     * Lädt die Seiten aus der JSON-Datei
     */
    private function load(): void {
        if (file_exists($this->file)) {
            $content = file_get_contents($this->file);
            if ($content !== false) {
                $data = json_decode($content, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $this->sites = is_array($data) ? $data : [];
                }
            }
        }
    }

    /**
     * Speichert die aktuellen Seiten atomar (Write-then-Rename)
     */
    public function save(?array $sites = null): bool {
        if ($sites !== null) {
            $this->sites = $sites;
        }

        $jsonContent = json_encode(array_values($this->sites), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($jsonContent === false) {
            return false;
        }

        // Atomares Speichern verhindert Datenverlust bei Abstürzen
        $tempFile = $this->file . '.tmp.' . bin2hex(random_bytes(8));

        if (file_put_contents($tempFile, $jsonContent, LOCK_EX) === false) {
            return false;
        }

        if (!rename($tempFile, $this->file)) {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
            return false;
        }

        return true;
    }

    /**
     * Gibt alle geladenen Seiten zurück
     */
    public function getAll(): array { 
        return $this->sites; 
    }

    /**
     * Aktualisiert die Daten einer WordPress-Seite via REST API
     */
    public function refreshSiteData(string $id): array|false {
        foreach ($this->sites as &$site) {
            if ($site['id'] === $id) {
                try {
                    $apiUrl = rtrim($site['url'], '/') . '/wp-json/vantixdash/v1/status';
                    
                    // Key validieren vor Nutzung
                    if (!$this->isValidApiKey((string)$site['api_key'])) {
                        throw new Exception("Gespeicherter API-Key hat ein ungültiges Format.");
                    }

                    $timeout = $this->config->getTimeout('site_check');

                    $options = [
                        'http' => [
                            'method' => 'GET',
                            'header' => "X-Vantix-Secret: " . $site['api_key'] . "\r\n" .
                                        "User-Agent: VantixDash-Monitor/1.0\r\n",
                            'timeout' => $timeout,
                            'ignore_errors' => true
                        ]
                    ];
                    
                    $context = stream_context_create($options);
                    $response = @file_get_contents($apiUrl, false, $context);

                    if ($response === false) {
                        throw new Exception("Verbindung fehlgeschlagen (Timeout oder URL nicht erreichbar)");
                    }

                    $data = json_decode($response, true);
                    
                    if (json_last_error() !== JSON_ERROR_NONE || !isset($data['version'])) {
                        throw new Exception("Ungültige Antwort von WordPress (JSON Fehler oder fehlende Daten)");
                    }

                    // Daten mappen
                    $site['status'] = 'online';
                    $site['wp_version'] = (string)$data['version'];
                    $site['php'] = (string)($data['php'] ?? 'unknown');
                    
                    $site['updates'] = [
                        'core'    => (int)($data['core'] ?? 0),
                        'plugins' => (int)($data['plugins'] ?? 0),
                        'themes'  => (int)($data['themes'] ?? 0)
                    ];

                    $site['plugin_list'] = (array)($data['plugin_list'] ?? []);
                    $site['theme_list']  = (array)($data['theme_list'] ?? []);
                    
                    $site['last_check'] = date('Y-m-d H:i:s');
                    
                    $this->save();
                    return $site;

                } catch (Exception $e) {
                    $this->logger->error("WordPress Check fehlgeschlagen", [
                        'site'    => $site['name'],
                        'message' => $e->getMessage()
                    ]);

                    $site['status'] = 'offline';
                    $site['last_check'] = date('Y-m-d H:i:s');
                    $this->save();
                    return false;
                }
            }
        }
        return false;
    }

    /**
     * Strikte Validierung: Erwartet exakt 32 Hex-Zeichen
     */
    private function isValidApiKey(string $key): bool {
        return (bool)preg_match('/^[a-f0-9]{32}$/i', $key);
    }

    /**
     * Fügt eine neue Seite hinzu
     */
    public function addSite(string $name, string $url): array|false {
        // 1. URL Validierung
        $url = filter_var(rtrim($url, '/'), FILTER_VALIDATE_URL);
        if (!$url || !preg_match('/^https?:\/\//i', $url)) {
            throw new Exception('Bitte gib eine gültige URL inkl. http/https an.');
        }

        // 2. Key-Generierung (32 Zeichen Hex)
        $apiKey = bin2hex(random_bytes(16));
        if (!$this->isValidApiKey($apiKey)) {
            throw new Exception('Fehler bei der kryptografischen Key-Generierung.');
        }

        $newSite = [
            'id'          => bin2hex(random_bytes(6)), // Eindeutige ID
            'name'        => htmlspecialchars(strip_tags(trim($name)), ENT_QUOTES, 'UTF-8'),
            'url'         => $url,
            'api_key'     => $apiKey,
            'status'      => 'pending',
            'wp_version'  => '0.0.0',
            'updates'     => ['core' => 0, 'plugins' => 0, 'themes' => 0],
            'last_check'  => date('Y-m-d H:i:s'),
            'plugin_list' => [],
            'theme_list'  => []
        ];

        $this->sites[] = $newSite;
        return $this->save() ? $newSite : false;
    }

    /**
     * Löscht eine Seite anhand der ID
     */
    public function deleteSite(string $id): bool {
        $originalCount = count($this->sites);
        $this->sites = array_values(array_filter($this->sites, fn($s) => $s['id'] !== $id));
        
        return (count($this->sites) < $originalCount) ? $this->save() : false;
    }
}
