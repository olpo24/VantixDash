<?php
/**
 * api.php - Zentraler AJAX-Endpunkt für VantixDash
 */
session_start();
header('Content-Type: application/json');

// 1. SERVICES LADEN
require_once __DIR__ . '/services/ConfigService.php';
require_once __DIR__ . '/services/SiteService.php';
require_once __DIR__ . '/libs/GoogleAuthenticator.php';

// 2. INITIALISIERUNG
$configService = new ConfigService();
$siteService = new SiteService(__DIR__ . '/data/sites.json', $configService);
$ga = new PHPGangsta_GoogleAuthenticator();

// 3. SICHERHEITSPRÜFUNGEN
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Nicht autorisiert']);
    exit;
}

$action = $_GET['action'] ?? '';

// CSRF-Check für schreibende Aktionen (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $headers = getallheaders();
    $token = $headers['X-CSRF-TOKEN'] ?? $_POST['csrf_token'] ?? '';
    
    if (empty($token) || $token !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => 'CSRF-Token ungültig']);
        exit;
    }
}

// 4. ROUTING DER AKTIONEN
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
            $options = [
                'http' => [
                    'method' => 'GET',
                    'header' => "X-Vantix-Secret: " . $targetSite['api_key'] . "\r\n" .
                                "User-Agent: VantixDash-Monitor/1.0\r\n",
                    'timeout' => 10
                ]
            ];
            
            $context = stream_context_create($options);
            $response = @file_get_contents($apiUrl, false, $context);
            
            if ($response) {
                $data = json_decode($response, true);
                if (isset($data['login_url'])) {
                    echo json_encode(['success' => true, 'login_url' => $data['login_url']]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Keine Login-URL vom Child-Plugin erhalten.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Verbindung zum Child-Plugin fehlgeschlagen.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Seite nicht gefunden.']);
        }
        break;

    case 'add_site':
        $name = $_POST['name'] ?? '';
        $url = $_POST['url'] ?? '';
        if ($name && $url) {
            $newSite = $siteService->addSite($name, $url);
            echo json_encode(['success' => (bool)$newSite, 'site' => $newSite]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Name oder URL fehlt']);
        }
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
        // XSS Schutz: Username escapen für den Issuer-String
        $safeUser = htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8');
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
