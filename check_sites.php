<?php
/**
 * check_sites.php - Hintergrund-Check für WordPress-Instanzen
 * Diese Datei sollte per Cronjob (CLI) oder sicherem Web-Call aufgerufen werden.
 */

declare(strict_types=1);

require_once __DIR__ . '/autoload.php';

use VantixDash\Config\ConfigService;
use VantixDash\Config\ConfigRepository;
use VantixDash\Config\SettingsService;
use VantixDash\SiteService;
use VantixDash\Logger;

// 1. INITIALISIERUNG
$logger = new Logger();
$repository = new ConfigRepository();
$configService = new ConfigService($repository);
$settingsService = new SettingsService($configService);

$sitesFile = __DIR__ . '/data/sites.json';
$siteService = new SiteService($sitesFile, $configService, $logger);

// 2. SICHERHEITSPRÜFUNG (CLI vs. WEB)
if (php_sapi_name() !== 'cli') {
    $providedToken = $_GET['token'] ?? $_SERVER['HTTP_X_CRON_TOKEN'] ?? '';
    $secretToken = $settingsService->getCronSecret();

    // Falls noch kein Token existiert, generieren wir einmalig einen
    if (empty($secretToken)) {
        $secretToken = $settingsService->generateCronSecret();
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

$siteTimeout = $settingsService->getTimeout('site_check');

echo "Starte VantixDash Hintergrund-Check (Timeout pro Seite: {$siteTimeout}s)...\n";

foreach ($sites as $site) {
    $results['checked']++;
    echo "Prüfe: " . ($site['name'] ?? $site['url']) . " ... ";

    try {
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
