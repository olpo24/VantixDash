<?php
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
            $content = file_get_contents($this->file);
            if ($content !== false) {
                $data = json_decode($content, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $this->sites = is_array($data) ? $data : [];
                }
            }
        }
    }

    public function save($sites = null) {
        if ($sites !== null) $this->sites = $sites;
        return file_put_contents($this->file, json_encode(array_values($this->sites), JSON_PRETTY_PRINT));
    }

    public function getAll() { return $this->sites; }

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
                        'ignore_errors' => true // Erlaubt das Lesen des Bodys auch bei Fehlern (z.B. 403)
                    ]
                ];
                
                $context = stream_context_create($options);
                
                // @ entfernt - wir prüfen das Ergebnis explizit
                $response = file_get_contents($apiUrl, false, $context);

                if ($response !== false) {
                    $data = json_decode($response, true);
                    
                    if (json_last_error() === JSON_ERROR_NONE && isset($data['version'])) {
                        $site['status'] = 'online';
                        $site['wp_version'] = $data['version'];
                        $site['php'] = $data['php'] ?? 'unknown';
                        
                        $site['updates'] = [
                            'core'    => (int)($data['core'] ?? 0),
                            'plugins' => (int)($data['plugins'] ?? 0),
                            'themes'  => (int)($data['themes'] ?? 0)
                        ];

                        $site['plugin_list'] = $data['plugin_list'] ?? [];
                        $site['theme_list']  = $data['theme_list'] ?? [];
                        
                        $site['details'] = [
                            'core' => [],
                            'plugins' => $data['plugin_list'] ?? [],
                            'themes' => $data['theme_list'] ?? []
                        ];

                        $site['last_check'] = date('Y-m-d H:i:s');
                        $this->save();
                        return $site;
                    }
                }
                
                // Wenn wir hier landen, war der Request oder das JSON fehlerhaft
                $site['status'] = 'offline';
                $site['last_check'] = date('Y-m-d H:i:s');
                $this->save();
                return false;
            }
        }
        return false;
    }

    private function isValidApiKey($key) {
        // Erlaubt: A-Z, a-z, 0-9 und - (Mind. 16 Zeichen)
        return preg_match('/^[A-Za-z0-9-]{16,}$/', $key);
    }

    public function addSite($name, $url) {
        // Generiere einen sicheren API-Key
        $apiKey = bin2hex(random_bytes(16)); 
        
        // Validierung des generierten oder übergebenen Keys
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

    public function deleteSite($id) {
        foreach ($this->sites as $k => $s) {
            if ($s['id'] === $id) { 
                unset($this->sites[$k]); 
                return $this->save(); 
            }
        }
        return false;
    }
}
