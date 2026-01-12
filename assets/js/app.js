/**
 * VantixDash - Main Application JS
 */

document.addEventListener('DOMContentLoaded', () => {
    // CSRF Token aus dem Meta-Tag holen
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    /**
     * Modal Funktionen
     */
    window.openDetails = async (siteId) => {
        const modal = document.getElementById('details-modal');
        const modalBody = document.getElementById('modal-body');
        modal.style.display = 'flex';
        modalBody.innerHTML = '<div class="loader-spinner"><i class="ph ph-circle-notch"></i></div>';

        try {
            // Wir rufen die API auf, um die aktuellen Daten der Seite aus der JSON zu erhalten
            // Falls deine api.php noch keine "get_site" Aktion hat, nutzen wir hier einen Workaround
            const response = await fetch(`api.php?action=refresh_site&id=${siteId}`);
            const result = await response.json();

            if (result.success && result.data) {
                renderModalContent(result.data);
            } else {
                modalBody.innerHTML = `<p class="alert error"><i class="ph ph-warning"></i> Fehler: ${result.message || 'Daten konnten nicht geladen werden.'}</p>`;
            }
        } catch (error) {
            modalBody.innerHTML = '<p class="alert error">Netzwerkfehler beim Laden der Details.</p>';
        }
    };

    window.closeModal = () => {
        document.getElementById('details-modal').style.display = 'none';
    };

    // Schließen bei Klick außerhalb des Modals
    window.onclick = (event) => {
        const modal = document.getElementById('details-modal');
        if (event.target == modal) closeModal();
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
});
