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

    // WICHTIG: Diese Methode muss PUBLIC sein, damit wir sie 
    // nach Änderungen aufrufen können
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
            'details' => ['core' => [], 'plugins' => [], 'themes' => []]
        ];

        $this->sites[] = $newSite;
        $this->save();
        return $newSite;
    }

    public function deleteSite($id) {
        foreach ($this->sites as $key => $site) {
            if ($site['id'] === $id) {
                unset($this->sites[$key]);
                return $this->save();
            }
        }
        return false;
    }

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
                    if ($data && (isset($data['version']) || isset($data['updates']))) {
                        $site['status'] = 'online';
                        $site['wp_version'] = $data['version'] ?? $site['wp_version'];
                        $site['php'] = $data['php'] ?? ($site['php'] ?? 'unknown');
                        $site['updates'] = [
                            'core' => (int)($data['updates']['core'] ?? 0),
                            'plugins' => (int)($data['updates']['plugins'] ?? 0),
                            'themes' => (int)($data['updates']['themes'] ?? 0)
                        ];
                        $site['details'] = $data['details'] ?? $site['details'];
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
} // Ende der Klasse
