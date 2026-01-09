<?php

class SiteService {
    private $file;

    public function __construct($file) {
        $this->file = $file;
    }

    // Geändert von private auf public, damit api.php darauf zugreifen kann
    public function getAll() {
        if (!file_exists($this->file)) return [];
        $content = file_get_contents($this->file);
        $data = json_decode($content, true);
        return is_array($data) ? array_values($data) : [];
    }

    public function addSite($name, $url) {
        $sites = $this->getAll();
        $newSite = [
            'id' => bin2hex(random_bytes(8)),
            'name' => htmlspecialchars($name),
            'url' => rtrim($url, '/'),
            'api_key' => bin2hex(random_bytes(16)),
            'last_check' => 'Nie',
            'status' => 'pending',
            'updates' => ['core' => 0, 'plugins' => 0, 'themes' => 0]
        ];
        $sites[] = $newSite;
        return $this->save($sites) ? $newSite : false;
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
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $site['url'] . '/wp-content/plugins/vantix-child/api.php',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 15,
                    CURLOPT_HTTPHEADER => [
                        'X-Vantix-Key: ' . $site['api_key'],
                        'Content-Type: application/json'
                    ],
                    CURLOPT_SSL_VERIFYPEER => true 
                ]);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode === 200 && $response) {
                    $data = json_decode($response, true);
                    if (isset($data['success']) && $data['success'] === true) {
                        $site['status'] = 'online';
                        $site['last_check'] = date('d.m.Y H:i');
                        $site['updates'] = $data['updates'];
                        $site['wp_version'] = $data['version'] ?? 'Unbekannt';
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
    $currentVersion = '1.0.0'; // Deine aktuelle Version
    $repo = "olpo24/VantixDash";
    
    // URL wählen: /releases für alle (inkl. Beta), /latest nur für Stable
    $url = $beta 
        ? "https://api.github.com/repos/$repo/releases" 
        : "https://api.github.com/repos/$repo/releases/latest";

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => [
            'User-Agent: VantixDash-Updater', // GitHub verlangt zwingend einen User-Agent
            'Accept: application/vnd.github.v3+json' // Best Practice für GitHub API
        ]
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // API-Fehler abfangen
    if ($httpCode !== 200 || !$response) {
        return [
            'success' => false, 
            'message' => 'GitHub API Fehler (Status: ' . $httpCode . ')',
            'current' => $currentVersion,
            'remote' => $currentVersion
        ];
    }

    $data = json_decode($response, true);
    
    // Das Release-Objekt finden
    $release = null;
    if ($beta && is_array($data) && !empty($data)) {
        // Bei /releases nehmen wir das oberste (neueste) Element
        $release = $data[0]; 
    } elseif (!$beta && isset($data['tag_name'])) {
        // Bei /latest ist das Objekt direkt die Antwort
        $release = $data;
    }

    // Wenn kein Release gefunden wurde, Fallback
    if (!$release) {
        return [
            'success' => true, 
            'update_available' => false, 
            'current' => $currentVersion, 
            'remote' => $currentVersion
        ];
    }

    // Version säubern (v1.4.3-beta -> 1.4.3-beta)
    $remoteVersion = str_replace('v', '', $release['tag_name']);

    // Vergleich (version_compare versteht auch "beta" Suffixe)
    $updateAvailable = version_compare($remoteVersion, $currentVersion, '>');

    return [
        'success' => true,
        'update_available' => $updateAvailable,
        'current' => $currentVersion,
        'remote' => $remoteVersion,
        'is_beta' => ($release['prerelease'] ?? false),
        'download_url' => $release['zipball_url'] ?? '',
        'changelog' => $release['body'] ?? ''
    ];
}
    public function installUpdate($url) {
        $tempFile = dirname(__DIR__) . '/data/update_temp.zip';
        $content = @file_get_contents($url, false, stream_context_create([
            "http" => ["header" => "User-Agent: VantixDash-Updater\r\n"]
        ]));
        
        if (!$content) return false;
        file_put_contents($tempFile, $content);

        $zip = new ZipArchive;
        if ($zip->open($tempFile) === TRUE) {
            $zip->extractTo(dirname(__DIR__) . '/');
            $zip->close();
            unlink($tempFile);
            return true;
        }
        return false;
    }

    private function save($sites) {
        return file_put_contents($this->file, json_encode(array_values($sites), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
