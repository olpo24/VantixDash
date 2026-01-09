<?php
// services/SiteService.php

class SiteService {
    private $file;

    public function __construct($sitesFile) {
        $this->file = $sitesFile;
    }

    private function getAll() {
        if (!file_exists($this->file)) return [];
        return json_decode(file_get_contents($this->file), true) ?: [];
    }

    private function save($sites) {
        return file_put_contents($this->file, json_encode(array_values($sites), JSON_PRETTY_PRINT));
    }

    public function addSite($name, $url) {
        // Validierung gehört hierher!
        if (!filter_var($url, FILTER_VALIDATE_URL)) return false;

        $sites = $this->getAll();
        $apiKey = bin2hex(random_bytes(16));
        
        $newSite = [
            'id' => bin2hex(random_bytes(8)),
            'name' => htmlspecialchars($name),
            'url' => rtrim($url, '/'),
            'api_key' => $apiKey,
            'status' => 'pending'
        ];

        $sites[] = $newSite;
        $this->save($sites);
        return $newSite;
    }

    public function deleteSite($id) {
        $sites = $this->getAll();
        $filtered = array_filter($sites, fn($s) => $s['id'] !== $id);
        return $this->save($filtered);
    }
    public function refreshSiteData($id) {
    $sites = $this->getAll();
    
    foreach ($sites as &$site) {
        if ($site['id'] === $id) {
            // 1. Verbindung aufbauen (cURL)
            $ch = curl_init();
            
            // Wir hängen den API-Key als Custom Header an
            curl_setopt_array($ch, [
                CURLOPT_URL => $site['url'] . '/wp-content/plugins/vantix-child/api.php', // Pfad zum Plugin
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 15,
                CURLOPT_HTTPHEADER => [
                    'X-Vantix-Key: ' . $site['api_key'],
                    'Content-Type: application/json'
                ],
                // Falls SSL-Probleme auf dem Host bestehen (nur im Notfall deaktivieren!)
                CURLOPT_SSL_VERIFYPEER => true 
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            // 2. Antwort verarbeiten
            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                
                if (isset($data['success']) && $data['success'] === true) {
                    $site['status'] = 'online';
                    $site['last_check'] = date('d.m.Y H:i');
                    $site['updates'] = [
                        'core'    => $data['updates']['core'] ?? 0,
                        'plugins' => $data['updates']['plugins'] ?? 0,
                        'themes'  => $data['updates']['themes'] ?? 0
                    ];
                    $site['wp_version'] = $data['version'] ?? 'Unbekannt';
                    $site['php_version'] = $data['php'] ?? 'Unbekannt';
                } else {
                    $site['status'] = 'error';
                }
            } else {
                $site['status'] = 'offline';
            }

            $this->save($sites);
            return $site;
        }
    }
    return false;
}
    public function checkAppUpdate($beta = false) {
    // Aktuelle Version deines Dashboards
    $currentVersion = '1.0.0'; 
    $repo = "olpo24/VantixDash";
    $url = "https://api.github.com/repos/$repo/releases/latest";

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => 'VantixDash-Updater', // GitHub setzt einen User-Agent voraus
        CURLOPT_TIMEOUT => 10
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    if (!$response) {
        return ['success' => false, 'message' => 'GitHub API nicht erreichbar'];
    }

    $data = json_decode($response, true);
    $remoteVersion = isset($data['tag_name']) ? str_replace('v', '', $data['tag_name']) : $currentVersion;

    // Einfacher Versionsvergleich
    $updateAvailable = version_compare($remoteVersion, $currentVersion, '>');

    return [
        'success' => true,
        'update_available' => $updateAvailable,
        'current' => $currentVersion,
        'remote' => $remoteVersion,
        'download_url' => $data['zipball_url'] ?? '',
        'changelog' => $data['body'] ?? ''
    ];
}
}
