<?php
/**
 * views/dashboard.php
 * Abgestimmt auf SiteService & Child-Plugin v1.6.0
 */

if (!isset($siteService)) exit;

$sites = $siteService->getAll();
?>

<div class="dashboard-container">
    <div class="header-action">
        <div>
            <h2><i class="ph ph-layout"></i> Dashboard</h2>
            <p class="text-muted">Status deiner WordPress-Instanzen</p>
        </div>
        <button onclick="refreshAllSites()" class="ghost-button">
            <i class="ph ph-arrows-counter-clockwise" id="refresh-all-icon"></i> Alle prüfen
        </button>
    </div>

    <?php if (empty($sites)): ?>
        <div class="card empty-state">
            <div class="empty-state-content" style="text-align: center; padding: 3rem;">
                <i class="ph ph-detective" style="font-size: 3rem; color: var(--text-muted);"></i>
                <p>Keine Seiten gefunden. <a href="index.php?view=add_site">Füge deine erste Seite hinzu.</a></p>
            </div>
        </div>
    <?php else: ?>
        <div class="dashboard-grid">
            <?php foreach ($sites as $site): ?>
                <?php 
                    // Daten-Mapping aus SiteService / Child-Plugin
                    $id       = $site['id'];
                    $status   = $site['status'] ?? 'offline';
                    $plugins  = (int)($site['updates']['plugins'] ?? 0);
                    $themes   = (int)($site['updates']['themes'] ?? 0);
                    $core     = (int)($site['updates']['core'] ?? 0);
                    $version  = htmlspecialchars($site['wp_version'] ?? '0.0.0');
                    $lastCheck = htmlspecialchars($site['last_check'] ?? 'Nie');
                ?>
                
                <div class="site-card card" data-id="<?php echo $id; ?>">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                        <div class="site-main" style="display: flex; align-items: center; gap: 10px;">
                            <i class="ph ph-wordpress-logo" style="font-size: 1.5rem; color: var(--wp-blue);"></i>
                            <strong style="font-size: 1.1rem;"><?php echo htmlspecialchars($site['name']); ?></strong>
                        </div>
                        <span class="status-indicator <?php echo $status; ?>" title="Status: <?php echo $status; ?>"></span>
                    </div>
                    
                    <div class="card-body">
                        <div class="update-overview" style="display: flex; gap: 10px; margin-bottom: 1.5rem;">
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
                        <p class="site-meta" style="font-size: 0.85rem; color: var(--text-muted);">
                            v<?php echo $version; ?> • <?php echo $lastCheck; ?>
                        </p>
                    </div>

                    <div class="card-footer">
                        <button class="action-link" onclick="openDetails('<?php echo $id; ?>')">
                            <i class="ph ph-magnifying-glass"></i> Details
                        </button>
                        
                        <div style="display: flex; gap: 12px;">
                            <button class="action-link" onclick="loginToSite('<?php echo $id; ?>')" title="Auto-Login">
                                <i class="ph ph-sign-in"></i> Login
                            </button>
                            <button class="action-link refresh-single" onclick="refreshSite('<?php echo $id; ?>')">
                                <i class="ph ph-arrows-clockwise"></i> Prüfen
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div id="details-modal" class="modal-overlay" style="display:none;">
    <div class="modal-content card">
        <div class="modal-header" style="display:flex; justify-content: space-between; padding: 1rem; border-bottom: 1px solid var(--border-color);">
            <h3 id="modal-title" style="margin:0;">Seiten-Details</h3>
            <button onclick="closeModal()" style="background:none; border:none; cursor:pointer; font-size: 1.2rem;"><i class="ph ph-x"></i></button>
        </div>
        <div id="modal-body" style="padding: 1.5rem;">
            </div>
    </div>
</div>
