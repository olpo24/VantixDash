/**
 * VantixDash - Main Application JS
 */

document.addEventListener('DOMContentLoaded', () => {
    // CSRF Token aus dem Meta-Tag holen
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    /**
     * Modal Funktionen
     */
window.openDetails = async (id) => {
    const modal = document.getElementById('details-modal');
    const modalBody = document.getElementById('modal-body');
    const modalTitle = document.getElementById('modal-title');
    
    modalBody.innerHTML = '<div style="text-align:center; padding:2rem;"><i class="ph ph-circle-notch animate-spin" style="font-size:2rem;"></i><p>Lade Details...</p></div>';
    modal.style.display = 'flex';

    try {
        // Wir holen die aktuellen Daten direkt aus der sites.json via API
        const response = await fetch(`api.php?action=refresh_site&id=${id}`);
        const result = await response.json();

        if (result.success && result.data) {
            const site = result.data;
            modalTitle.innerText = `Details: ${site.name}`;

            let html = `
                <div class="site-info-grid" style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:20px; font-size:0.9rem;">
                    <div class="card" style="padding:10px; background:var(--bg-color);"><strong>WordPress:</strong> ${site.wp_version}</div>
                    <div class="card" style="padding:10px; background:var(--bg-color);"><strong>PHP:</strong> ${site.php}</div>
                </div>
            `;

            // --- PLUGINS SEKTION ---
            html += `<h4 style="margin-bottom:10px; display:flex; align-items:center; gap:8px;"><i class="ph ph-plug"></i> Plugins (${site.updates.plugins})</h4>`;
            
            if (site.plugin_list && site.plugin_list.length > 0) {
                html += `<div class="plugin-list" style="display:grid; gap:8px; margin-bottom:20px;">`;
                site.plugin_list.forEach(plugin => {
                    html += `
                        <div class="item-row" style="padding:10px; border:1px solid var(--border-color); border-radius:8px; display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <div style="font-weight:600;">${plugin.name}</div>
                                <div style="font-size:0.8rem; color:var(--text-muted);">${plugin.old_version}</div>
                            </div>
                            <div style="text-align:right;">
                                <span class="badge" style="background:rgba(255, 107, 107, 0.1); color:#ff6b6b; padding:4px 8px; border-radius:5px; font-size:0.8rem; font-weight:600;">
                                    <i class="ph ph-arrow-right"></i> ${plugin.new_version}
                                </span>
                            </div>
                        </div>
                    `;
                });
                html += `</div>`;
            } else {
                html += `<p style="color:var(--text-muted); font-size:0.9rem; margin-bottom:20px;">Alle Plugins sind auf dem neuesten Stand.</p>`;
            }

            // --- THEMES SEKTION ---
            html += `<h4 style="margin-bottom:10px; display:flex; align-items:center; gap:8px;"><i class="ph ph-palette"></i> Themes (${site.updates.themes})</h4>`;
            
            if (site.theme_list && site.theme_list.length > 0) {
                html += `<div class="theme-list" style="display:grid; gap:8px;">`;
                site.theme_list.forEach(theme => {
                    html += `
                        <div class="item-row" style="padding:10px; border:1px solid var(--border-color); border-radius:8px; display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <div style="font-weight:600;">${theme.name}</div>
                                <div style="font-size:0.8rem; color:var(--text-muted);">${theme.old_version}</div>
                            </div>
                            <div style="text-align:right;">
                                <span class="badge" style="background:rgba(255, 107, 107, 0.1); color:#ff6b6b; padding:4px 8px; border-radius:5px; font-size:0.8rem; font-weight:600;">
                                    <i class="ph ph-arrow-right"></i> ${theme.new_version}
                                </span>
                            </div>
                        </div>
                    `;
                });
                html += `</div>`;
            } else {
                html += `<p style="color:var(--text-muted); font-size:0.9rem;">Alle Themes sind auf dem neuesten Stand.</p>`;
            }

            modalBody.innerHTML = html;
        } else {
            modalBody.innerHTML = `<p style="color:red;">Fehler beim Laden der Daten: ${result.message}</p>`;
        }
    } catch (e) {
        modalBody.innerHTML = `<p style="color:red;">Netzwerkfehler: ${e.message}</p>`;
    }
};

window.closeModal = () => {
    document.getElementById('details-modal').style.display = 'none';
};

// Schließen beim Klick außerhalb des Modals
window.onclick = (event) => {
    const modal = document.getElementById('details-modal');
    if (event.target == modal) {
        closeModal();
    }
};

    /**
     * Rendert den Inhalt des Modals basierend auf der sites.json Struktur
     */
    function renderModalContent(site) {
        const modalBody = document.getElementById('modal-body');
        document.getElementById('modal-title').innerText = `Details: ${site.name}`;

        let html = `
            <div class="site-detail-info">
                <p><strong>URL:</strong> ${site.url}</p>
                <p><strong>PHP:</strong> ${site.php || 'Unbekannt'} | <strong>WP:</strong> ${site.wp_version || site.version}</p>
            </div>
            <hr>
        `;

        // 1. Core Updates
        if (site.updates.core > 0) {
            html += `
                <div class="update-section core-update">
                    <h4><i class="ph ph-cpu"></i> WordPress Core</h4>
                    <div class="update-item">Ein neues WordPress Update ist verfügbar.</div>
                </div>
            `;
        }

        // 2. Plugins
        html += `<div class="update-section"><h4><i class="ph ph-plug"></i> Plugins (${site.updates.plugins})</h4>`;
        if (site.details.plugins && site.details.plugins.length > 0) {
            site.details.plugins.forEach(plugin => {
                html += `
                    <div class="update-item">
                        <div class="update-info">
                            <strong>${plugin.name}</strong>
                            <span>${plugin.version} <i class="ph ph-arrow-right"></i> ${plugin.update_version}</span>
                        </div>
                        <button class="mini-btn" onclick="updateItem('${site.id}', 'plugin', '${plugin.slug}')">Update</button>
                    </div>`;
            });
        } else {
            html += '<p class="text-muted">Alle Plugins sind aktuell.</p>';
        }
        html += `</div>`;

        // 3. Themes
        html += `<div class="update-section"><h4><i class="ph ph-palette"></i> Themes (${site.updates.themes})</h4>`;
        if (site.details.themes && site.details.themes.length > 0) {
            site.details.themes.forEach(theme => {
                html += `
                    <div class="update-item">
                        <div class="update-info">
                            <strong>${theme.name}</strong>
                            <span>${theme.version} <i class="ph ph-arrow-right"></i> ${theme.update_version}</span>
                        </div>
                        <button class="mini-btn" onclick="updateItem('${site.id}', 'theme', '${theme.slug}')">Update</button>
                    </div>`;
            });
        } else {
            html += '<p class="text-muted">Alle Themes sind aktuell.</p>';
        }
        html += `</div>`;

        modalBody.innerHTML = html;
    }

    /**
     * Einzelne Seite aktualisieren (API Check)
     */
    window.refreshSite = async (id) => {
    // Suche die Tabellenzeile statt der Card
    const row = document.querySelector(`tr[data-id="${id}"]`);
    if (!row) {
        console.error(`Zeile mit ID ${id} nicht gefunden.`);
        return;
    }

    const btn = row.querySelector('.refresh-single');
    const icon = btn.querySelector('i');
    
    // Animation starten
    icon.classList.add('ph-spin');
    btn.disabled = true;

    try {
        const response = await fetch(`api.php?action=refresh_site&id=${id}`);
        const result = await response.json();

        if (result.success && result.data) {
            const site = result.data;
            
            // 1. Status-Punkt aktualisieren
            const indicator = row.querySelector('.status-indicator');
            indicator.className = `status-indicator ${site.status}`;
            
            // 2. Update-Pillen aktualisieren
            // Hier greifen wir auf die Indizes der Pillen zu (0=Core, 1=Plugins, 2=Themes)
            const pills = row.querySelectorAll('.update-pill');
            
            const updates = [
                { count: site.updates.core, el: pills[0] },
                { count: site.updates.plugins, el: pills[1] },
                { count: site.updates.themes, el: pills[2] }
            ];

            updates.forEach(item => {
                if (item.el) {
                    item.el.querySelector('span').innerText = item.count;
                    if (item.count > 0) {
                        item.el.classList.add('has-updates');
                    } else {
                        item.el.classList.remove('has-updates');
                    }
                }
            });

            // 3. WP Version und Zeit aktualisieren
            const versionCell = row.cells[3]; // Die 4. Spalte (Index 3)
            if (versionCell) {
                versionCell.innerHTML = `<span class="text-muted" style="font-size: 0.9rem;">v${site.wp_version}</span>`;
            }

        } else {
            alert("Fehler: " + (result.message || "Unbekannter Fehler"));
        }
    } catch (e) {
        console.error("Refresh Error:", e);
    } finally {
        // Animation stoppen
        icon.classList.remove('ph-spin');
        btn.disabled = false;
    }
};

    /**
     * Alle Seiten nacheinander prüfen
     */
   window.refreshAllSites = async () => {
    // 1. Alle Tabellenzeilen finden, die eine ID haben
    const rows = document.querySelectorAll('tbody tr[data-id]');
    const refreshIcon = document.getElementById('refresh-all-icon');
    
    if (rows.length === 0) return;

    // Animation am Haupt-Button starten
    if (refreshIcon) refreshIcon.classList.add('ph-spin');

    // 2. Alle Zeilen nacheinander (oder parallel) abarbeiten
    // Wir nutzen hier die bereits existierende refreshSite Funktion
    const promises = Array.from(rows).map(row => {
        const id = row.getAttribute('data-id');
        return refreshSite(id); // Diese Funktion haben wir im vorherigen Schritt angepasst
    });

    try {
        await Promise.all(promises);
    } catch (e) {
        console.error("Fehler beim globalen Refresh:", e);
    } finally {
        // Animation am Haupt-Button stoppen
        if (refreshIcon) refreshIcon.classList.remove('ph-spin');
    }
};

    /**
     * Platzhalter für Update-Aktionen (Plugin/Theme/Core)
     */
    window.updateItem = async (siteId, type, slug) => {
        if (!confirm(`Möchtest du dieses ${type} wirklich aktualisieren?`)) return;
        
        alert(`Update-Funktion für ${slug} wird in Kürze implementiert.`);
        // Hier würde später der Fetch-Aufruf an api.php?action=update_resource folgen
    };
	window.loginToSite = async (id) => {
    try {
        const response = await fetch(`api.php?action=login_site&id=${id}`);
        const result = await response.json();
        if (result.success && result.login_url) {
            window.open(result.login_url, '_blank');
        } else {
            alert("Login fehlgeschlagen: " + (result.message || "Unbekannter Fehler"));
        }
    } catch (e) {
        console.error("Login Error:", e);
    }
};
	async function start2FASetup() {
    const response = await fetch('api.php?action=setup_2fa');
    const data = await response.json();
    
    if (data.success) {
        document.getElementById('2fa-qr-img').src = data.qrCodeUrl;
        document.getElementById('2fa-secret-text').innerText = data.secret;
        document.getElementById('2fa-setup-modal').style.display = 'flex';
    }
}

async function confirm2FA() {
    const code = document.getElementById('2fa-verify-code').value;
    const formData = new FormData();
    formData.append('code', code);

    const response = await fetch('api.php?action=verify_2fa', {
        method: 'POST',
        body: formData
    });
    const data = await response.json();

    if (data.success) {
        alert('2FA erfolgreich aktiviert!');
        location.reload();
    } else {
        alert(data.message);
    }
}

async function disable2FA() {
    if (!confirm('Möchtest du 2FA wirklich deaktivieren?')) return;
    const response = await fetch('api.php?action=disable_2fa');
    if ((await response.json()).success) location.reload();
}

function close2FAModal() {
    document.getElementById('2fa-setup-modal').style.display = 'none';
}
});
