<?php
declare(strict_types=1);

class SiteService {
    private string $file;
    private ConfigService $config;
    private Logger $logger; // Logger hinzugefügt
    private array $sites = [];

    /**
     * @param string $file Pfad zur sites.json
     * @param ConfigService $config Instanz des ConfigService
     * @param Logger $logger Instanz des LoggerService
     */
    public function __construct(string $file, ConfigService $config, Logger $logger) {
        $this->file = $file;
        $this->config = $config;
        $this->logger = $logger; // Initialisierung
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
     * Speichert die aktuellen Seiten atomar in die JSON-Datei
     */
    public function save(?array $sites = null): bool {
        if ($sites !== null) {
            $this->sites = $sites;
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
     * Aktualisiert die Daten einer WordPress-Seite via API
     */
    public function refreshSiteData(string $id): array|false {
        foreach ($this->sites as &$site) {
            if ($site['id'] === $id) {
                try {
                    $apiUrl = rtrim($site['url'], '/') . '/wp-json/vantixdash/v1/status';
                    
                    // Header Injection verhindern & Timeout laden
                    $safeApiKey = preg_replace('/[\r\n]/', '', (string)$site['api_key']);
                    $timeout = $this->config->getTimeout('site_check');

                    $options = [
                        'http' => [
                            'method' => 'GET',
                            'header' => "X-Vantix-Secret: " . $safeApiKey . "\r\n" .
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
                        throw new Exception("Ungültiges JSON-Format oder fehlende Daten von WordPress");
                    }

                    // Erfolgreiche Daten-Zuweisung
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
                    
                    $site['details'] = [
                        'core' => [],
                        'plugins' => $site['plugin_list'],
                        'themes' => $site['theme_list']
                    ];

                    $site['last_check'] = date('Y-m-d H:i:s');
                    $this->save();
                    return $site;

                } catch (Exception $e) {
                    // Logging des Fehlers
                    $this->logger->error("WordPress API Request fehlgeschlagen", [
                        'site_id' => $id,
                        'url'     => $apiUrl ?? $site['url'],
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
     * Validiert das Format des API Keys
     */
    private function isValidApiKey(string $key): bool {
        return (bool)preg_match('/^[A-Za-z0-9-]{16,}$/', $key);
    }

    /**
     * Fügt eine neue Seite hinzu
     */
    public function addSite(string $name, string $url): array|false {
        $apiKey = bin2hex(random_bytes(16)); 
        
        $id = bin2hex(random_bytes(6));
        $newSite = [
            'id' => $id,
            'name' => htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
            'url' => rtrim($url, '/'),
            'api_key' => $apiKey,
            'status' => 'pending',
            'wp_version' => '0.0.0',
            'updates' => ['core' => 0, 'plugins' => 0, 'themes' => 0],
            'last_check' => date('Y-m-d H:i:s'),
            'plugin_list' => [],
            'theme_list' => []
        ];

        $this->sites[] = $newSite;
        return $this->save() ? $newSite : false;
    }

    /**
     * Löscht eine Seite anhand der ID
     */
    public function deleteSite(string $id): bool {
        $originalCount = count($this->sites);
        $this->sites = array_filter($this->sites, fn($s) => $s['id'] !== $id);
        
        if (count($this->sites) === $originalCount) {
            return false;
        }

        return $this->save();
    }
}
