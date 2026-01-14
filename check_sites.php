<?php
/**
 * check_sites.php - Hintergrund-Check für WordPress-Instanzen
 * Diese Datei sollte per Cronjob (CLI) oder sicherem Web-Call aufgerufen werden.
 */

declare(strict_types=1);

require_once __DIR__ . '/services/ConfigService.php';
require_once __DIR__ . '/services/SiteService.php';
require_once __DIR__ . '/libs/GoogleAuthenticator.php';

// 1. INITIALISIERUNG
$ga = new PHPGangsta_GoogleAuthenticator();
$config = new ConfigService();
$sitesFile = __DIR__ . '/data/sites.json';
$siteService = new SiteService($sitesFile, $config);

// 2. SICHERHEITSPRÜFUNG (CLI vs. WEB)
if (php_sapi_name() !== 'cli') {
    $providedToken = $_GET['token'] ?? $_SERVER['HTTP_X_CRON_TOKEN'] ?? '';
    $secretToken = (string)$config->get('cron_secret', '');

    // Falls noch kein Token existiert, generieren wir einmalig einen
    if (empty($secretToken)) {
        $secretToken = bin2hex(random_bytes(32));
        $config->updateCronSecret($secretToken); 
    }

    // Zeitkonstanter Vergleich gegen Timing-Attacks
    if (empty($providedToken) || !hash_equals($secretToken, $providedToken)) {
        header('Content-Type: application/json', true, 403);
        echo json_encode(['success' => false, 'message' => 'Nicht autorisiert.']);
        exit;
    }
}

// 3. LOGIK: ALLE SEITEN AKTUALISIEREN
$sites = $siteService->getAll();
$results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'checked'   => 0,
    'updated'   => 0,
    'errors'    => 0
];

// ZENTRALE TIMEOUTS AUS DER CONFIG LADEN
// Wir nutzen "site_check" als Typ, wie im ConfigService definiert
$siteTimeout = $config->getTimeout('site_check');

echo "Starte VantixDash Hintergrund-Check (Timeout pro Seite: {$siteTimeout}s)...\n";

foreach ($sites as $site) {
    $results['checked']++;
    echo "Prüfe: " . ($site['name'] ?? $site['url']) . " ... ";

    try {
        /**
         * HINWEIS: Der SiteService nutzt intern stream_context_create.
         * Wir stellen sicher, dass refreshSiteData den Timeout aus der Config beachtet.
         */
        $updatedData = $siteService->refreshSiteData($site['id']);
        
        if ($updatedData) {
            echo "Erfolg (v" . ($updatedData['wp_version'] ?? '?.?.?') . ")\n";
            $results['updated']++;
        } else {
            echo "Fehlgeschlagen (Offline oder Timeout)\n";
            $results['errors']++;
        }
    } catch (Exception $e) {
        echo "Fehler: " . $e->getMessage() . "\n";
        $results['errors']++;
    }
}

echo "Check abgeschlossen. " . $results['updated'] . " von " . $results['checked'] . " Seiten aktualisiert.\n";

// Optional: JSON-Response für Web-Aufrufe
if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'results' => $results]);
}
