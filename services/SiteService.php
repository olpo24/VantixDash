<?php
/**
 * SiteService - Zentrale Geschäftslogik für VantixDash
 * Verwaltet Webseiten-Daten, WordPress-Abfragen und System-Updates.
 */

class SiteService {
    private $file;
    private $config;

    /**
     * @param string $file Pfad zur sites.json
     * @param ConfigService $config Instanz des zentralen Config-Service
     */
    public function __construct($file, ConfigService $config) {
        $this->file = $file;
        $this->config = $config;
    }

    /**
     * Lädt alle registrierten Webseiten
     */
    public function getAll() {
        if (!file_exists($this->file)) return [];
        $content = file_get_contents($this->file);
        $data = json_decode($content, true);
        return is_array($data) ? array_values($data) : [];
    }

    /**
     * Speichert die Webseiten-Liste
     */
    private function save($sites) {
        return file_put_contents(
            $this->file, 
            json_encode(array_values($sites), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Fügt eine neue Webseite hinzu
     */
    public function addSite($name, $url) {
        $sites = $this->getAll();
        $newSite = [
            'id' => bin2hex(random_bytes(8)),
            'name' => htmlspecialchars($name),
            'url' => rtrim($url, '/'),
            'api_key' => bin2hex(random_bytes(16)),
            'last_check' => 'Nie',
            'status' => 'pending',
            'wp_version' => '?',
            'updates' => ['core' => 0, 'plugins' => 0, 'themes' => 0]
        ];
        $sites[] = $newSite;
        return $this->save($sites) ? $newSite : false;
    }

    /**
     * Löscht eine Webseite
     */
    public function deleteSite($id) {
        $sites = $this->getAll();
        $filtered = array_filter($sites, fn($s) => $s['id'] !== $id);
        return $this->save($filtered);
    }

    /**
     * Ruft Live-Daten von einer WordPress-Seite ab (via Child Plugin)
     */
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

    /**
     * Prüft auf Updates im GitHub Repository
     */
    public function checkAppUpdate($beta = false) {
        $currentVersion = $this->config->getVersion();
        $token = $this->config->get('github_token');
        $repo = "olpo24/VantixDash";
        
        $url = $beta 
            ? "https://api.github.com/repos/$repo/releases" 
            : "https://api.github.com/repos/$repo/releases/latest";

        $ch = curl_init();
        $headers = [
            'User-Agent: VantixDash-Updater',
            'Accept: application/vnd.github.v3+json'
        ];
        if (!empty($token)) $headers[] = "Authorization: token $token";

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => $headers
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) return ['success' => false, 'message' => "GitHub Fehler $httpCode"];

        $data = json_decode($response, true);
        $release = ($beta && is_array($data)) ? $data[0] : $data;

        if (!isset($release['tag_name'])) return ['success' => false, 'message' => 'Kein Release gefunden'];

        $remoteVersion = str_replace('v', '', $release['tag_name']);
        
        return [
            'success' => true,
            'update_available' => version_compare($remoteVersion, $currentVersion, '>'),
            'current' => $currentVersion,
            'remote' => $remoteVersion,
            'is_beta' => ($release['prerelease'] ?? false),
            'download_url' => $release['zipball_url'] ?? '',
            'changelog' => $release['body'] ?? ''
        ];
    }

    /**
     * Lädt das Update von GitHub, entpackt es, verschiebt die Dateien 
     * und aktualisiert die Versionsdatei.
     */
    public function installUpdate($url) {
        $basePath = dirname(__DIR__) . '/';
        $tempFile = $basePath . 'data/update_temp.zip';
        $token = $this->config->get('github_token');

        // 1. DOWNLOAD
        $ch = curl_init($url);
        $fp = fopen($tempFile, 'w+');
        $headers = [
            'User-Agent: VantixDash-Updater',
            'Accept: application/vnd.github.v3+json'
        ];
        if (!empty($token)) $headers[] = "Authorization: token $token";

        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_FILE => $fp,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $success = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if (!$success || $httpCode !== 200) {
            if (file_exists($tempFile)) unlink($tempFile);
            return false;
        }

        // 2. ENTPACKEN
        $zip = new ZipArchive;
        if ($zip->open($tempFile) === TRUE) {
            $zip->extractTo($basePath);
            $zip->close();
            unlink($tempFile);

            // 3. GITHUB-ORDNER IDENTIFIZIEREN
            $githubDir = null;
            $allFiles = scandir($basePath);
            foreach ($allFiles as $f) {
                if (is_dir($basePath . $f) && strpos($f, 'olpo24-VantixDash-') === 0) {
                    $githubDir = $basePath . $f;
                    break;
                }
            }

            if ($githubDir) {
                // 4. DATEIEN VERSCHIEBEN
                $this->moveRecursive($githubDir, $basePath);
                
                // 5. CLEANUP
                $this->deleteDir($githubDir);
                
                // 6. VERSIONSDATEI SCHREIBEN
                preg_match('/zipball\/(v?[\d\.]+[^)]*)/', $url, $matches);
                $newVersion = isset($matches[1]) ? str_replace('v', '', $matches[1]) : 'unbekannt';
                $this->updateVersionFile($newVersion);

                if (function_exists('opcache_reset')) @opcache_reset();
                
                return true;
            }
        }
        return false;
    }

    /**
     * Hilfsmethode: Verschiebt Dateien rekursiv und schützt Nutzerkonfigurationen
     */
    private function moveRecursive($src, $dest) {
        $files = array_diff(scandir($src), ['.', '..']);
        foreach ($files as $file) {
            $currentSrc = $src . '/' . $file;
            $currentDest = $dest . '/' . $file;
            
            // Schutz vor Überschreiben der eigenen Config/Daten
            $protected = ['config.json', 'sites.json', '.htaccess', 'config.php', 'sites.php'];
            if (in_array($file, $protected)) continue;

            if (is_dir($currentSrc)) {
                if (!is_dir($currentDest)) mkdir($currentDest, 0755, true);
                $this->moveRecursive($currentSrc, $currentDest);
            } else {
                rename($currentSrc, $currentDest);
            }
        }
    }

    /**
     * Hilfsmethode: Löscht einen Ordner und dessen Inhalt rekursiv
     */
    private function deleteDir($dirPath) {
        if (!is_dir($dirPath)) return;
        $files = array_diff(scandir($dirPath), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dirPath/$file")) ? $this->deleteDir("$dirPath/$file") : unlink("$dirPath/$file");
        }
        return rmdir($dirPath);
    }

    /**
     * Hilfsmethode: Schreibt die neue version.php
     */
    private function updateVersionFile($newVersion) {
        $versionFile = dirname(__DIR__) . '/version.php';
        $content = "<?php\n\n";
        $content .= "// Automatisch generiert durch VantixDash Updater\n";
        $content .= "return [\n";
        $content .= "    'version' => '" . htmlspecialchars($newVersion) . "',\n";
        $content .= "    'last_update' => '" . date('d.m.Y H:i:s') . "',\n";
        $content .= "    'branch' => 'main'\n";
        $content .= "];\n";

        return file_put_contents($versionFile, $content);
    }
}
