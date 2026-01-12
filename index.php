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
	'edit_site'         => 'views/edit_site.php',
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
	<script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="assets/css/style.css"> <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
</head>
<body>

<nav class="main-nav">
    <div style="color: white; font-weight: 800; margin-right: 1rem;">VantixDash</div>
    <a href="index.php?view=dashboard" class="<?php echo $view === 'dashboard' ? 'active' : ''; ?>">Dashboard</a>
    <a href="index.php?view=manage_sites" class="<?php echo $view === 'manage_sites' ? 'active' : ''; ?>">Seiten</a>
    <a href="index.php?view=settings_general" class="<?php echo $view === 'settings_general' ? 'active' : ''; ?>">Einstellungen</a>
    <div class="nav-controls">
    <button id="view-toggle" class="ghost-button" title="Ansicht umschalten">
        <i class="ph ph-list" id="toggle-icon"></i>
    </button>
    <a href="logout.php" class="action-link"><i class="ph ph-sign-out"></i> Abmelden</a>
</div>
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
