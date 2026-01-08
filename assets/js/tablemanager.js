/**
 * assets/js/tablemanager.js
 * Verwaltet das Rendern der Tabellen, Statistiken und Detail-Modale
 */

const TableManager = {
    
    /**
     * Dashboard-Tabelle rendern
     */
    renderDashboardTable(sites) {
        const tbody = document.getElementById('sites-tbody');
        if (!tbody) return;

        if (sites.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:3rem;">Keine Webseiten verbunden.</td></tr>';
            return;
        }

        tbody.innerHTML = ''; 
        
        let totalUpdates = { core: 0, plugins: 0, themes: 0 };

        sites.forEach(site => {
            // Updates zählen für Statistik-Kacheln
            totalUpdates.core += parseInt(site.updates?.core || 0);
            totalUpdates.plugins += parseInt(site.updates?.plugins || 0);
            totalUpdates.themes += parseInt(site.updates?.themes || 0);

            const tr = document.createElement('tr');
            tr.style.cursor = 'pointer';
            
            // Klick auf die Zeile öffnet Details, aber nicht wenn auf den Login-Button geklickt wird
            tr.addEventListener('click', (e) => {
                if (!e.target.closest('button')) {
                    this.showDetails(site);
                }
            });

            tr.innerHTML = `
                <td style="padding-left: 1.5rem;">
                    <div style="font-weight: 700; color: var(--text-main);">${site.name}</div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);">${site.url}</div>
                </td>
                <td style="text-align: center;">
                    <span class="badge ${site.status === 'online' ? 'badge-success' : 'badge-warning'}">
                        ${site.status === 'online' ? 'Online' : 'Pending'}
                    </span>
                </td>
                <td style="text-align: center;">
                    <div style="font-size: 0.85rem;">WP: ${site.version || '-'}</div>
                    <div style="font-size: 0.7rem; color: var(--text-muted);">PHP: ${site.php || '-'}</div>
                </td>
                <td style="text-align: center;">
                    ${this.renderUpdateBadges(site.updates)}
                </td>
                <td style="text-align: right; padding-right: 1.5rem; white-space: nowrap;">
    <button class="btn-icon" onclick="App.refreshSite('${site.id}', event)" title="Daten jetzt abrufen" style="margin-right: 0.5rem;">
        <i class="ph ph-arrows-clockwise"></i>
    </button>
    <button class="btn-icon" onclick="App.loginToSite('${site.id}', event)" title="Direkt-Login">
        <i class="ph ph-sign-in"></i>
    </button>
</td>
            `;
            tbody.appendChild(tr);
        });

        this.updateStats(sites.length, totalUpdates, sites);
    },

    /**
     * Erzeugt die kleinen Badges für Updates in der Tabelle
     */
    renderUpdateBadges(updates) {
        if (!updates || (updates.core == 0 && updates.plugins == 0 && updates.themes == 0)) {
            return '<span style="color: #16a34a; font-size: 0.85rem;"><i class="ph ph-check-circle"></i> Aktuell</span>';
        }
        
        let html = '<div style="display: flex; gap: 0.25rem; justify-content: center;">';
        if (updates.core > 0) html += `<span class="badge badge-danger" title="Core Update verfügbar">C</span>`;
        if (updates.plugins > 0) html += `<span class="badge badge-warning" title="${updates.plugins} Plugin Updates">${updates.plugins}</span>`;
        if (updates.themes > 0) html += `<span class="badge badge-info" title="${updates.themes} Theme Updates">${updates.themes}</span>`;
        html += '</div>';
        return html;
    },

    /**
     * Statistiken im Dashboard aktualisieren
     */
    updateStats(count, updates, sites) {
        const totalSitesEl = document.getElementById('stat-total-sites');
        if (totalSitesEl) totalSitesEl.innerText = count;

        const detailedUpdatesEl = document.getElementById('stat-detailed-updates');
        if (detailedUpdatesEl) {
            detailedUpdatesEl.innerText = `WP: ${updates.core} | Plugs: ${updates.plugins} | Themes: ${updates.themes}`;
        }

        const timeDisplay = document.getElementById('last-update-time');
        if (timeDisplay) {
            let latestCheckDate = null;

            if (sites && sites.length > 0) {
                sites.forEach(site => {
                    if (site.last_check) {
                        const dateStr = site.last_check.replace(' ', 'T');
                        const checkTime = new Date(dateStr);
                        if (!isNaN(checkTime.getTime())) {
                            if (!latestCheckDate || checkTime > latestCheckDate) {
                                latestCheckDate = checkTime;
                            }
                        }
                    }
                });
            }

            if (latestCheckDate) {
                const hours = latestCheckDate.getHours().toString().padStart(2, '0');
                const minutes = latestCheckDate.getMinutes().toString().padStart(2, '0');
                timeDisplay.innerText = `${hours}:${minutes} Uhr`;
            } else {
                timeDisplay.innerText = "Ausstehend";
            }
        }
    },

    /**
     * Detail-Modal anzeigen (inkl. Plugin- und Theme-Listen)
     */
    showDetails(site) {
        document.getElementById('details-site-name').innerText = site.name;
        const body = document.getElementById('details-modal-body');
        
        // Helfer-Funktion für Listen-Generierung
        const generateList = (items, iconClass, colorClass) => {
            if (!items || items.length === 0) return '';
            let html = `<div style="margin-bottom: 1.5rem;">
                <h4 style="font-size: 0.85rem; font-weight: 800; display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem; color: var(--text-main);">
                    <i class="${iconClass} ${colorClass}"></i> ${items.length} verfügbare Updates
                </h4>
                <ul style="font-size: 0.85rem; color: var(--text-muted); padding-left: 1.25rem; margin: 0;">`;
            
            items.forEach(item => {
                html += `<li style="margin-bottom: 0.4rem;">
                    <span style="color: var(--text-main); font-weight: 600;">${item.name}</span> 
                    <span style="font-size: 0.75rem;">(v${item.version} <i class="ph ph-arrow-right"></i> ${item.update_version})</span>
                </li>`;
            });
            html += '</ul></div>';
            return html;
        };

        const pluginHtml = generateList(site.details?.plugins, 'ph ph-plugin', 'text-warning');
        const themeHtml = generateList(site.details?.themes, 'ph ph-paint-brush', 'text-info');
        const coreHtml = site.updates?.core > 0 
            ? `<div style="padding: 1rem; background: #fee2e2; color: #991b1b; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.85rem; font-weight: 700;">
                <i class="ph ph-warning-circle"></i> Ein WordPress Core-Update ist verfügbar!
               </div>` 
            : '';

        body.innerHTML = `
            <div style="display: grid; gap: 1rem;">
                <div style="display: flex; gap: 1rem; margin-bottom: 1rem;">
                    <div style="flex: 1; background: var(--bg-body); padding: 1rem; border-radius: 12px; border: 1px solid var(--border);">
                        <div style="font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase;">WordPress</div>
                        <div style="font-weight: 800; font-size: 1.1rem;">${site.version}</div>
                    </div>
                    <div style="flex: 1; background: var(--bg-body); padding: 1rem; border-radius: 12px; border: 1px solid var(--border);">
                        <div style="font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase;">PHP Version</div>
                        <div style="font-weight: 800; font-size: 1.1rem;">${site.php}</div>
                    </div>
                </div>

                ${coreHtml}
                ${pluginHtml || themeHtml ? pluginHtml + themeHtml : '<p style="text-align:center; padding: 2rem; color: var(--text-muted);">Alles auf dem neuesten Stand!</p>'}
            </div>
        `;

        const loginBtn = document.getElementById('details-admin-link-btn');
        loginBtn.onclick = () => App.loginToSite(site.id);

        document.getElementById('detailsModal').showModal();
    },

    /**
     * Lade-Zustand für Buttons umschalten
     */
    setLoading(isLoading) {
        const btn = document.getElementById('refresh-all-btn');
        if (btn) {
            btn.disabled = isLoading;
            btn.innerHTML = isLoading ? '<i class="ph ph-circle-notch ph-spin"></i>' : '<i class="ph ph-arrows-clockwise"></i> Jetzt aktualisieren';
        }
    }
};
