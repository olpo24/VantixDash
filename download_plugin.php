<?php
/**
 * VantixDash - Sicherer Plugin Generator (v1.6.2)
 */

$originInput = $_GET['origin'] ?? '';

// Strikte Validierung (wie zuvor)
if (empty($originInput) || !filter_var($originInput, FILTER_VALIDATE_URL)) {
    header("HTTP/1.1 403 Forbidden");
    die("Ungültiger Origin.");
}

// Protokoll-Check & Bereinigung
$origin = esc_url_raw(rtrim($originInput, '/')); 

$pluginName = "vantixdash-child";
$zipName = "vantixdash-child.zip";

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zipName . '"');

$zip = new ZipArchive();
$tempFile = tmpfile();
$tempFilePath = stream_get_meta_data($tempFile)['uri'];

if ($zip->open($tempFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
    
    // Wir nutzen hier eine saubere Verkettung für den Header-Teil
    $pluginCode = <<<EOD
<?php
/**
 * Plugin Name: VantixDash Child
 * Description: Sicherer Connector für dein VantixDash Monitoring.
 * Version: 1.6.2
 * Author: VantixDash
 */

if (!defined('ABSPATH')) exit;

/**
 * 1. CORS-Sicherheit mit hartcodierter Dashboard-URL
 */
add_action('rest_api_init', function() {
    // Wir entfernen die Standard-Header von WP
    remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
    
    add_filter('rest_pre_serve_request', function(\$value) {
        // Hier wird die Dashboard-URL sicher als String eingefügt
        header('Access-Control-Allow-Origin: ' . '$origin'); 
        header('Access-Control-Allow-Methods: GET');
        header('Access-Control-Allow-Headers: X-Vantix-Secret, Content-Type');
        header('Access-Control-Allow-Credentials: true');
        header('Vary: Origin');
        return \$value;
    });
}, 15);

/**
 * 2. Einstellungsseite & Restliche Logik...
 */
EOD;

    // Hier folgt der restliche Code (Admin-Menü, API-Routes etc. wie in der vorigen Version)
    // Achte darauf, dass alle Variablen im Plugin-Code mit \$ maskiert sind!
    
    // ... (Code gekürzt für Übersichtlichkeit, Rest bleibt wie gehabt)

    $zip->addFromString($pluginName . '/' . $pluginName . '.php', $pluginCode);
    $zip->close();
    readfile($tempFilePath);
}
exit;
