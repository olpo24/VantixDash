<?php
/**
 * api.php - Zentraler AJAX-Endpunkt für VantixDash
 */
declare(strict_types=1);
session_start();
// Nach session_start()
$timeout = 3600; // 1 Stunde in Sekunden

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
        // Session ist abgelaufen
        session_unset();     // Variablen löschen
        session_destroy();   // Session zerstören
        
        // Bei API-Requests senden wir ein JSON, bei Seitenaufrufen einen Redirect
        if (str_contains($_SERVER['PHP_SELF'], 'api.php')) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Session abgelaufen. Bitte neu einloggen.']);
            exit;
        } else {
            header('Location: login.php?timeout=1');
            exit;
        }
    }
    // Aktivität aktualisieren
    $_SESSION['last_activity'] = time();
}
header('Content-Type: application/json');

// 1. SERVICES & LIBS
require_once __DIR__ . '/libs/GoogleAuthenticator.php';
require_once __DIR__ . '/services/ConfigService.php';
require_once __DIR__ . '/services/SiteService.php';
require_once __DIR__ . '/services/RateLimiter.php';

/**
 * Portabler Ersatz für getallheaders()
 */
function getRequestHeader(string $name): string {
    $name = strtoupper(str_replace('-', '_', $name));
    return $_SERVER['HTTP_' . $name] ?? '';
}

// 2. INITIALISIERUNG
$rateLimiter = new RateLimiter();
$ga = new PHPGangsta_GoogleAuthenticator();
$configService = new ConfigService($ga);
$siteService = new SiteService(__DIR__ . '/data/sites.json', $configService);

// 3. RATE LIMITING (Globaler Schutz vor API-Abuse)
// 30 Anfragen pro Minute pro IP
if (!$rateLimiter->checkLimit($_SERVER['REMOTE_ADDR'], 30, 60)) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Zu viele Anfragen. Bitte kurz warten.']);
    exit;
}

// 4. AUTHENTIFIZIERUNGSPRÜFUNG
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Nicht autorisiert']);
    exit;
}

$action = $_GET['action'] ?? '';

// 5. CSRF-SCHUTZ für schreibende Aktionen (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = getRequestHeader('X-CSRF-TOKEN') ?: ($_POST['csrf_token'] ?? '');
    
    if (empty($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        echo json_encode(['success' => false, 'message' => 'CSRF-Token ungültig']);
        exit;
    }
}

// 6. ROUTING DER AKTIONEN
switch ($action) {

    case 'check_update':
        $beta = isset($_GET['beta']) && $_GET['beta'] === 'true';
        $result = $siteService->checkAppUpdate($beta);
        echo json_encode($result);
        break;

    case 'install_update':
        $url = $_POST['url'] ?? '';
        if (empty($url)) {
            echo json_encode(['success' => false, 'message' => 'Keine URL angegeben']);
            break;
        }
        $success = $siteService->installUpdate($url);
        echo json_encode([
            'success' => $success, 
            'message' => $success ? 'Update installiert' : 'Installation fehlgeschlagen'
        ]);
        break;

    case 'refresh_site':
        $id = $_GET['id'] ?? '';
        if (empty($id)) {
            echo json_encode(['success' => false, 'message' => 'ID fehlt']);
            break;
        }
        $updatedSite = $siteService->refreshSiteData($id);
        if ($updatedSite) {
            echo json_encode(['success' => true, 'data' => $updatedSite]);
        } else {
            echo json_encode([
                'success' => false, 
                'message' => 'Verbindung zur WordPress-Seite fehlgeschlagen. Prüfe API-Key und Plugin-Status.'
            ]);
        }
        break;

    case 'login_site':
        $id = $_GET['id'] ?? '';
        if (empty($id)) {
            echo json_encode(['success' => false, 'message' => 'ID fehlt']);
            break;
        }

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
            
            // FIX: Header-Injection verhindern (API-Key bereinigen)
            $safeApiKey = preg_replace('/[\r\n]/', '', (string)$targetSite['api_key']);
            
            $options = [
                'http' => [
                    'method' => 'GET',
                    'header' => "X-Vantix-Secret: " . $safeApiKey . "\r\n" .
                                "User-Agent: VantixDash-Monitor/1.0\r\n",
                    'timeout' => 10,
                    'ignore_errors' => true
                ]
            ];
            
            $context = stream_context_create($options);

            try {
                $response = file_get_contents($apiUrl, false, $context);
                
                if ($response === false) {
                    throw new Exception('Verbindung zum Child-Plugin fehlgeschlagen (Netzwerkfehler).');
                }

                $data = json_decode($response, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('Ungültige Antwort vom Child-Plugin (JSON Fehler).');
                }

                if (isset($data['login_url'])) {
                    echo json_encode(['success' => true, 'login_url' => $data['login_url']]);
                } else {
                    $errorMsg = $data['message'] ?? 'Keine Login-URL vom Child-Plugin erhalten.';
                    echo json_encode(['success' => false, 'message' => htmlspecialchars((string)$errorMsg)]);
                }

            } catch (Exception $e) {
                echo json_encode([
                    'success' => false, 
                    'message' => 'Fehler: ' . htmlspecialchars($e->getMessage())
                ]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Seite nicht gefunden.']);
        }
        break;

    case 'add_site':
        $name = trim($_POST['name'] ?? '');
        $url  = trim($_POST['url'] ?? '');

        if (strlen($name) < 2 || strlen($name) > 100 || strlen($url) > 255) {
            echo json_encode(['success' => false, 'message' => 'Name (2-100 Zeichen) oder URL (max 255) ungültig']);
            break;
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            echo json_encode(['success' => false, 'message' => 'Ungültiges URL-Format']);
            break;
        }

        $parsedUrl = parse_url($url);
        $host = $parsedUrl['host'] ?? '';

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                echo json_encode(['success' => false, 'message' => 'Private IP-Adressen sind aus Sicherheitsgründen nicht erlaubt']);
                break;
            }
        }

        if (!in_array($parsedUrl['scheme'] ?? '', ['http', 'https'], true)) {
            echo json_encode(['success' => false, 'message' => 'Nur http:// und https:// sind erlaubt']);
            break;
        }

        $newSite = $siteService->addSite($name, $url);
        echo json_encode(['success' => (bool)$newSite, 'site' => $newSite]);
        break;

    case 'delete_site':
        $id = $_POST['id'] ?? '';
        $success = $siteService->deleteSite($id);
        echo json_encode(['success' => $success]);
        break;

    case 'update_profile':
        $username = $_POST['username'] ?? '';
        $email = $_POST['email'] ?? '';
        $success = $configService->updateUser($username, $email);
        echo json_encode(['success' => $success]);
        break;

    case 'update_password':
        $new_pw = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        
        if ($new_pw !== $confirm || strlen($new_pw) < 8) {
            echo json_encode(['success' => false, 'message' => 'Passwörter stimmen nicht überein oder zu kurz']);
            break;
        }
        
        $hash = password_hash($new_pw, PASSWORD_DEFAULT);
        $success = $configService->updatePassword($hash);
        echo json_encode(['success' => $success]);
        break;

    case 'setup_2fa':
        $secret = $ga->createSecret();
        $_SESSION['temp_2fa_secret'] = $secret;
        $safeUser = htmlspecialchars((string)($_SESSION['username'] ?? 'User'), ENT_QUOTES, 'UTF-8');
        $qrCodeUrl = $ga->getQRCodeGoogleUrl('VantixDash', $secret, 'VantixDash (' . $safeUser . ')');
        
        echo json_encode([
            'success' => true, 
            'qrCodeUrl' => $qrCodeUrl, 
            'secret' => $secret
        ]);
        break;

    case 'verify_2fa':
        $code = $_POST['code'] ?? '';
        $secret = $_SESSION['temp_2fa_secret'] ?? '';
        
        if ($ga->verifyCode($secret, $code, 2)) {
            $configService->update2FA(true, $secret);
            unset($_SESSION['temp_2fa_secret']);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Ungültiger Code. Bitte erneut versuchen.']);
        }
        break;

    case 'disable_2fa':
        $configService->update2FA(false, null);
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unbekannte Aktion']);
        break;
}
