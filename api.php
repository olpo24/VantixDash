<?php
/**
 * api.php - Zentraler AJAX-Endpunkt für VantixDash
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

// Rate Limiting (Schutz vor Brute-Force/Spam)
if (!$rateLimiter->checkLimit($_SERVER['REMOTE_ADDR'] . '_api', 100, 60)) {
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
            try {
                $id = $_GET['id'] ?? '';
                if (empty($id)) {
                    jsonError(400, 'ID fehlt');
                }

                // Der Service wirft jetzt bei Problemen eine SiteRefreshException
                $updatedSite = $siteService->refreshSiteData($id);
                
                jsonSuccess([
                    'data' => $updatedSite
                ], 'Seite erfolgreich aktualisiert.');

            } catch (\VantixDash\Exception\SiteRefreshException $e) {
                // Dies sind "erwartete" Fehler (z.B. WP-Seite offline oder API-Key falsch)
                // Wir loggen es als Info/Warnung und geben die Nachricht ans Frontend
                $logger->info("Refresh-Warnung für ID $id: " . $e->getMessage());
                jsonError(422, $e->getMessage()); 

            } catch (Exception $e) {
                // Dies sind kritische Systemfehler (z.B. Dateisystem-Fehler)
                $logger->error("Kritischer Fehler bei Refresh: " . $e->getMessage());
                jsonError(500, 'Ein interner Systemfehler ist aufgetreten.');
            }
            break;

        case 'login_site':
            $id = $_GET['id'] ?? '';
            // Schnelle Suche via getAll() - in Zukunft könnte man hier getSiteById() ergänzen
            $targetSite = null;
            foreach ($siteService->getAll() as $s) {
                if ($s['id'] === $id) {
                    $targetSite = $s;
                    break;
                }
            }

            if ($targetSite) {
                $apiUrl = rtrim($targetSite['url'], '/') . '/wp-json/vantixdash/v1/login';
                $options = ['http' => [
                    'header' => "X-Vantix-Secret: " . $targetSite['api_key'] . "\r\n",
                    'timeout' => 10,
                    'ignore_errors' => true
                ]];
                
                $response = @file_get_contents($apiUrl, false, stream_context_create($options));
                $data = json_decode((string)$response, true);
                
                if (isset($data['login_url'])) {
                    jsonSuccess(['login_url' => $data['login_url']]);
                } else {
                    $msg = $data['message'] ?? 'Login fehlgeschlagen.';
                    jsonError(500, $msg);
                }
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

            // SiteService wirft Exceptions bei Validierungsfehlern (URL/Key)
            $newSite = $siteService->addSite($name, $url);
            if ($newSite) {
                $logger->info("Neue Seite hinzugefügt: $name");
                jsonSuccess(['site' => $newSite], 'Seite erfolgreich hinzugefügt.');
            } else {
                jsonError(500, 'Fehler beim Speichern der Konfiguration.');
            }
            break;

        case 'delete_site':
            $id = $_POST['id'] ?? '';
            if ($siteService->deleteSite($id)) {
                $logger->info("Seite gelöscht (ID: $id)");
                jsonSuccess([], 'Seite erfolgreich entfernt.');
            } else {
                jsonError(500, 'Löschen fehlgeschlagen oder Seite existiert nicht.');
            }
            break;

        case 'get_logs':
            $logs = $logger->getEntries(); 
            jsonSuccess(['data' => $logs]);
            break;

        case 'clear_logs':
            if ($logger->clear()) {
                $logger->info("Log-Historie wurde geleert.");
                jsonSuccess([], 'Logs erfolgreich geleert.');
            } else {
                jsonError(500, 'Fehler beim Leeren der Logs.');
            }
            break;

        case 'update_profile':
            $username = $_POST['username'] ?? '';
            $email = $_POST['email'] ?? '';
            
            if ($configService->updateUser($username, $email)) {
                jsonSuccess([], 'Profil erfolgreich aktualisiert.');
            } else {
                jsonError(500, 'Speichern des Profils fehlgeschlagen.');
            }
            break;

        case 'setup_2fa':
            $secret = $ga->createSecret();
            $_SESSION['temp_2fa_secret'] = $secret;
            $qrCodeUrl = $ga->getQRCodeGoogleUrl('VantixDash', $secret, 'VantixDash-Monitor');
            jsonSuccess(['qrCodeUrl' => $qrCodeUrl, 'secret' => $secret]);
            break;

        case 'verify_2fa':
            $secret = $_SESSION['temp_2fa_secret'] ?? '';
            $code = $_POST['code'] ?? '';
            
            if (empty($secret)) {
                jsonError(400, 'Keine aktive 2FA-Sitzung gefunden.');
            }
            
            if ($ga->verifyCode($secret, $code, 2)) {
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
                jsonError(400, 'Bitte eine gültige Empfänger-E-Mail angeben.');
            }

            $subject = "VantixDash - SMTP Test";
            $body = "<h1>SMTP Test erfolgreich!</h1><p>Diese E-Mail bestätigt, dass deine SMTP-Konfiguration korrekt funktioniert.</p>";
            
            if ($mailService->send($targetEmail, $subject, $body, strip_tags($body))) {
                jsonSuccess([], 'Test-E-Mail wurde erfolgreich versendet.');
            } else {
                jsonError(500, 'Versand fehlgeschlagen. Bitte Logs prüfen.');
            }
            break;

        default:
            jsonError(404, 'Die angeforderte API-Aktion ist unbekannt.');
            break;
    }
} catch (Exception $e) {
    // Fängt alle Exceptions (z.B. aus SiteService->addSite() oder Netzwerkfehler)
    $logger->error("API Exception: " . $e->getMessage());
    jsonError(400, $e->getMessage());
}
