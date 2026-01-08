<?php
/**
 * VantixDash - Backend API (data.php)
 */
header('Content-Type: application/json');
$jsonFile = __DIR__ . '/data/sites.json';

if (!file_exists(dirname($jsonFile))) mkdir(dirname($jsonFile), 0777, true);
if (!file_exists($jsonFile)) file_put_contents($jsonFile, json_encode([]));

$method = $_SERVER['REQUEST_METHOD'];

function getLiveStatus($site) {
    $apiUrl = rtrim($site['url'], '/') . '/wp-json/vantixdash/v1/status';
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-Vantix-Secret: ' . ($site['secret'] ?? '')]);
    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $res) {
        $data = json_decode($res, true);
        return array_merge($site, [
            'status' => 'online',
            'core' => $data['core'] ?? 0,
            'plugins' => $data['plugins'] ?? 0,
            'themes' => $data['themes'] ?? 0,
            'version' => $data['version'] ?? '?',
            'php' => $data['php'] ?? '?',
            'update_details' => $data['details'] ?? [],
            'last_check' => date('d.m.Y H:i')
        ]);
    }
    return array_merge($site, ['status' => 'offline', 'last_check' => date('d.m.Y H:i')]);
}

if ($method === 'GET') {
    $data = json_decode(file_get_contents($jsonFile), true) ?: [];
    if (isset($_GET['refresh'])) {
        foreach ($data as &$site) { $site = getLiveStatus($site); }
        file_put_contents($jsonFile, json_encode($data, JSON_PRETTY_PRINT));
    }
    echo json_encode($data);
    exit;
}

if ($method === 'POST') {
    $in = json_decode(file_get_contents('php://input'), true);
    $data = json_decode(file_get_contents($jsonFile), true) ?: [];
    $action = $in['action'] ?? '';

    if ($action === 'update') {
        foreach ($data as &$s) {
            if ($s['url'] === $in['oldUrl']) {
                $s['name'] = $in['name']; $s['url'] = rtrim($in['url'], '/');
                if ($in['regenerateKey']) $s['secret'] = bin2hex(random_bytes(16));
                $newKey = $in['regenerateKey'] ? $s['secret'] : null;
                break;
            }
        }
        file_put_contents($jsonFile, json_encode(array_values($data), JSON_PRETTY_PRINT));
        echo json_encode(['success' => true, 'newKey' => $newKey]);
        exit;
    }
    // ... Löschen / Hinzufügen analog ...
}
