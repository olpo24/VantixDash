/**
 * assets/js/tablemanager.js
 * Verwaltet das Rendern der Tabellen
 */

const TableManager = {
    /**
     * Dashboard Haupttabelle rendern
     */
    renderDashboardTable(sites) {
        const tbody = document.getElementById('sites-tbody');
        if (!tbody) return;
        tbody.innerHTML = '';

        if (sites.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding: 3rem; color: #64748b;">Keine Webseiten gefunden.</td></tr>';
            return;
        }

        sites.forEach(site => {
            const tr = document.createElement('tr');
            tr.className = 'cursor-pointer';
            
            // Fix: Modal öffnen anstatt nur Console Log
            tr.onclick = () => this.showDetails(site.id);

            const statusClass = site.status === 'online' ? 'bg-success' : 'bg-danger';
            
            // --- Update-Logik verfeinern ---
            const u = site.updates || { core: 0, plugins: 0, themes: 0 };
            const totalUpdates = (parseInt(u.core) || 0) + (parseInt(u.plugins) || 0) + (parseInt(u.themes) || 0);
            
            let updateHTML = '';
            if (totalUpdates > 0) {
                let details = [];
                if (parseInt(u.core) > 0) details.push(`<i class="ph ph-cpu" title="Core"></i>`);
                if (parseInt(u.plugins) > 0) details.push(`<i class="ph ph-plug" title="Plugins"></i> ${u.plugins}`);
                if (parseInt(u.themes) > 0) details.push(`<i class="ph ph-palette" title="Themes"></i> ${u.themes}`);
                
                updateHTML = `<span class="badge bg-warning text-dark d-inline-flex align-items-center gap-1">
                                <i class="ph ph-arrow-fat-up"></i> ${details.join(' · ')}
                              </span>`;
            } else {
                updateHTML = '<span class="badge" style="background: #dcfce7; color: #166534; border: 1px solid #bbf7d0;">Aktuell</span>';
            }

            const formattedDate = App.formatDate(site.last_check);

            tr.innerHTML = `
                <td>
                    <div class="site-info-wrapper">
                        <span class="status-indicator ${statusClass}"></span>
                        <div>
                            <span class="site-name">${Utils.escapeHTML(site.name)}</span>
                            <span class="site-url">${Utils.escapeHTML(site.url)}</span>
                        </div>
                    </div>
                </td>
                <td><span class="badge bg-light text-dark border">${Utils.escapeHTML(site.version || '-')}</span></td>
                <td><span class="text-muted">${Utils.escapeHTML(site.php || '-')}</span></td>
                <td>${updateHTML}</td>
                <td class="text-muted small">${formattedDate}</td>
                <td onclick="event.stopPropagation()">
                    <button class="btn-icon" onclick="App.refreshSite('${site.id}', event)" title="Jetzt prüfen">
                        <i class="ph ph-arrows-clockwise"></i>
                    </button>
                    <button class="btn-icon" onclick="App.loginToSite('${site.id}')" title="WP-Admin Login" style="margin-left: 4px;">
                        <i class="ph ph-sign-in"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        });
    },

    /**
     * Öffnet das Detail-Modal für eine Webseite
     */
    showDetails(siteId) {
        const site = App.sites.find(s => s.id === siteId);
        if (!site) return;

        // Falls du ein HTML-Element für das Modal hast (z.B. id="siteDetailsModal")
        const modal = document.getElementById('siteDetailsModal');
        if (modal) {
            // Hier werden die Daten ins Modal geschrieben
            document.getElementById('modal-site-name').innerText = site.name;
            document.getElementById('modal-site-url').innerText = site.url;
            
            // Modal anzeigen (Native HTML Dialog API)
            modal.showModal();
        } else {
            console.warn("Modal 'siteDetailsModal' nicht im HTML gefunden.");
            // Fallback für Testzwecke:
            alert(`Details für ${site.name}\nIP: ${site.ip || 'Unbekannt'}\nPHP: ${site.php}`);
        }
    },

    /**
     * Lade-Zustand anzeigen
     */
    setLoading(isLoading) {
        const tbody = document.getElementById('sites-tbody');
        if (!tbody) return;
        if (isLoading) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding: 3rem;"><i class="ph ph-circle-notch ph-spin" style="font-size: 2rem; color: var(--primary);"></i></td></tr>';
        }
    }
};
