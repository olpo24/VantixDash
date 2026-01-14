<?php
/**
 * views/dashboard.php
 * Zentrales Dashboard mit Live-Status und Update-Übersicht
 */

if (!isset($siteService)) exit;
$sites = $siteService->getAll();
?>

<div class="dashboard-container">
    <div class="header-action">
        <div>
            <h2><i class="ph ph-layout"></i> Dashboard</h2>
            <p class="text-muted">Zentrales Management deiner Instanzen</p>
        </div>
        <button onclick="refreshAllSites()" class="ghost-button">
            <i class="ph ph-arrows-counter-clockwise" id="refresh-all-icon"></i> Alle prüfen
        </button>
    </div>

    <div class="card table-card">
        <table class="native-table">
            <thead>
                <tr>
                    <th style="width: 50px; text-align: center;">Status</th>
                    <th>Seite</th>
                    <th>Updates</th>
                    <th>WordPress</th>
                    <th style="text-align: right;">Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($sites)): ?>
                    <tr>
                        <td colspan="5" class="text-center" style="padding: 3rem;">
                            <i class="ph ph-detective" style="font-size: 2rem; display: block; margin-bottom: 10px; color: var(--text-muted);"></i>
                            Keine Seiten gefunden. <a href="index.php?view=add_site">Füge deine erste Seite hinzu.</a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($sites as $site): 
                        $id = $site['id'];
                        $status = $site['status'] ?? 'offline';
                        $plugins = (int)($site['updates']['plugins'] ?? 0);
                        $themes  = (int)($site['updates']['themes'] ?? 0);
                        $core    = (int)($site['updates']['core'] ?? 0);
                    ?>
                        <tr data-id="<?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>">
                            <td style="text-align: center;">
                                <span class="status-indicator status-badge <?php echo htmlspecialchars($status); ?>" 
                                      title="Status: <?php echo htmlspecialchars($status); ?>"
                                      style="margin: 0 auto;"></span>
                            </td>
                            <td>
                                <div class="site-info">
                                    <span class="site-name" style="font-weight: 600;">
                                        <?php echo htmlspecialchars($site['name'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                    <small class="site-url" style="display: block; color: var(--text-muted); font-size: 0.75rem;">
                                        <?php echo htmlspecialchars($site['url'], ENT_QUOTES, 'UTF-8'); ?>
                                    </small>
                                </div>
                            </td>
                            <td>
                                <div class="update-overview" style="display: flex; gap: 6px;">
                                    <div class="update-pill mini <?php echo ($core > 0) ? 'has-updates' : ''; ?>" title="Core">
                                        <i class="ph ph-cpu"></i> <span class="update-count-core"><?php echo $core; ?></span>
                                    </div>
                                    <div class="update-pill mini <?php echo ($plugins > 0) ? 'has-updates' : ''; ?>" title="Plugins">
                                        <i class="ph ph-plug"></i> <span class="update-count-plugins"><?php echo $plugins; ?></span>
                                    </div>
                                    <div class="update-pill mini <?php echo ($themes > 0) ? 'has-updates' : ''; ?>" title="Themes">
                                        <i class="ph ph-palette"></i> <span class="update-count-themes"><?php echo $themes; ?></span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="text-muted wp-version" style="font-size: 0.9rem;">
                                    v<?php echo htmlspecialchars($site['wp_version'] ?? '0.0.0', ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <div class="action-buttons" style="display: flex; justify-content: flex-end; gap: 8px;">
                                    <button class="icon-btn" onclick="openDetails('<?php echo addslashes($id); ?>')" title="Details">
                                        <i class="ph ph-magnifying-glass"></i>
                                    </button>
                                    <button class="icon-btn" onclick="loginToSite('<?php echo addslashes($id); ?>')" title="Auto-Login">
                                        <i class="ph ph-sign-in"></i>
                                    </button>
                                    <button class="icon-btn refresh-single" onclick="refreshSite('<?php echo addslashes($id); ?>')" title="Prüfen">
                                        <i class="ph ph-arrows-clockwise"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
