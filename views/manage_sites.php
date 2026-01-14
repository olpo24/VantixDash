<?php
if (!isset($siteService)) exit;
$sites = $siteService->getAll();
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
            <tbody id="sites-table-body">
                <?php if (empty($sites)): ?>
                    <tr id="no-sites-row">
                        <td colspan="5" style="text-align: center; padding: 2rem;">Keine Seiten gefunden.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($sites as $site): ?>
                        <tr data-id="<?php echo $site['id']; ?>">
                            <td>
                                <div class="site-info">
                                    <span class="site-name" style="font-weight: 600;"><?php echo htmlspecialchars($site['name']); ?></span><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($site['url']); ?></small>
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
                                    <button type="button" 
                                            onclick="deleteSiteHandler('<?php echo $site['id']; ?>', '<?php echo htmlspecialchars($site['name'], ENT_QUOTES); ?>')" 
                                            class="icon-btn" 
                                            style="color: var(--danger);" 
                                            title="Löschen">
                                        <i class="ph ph-trash"></i>
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

<script>
/**
 * Handler zum Löschen einer Seite
 */
async function deleteSiteHandler(id, name) {
    // Sicherstellen, dass die Funktionen aus der app.js geladen sind
    if (typeof window.showConfirm !== 'function' || typeof window.apiCall !== 'function') {
        console.error('Core JS functions not loaded yet');
        return;
    }

    const confirmed = await window.showConfirm(
        'Seite entfernen',
        `Möchtest du die Webseite "${name}" wirklich aus dem Dashboard löschen?`,
        { okText: 'Ja, unwiderruflich löschen', isDanger: true }
    );

    if (confirmed) {
        const formData = new FormData();
        formData.append('id', id);

        // Nutzt jetzt das explizite window.apiCall
        const result = await window.apiCall('delete_site', 'POST', formData);

        if (result && result.success) {
            showToast(`"${name}" wurde erfolgreich gelöscht.`, 'success');

            const row = document.querySelector(`tr[data-id="${id}"]`);
            if (row) {
                row.style.transition = 'opacity 0.3s ease';
                row.style.opacity = '0';
                setTimeout(() => {
                    row.remove();
                    const tbody = document.getElementById('sites-table-body');
                    if (tbody && tbody.querySelectorAll('tr').length === 0) {
                        tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 2rem;">Keine Seiten gefunden.</td></tr>';
                    }
                }, 300);
            }
        }
    }
}
</script>
