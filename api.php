<?php
/**
 * api.php - Zentraler AJAX-Endpunkt für VantixDash
 */
declare(strict_types=1);

session_start();

// 1. HELPER FUNKTIONEN (Müssen vor der Nutzung definiert sein)

/**
 * Sendet eine standardisierte Fehlerantwort und bricht ab.
 */
function jsonError(int $httpCode, string $message): never {
    http_response_code($httpCode);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $message,
        'code'    => $httpCode
    ]);
    exit;
}

/**
 * Sendet eine standardisierte Erfolgsantwort.
 */
function jsonSuccess(array $data = [], string $message = ''): void {
    header('Content-Type: application/json');
    echo json_encode(array_merge([
        'success' => true,
        'message' => $message
    ], $data));
    exit;
}

/**
 * Portabler Ersatz für getallheaders()
 */
function getRequestHeader(string $name): string {
    $name = strtoupper(str_replace('-', '_', $name));
    return $_SERVER['HTTP_' . $name] ?? '';
}

// 2. SESSION & TIMEOUT PRÜFUNG
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    // ConfigService wird hier noch nicht benötigt, wir nutzen einen statischen Fallback oder laden ihn gleich
    $timeout = 3600; 
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
        session_unset();
        session_destroy();
        jsonError(401, 'Session abgelaufen. Bitte neu einloggen.');
    }
    $_SESSION['last_activity'] = time();
}

// 3. SERVICES & LIBS LADEN
require_once __DIR__ . '/libs/GoogleAuthenticator.php';
require_once __DIR__ . '/services/Logger.php';
require_once __DIR__ . '/services/ConfigService.php';
require_once __DIR__ . '/services/SiteService.php';

$logger = new Logger(); // Nutzt Standard-Pfad data/app.log
$configService = new ConfigService();
$siteService = new SiteService(__DIR__ . '/data/sites.json', $configService, $logger);

$rateLimiter = new RateLimiter();
$ga = new PHPGangsta_GoogleAuthenticator();
$configService = new ConfigService();
$siteService = new SiteService(__DIR__ . '/data/sites.json', $configService);

// 4. GLOBALER SCHUTZ (Rate Limiting & Auth)

// 30 Anfragen pro Minute pro IP
if (!$rateLimiter->checkLimit($_SERVER['REMOTE_ADDR'], 30, 60)) {
    jsonError(429, 'Zu viele Anfragen. Bitte kurz warten.');
}

// Authentifizierung
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    jsonError(401, 'Nicht autorisiert');
}

$action = $_GET['action'] ?? '';

// 5. CSRF-SCHUTZ für schreibende Aktionen (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = getRequestHeader('X-CSRF-TOKEN') ?: ($_POST['csrf_token'] ?? '');
    if (empty($token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        jsonError(403, 'CSRF-Token ungültig oder abgelaufen.');
    }
}

// 6. ROUTING DER AKTIONEN
switch ($action) {

    case 'refresh_site':
        $id = $_GET['id'] ?? '';
        if (empty($id)) jsonError(400, 'ID fehlt');
        
        $updatedSite = $siteService->refreshSiteData($id);
        if ($updatedSite) {
            jsonSuccess(['data' => $updatedSite], 'Seite erfolgreich aktualisiert.');
        } else {
            jsonError(500, 'Verbindung zur WordPress-Seite fehlgeschlagen.');
        }
        break;

    case 'login_site':
        $id = $_GET['id'] ?? '';
        if (empty($id)) jsonError(400, 'ID fehlt');

        $sites = $siteService->getAll();
        $targetSite = null;
        foreach ($sites as $site) {
            if ($site['id'] === $id) {
                $targetSite = $site;
                break;
            }
        }

        if ($targetSite) {
            $apiUrl = rtrim($targetSite['url'], '/') . '/wp-json/vantixdash/v1/login';
            $safeApiKey = preg_replace('/[\r\n]/', '', (string)$targetSite['api_key']);
            
            $options = [
                'http' => [
                    'method' => 'GET',
                    'header' => "X-Vantix-Secret: " . $safeApiKey . "\r\n" .
                                "User-Agent: VantixDash-Monitor/1.0\r\n",
                    'timeout' => $configService->getTimeout('api'),
                    'ignore_errors' => true
                ]
            ];
            
            $context = stream_context_create($options);
            $response = @file_get_contents($apiUrl, false, $context);
            
            if ($response === false) {
                jsonError(502, 'Verbindung zum Child-Plugin fehlgeschlagen.');
            }

            $data = json_decode($response, true);
            if (isset($data['login_url'])) {
                jsonSuccess(['login_url' => $data['login_url']]);
            } else {
                jsonError(500, $data['message'] ?? 'Keine Login-URL erhalten.');
            }
        } else {
            jsonError(404, 'Seite nicht gefunden.');
        }
        break;

    case 'add_site':
        $name = trim($_POST['name'] ?? '');
        $url  = trim($_POST['url'] ?? '');

        if (strlen($name) < 2 || strlen($url) > 255) jsonError(400, 'Eingabedaten ungültig.');
        if (!filter_var($url, FILTER_VALIDATE_URL)) jsonError(400, 'Ungültiges URL-Format.');

        $newSite = $siteService->addSite($name, $url);
        $newSite ? jsonSuccess(['site' => $newSite], 'Seite hinzugefügt.') : jsonError(500, 'Fehler beim Speichern.');
        break;

    case 'delete_site':
        $id = $_POST['id'] ?? '';
        $siteService->deleteSite($id) ? jsonSuccess([], 'Gelöscht.') : jsonError(500, 'Löschen fehlgeschlagen.');
        break;

    case 'update_profile':
        $username = $_POST['username'] ?? '';
        $email = $_POST['email'] ?? '';
        $configService->updateUser($username, $email) ? jsonSuccess() : jsonError(500, 'Update fehlgeschlagen.');
        break;

    case 'update_password':
        $new_pw = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        
        if ($new_pw !== $confirm || strlen($new_pw) < 8) {
            jsonError(400, 'Passwörter ungültig oder zu kurz.');
        }
        
        $hash = password_hash($new_pw, PASSWORD_DEFAULT);
        $configService->updatePassword($hash) ? jsonSuccess([], 'Passwort geändert.') : jsonError(500, 'Fehler beim Speichern.');
        break;

    case 'setup_2fa':
        $secret = $ga->createSecret();
        $_SESSION['temp_2fa_secret'] = $secret;
        $safeUser = (string)($_SESSION['username'] ?? 'User');
        $qrCodeUrl = $ga->getQRCodeGoogleUrl('VantixDash', $secret, 'VantixDash (' . $safeUser . ')');
        
        jsonSuccess(['qrCodeUrl' => $qrCodeUrl, 'secret' => $secret]);
        break;

    case 'verify_2fa':
        $code = $_POST['code'] ?? '';
        $secret = $_SESSION['temp_2fa_secret'] ?? '';
        
        if ($ga->verifyCode($secret, $code, 2)) {
            $configService->update2FA(true, $secret);
            unset($_SESSION['temp_2fa_secret']);
            jsonSuccess([], '2FA aktiviert.');
        } else {
            jsonError(400, 'Ungültiger Code.');
        }
        break;

    case 'disable_2fa':
        $configService->update2FA(false, null);
        jsonSuccess([], '2FA deaktiviert.');
        break;

    default:
        jsonError(404, 'Unbekannte Aktion');
        break;
		case 'get_logs':
        $logFile = __DIR__ . '/data/app.log';
        if (!file_exists($logFile)) {
            jsonSuccess(['logs' => 'Keine Log-Daten vorhanden.']);
        }
        
        // Die letzten 50 Zeilen einlesen
        $lines = file($logFile);
        $lastLines = array_slice($lines, -50);
        $logContent = implode("", array_reverse($lastLines)); // Neueste zuerst
        
        jsonSuccess(['logs' => htmlspecialchars($logContent)]);
        break;

    case 'clear_logs':
        $logFile = __DIR__ . '/data/app.log';
        file_put_contents($logFile, "");
        jsonSuccess([], 'Logs gelöscht.');
        break;
}
