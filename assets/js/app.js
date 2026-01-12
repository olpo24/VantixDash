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
    // 1. Die Card finden
    const card = document.querySelector(`.site-card[data-id="${id}"]`);
    if (!card) {
        console.error(`Card mit ID ${id} nicht gefunden.`);
        return;
    }

    // 2. Das Icon im Button finden (sicherer Selektor)
    const icon = card.querySelector('.refresh-single i');
    
    // Animation starten, falls das Icon existiert
    if (icon) icon.classList.add('ph-spin');

    try {
        const response = await fetch(`api.php?action=refresh_site&id=${id}`);
        const result = await response.json();
        
        if (result.success) {
            // Seite neu laden, um die neuen Daten aus der JSON anzuzeigen
            window.location.reload();
        } else {
            alert("Fehler beim Prüfen: " + result.message);
        }
    } catch (e) {
        console.error("API Fehler:", e);
        alert("Verbindung zur API fehlgeschlagen.");
    } finally {
        // Animation stoppen
        if (icon) icon.classList.remove('ph-spin');
    }
};

    /**
     * Alle Seiten nacheinander prüfen
     */
    window.refreshAllSites = async () => {
        const cards = document.querySelectorAll('.site-card');
        const icon = document.getElementById('refresh-all-icon');
        icon.classList.add('ph-spin');

        for (const card of cards) {
            const id = card.getAttribute('data-id');
            await refreshSite(id);
        }

        icon.classList.remove('ph-spin');
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
});
