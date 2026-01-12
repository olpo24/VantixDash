<?php
/**
 * views/manage_sites.php
 * Native Version mit Phosphor Icons und API-Key Vorschau
 */

if (!isset($siteService)) {
    header('Location: index.php');
    exit;
}

$sites = $siteService->getAll();
$message = '';

// Löschen-Logik (Fallback)
if (isset($_POST['delete_id'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = '<div class="alert error"><i class="ph ph-warning"></i> CSRF Fehler.</div>';
    } else {
        if ($siteService->deleteSite($_POST['delete_id'])) {
            $message = '<div class="alert success"><i class="ph ph-check"></i> Seite erfolgreich gelöscht.</div>';
            $sites = $siteService->getAll();
        }
    }
}
?>

<div class="manage-container">
    <div class="header-action">
        <div>
            <h2><i class="ph ph-globe"></i> Webseiten verwalten</h2>
            <p class="text-muted">Hier findest du alle verknüpften WordPress-Instanzen.</p>
        </div>
        <a href="index.php?view=add_site" class="main-button">
            <i class="ph ph-plus-circle"></i> Neue Seite hinzufügen
        </a>
    </div>

    <?php echo $message; ?>

    <div class="card table-card">
        <table class="native-table">
            <thead>
                <tr>
                    <th>Seite</th>
                    <th>Status</th>
                    <th>WordPress</th>
                    <th>API Key</th>
                    <th style="text-align: right;">Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($sites)): ?>
                    <tr>
                        <td colspan="5" style="text-align:center; padding: 3rem;">
                            <i class="ph ph-folder-not-found" style="font-size: 2rem; display: block; margin-bottom: 10px;"></i>
                            Keine Seiten gefunden. <a href="index.php?view=add_site">Jetzt erste Seite anlegen.</a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($sites as $site): ?>
                        <tr data-id="<?php echo $site['id']; ?>">
                            <td>
                                <div class="site-info">
                                    <span class="site-name"><?php echo htmlspecialchars($site['name']); ?></span>
                                    <small class="site-url"><?php echo htmlspecialchars($site['url']); ?></small>
                                </div>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $site['status']; ?>">
                                    <i class="ph ph-circle-fill"></i> <?php echo ucfirst($site['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="wp-version-info">
                                    <i class="ph ph-wordpress-logo"></i> 
                                    <span><?php echo htmlspecialchars($site['wp_version']); ?></span>
                                </div>
                            </td>
                            <td>
                                <div class="mini-copy-box">
                                    <code><?php echo substr($site['api_key'], 0, 8); ?>...</code>
                                    <button onclick="navigator.clipboard.writeText('<?php echo $site['api_key']; ?>'); alert('Key kopiert!')" title="Key kopieren">
                                        <i class="ph ph-copy"></i>
                                    </button>
                                </div>
                            </td>
                            <td style="text-align: right;">
                                <div class="action-buttons">
                                    <button class="icon-btn refresh" onclick="refreshSite('<?php echo $site['id']; ?>')" title="Aktualisieren">
                                        <i class="ph ph-arrows-counter-clockwise"></i>
                                    </button>
                                    
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Möchtest du diese Seite wirklich löschen?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="delete_id" value="<?php echo $site['id']; ?>">
                                        <button type="submit" class="icon-btn delete" title="Löschen">
                                            <i class="ph ph-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.table-card { padding: 0; overflow: hidden; }
.native-table { width: 100%; border-collapse: collapse; }
.native-table th { background: #f8fafc; padding: 12px 20px; text-align: left; font-size: 0.8rem; text-transform: uppercase; color: var(--text-muted); border-bottom: 1px solid var(--border-color); }
.native-table td { padding: 15px 20px; border-bottom: 1px solid var(--border-color); vertical-align: middle; }

.site-info { display: flex; flex-direction: column; }
.site-name { font-weight: 600; color: var(--text-main); }
.site-url { color: var(--text-muted); font-size: 0.85rem; }

/* Status Badges */
.status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
.status-badge.online { background: #dcfce7; color: #166534; }
.status-badge.offline { background: #fee2e2; color: #991b1b; }
.status-badge.pending { background: #fef9c3; color: #854d0e; }
.status-badge i { font-size: 8px; }

.wp-version-info { display: flex; align-items: center; gap: 8px; color: var(--text-muted); font-size: 0.9rem; }

/* Key & Buttons */
.mini-copy-box { display: flex; align-items: center; gap: 8px; background: #f1f5f9; padding: 4px 8px; border-radius: 4px; display: inline-flex; }
.mini-copy-box code { font-family: monospace; font-size: 0.85rem; }
.mini-copy-box button { background: none; border: none; cursor: pointer; color: var(--primary-color); padding: 0; }

.action-buttons { display: flex; gap: 8px; justify-content: flex-end; }
.icon-btn { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 6px; border: 1px solid var(--border-color); background: white; cursor: pointer; transition: 0.2s; font-size: 1.1rem; }
.icon-btn.refresh:hover { background: #eff6ff; color: var(--primary-color); border-color: var(--primary-color); }
.icon-btn.delete:hover { background: #fef2f2; color: var(--danger); border-color: var(--danger); }
</style>
