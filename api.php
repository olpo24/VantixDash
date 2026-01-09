<?php
/**
 * api.php
 * Backend-Schnittstelle für VantixDash
 */

error_reporting(E_ALL);
ini_set('display_errors', 0); 

header('Content-Type: application/json');

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
    return file_put_contents($file, json_encode(array_values($sites), JSON_PRETTY_PRINT));
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
                // BETA-LOGIK: Suche in allen Releases nach dem neuesten "Pre-release"
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

                // FALLBACK für Beta: Falls kein Pre-Release gefunden wurde, nimm den Branch
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
                // STABLE-LOGIK: Nur neuester offizieller Release
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

            // Automatische Erkennung der Struktur (Flat vs. GitHub-Subfolder)
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
                    // config.php und data-Ordner schützen
                    if ($filename !== 'config.php' && strpos($destPath, '/data/') === false) {
                        copy($file->getRealPath(), $destPath);
                    }
                }
            }

            // Cleanup
            if (file_exists($tempZip)) unlink($tempZip);
            if (is_dir($extractPath)) {
                $it = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($extractPath, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach($it as $file) {
                    $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
                }
                rmdir($extractPath);
            }
            
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
        $newId = bin2hex(random_bytes(8));
        $apiKey = bin2hex(random_bytes(16));
        $newSite = [
            'id' => $newId,
            'name' => $_POST['name'] ?? 'Unbenannt',
            'url' => rtrim($_POST['url'] ?? '', '/'),
            'api_key' => $apiKey,
            'last_check' => null,
            'status' => 'pending'
        ];
        $sites[] = $newSite;
        if (saveSites($sitesFile, $sites)) echo json_encode(['success' => true, 'api_key' => $apiKey]);
        else echo json_encode(['success' => false]);
        break;

    case 'update_site':
        $sites = getSites($sitesFile);
        $id = $_POST['id'] ?? '';
        $found = false;
        foreach ($sites as &$site) {
            if ($site['id'] === $id) {
                $site['name'] = $_POST['name'] ?? $site['name'];
                $site['url'] = rtrim($_POST['url'] ?? $site['url'], '/');
                if (isset($_POST['regen_key']) && $_POST['regen_key'] === 'true') {
                    $site['api_key'] = bin2hex(random_bytes(16));
                    $found = $site['api_key'];
                } else { $found = true; }
                break;
            }
        }
        if ($found) {
            saveSites($sitesFile, $sites);
            echo json_encode(['success' => true, 'api_key' => ($found === true ? null : $found)]);
        } else echo json_encode(['success' => false]);
        break;

    case 'delete_site':
        $sites = getSites($sitesFile);
        $id = $_POST['id'] ?? '';
        $filtered = array_filter($sites, fn($s) => $s['id'] !== $id);
        if (saveSites($sitesFile, $filtered)) echo json_encode(['success' => true]);
        else echo json_encode(['success' => false]);
        break;

    case 'refresh_site':
        $sites = getSites($sitesFile);
        $id = $_POST['id'] ?? '';
        foreach ($sites as &$site) {
            if ($site['id'] === $id) {
                $site['last_check'] = date('Y-m-d H:i:s');
                $site['status'] = 'online';
                saveSites($sitesFile, $sites);
                echo json_encode(['success' => true, 'site' => $site]);
                exit;
            }
        }
        echo json_encode(['success' => false]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
        break;
}
