<?php
/**
 * index.php - Zentraler Router und Service-Initialisierung
 */
session_start();

// 1. SERVICES LADEN
require_once __DIR__ . '/services/ConfigService.php';
require_once __DIR__ . '/services/SiteService.php';

// 2. INITIALISIERUNG
$configService = new ConfigService();
$siteService = new SiteService(__DIR__ . '/data/sites.json', $configService);

// Login-Check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// 3. ROUTING (Welche View soll geladen werden?)
$view = $_GET['view'] ?? 'dashboard';

// Erlaubte Views (Whitelisting für Sicherheit)
$allowedViews = [
    'dashboard'        => 'views/dashboard.php',
    'manage_sites'     => 'views/manage_sites.php',
    'add_site'         => 'views/add_site.php',
    'settings_general' => 'views/settings_general.php',
    'settings_profile' => 'views/settings_profile.php'
];

$viewPath = $allowedViews[$view] ?? 'views/dashboard.php';

// Falls die Datei fehlt, Dashboard laden
if (!file_exists($viewPath)) {
    $viewPath = 'views/dashboard.php';
}

// 4. HEADER / NAVIGATION (Hier kannst du dein HTML-Gerüst laden)
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VantixDash - v<?php echo $configService->getVersion(); ?></title>
    <link rel="stylesheet" href="assets/css/style.css"> <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
</head>
<body>

    <nav class="main-nav">
        <a href="index.php?view=dashboard">Dashboard</a>
        <a href="index.php?view=manage_sites">Seiten</a>
        <a href="index.php?view=settings_general">Einstellungen</a>
        <a href="logout.php">Abmelden</a>
    </nav>

    <main class="content-wrapper">
        <?php 
            // Hier werden die Services automatisch in die View "injiziert"
            include $viewPath; 
        ?>
    </main>

    <script src="assets/js/app.js"></script>
</body>
</html>