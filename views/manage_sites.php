<?php
if (!isset($siteService)) exit;

$sites = $siteService->getAll();
$message = '';

if (isset($_POST['delete_id'])) {
    if ($_POST['csrf_token'] === $_SESSION['csrf_token']) {
        if ($siteService->deleteSite($_POST['delete_id'])) {
            $message = '<div class="alert success"><i class="ph ph-check"></i> Seite gelöscht.</div>';
            $sites = $siteService->getAll();
        }
    }
}
?>

<div class="manage-container">
    <div class="header-action">
        <div>
            <h2><i class="ph ph-globe"></i> Webseiten verwalten</h2>
            <p class="text-muted">Übersicht und Verwaltung der WordPress-Instanzen.</p>
        </div>
        <a href="index.php?view=add_site" class="main-button">
            <i class="ph ph-plus-circle"></i> Neue Seite
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
                        <td colspan="5" class="text-center">Keine Seiten gefunden.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($sites as $site): ?>
                        <tr>
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
                            <td>v<?php echo htmlspecialchars($site['wp_version'] ?? '0.0.0'); ?></td>
                            <td><code><?php echo substr($site['api_key'], 0, 8); ?>...</code></td>
                            <td style="text-align: right;">
                                <div class="action-buttons" style="display: flex; justify-content: flex-end; gap: 8px;">
                                    <a href="index.php?view=edit_site&id=<?php echo $site['id']; ?>" class="icon-btn" title="Bearbeiten">
                                        <i class="ph ph-pencil-simple"></i>
                                    </a>
                                    <form method="POST" onsubmit="return confirm('Wirklich löschen?');">
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
