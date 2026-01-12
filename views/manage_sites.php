<?php
/**
 * views/manage_sites.php
 * Native Version ohne TableManager-Referenzen.
 */

if (!isset($siteService)) {
    header('Location: ../index.php');
    exit;
}

$sites = $siteService->getAll();
$message = '';

// Einfaches Löschen via POST (als Fallback für AJAX)
if (isset($_POST['delete_id'])) {
    if ($siteService->deleteSite($_POST['delete_id'])) {
        $message = '<div class="alert success">Seite erfolgreich entfernt.</div>';
        $sites = $siteService->getAll(); // Liste aktualisieren
    }
}
?>

<div class="manage-container">
    <div class="header-action">
        <h2>Seiten verwalten</h2>
        <a href="index.php?view=add_site" class="main-button"> + Neue Seite</a>
    </div>

    <?php echo $message; ?>

    <div class="table-responsive">
        <table class="native-table" id="management-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>URL</th>
                    <th>Status</th>
                    <th>WP-Version</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody id="sites-list-body">
                <?php if (empty($sites)): ?>
                    <tr><td colspan="5" style="text-align:center;">Noch keine Seiten hinzugefügt.</td></tr>
                <?php else: ?>
                    <?php foreach ($sites as $site): ?>
                        <tr data-id="<?php echo $site['id']; ?>">
                            <td><strong><?php echo htmlspecialchars($site['name']); ?></strong></td>
                            <td><small><?php echo htmlspecialchars($site['url']); ?></small></td>
                            <td><span class="status-badge <?php echo $site['status']; ?>"><?php echo ucfirst($site['status']); ?></span></td>
                            <td><?php echo htmlspecialchars($site['wp_version']); ?></td>
                            <td>
                                <button class="btn-refresh" onclick="refreshSite('<?php echo $site['id']; ?>')">🔄</button>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Wirklich löschen?');">
                                    <input type="hidden" name="delete_id" value="<?php echo $site['id']; ?>">
                                    <button type="submit" class="btn-delete">🗑️</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<style>
.header-action { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.native-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
.native-table th, .native-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
.native-table th { background: #f8f9fa; font-weight: bold; font-size: 0.85em; text-transform: uppercase; color: #666; }
.status-badge { padding: 4px 8px; border-radius: 4px; font-size: 0.75em; font-weight: bold; }
.status-badge.online { background: #d4edda; color: #155724; }
.status-badge.offline { background: #f8d7da; color: #721c24; }
.status-badge.pending { background: #fff3cd; color: #856404; }
.btn-refresh, .btn-delete { background: none; border: 1px solid #ddd; padding: 5px 8px; border-radius: 4px; cursor: pointer; transition: 0.2s; }
.btn-refresh:hover { background: #e3f2fd; border-color: #2196f3; }
.btn-delete:hover { background: #f8d7da; border-color: #dc3545; }
</style>