<?php
/**
 * index.php - Zentraler Router und Service-Initialisierung
 */
declare(strict_types=1);

/**
 * 1. HTTPS erzwingen & Sicherheits-Header
 */
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === "off") {
    $location = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header('HTTP/1.1 301 Moved Permanently');
    header('Location: ' . $location);
    exit;
}

// HSTS & Security Header
header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

// 2. SESSION STARTEN
session_start();

// 3. SERVICES LADEN
require_once __DIR__ . '/services/Logger.php';
require_once __DIR__ . '/services/ConfigService.php';
require_once __DIR__ . '/services/SiteService.php';

// 4. INITIALISIERUNG
$logger = new Logger();
$configService = new ConfigService();
$siteService = new SiteService(__DIR__ . '/data/sites.json', $configService, $logger);

// 5. SESSION-TIMEOUT PRÜFUNG
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    $sessionTimeout = $configService->getTimeout('session');

    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
        session_unset();
        session_destroy();
        
        if (str_contains($_SERVER['PHP_SELF'], 'api.php')) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Session abgelaufen. Bitte neu einloggen.']);
            exit;
        } else {
            header('Location: login.php?timeout=1');
            exit;
        }
    }
    $_SESSION['last_activity'] = time();
}

// 6. AUTH-CHECK
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// CSRF-Token sicherstellen
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 7. ROUTING
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

// Fallback auf Dashboard, falls View nicht existiert oder nicht erlaubt ist
$viewPath = $allowedViews[$view] ?? 'views/dashboard.php';
if (!file_exists(__DIR__ . '/' . $viewPath)) {
    $viewPath = 'views/dashboard.php';
}

$safeView = htmlspecialchars($view, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VantixDash - v<?php echo htmlspecialchars((string)$configService->getVersion()); ?></title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="assets/css/style.css"> 
    <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
</head>
<body>

<nav class="main-nav">
    <div class="nav-brand">VantixDash</div>
    <div class="nav-links">
        <a href="index.php?view=dashboard" class="<?php echo $safeView === 'dashboard' ? 'active' : ''; ?>"><i class="ph ph-house"></i> Dashboard</a>
        <a href="index.php?view=manage_sites" class="<?php echo $safeView === 'manage_sites' ? 'active' : ''; ?>"><i class="ph ph-browsers"></i> Seiten</a>
        <a href="index.php?view=settings_smtp" class="<?php echo $safeView === 'settings_smtp' ? 'active' : ''; ?>"><i class="ph ph-envelope-simple"></i> SMTP</a>
        <a href="index.php?view=logs" class="<?php echo $safeView === 'logs' ? 'active' : ''; ?>"><i class="ph ph-terminal-window"></i> Logs</a>
        <a href="index.php?view=profile" class="<?php echo $safeView === 'profile' ? 'active' : ''; ?>"><i class="ph ph-user-circle"></i> Profil</a>
    </div>
    <a href="logout.php" class="action-link logout-btn"><i class="ph ph-sign-out"></i> Abmelden</a>
</nav>

<main class="content-wrapper">
    <?php 
        // Die Views nutzen die oben initialisierten Objekte $configService, $logger, $siteService
        include __DIR__ . '/' . $viewPath; 
    ?>
</main>

<script src="assets/js/app.js"></script>
</body>
</html>
