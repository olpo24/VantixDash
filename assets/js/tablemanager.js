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
            tr.onclick = () => console.log("Details für Seite:", site.id);

            const statusClass = site.status === 'online' ? 'bg-success' : 'bg-danger';
            const totalUpdates = (site.updates?.core || 0) + (site.updates?.plugins || 0) + (site.updates?.themes || 0);
            
            // Datum formatieren über die App-Funktion
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
                <td>
                    ${totalUpdates > 0 
                        ? `<span class="badge bg-warning text-dark"><i class="ph ph-arrow-fat-up"></i> ${totalUpdates} Updates</span>` 
                        : '<span class="badge" style="background: #dcfce7; color: #166534; border: 1px solid #bbf7d0;">Aktuell</span>'}
                </td>
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
