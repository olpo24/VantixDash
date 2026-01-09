/**
 * assets/js/tablemanager.js
 * Verantwortlich für das Rendering der Tabellen und Modals
 */

const TableManager = {
    /**
     * Dashboard Haupttabelle rendern
     */
    renderDashboardTable(sites) {
        const tbody = document.getElementById('sites-tbody'');
        if (!tbody) return;
        tbody.innerHTML = '';

        if (sites.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-muted">Keine Webseiten gefunden.</td></tr>';
            return;
        }

        sites.forEach(site => {
            const tr = document.createElement('tr');
            tr.className = 'align-middle cursor-pointer';
            tr.onclick = () => this.showDetails(site.id);

            const statusClass = site.status === 'online' ? 'bg-success' : 'bg-danger';
            const totalUpdates = (site.updates?.core || 0) + (site.updates?.plugins || 0) + (site.updates?.themes || 0);

            tr.innerHTML = `
                <td>
                    <div class="d-flex align-items-center">
                        <span class="status-indicator ${statusClass} me-2"></span>
                        <div>
                            <div class="fw-bold text-main">${Utils.escapeHTML(site.name)}</div>
                            <div class="text-muted" style="font-size: 0.75rem;">${Utils.escapeHTML(site.url)}</div>
                        </div>
                    </div>
                </td>
                <td><span class="badge bg-light text-dark border">${Utils.escapeHTML(site.version || '-')}</span></td>
                <td><span class="text-muted">${Utils.escapeHTML(site.php || '-')}</span></td>
                <td>
                    ${totalUpdates > 0 
                        ? `<span class="badge bg-warning text-dark"><i class="ph ph-arrow-fat-up"></i> ${totalUpdates} Updates</span>` 
                        : '<span class="badge bg-light text-success border">Aktuell</span>'}
                </td>
                <td class="text-muted small">${site.last_check || 'Nie'}</td>
                <td class="text-end" onclick="event.stopPropagation()">
                    <button class="btn-icon me-1" onclick="App.refreshSite('${site.id}', event)" title="Jetzt prüfen">
                        <i class="ph ph-arrows-clockwise"></i>
                    </button>
                    <button class="btn-icon" onclick="App.loginToSite('${site.id}')" title="WP-Admin Login">
                        <i class="ph ph-sign-in"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        });
    },

    /**
     * Detail-Modal anzeigen
     */
    showDetails(siteId) {
        const site = App.sites.find(s => s.id === siteId);
        if (!site) return;

        document.getElementById('modalSiteName').innerText = site.name;
        const container = document.getElementById('detailsContainer');
        container.innerHTML = '';

        const details = site.details || {};

        // Helfer für Listen (verarbeitet strukturierte Objekte vom Child-Plugin)
        const generateList = (items, title, icon, colorClass) => {
            if (!items || items.length === 0) return '';
            
            let html = `
                <div class="mb-4">
                    <h6 class="fw-bold mb-3 d-flex align-items-center gap-2">
                        <i class="${icon} ${colorClass}"></i> ${items.length} ${title}
                    </h6>
                    <ul class="list-group list-group-flush border rounded">`;
            
            items.forEach(item => {
                const name = typeof item === 'string' ? item : (item.name || 'Unbekannt');
                const vInfo = (item.version && item.update_version) 
                    ? `<span class="badge bg-light text-muted border">${item.version} <i class="ph ph-arrow-right px-1"></i> ${item.update_version}</span>`
                    : '';

                html += `
                    <li class="list-group-item d-flex justify-content-between align-items-center small">
                        <span class="fw-semibold text-main">${Utils.escapeHTML(name)}</span>
                        ${vInfo}
                    </li>`;
            });
            
            html += '</ul></div>';
            return html;
        };

        let content = '';
        content += generateList(details.core, 'Core Updates', 'ph ph-cpu', 'text-primary');
        content += generateList(details.plugins, 'Plugin Updates', 'ph ph-plug', 'text-warning');
        content += generateList(details.themes, 'Theme Updates', 'ph ph-palette', 'text-info');

        if (content === '') {
            content = '<div class="text-center py-4 text-muted"><i class="ph ph-check-circle display-4 d-block mb-2 text-success"></i>Alles auf dem neuesten Stand!</div>';
        }

        container.innerHTML = content;
        document.getElementById('detailsModal').showModal();
    },

    /**
     * Loading State setzen
     */
    setLoading(isLoading) {
        const loader = document.getElementById('global-loader');
        if (loader) loader.style.display = isLoading ? 'block' : 'none';
    },

    /**
     * Verwaltungstabelle rendern (view=manage_sites)
     */
    renderManagementTable(sites) {
        const tbody = document.getElementById('manage-tbody');
        if (!tbody) return;
        tbody.innerHTML = '';

        sites.forEach(site => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="fw-bold">${Utils.escapeHTML(site.name)}</td>
                <td class="text-muted">${Utils.escapeHTML(site.url)}</td>
                <td class="text-end">
                    <button class="btn btn-sm btn-outline-primary me-1" onclick="App.editSite('${site.id}')">
                        <i class="ph ph-pencil"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="App.deleteSite('${site.id}')">
                        <i class="ph ph-trash"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }
};
