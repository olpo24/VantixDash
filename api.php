<?php
session_start();
header('Content-Type: application/json');

// 1. Sicherheit
if (!isset($_SESSION['authenticated'])) {
    echo json_encode(['success' => false, 'message' => 'Nicht eingeloggt']);
    exit;
}

$dataDir = __DIR__ . '/data';
$sitesFile = $dataDir . '/sites.json';

// 2. Verzeichnis & Datei sicherstellen
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}
if (!file_exists($sitesFile)) {
    file_put_contents($sitesFile, json_encode([]));
}

function loadSites($file) {
    return json_decode(file_get_contents($file), true) ?: [];
}

function saveSites($file, $data) {
    // JSON_INVALID_UTF8_SUBSTITUTE hilft bei Encoding-Problemen
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
        $sites = loadSites($sitesFile);
        $filtered = array_filter($sites, fn($s) => $s['id'] !== $id);
        
        if (saveSites($sitesFile, $filtered)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
        break;
		
		case 'refresh_site':
        // 1. ID sicher abgreifen
        $id = isset($_POST['id']) ? trim($_POST['id']) : '';
        if (empty($id)) {
            $jsonInput = json_decode(file_get_contents('php://input'), true);
            $id = isset($jsonInput['id']) ? trim($jsonInput['id']) : '';
        }

        // 2. Sites laden
        if (!file_exists($sitesFile)) {
            echo json_encode(['success' => false, 'message' => 'sites.json nicht gefunden']);
            exit;
        }
        
        $sites = json_decode(file_get_contents($sitesFile), true);
        if (!$sites) {
            echo json_encode(['success' => false, 'message' => 'sites.json leer oder korrupt']);
            exit;
        }

        $foundIndex = -1;
        foreach ($sites as $index => $site) {
            // Wir nutzen hier den losen Vergleich == statt === falls Typ-Unterschiede vorliegen
            if (isset($site['id']) && trim($site['id']) == $id) {
                $foundIndex = $index;
                break;
            }
        }

        if ($foundIndex === -1) {
            // HIER IST DER WICHTIGE DEBUG-OUTPUT:
            echo json_encode([
                'success' => false, 
                'message' => "ID '$id' nicht in sites.json gefunden. Vorhandene IDs: " . implode(', ', array_column($sites, 'id'))
            ]);
            exit;
        }

        // Ab hier folgt die CURL-Logik (wie gehabt)...
        $site = &$sites[$foundIndex];
        // ... (dein restlicher Curl Code)

        // Logik aus check_sites.php für diese eine Seite ausführen
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
                echo json_encode(['success' => false, 'message' => 'Ungültige Antwort von WP']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Verbindungsfehler (HTTP '.$httpCode.')']);
        }
        break;
	case 'check_update':
    $local = include('version.php');
    $remoteContent = @file_get_contents($local['remote_url']);
    
    if (!$remoteContent) {
        echo json_encode(['success' => false, 'message' => 'GitHub nicht erreichbar']);
        exit;
    }

    // Wir extrahieren die Version aus dem Remote-PHP-Code per Regex
    preg_match("/'version' => '(.+?)'/", $remoteContent, $matches);
    $remoteVersion = $matches[1] ?? '0.0.0';

    echo json_encode([
        'success' => true,
        'local' => $local['version'],
        'remote' => $remoteVersion,
        'update_available' => version_compare($remoteVersion, $local['version'], '>')
    ]);
    break;

case 'install_update':
    // 1. ZIP von GitHub laden (Die automatische ZIP-Funktion von GitHub)
    $repoZip = "https://github.com/olpo24/VantixDash/archive/refs/heads/main.zip";
    $tempZip = __DIR__ . '/data/update_temp.zip';
    
    if (!copy($repoZip, $tempZip)) {
        echo json_encode(['success' => false, 'message' => 'Download fehlgeschlagen']);
        exit;
    }

    $zip = new ZipArchive;
    if ($zip->open($tempZip) === TRUE) {
        // Entpacken in einen Unterordner
        $extractPath = __DIR__ . '/data/temp_extract/';
        $zip->extractTo($extractPath);
        $zip->close();

        // GitHub packt alles in VantixDash-main/, wir müssen die Dateien eine Ebene hochschieben
        $source = $extractPath . 'VantixDash-main/';
        
        // Dateien kopieren (außer config.php und data/)
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        
        foreach ($files as $file) {
            $destPath = __DIR__ . '/' . $files->getSubPathName();
            if ($file->isDir()) {
                if (!is_dir($destPath)) mkdir($destPath);
            } else {
                // Sicherheitscheck: Überschreibe niemals deine config.php oder den data-Ordner!
                if (basename($destPath) !== 'config.php' && strpos($destPath, '/data/') === false) {
                    copy($file, $destPath);
                }
            }
        }

        // Aufräumen
        unlink($tempZip);
        // (Ordner löschen Funktion hier optional)
        
        echo json_encode(['success' => true, 'message' => 'Update erfolgreich installiert!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'ZIP konnte nicht geöffnet werden']);
    }
    break;
}
