const Utils = {
    escapeHTML(str) {
        if (!str) return "";
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
};

const App = {
    sites: [],

    init() {
        const addForm = document.getElementById('addSiteForm');
        if (addForm) addForm.addEventListener('submit', (e) => this.handleAddSite(e));
        this.loadSites();
    },

    formatDate(dateString) {
        if (!dateString || dateString === 'Nie') return 'Nie';
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return dateString;
        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const year = date.getFullYear();
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        return `${day}.${month}.${year} - ${hours}:${minutes} Uhr`;
    },

    async loadSites() {
        if (typeof TableManager !== 'undefined') TableManager.setLoading(true);
        try {
            const response = await fetch('api.php?action=get_sites');
            this.sites = await response.json();
            this.updateStats();
            if (typeof TableManager !== 'undefined') TableManager.renderDashboardTable(this.sites);
        } catch (error) { console.error("Load error:", error); }
        finally { if (typeof TableManager !== 'undefined') TableManager.setLoading(false); }
    },

    updateStats() {
        const totalSitesEl = document.getElementById('stat-total-sites');
        if (totalSitesEl) totalSitesEl.innerText = this.sites.length;

        let plugins = 0, themes = 0;
        this.sites.forEach(site => {
            plugins += (parseInt(site.updates?.plugins) || 0);
            themes += (parseInt(site.updates?.themes) || 0);
        });

        const detailedUpdatesEl = document.getElementById('stat-detailed-updates');
        if (detailedUpdatesEl) {
            detailedUpdatesEl.innerHTML = (plugins + themes > 0) 
                ? `<span style="color: #d97706;">${plugins} Plugins</span>, <span style="color: #2563eb;">${themes} Themes</span>`
                : "Alle aktuell";
        }

        const lastCheckEl = document.getElementById('last-update-time');
        if (lastCheckEl) lastCheckEl.innerText = this.formatDate(new Date());
    },

    async refreshSite(siteId, event) {
        if (event) event.stopPropagation();
        const icon = event?.currentTarget.querySelector('i');
        if (icon) icon.classList.add('ph-spin');
        try {
            const formData = new FormData();
            formData.append('id', siteId);
            const response = await fetch('api.php?action=refresh_site', { method: 'POST', body: formData });
            const result = await response.json();
            if (result.success) {
                this.sites = this.sites.map(s => s.id === siteId ? result.site : s);
                this.updateStats();
                TableManager.renderDashboardTable(this.sites);
            }
        } finally { if (icon) icon.classList.remove('ph-spin'); }
    }
};

document.addEventListener('DOMContentLoaded', () => App.init());
