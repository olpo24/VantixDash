<?php
declare(strict_types=1);

class SiteService {
    private string $file;
    private ConfigService $config;
    private array $sites = [];

    /**
     * @param string $file Pfad zur sites.json
     * @param ConfigService $config Instanz des ConfigService
     */
    public function __construct(string $file, ConfigService $config) {
        $this->file = $file;
        $this->config = $config;
        $this->load();
    }

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

    public function save(?array $sites = null): bool {
        if ($sites !== null) {
            $this->sites = $sites;
        }
        $result = file_put_contents($this->file, json_encode(array_values($this->sites), JSON_PRETTY_PRINT));
        return $result !== false;
    }

    public function getAll(): array { 
        return $this->sites; 
    }

    /**
     * Aktualisiert die Daten einer WordPress-Seite via API
     * @return array|false Die aktualisierten Daten oder false bei Fehler
     */
    public function refreshSiteData(string $id) {
        foreach ($this->sites as &$site) {
            if ($site['id'] === $id) {
                $apiUrl = rtrim($site['url'], '/') . '/wp-json/vantixdash/v1/status';
                
                $options = [
                    'http' => [
                        'method' => 'GET',
                        'header' => "X-Vantix-Secret: " . $site['api_key'] . "\r\n" .
                                    "User-Agent: VantixDash-Monitor/1.0\r\n",
                        'timeout' => 15,
                        'ignore_errors' => true
                    ]
                ];
                
                $context = stream_context_create($options);
                $response = file_get_contents($apiUrl, false, $context);

                if ($response !== false) {
                    $data = json_decode($response, true);
                    
                    if (json_last_error() === JSON_ERROR_NONE && isset($data['version'])) {
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
                    }
                }
                
                $site['status'] = 'offline';
                $site['last_check'] = date('Y-m-d H:i:s');
                $this->save();
                return false;
            }
        }
        return false;
    }

    private function isValidApiKey(string $key): bool {
        return (bool)preg_match('/^[A-Za-z0-9-]{16,}$/', $key);
    }

    /**
     * Fügt eine neue Seite hinzu
     * @return array|false Die neue Seite oder false bei Validierungsfehler
     */
    public function addSite(string $name, string $url) {
        $apiKey = bin2hex(random_bytes(16)); 
        
        if (!$this->isValidApiKey($apiKey)) {
            return false; 
        }

        $id = uniqid();
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
        $this->save();
        return $newSite;
    }

    public function deleteSite(string $id): bool {
        foreach ($this->sites as $k => $s) {
            if ($s['id'] === $id) { 
                unset($this->sites[$k]); 
                return $this->save(); 
            }
        }
        return false;
    }
}
