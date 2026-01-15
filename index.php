<?php
/**
 * index.php - Zentraler Router & Layout-Composer
 */
declare(strict_types=1);

// 1. HTTPS & Security Header
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === "off") {
    header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], true, 301);
    exit;
}
header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

session_start();
require_once __DIR__ . '/autoload.php';

use VantixDash\Logger;
use VantixDash\ConfigService;
use VantixDash\SiteService;

// 2. Initialisierung
$logger = new Logger();
$configService = new ConfigService();
$siteService = new SiteService(__DIR__ . '/data/sites.json', $configService, $logger);

// 3. Auth-Check & Session-Management
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $configService->getTimeout('session'))) {
        // Session Fixation Protection: Regenerate session ID before destroying
        session_regenerate_id(true);
        session_unset();
        session_destroy();
        header('Location: login.php?timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
} else {
    header('Location: login.php'); exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 4. Routing Logik
$view = $_GET['view'] ?? 'dashboard';
$allowedViews = [
    'dashboard'        => 'views/dashboard.php',
    'manage_sites'     => 'views/manage_sites.php',
    'add_site'         => 'views/add_site.php',
    'edit_site'        => 'views/edit_site.php',
    'settings_general' => 'views/settings_general.php',
    'settings_profile' => 'views/settings_profile.php',
    'profile'          => 'views/profile.php',
    'logs'             => 'views/logs.php',
    'settings_smtp'    => 'views/settings_smtp.php'
];

$viewPath = $allowedViews[$view] ?? 'views/dashboard.php';
$safeView = htmlspecialchars($view, ENT_QUOTES, 'UTF-8');

// 5. Layout zusammensetzen
include __DIR__ . '/views/layout/header.php';
?>
<div class="wrapper">
    <?php include __DIR__ . '/views/layout/sidebar.php'; ?>
    <div class="main">
        <?php include __DIR__ . '/views/layout/topbar.php'; ?>
        <main class="content">
            <?php include __DIR__ . '/' . $viewPath; ?>
        </main>
    </div>
</div>
<?php 
include __DIR__ . '/views/layout/footer.php'; 
?>
