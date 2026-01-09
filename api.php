<?php
/**
 * VantixDash - Zentrale API
 */
session_start();
header('Content-Type: application/json');

// 1. Sicherheit: Authentifizierung
if (!isset($_SESSION['authenticated'])) {
    echo json_encode(['success' => false, 'message' => 'Nicht eingeloggt']);
    exit;
}

$dataDir = __DIR__ . '/data';
$sitesFile = $dataDir . '/sites.json';
$versionFile = __DIR__ . '/version.php';

// 2. Verzeichnis & Datei sicherstellen
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}
if (!file_exists($sitesFile)) {
    file_put_contents($sitesFile, json_encode([]));
}

// Hilfsfunktionen
function loadSites($file) {
    return json_decode(file_get_contents($file), true) ?: [];
}

function saveSites($file, $data) {
    return file_put_contents($file, json_encode(array_values($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_sites':
        echo json_encode(loadSites($sitesFile));
        break;

    case 'add_site':
        $name = trim($_POST['name'] ?? '');
        $url  = rtrim(trim($_POST['url'] ?? ''), '/');
        
        if (!$name || !$url) {
            echo json_encode(['success' => false, 'message' => 'Eingaben unvollständig']);
            exit;
        }

        $sites = loadSites($sitesFile);
        $newKey = bin2hex(random_bytes(16));

        $sites[] = [
            'id' => uniqid(),
            'name' => $name,
            'url' => $url,
            'api_key' => $newKey,
            'status' => 'pending',
            'version' => '-',
            'updates' => ['core' => 0, 'plugins' => 0, 'themes' => 0]
        ];

        if (saveSites($sitesFile, $sites)) {
            echo json_encode(['success' => true, 'api_key' => $newKey]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Datei nicht schreibbar']);
        }
        break;

    case 'update_site':
        $id = $_POST['id'] ?? '';
        // Sicherheits-Validierung der ID
        if (!preg_match('/^[a-f0-9]+$/i', $id)) {
            echo json_encode(['success' => false, 'message' => 'Ungültiges ID Format']);
            exit;
        }

        $name = trim($_POST['name'] ?? '');
        $url = rtrim(trim($_POST['url'] ?? ''), '/');
        $renew = isset($_POST['renew_key']);

        $sites = loadSites($sitesFile);
        $updatedKey = null;
        $found = false;

        foreach ($sites as &$site) {
            if ($site['id'] === $id) {
                $site['name'] = $name;
                $site['url'] = $url;
                if ($renew) {
                    $updatedKey = bin2hex(random_bytes(16));
                    $site['api_key'] = $updatedKey;
                }
                $found = true;
                break;
            }
        }

        if ($found && saveSites($sitesFile, $sites)) {
            echo json_encode(['success' => true, 'api_key' => $updatedKey]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Update fehlgeschlagen']);
        }
        break;
        
    case 'delete_site':
        $id = $_POST['id'] ?? '';
        if (!preg_match('/^[a-f0-9]+$/i', $id)) { exit; }

        $sites = loadSites($sitesFile);
        $filtered = array_filter($sites, fn($s) => $s['id'] !== $id);
        
        if (saveSites($sitesFile, $filtered)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
        break;
        
    case 'refresh_site':
        $id = trim($_POST['id'] ?? '');
        if (!preg_match('/^[a-f0-9]+$/i', $id)) {
            echo json_encode(['success' => false, 'message' => 'Ungültige ID']);
            exit;
        }

        $sites = loadSites($sitesFile);
        $foundIndex = -1;
        foreach ($sites as $index => $site) {
            if ($site['id'] == $id) {
                $foundIndex = $index;
                break;
            }
        }

        if ($foundIndex === -1) {
            echo json_encode(['success' => false, 'message' => 'Seite nicht gefunden']);
            exit;
        }

        $site = &$sites[$foundIndex];
        $apiUrl = rtrim($site['url'], '/') . '/wp-json/vantixdash/v1/status';
        
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-Vantix-Secret: ' . $site['api_key'],
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            if ($data) {
                $site['status'] = 'online';
                $site['version'] = $data['version'] ?? '-';
                $site['php'] = $data['php'] ?? '-';
                $site['updates'] = [
                    'core' => $data['core'] ?? 0,
                    'plugins' => $data['plugins'] ?? 0,
                    'themes' => $data['themes'] ?? 0
                ];
                $site['details'] = $data['details'] ?? [];
                $site['last_check'] = date('Y-m-d H:i:s');
                
                saveSites($sitesFile, $sites);
                echo json_encode(['success' => true, 'site' => $site]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Ungültige Antwort']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Fehler: HTTP ' . $httpCode]);
        }
        break;

    case 'check_update':
        $local = include($versionFile);
        $useBeta = isset($_GET['beta']) && $_GET['beta'] === 'true';
        
        if ($useBeta) {
            // BETA: Raw-Datei vom beta Branch (Cache-Busting inkludiert)
            $remoteUrl = "https://raw.githubusercontent.com/olpo24/VantixDash/beta/version.php?t=" . time();
            $remoteContent = @file_get_contents($remoteUrl);
            preg_match("/'version' => '(.+?)'/", $remoteContent, $matches);
            $remoteVersion = $matches[1] ?? '0.0.0';
            $downloadUrl = "https://github.com/olpo24/VantixDash/archive/refs/heads/beta.zip";
        } else {
            // STABLE: GitHub Releases API
            $opts = ["http" => ["header" => "User-Agent: VantixDash-Updater\r\n"]];
            $context = stream_context_create($opts);
            $apiUrl = "https://api.github.com/repos/olpo24/VantixDash/releases/latest";
            $apiRes = @file_get_contents($apiUrl, false, $context);
            $release = json_decode($apiRes, true);
            
            $remoteVersion = isset($release['tag_name']) ? str_replace('v', '', $release['tag_name']) : '0.0.0';
            $downloadUrl = $release['zipball_url'] ?? '';
        }

        echo json_encode([
            'success' => true,
            'local' => $local['version'],
            'remote' => $remoteVersion,
            'update_available' => version_compare($remoteVersion, $local['version'], '>'),
            'download_url' => $downloadUrl,
            'mode' => $useBeta ? 'Beta' : 'Stable'
        ]);
        break;

    case 'install_update':
        $downloadUrl = $_POST['url'] ?? '';
        if (empty($downloadUrl)) {
            echo json_encode(['success' => false, 'message' => 'Keine URL']);
            exit;
        }

        $tempZip = $dataDir . '/update_temp.zip';
        $extractPath = $dataDir . '/temp_extract/';

        if (!copy($downloadUrl, $tempZip)) {
            echo json_encode(['success' => false, 'message' => 'Download fehlgeschlagen']);
            exit;
        }

        $zip = new ZipArchive;
        if ($zip->open($tempZip) === TRUE) {
            if (!is_dir($extractPath)) mkdir($extractPath, 0755, true);
            $zip->extractTo($extractPath);
            $zip->close();

            // Root-Ordner im ZIP finden (GitHub Struktur)
            $subDirs = array_filter(glob($extractPath . '*'), 'is_dir');
            $sourceRoot = reset($subDirs);

            if ($sourceRoot) {
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($sourceRoot, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::SELF_FIRST
                );

                foreach ($files as $file) {
                    $relativePath = str_replace($sourceRoot, '', $file->getRealPath());
                    $destPath = __DIR__ . $relativePath;

                    if ($file->isDir()) {
                        if (!is_dir($destPath)) mkdir($destPath, 0755, true);
                    } else {
                        // Schutz von config.php und data Ordner
                        if (basename($destPath) !== 'config.php' && strpos($destPath, '/data/') === false) {
                            copy($file->getRealPath(), $destPath);
                        }
                    }
                }
            }

            // Aufräumen
            unlink($tempZip);
            // Hilfsfunktion zum rekursiven Löschen des Temp-Ordners könnte hier folgen
            
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'ZIP konnte nicht entpackt werden']);
        }
        break;
}
