<?php
/**
 * VantixDash - Zentrale API Schnittstelle
 * Sicherheit: CSRF-Schutz, Session-Validierung, Domain-Whitelisting
 */

session_start();
header('Content-Type: application/json');

// 1. AUTHENTIFIZIERUNGSPRÜFUNG
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nicht autorisiert']);
    exit;
}

// 2. CSRF-SCHUTZ (Nur für POST-Anfragen)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'CSRF-Token ungültig']);
        exit;
    }
}

// 3. SERVICES LADEN
require_once __DIR__ . '/services/SiteService.php';
$sitesFile = __DIR__ . '/data/sites.json';
$siteService = new SiteService($sitesFile);

$action = $_GET['action'] ?? '';

switch ($action) {
    
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

    case 'check_update':
        $beta = ($_GET['beta'] ?? 'false') === 'true';
        // Logik für GitHub Update-Check
        $updateInfo = $siteService->checkAppUpdate($beta);
        echo json_encode($updateInfo);
        break;

    case 'install_update':
        $downloadUrl = $_POST['url'] ?? '';
        
        // SICHERHEIT: GitHub-Whitelist (dein Regex-Fix)
        if (!preg_match('/^https:\/\/(github\.com|api\.github\.com|raw\.githubusercontent\.com)\/olpo24\/VantixDash\//', $downloadUrl)) {
            echo json_encode(['success' => false, 'message' => 'Ungültige Update-Quelle']);
            exit;
        }

        $success = $siteService->installUpdate($downloadUrl);
        echo json_encode(['success' => $success]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Unbekannte Aktion']);
        break;
}
