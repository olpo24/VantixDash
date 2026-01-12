<?php
/**
 * VantixDash - Zentrale API Schnittstelle (Controller)
 * Architektur: Nutzt ConfigService & SiteService
 */

session_start();
header('Content-Type: application/json');

// 1. SERVICES LADEN
require_once __DIR__ . '/services/ConfigService.php';
require_once __DIR__ . '/services/SiteService.php';

$configService = new ConfigService();
$siteService = new SiteService(__DIR__ . '/data/sites.json', $configService);

// 2. AUTHENTIFIZIERUNGSPRÜFUNG
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nicht autorisiert']);
    exit;
}

// 3. CSRF-SCHUTZ (Nur für POST-Anfragen)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'CSRF-Token ungültig']);
        exit;
    }
}

$action = $_GET['action'] ?? '';

switch ($action) {
    
    // --- SEITEN VERWALTUNG ---

    case 'get_sites':
        echo json_encode($siteService->getAll());
        break;

    case 'add_site':
        $name = filter_var($_POST['name'] ?? '', FILTER_SANITIZE_SPECIAL_CHARS);
        $url  = filter_var($_POST['url'] ?? '', FILTER_VALIDATE_URL);
        
        if (!$url) {
            echo json_encode(['success' => false, 'message' => 'Ungültige URL']);
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

    case 'refresh_site':
        $id = $_POST['id'] ?? '';
        $updatedSite = $siteService->refreshSiteData($id);
        echo json_encode(['success' => (bool)$updatedSite, 'site' => $updatedSite]);
        break;


    // --- SYSTEM UPDATES ---

    case 'check_update':
        // Beta-Kanal Prüfung (true/false)
        $beta = (isset($_GET['beta']) && $_GET['beta'] === 'true');
        $updateInfo = $siteService->checkAppUpdate($beta);
        echo json_encode($updateInfo);
        break;

    case 'install_update':
        $downloadUrl = $_POST['url'] ?? '';

        // SICHERHEIT: Whitelist für GitHub Pfade (Regex-Fix)
        $pattern = '/^https:\/\/(www\.)?(github\.com|codeload\.github\.com|api\.github\.com)\/repos\/olpo24\/VantixDash\/(zipball|archive)\//i';
        $pattern2 = '/^https:\/\/github\.com\/olpo24\/VantixDash\/(archive|releases)\//i';

        if (!preg_match($pattern, $downloadUrl) && !preg_match($pattern2, $downloadUrl)) {
            echo json_encode([
                'success' => false, 
                'message' => 'Sicherheitsfehler: Ungültige Update-Quelle! URL muss von olpo24/VantixDash stammen.'
            ]);
            exit;
        }

        $success = $siteService->installUpdate($downloadUrl);
        echo json_encode(['success' => $success]);
        break;


    // --- DEFAULT ---

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Unbekannte Aktion: ' . htmlspecialchars($action)]);
        break;
}
