<?php
/**
 * services/SiteService.php
 * Zentraler Dienst für die Verwaltung der WordPress-Instanzen
 */

class SiteService {
    private $file;
    private $config;
    private $sites = [];

    public function __construct($file, $config) {
        $this->file = $file;
        $this->config = $config;
        $this->load();
    }

    private function load() {
        if (file_exists($this->file)) {
            $data = json_decode(file_get_contents($this->file), true);
            $this->sites = is_array($data) ? $data : [];
        }
    }

    /**
     * Speichert den aktuellen Status in die sites.json
     */
    public function save($sites = null) {
        if ($sites !== null) {
            $this->sites = $sites;
        }
        return file_put_contents(
            $this->file, 
            json_encode(array_values($this->sites), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    public function getAll() {
        return $this->sites;
    }

    /**
     * Fügt eine neue Seite mit Standard-Struktur hinzu
     */
    public function addSite($name, $url) {
        $id = uniqid();
        $newSite = [
            'id' => $id,
            'name' => $name,
            'url' => rtrim($url, '/'),
            'api_key' => bin2hex(random_bytes(16)),
            'status' => 'pending',
            'wp_version' => '0.0.0',
            'updates' => ['core' => 0, 'plugins' => 0, 'themes' => 0],
            'last_check' => date('Y-m-d H:i:s'),
            'details' => ['core' => [], 'plugins' => [], 'themes' => []],
            'plugin_list' => [],
            'theme_list' => []
        ];

        $this->sites[] = $newSite;
        $this->save();
        return $newSite;
    }

    /**
     * Entfernt eine Seite aus der Liste
     */
    public function deleteSite($id) {
        foreach ($this->sites as $key => $site) {
            if ($site['id'] === $id) {
                unset($this->sites[$key]);
                return $this->save();
            }
        }
        return false;
    }

    /**
     * Holt frische Daten von der WordPress-Seite (Child-Plugin)
     */
    public function refreshSiteData($id) {
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
                $response = @file_get_contents($apiUrl, false, $context);

                if ($response) {
                    $data = json_decode($response, true);
                    
                    if ($data && (isset($data['version']) || isset($data['updates']) || isset($data['details']))) {
                        $site['status'] = 'online';
                        $site['wp_version'] = $data['version'] ?? ($data['wp_version'] ?? $site['wp_version']);
                        $site['php'] = $data['php'] ?? ($site['php'] ?? 'unknown');
                        
                        // 1. Details speichern (Listen der Plugins/Themes)
                        $site['details'] = $data['details'] ?? [
                            'core' => [],
                            'plugins' => [],
                            'themes' => []
                        ];

                        // 2. Updates berechnen durch Zählen der Einträge in den Detail-Listen
                        // Das ist sicherer, falls das Child-Plugin nur die Listen schickt
                        $site['updates'] = [
                            'core'    => count($site['details']['core'] ?? []),
                            'plugins' => count($site['details']['plugins'] ?? []),
                            'themes'  => count($site['details']['themes'] ?? [])
                        ];
                        
                        $site['last_check'] = date('Y-m-d H:i:s');
                        $site['ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
                        
                        $this->save();
                        return $site;
                    }
                }
                
                // Falls keine Antwort oder Fehler: Status auf offline
                $site['status'] = 'offline';
                $site['last_check'] = date('Y-m-d H:i:s');
                $this->save();
                return false;
            }
        }
        return false;
    }
}
