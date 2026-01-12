<?php
/**
 * views/dashboard.php
 * Vollständig bereinigte Version ohne Inline-Styles.
 */

if (!isset($siteService)) exit;

$sites = $siteService->getAll();
?>

<div class="dashboard-container">
    <div class="header-action">
        <div>
            <h2><i class="ph ph-layout"></i> Dashboard</h2>
            <p class="text-muted">Statusübersicht deiner WordPress-Instanzen</p>
        </div>
        <button onclick="refreshAllSites()" class="ghost-button">
            <i class="ph ph-arrows-counter-clockwise" id="refresh-all-icon"></i> Alle prüfen
        </button>
    </div>

    <?php if (empty($sites)): ?>
        <div class="card empty-state">
            <div class="empty-state-content">
                <i class="ph ph-detective"></i>
                <p>Keine Seiten gefunden. <a href="index.php?view=add_site">Füge deine erste Seite hinzu.</a></p>
            </div>
        </div>
    <?php else: ?>
        <div class="dashboard-grid">
            <?php foreach ($sites as $site): ?>
                <div class="site-card card" data-id="<?php echo $site['id']; ?>">
                    <div class="card-header">
                        <div class="site-main">
                            <i class="ph ph-wordpress-logo"></i>
                            <strong><?php echo htmlspecialchars($site['name']); ?></strong>
                        </div>
                        <span class="status-indicator <?php echo $site['status']; ?>" title="Status: <?php echo $site['status']; ?>"></span>
                    </div>
                    
                    <div class="card-body">
    <div class="update-overview">
        <?php 
            // Sicherstellen, dass wir Integers haben, auch wenn der Key fehlt
            // Wir priorisieren die echten Einträge in 'details' vor dem Feld 'updates'
    $pluginList = $site['details']['plugins'] ?? [];
    $themeList  = $site['details']['themes'] ?? [];
    $coreList   = $site['details']['core'] ?? [];

    $plugins = (count($pluginList) > 0) ? count($pluginList) : (int)($site['updates']['plugins'] ?? 0);
    $themes  = (count($themeList) > 0) ? count($themeList) : (int)($site['updates']['themes'] ?? 0);
    $core    = (count($coreList) > 0) ? count($coreList) : (int)($site['updates']['core'] ?? 0);
        ?>

        <div class="update-pill <?php echo ($core > 0) ? 'has-updates' : ''; ?>" title="Core Updates">
            <i class="ph ph-cpu"></i>
            <span><?php echo $core; ?></span>
        </div>

        <div class="update-pill <?php echo ($plugins > 0) ? 'has-updates' : ''; ?>" title="Plugins">
            <i class="ph ph-plug"></i>
            <span><?php echo $plugins; ?></span>
        </div>

        <div class="update-pill <?php echo ($themes > 0) ? 'has-updates' : ''; ?>" title="Themes">
            <i class="ph ph-palette"></i>
            <span><?php echo $themes; ?></span>
        </div>
    </div>

    <p class="site-meta">
        v<?php echo htmlspecialchars($site['wp_version'] ?? ($site['version'] ?? '0.0.0')); ?> 
        • <?php echo htmlspecialchars($site['last_check'] ?? 'Nie geprüft'); ?>
    </p>
</div>

                    <div class="card-footer">
                        <button class="action-link" onclick="openDetails('<?php echo $site['id']; ?>')">
                            <i class="ph ph-magnifying-glass"></i> Details
                        </button>
                       <button class="action-link refresh-single" onclick="refreshSite('<?php echo $site['id']; ?>')">
    <i class="ph ph-arrows-clockwise"></i> Prüfen
</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div id="details-modal" class="modal-overlay" style="display:none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modal-title">Seiten-Details</h3>
            <button onclick="closeModal()" class="close-modal"><i class="ph ph-x"></i></button>
        </div>
        <div id="modal-body" class="modal-body">
            </div>
    </div>
</div>
