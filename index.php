<?php
session_start();

// 1. Zentraler Schutz: Wenn nicht eingeloggt, sofort weg.
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}

// 2. Logout-Logik (vor dem restlichen Content)
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: login.php");
    exit;
}

// 3. Daten laden
require_once __DIR__ . '/services/ConfigService.php';
$configService = new ConfigService();
$config = $configService->getAll(); // Damit die Variable $config für die Views erhalten bleibt
$sitesFile = __DIR__ . '/data/sites.json';
// Hier laden wir die Daten, die du unten im JS brauchst:
$siteData = file_exists($sitesFile) ? json_decode(file_get_contents($sitesFile), true) : [];

// 4. CSRF-Token sicherstellen (nur wenn noch keins da ist)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$view = $_GET['view'] ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo $_SESSION['csrf_token']; ?>">
    <title>VantixDash</title>
    
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>⚡</text></svg>">
</head>
<body>

<div id="wrapper">
    <aside id="sidebar">
        <div class="brand">Vantix<span>Dash</span></div>
        
        <nav class="nav-menu">
            <div class="nav-section">Hauptmenü</div>
            <a href="index.php?view=dashboard" class="nav-link <?php echo ($view == 'dashboard') ? 'active' : ''; ?>">
                <i class="ph ph-gauge"></i> <span>Dashboard</span>
            </a>
            <a href="index.php?view=manage_sites" class="nav-link <?php echo ($view == 'manage_sites') ? 'active' : ''; ?>">
                <i class="ph ph-globe"></i> <span>Webseiten</span>
            </a>

            <div class="nav-section">Konfiguration</div>
            <a href="index.php?view=settings_plugin" class="nav-link <?php echo ($view == 'settings_plugin') ? 'active' : ''; ?>">
                <i class="ph ph-plug-connected"></i> <span>Child Plugin</span>
            </a>
            <a href="index.php?view=settings_profile" class="nav-link <?php echo ($view == 'settings_profile') ? 'active' : ''; ?>">
                <i class="ph ph-user-circle-gear"></i> <span>Profil & 2FA</span>
            </a>
            <a href="index.php?view=settings_general" class="nav-link <?php echo ($view == 'settings_general') ? 'active' : ''; ?>">
                <i class="ph ph-sliders"></i> <span>Allgemein</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <a href="index.php?action=logout" class="logout-btn">
                <i class="ph ph-sign-out"></i> <span>Abmelden</span>
            </a>
        </div>
    </aside>

    <main id="content-wrapper">
        <header class="content-header">
            <h1>
                <?php 
                    $titles = [
                        'dashboard' => 'Übersicht',
                        'manage_sites' => 'Webseiten verwalten',
                        'settings_plugin' => 'Child Plugin Generator',
                        'settings_profile' => 'Profil & Sicherheit',
                        'settings_general' => 'Allgemeine Einstellungen'
                    ];
                    echo $titles[$view] ?? 'VantixDash';
                ?>
            </h1>
        </header>

        <section class="content-body">
            <?php 
                $viewFile = "views/{$view}.php";
                if (file_exists($viewFile)) { 
                    include $viewFile; 
                } else { 
                    echo '<div class="error-notice"><i class="ph ph-warning-octagon"></i><p>Seite nicht gefunden.</p></div>'; 
                }
            ?>
        </section>
    </main>
</div>

<script>
    // Daten sicher an JS übergeben
    const rawData = <?php echo json_encode(array_values($siteData)); ?>;
    
    document.addEventListener('DOMContentLoaded', () => {
        const urlParams = new URLSearchParams(window.location.search);
        const currentView = urlParams.get('view') || 'dashboard';

        if (typeof TableManager !== 'undefined') {
            if (currentView === 'dashboard') {
                TableManager.renderDashboardTable(rawData);
            } else if (currentView === 'manage_sites') {
                TableManager.renderManagementTable(rawData);
            }
        }
    });
</script>
<script src="assets/js/tablemanager.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
