<?php
/**
 * api.php - Zentraler AJAX-Endpunkt für VantixDash (PSR-4 Version)
 */
declare(strict_types=1);

session_start();

/**
 * 1. AUTOLOADER & NAMESPACES
 */
require_once __DIR__ . '/autoload.php';

use VantixDash\Logger;
use VantixDash\ConfigService;
use VantixDash\SiteService;
use VantixDash\MailService;
use VantixDash\RateLimiter;

/**
 * 2. HELPER FUNKTIONEN
 */
function jsonError(int $httpCode, string $message): never {
    http_response_code($httpCode);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $message, 'code' => $httpCode]);
    exit;
}

function jsonSuccess(array $data = [], string $message = ''): void {
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => true, 'message' => $message], $data));
    exit;
}

function getRequestHeader(string $name): string {
    $name = strtoupper(str_replace('-', '_', $name));
    return $_SERVER['HTTP_' . $name] ?? '';
}

/**
 * 3. INITIALISIERUNG
 */
$logger = new Logger();
$configService = new ConfigService();
$siteService = new SiteService(__DIR__ . '/data/sites.json', $configService, $logger);
$rateLimiter = new RateLimiter();

// Externe Libs (nicht PSR-4 konform)
require_once __DIR__ . '/libs/GoogleAuthenticator.php';
$ga = new \PHPGangsta_GoogleAuthenticator();

/**
 * 4. GLOBALER SCHUTZ
 */

// Rate Limiting
if (!$rateLimiter->checkLimit($_SERVER['REMOTE_ADDR'] . '_api', 60, 60)) {
    jsonError(429, 'Zu viele Anfragen. Bitte kurz warten.');
}

// Authentifizierung
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    jsonError(401, 'Nicht autorisiert');
}

// Session Timeout
$timeout = $configService->getTimeout('session');
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
    session_unset();
    session_destroy();
    jsonError(401, 'Session abgelaufen.');
}
$_SESSION['last_activity'] = time();

// CSRF-SCHUTZ bei POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = getRequestHeader('X-CSRF-TOKEN') ?: ($_POST['csrf_token'] ?? '');
    if (empty($token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        jsonError(403, 'CSRF-Token ungültig.');
    }
}

/**
 * 5. ROUTING MIT FEHLERBEHANDLUNG
 */
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'refresh_site':
            $id = $_GET['id'] ?? '';
            if (empty($id)) jsonError(400, 'ID fehlt');
            $updatedSite = $siteService->refreshSiteData($id);
            $updatedSite ? jsonSuccess(['data' => $updatedSite]) : jsonError(500, 'Check fehlgeschlagen.');
            break;

        case 'login_site':
            $id = $_GET['id'] ?? '';
            $sites = $siteService->getAll();
            $targetSite = null;
            foreach ($sites as $s) { if ($s['id'] === $id) { $targetSite = $s; break; } }

            if ($targetSite) {
                $apiUrl = rtrim($targetSite['url'], '/') . '/wp-json/vantixdash/v1/login';
                $options = ['http' => [
                    'header' => "X-Vantix-Secret: " . $targetSite['api_key'] . "\r\n",
                    'timeout' => 10
                ]];
                $response = @file_get_contents($apiUrl, false, stream_context_create($options));
                $data = json_decode((string)$response, true);
                isset($data['login_url']) ? jsonSuccess(['login_url' => $data['login_url']]) : jsonError(500, 'Login fehlgeschlagen.');
            } else {
                jsonError(404, 'Seite nicht gefunden.');
            }
            break;

        case 'add_site':
            $name = $_POST['name'] ?? '';
            $url = $_POST['url'] ?? '';
            
            if (empty($name) || empty($url)) {
                jsonError(400, 'Bitte Name und URL angeben.');
            }

            // Hier werden die neuen Exceptions aus SiteService gefangen
            $newSite = $siteService->addSite($name, $url);
            $newSite ? jsonSuccess(['site' => $newSite], 'Seite hinzugefügt.') : jsonError(500, 'Fehler beim Speichern.');
            break;

        case 'delete_site':
            $siteService->deleteSite($_POST['id'] ?? '') ? jsonSuccess([], 'Seite entfernt.') : jsonError(500, 'Löschen fehlgeschlagen.');
            break;

        case 'get_logs':
            // Nutzt jetzt die Struktur aus SiteService-Refactoring
            $logs = $logger->getEntries(); 
            jsonSuccess(['data' => $logs]);
            break;

        case 'clear_logs':
            if ($logger->clear()) {
                $logger->info("Logs geleert.");
                jsonSuccess([], 'Logs erfolgreich geleert.');
            } else {
                jsonError(500, 'Fehler beim Leeren der Logs.');
            }
            break;

        case 'update_profile':
            $configService->updateUser($_POST['username'] ?? '', $_POST['email'] ?? '') ? jsonSuccess([], 'Profil aktualisiert.') : jsonError(500, 'Fehler.');
            break;

        case 'setup_2fa':
            $secret = $ga->createSecret();
            $_SESSION['temp_2fa_secret'] = $secret;
            jsonSuccess(['qrCodeUrl' => $ga->getQRCodeGoogleUrl('VantixDash', $secret, 'VantixDash'), 'secret' => $secret]);
            break;

        case 'verify_2fa':
            $secret = $_SESSION['temp_2fa_secret'] ?? '';
            if ($ga->verifyCode($secret, $_POST['code'] ?? '', 2)) {
                $configService->update2FA(true, $secret);
                unset($_SESSION['temp_2fa_secret']);
                jsonSuccess([], '2FA erfolgreich aktiviert.');
            } else {
                jsonError(400, 'Der eingegebene Code ist falsch.');
            }
            break;

        case 'test_smtp':
            $mailService = new MailService($configService, $logger);
            $targetEmail = $_POST['email'] ?? '';
            
            if (empty($targetEmail) || !filter_var($targetEmail, FILTER_VALIDATE_EMAIL)) {
                jsonError(400, 'Ungültige Empfänger-E-Mail.');
            }

            if ($mailService->send($targetEmail, "VantixDash - SMTP Test", "<h1>Test erfolgreich!</h1>", "Test erfolgreich!")) {
                jsonSuccess([], 'Test-E-Mail versendet.');
            } else {
                jsonError(500, 'Versand fehlgeschlagen.');
            }
            break;

        default:
            jsonError(404, 'Unbekannte Aktion');
            break;
    }
} catch (Exception $e) {
    // Zentrales Abfangen aller Exceptions (z.B. URL-Validierungsfehler)
    jsonError(400, $e->getMessage());
}
