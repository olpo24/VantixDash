<?php
/**
 * index.php - Zentraler Router und Service-Initialisierung
 */
declare(strict_types=1);

/**
 * 1. HTTPS erzwingen & HSTS
 */
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === "off") {
    $location = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header('HTTP/1.1 301 Moved Permanently');
    header('Location: ' . $location);
    exit;
}

// HSTS Header (Browser merkt sich HTTPS für 1 Jahr)
header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

// 2. SESSION STARTEN
session_start();

// 3. SERVICES LADEN
require_once __DIR__ . '/services/ConfigService.php';
require_once __DIR__ . '/services/SiteService.php';

// 4. INITIALISIERUNG
$configService = new ConfigService();
$siteService = new SiteService(__DIR__ . '/data/sites.json', $configService);

// 5. SESSION-TIMEOUT PRÜFUNG (Muss NACH $configService Initialisierung stehen)
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    $sessionTimeout = $configService->getTimeout('session');

    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $sessionTimeout)) {
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

// 6. AUTH-CHECK (Login-Check)
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// CSRF-Token sicherstellen für das Dashboard
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 7. ROUTING (Welche View soll geladen werden?)
$view = $_GET['view'] ?? 'dashboard';

// Erlaubte Views (Whitelisting für Sicherheit)
$allowedViews = [
    'dashboard'        => 'views/dashboard.php',
    'manage_sites'     => 'views/manage_sites.php',
    'add_site'         => 'views/add_site.php',
    'edit_site'        => 'views/edit_site.php',
    'settings_general' => 'views/settings_general.php',
    'settings_profile' => 'views/settings_profile.php',
    'profile'          => 'views/profile.php'
];

$viewPath = $allowedViews[$view] ?? 'views/dashboard.php';

// Falls die Datei fehlt, Dashboard laden
if (!file_exists($viewPath)) {
    $viewPath = 'views/dashboard.php';
}

// 8. HTML-AUSGABE
$safeView = htmlspecialchars($view, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VantixDash - v<?php echo htmlspecialchars($configService->getVersion()); ?></title>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="assets/css/style.css"> 
    <meta name="csrf-token" content="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
</head>
<body>

<nav class="main-nav">
    <div style="color: white; font-weight: 800; margin-right: 1rem;">VantixDash</div>
    <a href="index.php?view=dashboard" class="<?php echo $safeView === 'dashboard' ? 'active' : ''; ?>">Dashboard</a>
    <a href="index.php?view=manage_sites" class="<?php echo $safeView === 'manage_sites' ? 'active' : ''; ?>">Seiten</a>
    <a href="index.php?view=settings_general" class="<?php echo $safeView === 'settings_general' ? 'active' : ''; ?>">Einstellungen</a>
    <a href="index.php?view=profile" class="<?php echo $safeView === 'profile' ? 'active' : ''; ?>">Profil</a>
    <a href="logout.php" class="action-link"><i class="ph ph-sign-out"></i> Abmelden</a>
</nav>

    <main class="content-wrapper">
        <?php 
            // Die Services ($configService, $siteService) stehen automatisch in der View zur Verfügung
            include $viewPath; 
        ?>
    </main>

    <script src="assets/js/app.js"></script>
</body>
</html>
