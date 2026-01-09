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
    // 1. Grundeinstellungen
    $currentVersion = '1.0.0'; 
    $repo = "olpo24/VantixDash";
    $configFile = dirname(__DIR__) . '/data/config.php';
    
    // Config laden für den GitHub-Token
    $config = file_exists($configFile) ? include $configFile : [];
    $token = $config['github_token'] ?? '';

    // 2. URL bestimmen (Releases-Liste für Beta, Latest für Stable)
    $url = $beta 
        ? "https://api.github.com/repos/$repo/releases" 
        : "https://api.github.com/repos/$repo/releases/latest";

    // 3. cURL Initialisierung
    $ch = curl_init();
    $headers = [
        'User-Agent: VantixDash-Updater',
        'Accept: application/vnd.github.v3+json'
    ];

    // Wenn ein Token vorhanden ist, mitsenden um 403 (Rate Limit) zu vermeiden
    if (!empty($token)) {
        $headers[] = "Authorization: token " . $token;
    }

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_HTTPHEADER => $headers
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // 4. Fehlerbehandlung (z.B. bei Rate Limit trotz Token oder DNS Fehlern)
    if ($httpCode !== 200 || !$response) {
        return [
            'success' => false, 
            'message' => "GitHub API Fehler (Status: $httpCode)",
            'current' => $currentVersion,
            'remote' => $currentVersion,
            'update_available' => false
        ];
    }

    $data = json_decode($response, true);
    $release = null;

    // 5. Daten-Extraktion basierend auf dem Modus (Beta vs Stable)
    if ($beta && is_array($data) && !empty($data)) {
        // Im Beta-Modus (/releases) nehmen wir das aktuellste Release aus dem Array
        $release = $data[0]; 
    } elseif (!$beta && isset($data['tag_name'])) {
        // Im Stable-Modus (/latest) ist die Antwort direkt das Objekt
        $release = $data;
    }

    if (!$release) {
        return ['success' => false, 'message' => 'Kein passendes Release gefunden.'];
    }

    // Version säubern (v1.4.3-beta -> 1.4.3-beta)
    $remoteVersion = str_replace('v', '', $release['tag_name']);

    // 6. Versionsvergleich
    // version_compare erkennt "beta", "rc", "alpha" automatisch korrekt
    $updateAvailable = version_compare($remoteVersion, $currentVersion, '>');

    return [
        'success' => true,
        'update_available' => $updateAvailable,
        'current' => $currentVersion,
        'remote' => $remoteVersion,
        'is_beta' => ($release['prerelease'] ?? false),
        'tag_name' => $release['tag_name'],
        'download_url' => $release['zipball_url'] ?? '',
        'changelog' => $release['body'] ?? 'Keine Beschreibung verfügbar.'
    ];
}
    public function installUpdate($url) {
    $tempFile = dirname(__DIR__) . '/data/update_temp.zip';
    $extractPath = dirname(__DIR__) . '/';

    // cURL nutzen statt file_get_contents für bessere Fehlerbehandlung und Auth
    $ch = curl_init($url);
    $fp = fopen($tempFile, 'w+');

    curl_setopt_array($ch, [
        CURLOPT_FOLLOWLOCATION => true, // WICHTIG: Folgt dem Redirect zu codeload.github.com
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_BINARYTRANSFER => true,
        CURLOPT_HEADER => false,
        CURLOPT_FILE => $fp,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_USERAGENT => 'VantixDash-Updater',
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    // Falls du einen Token hast, sende ihn auch hier mit
    $config = file_exists(dirname(__DIR__) . '/data/config.php') ? include dirname(__DIR__) . '/data/config.php' : [];
    if (!empty($config['github_token'])) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: token " . $config['github_token']]);
    }

    $success = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);

    if (!$success || $httpCode !== 200) {
        if (file_exists($tempFile)) unlink($tempFile);
        return false;
    }

    // Entpacken
    $zip = new ZipArchive;
    if ($zip->open($tempFile) === TRUE) {
        $zip->extractTo($extractPath);
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
