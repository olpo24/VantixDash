<?php
// Sicherstellen, dass die Daten geladen sind
$sitesFile = 'data/sites.json';
$sites = file_exists($sitesFile) ? json_decode(file_get_contents($sitesFile), true) : [];
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 800;">Webseiten verwalten</h1>
        <p class="text-muted small">Hier kannst du deine verbundenen WordPress-Instanzen verwalten und API-Keys generieren.</p>
    </div>
    <button class="btn btn-primary" onclick="document.getElementById('addSiteModal').showModal()">
        <i class="ph ph-plus-circle"></i> Seite hinzufügen
    </button>
</div>

<div class="card" style="padding: 0; overflow: hidden;">
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Name der Seite</th>
                    <th>URL / Endpunkt</th>
                    <th>API Key (verschleiert)</th>
                    <th style="text-align: right;">Aktionen</th>
                </tr>
            </thead>
            <tbody id="manage-sites-tbody">
                <?php if (empty($sites)): ?>
                <tr>
                    <td colspan="4" style="text-align: center; padding: 4rem; color: var(--text-muted);">
                        <i class="ph ph-folder-open" style="font-size: 3rem; display: block; margin-bottom: 1rem; opacity: 0.3;"></i>
                        Keine Webseiten gefunden. Klicke auf "Seite hinzufügen", um zu starten.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($sites as $site): ?>
                    <tr>
                        <td>
                            <div style="font-weight: 700; color: var(--text-main);"><?php echo htmlspecialchars($site['name']); ?></div>
                        </td>
                        <td>
                            <code style="font-size: 0.85rem; color: var(--primary);"><?php echo htmlspecialchars($site['url']); ?></code>
                        </td>
                        <td>
                            <span style="font-family: monospace; color: var(--text-muted); font-size: 0.9rem;">
                                ••••••••••••<?php echo substr($site['api_key'] ?? '', -4); ?>
                            </span>
                        </td>
                        <td style="text-align: right;">
                            <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                                <button class="btn-icon" title="Bearbeiten" 
                                        onclick='openEditModal(<?php echo json_encode($site); ?>)'>
                                    <i class="ph ph-pencil-simple"></i>
                                </button>
                                <button class="btn-icon" title="Löschen" style="--primary: var(--danger);" 
                                        onclick="App.deleteSite('<?php echo $site['id']; ?>')">
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

<dialog id="addSiteModal" class="card shadow-lg" style="width: 450px; border: none; padding: 2rem; margin: auto;">
    <div style="display: flex; justify-content: space-between; margin-bottom: 1.5rem;">
        <h3 style="font-weight: 800;">Neue Webseite</h3>
        <button onclick="this.closest('dialog').close()" style="background:none; border:none; cursor:pointer;"><i class="ph ph-x" style="font-size: 1.5rem;"></i></button>
    </div>
    <form id="addSiteForm">
        <div class="form-group">
            <label>Anzeigename</label>
            <input type="text" name="name" placeholder="z.B. Kundenprojekt XY" required>
        </div>
        <div class="form-group">
            <label>URL</label>
            <input type="url" name="url" placeholder="https://deine-seite.de" required>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 2rem;">
            <button type="button" class="btn" style="background: var(--border);" onclick="this.closest('dialog').close()">Abbrechen</button>
            <button type="submit" class="btn btn-primary">Key generieren</button>
        </div>
    </form>
</dialog>

<dialog id="editSiteModal" class="card shadow-lg" style="width: 450px; border: none; padding: 2rem; margin: auto;">
    <div style="display: flex; justify-content: space-between; margin-bottom: 1.5rem;">
        <h3 style="font-weight: 800;">Webseite bearbeiten</h3>
        <button onclick="this.closest('dialog').close()" style="background:none; border:none; cursor:pointer;"><i class="ph ph-x" style="font-size: 1.5rem;"></i></button>
    </div>
    <form id="editSiteForm">
        <input type="hidden" name="id" id="edit-id">
        <div class="form-group">
            <label>Anzeigename</label>
            <input type="text" name="name" id="edit-name" required>
        </div>
        <div class="form-group">
            <label>URL</label>
            <input type="url" name="url" id="edit-url" required>
        </div>
        
        <div style="margin-top: 1.5rem; padding: 1rem; background: var(--bg-body); border-radius: 8px; border: 1px dashed var(--border);">
            <label style="display: flex; align-items: center; gap: 0.75rem; cursor: pointer; font-weight: 700; color: var(--text-main);">
                <input type="checkbox" name="renew_key" value="1" style="width: auto;"> 
                Neuen API-Key generieren?
            </label>
            <p class="small text-muted" style="margin-top: 0.5rem; line-height: 1.4;">
                Der aktuelle Key im WordPress-Plugin verliert bei Aktivierung sofort seine Gültigkeit.
            </p>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 2rem;">
            <button type="button" class="btn" style="background: var(--border);" onclick="this.closest('dialog').close()">Abbrechen</button>
            <button type="submit" class="btn btn-primary">Speichern</button>
        </div>
    </form>
</dialog>

<dialog id="keySuccessModal" class="card shadow-lg" style="width: 450px; border: none; padding: 2.5rem; margin: auto;">
    <div style="text-align: center;">
        <div style="width: 60px; height: 60px; background: #dcfce7; color: #166534; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; font-size: 2rem;">
            <i class="ph ph-check-bold"></i>
        </div>
        <h3 style="font-weight: 800; margin-bottom: 0.5rem;">API Key generiert</h3>
        <p class="text-muted small" style="margin-bottom: 1.5rem;">Kopiere diesen Schlüssel und füge ihn im VantixDash Child-Plugin unter "API Key" ein.</p>
        
        <div style="background: #f1f5f9; padding: 1.25rem; border-radius: var(--radius); border: 1px solid var(--border); font-family: 'JetBrains Mono', monospace; font-size: 0.95rem; font-weight: 700; word-break: break-all; color: var(--primary); margin-bottom: 2rem;" id="generatedKeyDisplay">
            </div>
        
        <button class="btn btn-primary" style="width: 100%;" onclick="location.reload()">
            Ich habe den Key kopiert
        </button>
    </div>
</dialog>

<script>
/**
 * Bereitet das Edit-Modal mit den Daten der Zeile vor
 */
function openEditModal(site) {
    document.getElementById('edit-id').value = site.id;
    document.getElementById('edit-name').value = site.name;
    document.getElementById('edit-url').value = site.url;
    // Checkbox immer zurücksetzen beim Öffnen
    document.querySelector('#editSiteForm input[name="renew_key"]').checked = false;
    document.getElementById('editSiteModal').showModal();
}
</script>
