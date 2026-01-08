<?php
/**
 * check_sites.php
 * Cronjob-Skript zum Abrufen der WordPress-Statusdaten.
 * Kann manuell oder per System-Cron aufgerufen werden.
 */

// Falls das Skript über den Browser aufgerufen wird, Sicherheit prüfen
if (php_sapi_name() !== 'cli') {
    session_start();
    if (!isset($_SESSION['authenticated'])) {
        die("Nicht autorisiert.");
    }
}

$sitesFile = __DIR__ . '/data/sites.json';

if (!file_exists($sitesFile)) {
    die("Keine sites.json gefunden.");
}

$sites = json_decode(file_get_contents($sitesFile), true);
if (!$sites) die("Keine Webseiten zum Prüfen vorhanden.");

echo "Starte Prüfung von " . count($sites) . " Seiten...\n";

foreach ($sites as &$site) {
    echo "Prüfe: " . $site['url'] . " ... ";

    $apiUrl = rtrim($site['url'], '/') . '/wp-json/vantixdash/v1/status';
    $apiKey = $site['api_key'];

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-Vantix-Secret: ' . $apiKey,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15); // 15 Sekunden Timeout
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    
    // SSL Prüfung für lokale Entwicklung/selbstsignierte Zertifikate ggf. deaktivieren
    // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 

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
                'core'    => $data['core'] ?? 0,
                'plugins' => $data['plugins'] ?? 0,
                'themes'  => $data['themes'] ?? 0
            ];
            $site['details'] = $data['details'] ?? ['core' => [], 'plugins' => [], 'themes' => []];
            $site['last_check'] = date('Y-m-d H:i:s');
            echo "ERFOLG\n";
        } else {
            $site['status'] = 'error';
            echo "FEHLER (Ungültige JSON Antwort)\n";
        }
    } else {
        $site['status'] = 'offline';
        echo "FEHLER (HTTP Code $httpCode)\n";
    }
}

// Ergebnisse speichern
file_put_contents($sitesFile, json_encode(array_values($sites), JSON_PRETTY_PRINT));
echo "Prüfung abgeschlossen und gespeichert.\n";
