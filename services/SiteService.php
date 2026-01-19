<?php
declare(strict_types=1);

namespace VantixDash;

use Exception;
use VantixDash\Exception\SiteRefreshException;
use VantixDash\Config\ConfigService;
use VantixDash\Config\SettingsService;
/**
 * SiteService - Hochperformante Verwaltung von WordPress-Instanzen
 */
class SiteService {
    private string $file;
    private ConfigService $config;
	 private SettingsService $settings;
    private Logger $logger;
    private array $sites = [];
    private array $indexMap = []; // [site_id => array_index] für O(1) Zugriff

 /**
     * @param string $file Pfad zur sites.json
     * @param ConfigService $config Instanz des ConfigService
     * @param Logger $logger Instanz des LoggerService
     * @param SettingsService|null $settings Optional: SettingsService für Timeouts
     */
public function __construct(
        string $file, 
        ConfigService $config, 
        Logger $logger,
        ?SettingsService $settings = null
    ) {
        $this->file = $file;
        $this->config = $config;
        $this->logger = $logger;
        $this->settings = $settings ?? new SettingsService($config); // ← Auto-init falls null
        $this->load();
    }

    /**
     * Lädt die Seiten und baut den Such-Index im Speicher auf
     */
    private function load(): void {
        if (file_exists($this->file)) {
            $content = file_get_contents($this->file);
            if ($content !== false) {
                $data = json_decode($content, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                    $this->sites = $data;
                    $this->rebuildIndex();
                }
            }
        }
    }

    /**
     * Erstellt die Index-Map für schnellen Zugriff neu
     */
    private function rebuildIndex(): void {
        $this->indexMap = [];
        foreach ($this->sites as $index => $site) {
            if (isset($site['id'])) {
                $this->indexMap[(string)$site['id']] = $index;
            }
        }
    }

    /**
     * Speichert die aktuellen Seiten atomar
     */
    public function save(?array $sites = null): bool {
        if ($sites !== null) {
            $this->sites = $sites;
            $this->rebuildIndex();
        }

        $jsonContent = json_encode(array_values($this->sites), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($jsonContent === false) {
            return false;
        }

        $tempFile = $this->file . '.tmp.' . bin2hex(random_bytes(8));

        if (file_put_contents($tempFile, $jsonContent, LOCK_EX) === false) {
            return false;
        }

        if (!rename($tempFile, $this->file)) {
            if (file_exists($tempFile)) unlink($tempFile);
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
     * Aktualisiert die Daten einer WordPress-Seite via REST API (Optimiert)
     */
    public function refreshSiteData(string $id): array|false {
        $index = $this->indexMap[$id] ?? null;
        if ($index === null || !isset($this->sites[$index])) {
            return false;
        }

        $site = &$this->sites[$index];

        try {
            $apiUrl = rtrim($site['url'], '/') . '/wp-json/vantixdash/v1/status';
            
            if (!$this->isValidApiKey((string)$site['api_key'])) {
                throw new Exception("Gespeicherter API-Key hat ein ungültiges Format.");
            }

            // ✅ JETZT NUTZEN WIR SettingsService
            $timeout = $this->settings->getTimeout('site_check');
            
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => "X-Vantix-Secret: " . $site['api_key'] . "\r\n" .
                                "User-Agent: VantixDash-Monitor/1.0\r\n",
                    'timeout' => $timeout,
                    'ignore_errors' => true
                ]
            ]);
            
            $response = @file_get_contents($apiUrl, false, $context);
            if ($response === false) {
                throw new Exception("Verbindung fehlgeschlagen (Timeout oder URL offline)");
            }

            $data = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE || !isset($data['version'])) {
                throw new Exception("Ungültige Antwort von WordPress");
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
                'site' => $site['name'],
                'message' => $e->getMessage()
            ]);

            $site['status'] = 'offline';
            $site['last_check'] = date('Y-m-d H:i:s');
            $this->save();
            return false;
        }
    }

    private function isValidApiKey(string $key): bool {
        return (bool)preg_match('/^[a-f0-9]{32}$/i', $key);
    }

    /**
     * Fügt eine neue Seite hinzu
     */
    public function addSite(string $name, string $url): array|false {
        $url = filter_var(rtrim($url, '/'), FILTER_VALIDATE_URL);
        if (!$url || !preg_match('/^https?:\/\//i', $url)) {
            throw new Exception('Bitte gib eine gültige URL inkl. http/https an.');
        }

        $apiKey = bin2hex(random_bytes(16));
        $id = bin2hex(random_bytes(6));

        $newSite = [
            'id'          => $id,
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
        
        // Index sofort aktualisieren vor dem Speichern
        $this->indexMap[$id] = count($this->sites) - 1;
        
        return $this->save() ? $newSite : false;
    }

    /**
     * Löscht eine Seite anhand der ID
     */
    public function deleteSite(string $id): bool {
        $index = $this->indexMap[$id] ?? null;
        if ($index === null) return false;

        unset($this->sites[$index]);
        // Array neu indizieren und IndexMap neu aufbauen
        $this->sites = array_values($this->sites);
        $this->rebuildIndex();
        
        return $this->save();
    }
}
