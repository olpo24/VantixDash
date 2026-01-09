<?php
/**
 * api.php
 * Backend-Schnittstelle für VantixDash
 * Erweitert um Full-Data-Refresh für Plugin- und Theme-Details.
 */
<?php
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Falls ein alter Session-Rest existiert, zerstören wir ihn zur Sicherheit
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}
header('Content-Type: application/json');

// CSRF-Schutz für alle POST-Anfragen
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Versuche das Token aus verschiedenen Quellen zu lesen
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''; // Standard für X-CSRF-Token Header
    if (empty($token)) {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $token = $headers['X-CSRF-Token'] ?? $headers['x-csrf-token'] ?? $_POST['csrf_token'] ?? '';
    }
    
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        echo json_encode([
            'success' => false, 
            'message' => 'CSRF-Token ungültig',
            'debug_received' => substr($token, 0, 5) . '...', // Nur zum Debuggen
            'debug_expected' => isset($_SESSION['csrf_token']) ? 'set' : 'not_set'
        ]);
        exit;
    }
}
// 1. PFADE UND KONFIGURATION
$baseDir = __DIR__;
$dataDir = $baseDir . '/data';
$versionFile = $baseDir . '/version.php';
$sitesFile = $dataDir . '/sites.json';

if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}

function getSites($file) {
    if (!file_exists($file)) return [];
    return json_decode(file_get_contents($file), true) ?: [];
}

function saveSites($file, $sites) {
    try {
        $jsonData = json_encode(array_values($sites), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        return file_put_contents($file, $jsonData);
    } catch (JsonException $e) {
        error_log("JSON Encoding Fehler: " . $e->getMessage());
        return false;
    }
}

$action = $_GET['action'] ?? '';

switch ($action) {

    case 'check_update':
        if (!file_exists($versionFile)) {
            echo json_encode(['success' => false, 'message' => 'version.php fehlt']);
            exit;
        }
        $local = include($versionFile);
        $localVersion = $local['version'] ?? '0.0.0';
        $useBeta = (isset($_GET['beta']) && ($_GET['beta'] === 'true' || $_GET['beta'] === '1'));
        
        $remoteVersion = '0.0.0';
        $downloadUrl = '';
        $modeName = $useBeta ? 'Beta' : 'Stable';

        try {
            $opts = ["http" => ["method" => "GET", "header" => "User-Agent: VantixDash-App\r\n"]];
            $context = stream_context_create($opts);

            if ($useBeta) {
                $apiUrl = "https://api.github.com/repos/olpo24/VantixDash/releases";
                $content = @file_get_contents($apiUrl, false, $context);
                $foundBetaAsset = false;

                if ($content) {
                    $releases = json_decode($content, true);
                    foreach ($releases as $release) {
                        if ($release['prerelease'] === true) {
                            $remoteVersion = str_replace('v', '', $release['tag_name'] ?? '0.0.0');
                            foreach ($release['assets'] as $asset) {
                                if (strpos($asset['name'], 'VantixDash_') === 0) {
                                    $downloadUrl = $asset['browser_download_url'];
                                    $foundBetaAsset = true;
                                    break;
                                }
                            }
                            break; 
                        }
                    }
                }
                if (!$foundBetaAsset) {
                    $apiUrl = "https://raw.githubusercontent.com/olpo24/VantixDash/beta/version.php";
                    $content = @file_get_contents($apiUrl, false, $context);
                    if ($content) {
                        preg_match("/'version' => '(.*)'/", $content, $matches);
                        $remoteVersion = $matches[1] ?? '0.0.0';
                        $downloadUrl = "https://github.com/olpo24/VantixDash/archive/refs/heads/beta.zip";
                    }
                }
            } else {
                $apiUrl = "https://api.github.com/repos/olpo24/VantixDash/releases/latest";
                $content = @file_get_contents($apiUrl, false, $context);
                if ($content) {
                    $release = json_decode($content, true);
                    $remoteVersion = str_replace('v', '', $release['tag_name'] ?? '0.0.0');
                    if (!empty($release['assets'])) {
                        foreach ($release['assets'] as $asset) {
                            if (strpos($asset['name'], 'VantixDash_') === 0) {
                                $downloadUrl = $asset['browser_download_url'];
                                break;
                            }
                        }
                    }
                    if (empty($downloadUrl)) $downloadUrl = $release['zipball_url'] ?? '';
                }
            }

            echo json_encode([
                'success' => true,
                'local' => $localVersion,
                'remote' => $remoteVersion,
                'update_available' => version_compare($remoteVersion, $localVersion, '>'),
                'download_url' => $downloadUrl,
                'mode' => $modeName
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'install_update':
        $downloadUrl = $_POST['url'] ?? '';
        if (empty($downloadUrl)) {
            echo json_encode(['success' => false, 'message' => 'Download-URL fehlt']);
            exit;
        }
        $tempZip = $dataDir . '/update_temp.zip';
        $extractPath = $dataDir . '/temp_extract/';
        $opts = ["http" => ["method" => "GET", "header" => "User-Agent: VantixDash-Updater\r\n"]];
        $context = stream_context_create($opts);
        $fileContent = @file_get_contents($downloadUrl, false, $context);
        
        if ($fileContent === false) {
            echo json_encode(['success' => false, 'message' => 'Download fehlgeschlagen']);
            exit;
        }
        file_put_contents($tempZip, $fileContent);

        $zip = new ZipArchive;
        if ($zip->open($tempZip) === TRUE) {
            if (!is_dir($extractPath)) mkdir($extractPath, 0755, true);
            $zip->extractTo($extractPath);
            $zip->close();

            $sourceRoot = $extractPath;
            $subDirs = array_filter(glob($extractPath . '*'), 'is_dir');
            if (count($subDirs) === 1 && empty(glob($extractPath . '*.php'))) {
                $sourceRoot = reset($subDirs) . '/';
            }

            $sourceRoot = rtrim($sourceRoot, '/') . '/';
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($sourceRoot, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($files as $file) {
                $relativePath = str_replace($sourceRoot, '', $file->getRealPath());
                $destPath = $baseDir . '/' . $relativePath;
                if ($file->isDir()) {
                    if (!is_dir($destPath)) mkdir($destPath, 0755, true);
                } else {
                    $filename = basename($destPath);
                    if ($filename !== 'config.php' && strpos($destPath, '/data/') === false) {
                        copy($file->getRealPath(), $destPath);
                    }
                }
            }
            if (file_exists($tempZip)) unlink($tempZip);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'ZIP-Fehler']);
        }
        break;

    case 'get_sites':
        echo json_encode(getSites($sitesFile));
        break;

    case 'add_site':
        $sites = getSites($sitesFile);
        
        // 1. Eingaben validieren & bereinigen
        $name = filter_var($_POST['name'] ?? '', FILTER_SANITIZE_SPECIAL_CHARS);
        $url = filter_var($_POST['url'] ?? '', FILTER_VALIDATE_URL);
        
        if (!$url) {
            echo json_encode(['success' => false, 'message' => 'Ungültige URL']);
            exit;
        }

        $newId = bin2hex(random_bytes(8));
        $apiKey = bin2hex(random_bytes(16));
        
        $newSite = [
            'id' => $newId,
            'name' => $name, // Bereinigter Name
            'url' => rtrim($url, '/'),
            'api_key' => $apiKey,
            'last_check' => null,
            'status' => 'pending',
            'updates' => ['core' => 0, 'plugins' => 0, 'themes' => 0],
            'plugin_list' => [],
            'theme_list' => []
        ];
        
        $sites[] = $newSite;
        if (saveSites($sitesFile, $sites)) {
            echo json_encode(['success' => true, 'api_key' => $apiKey]);
        } else {
            echo json_encode(['success' => false]);
        }
        break;

    case 'refresh_site':
        $sites = getSites($sitesFile);
        $id = $_POST['id'] ?? '';
        foreach ($sites as &$site) {
            if ($site['id'] === $id) {
                // EXTERNEN CHECK DURCHFÜHREN
                $apiUrl = $site['url'] . '/wp-json/vantixdash/v1/status';
                
                $ch = curl_init($apiUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'X-Vantix-Secret: ' . $site['api_key']
                ]);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($httpCode === 200 && $response) {
    $data = json_decode($response, true);
    
    // NUR explizit erlaubte Felder aktualisieren (Whitelist)
    $site['status'] = 'online';
    $site['last_check'] = date('Y-m-d H:i:s');
    $site['version'] = filter_var($data['version'] ?? '', FILTER_SANITIZE_STRING);
    $site['php'] = filter_var($data['php'] ?? '', FILTER_SANITIZE_STRING);
    $site['ip'] = filter_var($data['ip'] ?? '', FILTER_VALIDATE_IP) ?: '';
    
    // Updates validieren (nur Integer erlauben)
    $site['updates'] = [
        'core' => (int)($data['core'] ?? 0),
        'plugins' => (int)($data['plugins'] ?? 0),
        'themes' => (int)($data['themes'] ?? 0)
    ];
    
    // Listen übernehmen (Struktur prüfen)
    $site['plugin_list'] = is_array($data['plugin_list'] ?? null) ? $data['plugin_list'] : [];
    $site['theme_list'] = is_array($data['theme_list'] ?? null) ? $data['theme_list'] : [];
} else {
                    $site['status'] = 'offline';
                    $site['last_check'] = date('Y-m-d H:i:s');
                }

                saveSites($sitesFile, $sites);
                echo json_encode(['success' => true, 'site' => $site]);
                exit;
            }
        }
        echo json_encode(['success' => false]);
        break;

    case 'delete_site':
        $sites = getSites($sitesFile);
        $id = $_POST['id'] ?? '';
        $filtered = array_filter($sites, fn($s) => $s['id'] !== $id);
        if (saveSites($sitesFile, $filtered)) echo json_encode(['success' => true]);
        else echo json_encode(['success' => false]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
        break;
}
