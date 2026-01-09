/**
 * VantixDash - Vollständige Anwendungslogik
 */

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
    currentVersion: '0.0.0',

    init() {
        // Event-Listener für das Hinzufügen von Seiten
        const addForm = document.getElementById('addSiteForm');
        if (addForm) {
            addForm.addEventListener('submit', (e) => this.handleAddSite(e));
        }

        // Initiale Daten laden
        this.loadSites();
        this.checkAppUpdates();
    },

    /**
     * DATEN-MANAGEMENT
     */
    async loadSites() {
        if (typeof TableManager !== 'undefined') TableManager.setLoading(true);
        try {
            const response = await fetch('api.php?action=get_sites');
            this.sites = await response.json();
            this.updateStats();
            if (typeof TableManager !== 'undefined') {
                TableManager.renderDashboardTable(this.sites);
            }
        } catch (error) {
            console.error("Fehler beim Laden:", error);
        } finally {
            if (typeof TableManager !== 'undefined') TableManager.setLoading(false);
        }
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
        if (lastCheckEl) {
            const now = new Date();
            lastCheckEl.innerText = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')} Uhr`;
        }
    },

    /**
     * SEITEN-AKTIONEN
     */
    async refreshSite(siteId, event) {
        if (event) event.stopPropagation();
        const icon = event?.currentTarget.querySelector('i');
        if (icon) icon.classList.add('ph-spin');

        try {
            const formData = new FormData();
            formData.append('id', siteId);
            const response = await fetch('api.php?action=refresh_site', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            if (result.success) {
                this.sites = this.sites.map(s => s.id === siteId ? result.site : s);
                this.updateStats();
                TableManager.renderDashboardTable(this.sites);
            }
        } catch (error) {
            console.error("Refresh Fehler:", error);
        } finally {
            if (icon) icon.classList.remove('ph-spin');
        }
    },

    async deleteSite(siteId) {
        if (!confirm('Möchtest du diese Webseite wirklich aus dem Dashboard löschen?')) return;

        try {
            const formData = new FormData();
            formData.append('id', siteId);
            const response = await fetch('api.php?action=delete_site', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            if (result.success) {
                this.loadSites();
            }
        } catch (error) {
            alert('Fehler beim Löschen der Seite.');
        }
    },

    async loginToSite(siteId) {
        const site = this.sites.find(s => s.id === siteId);
        if (!site) return;
        try {
            const response = await fetch(`${site.url}/wp-json/vantixdash/v1/login`, {
                headers: { 'X-Vantix-Secret': site.api_key }
            });
            const data = await response.json();
            if (data.login_url) {
                window.open(data.login_url, '_blank');
            } else {
                alert('Login-Schnittstelle nicht erreichbar.');
            }
        } catch (error) {
            alert('Verbindung zur Seite fehlgeschlagen.');
        }
    },

    async handleAddSite(e) {
        e.preventDefault();
        const formData = new FormData(e.target);
        try {
            const response = await fetch('api.php?action=add_site', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            if (result.success) {
                e.target.reset();
                this.loadSites();
                // Falls du ein <dialog> oder CSS-Modal für "Hinzufügen" nutzt:
                const modal = document.getElementById('addSiteModal');
                if (modal && modal.close) modal.close();
            }
        } catch (error) {
            console.error("Add Fehler:", error);
        }
    },

    /**
     * VANTIXDASH UPDATE PROZESS (Das Dashboard selbst)
     */
    async checkAppUpdates() {
        const updateBanner = document.getElementById('app-update-banner');
        const betaMode = localStorage.getItem('vantix_beta') === 'true';

        try {
            const response = await fetch(`api.php?action=check_update&beta=${betaMode}`);
            const data = await response.json();

            if (data.success && data.update_available) {
                if (updateBanner) {
                    updateBanner.style.display = 'flex';
                    document.getElementById('new-version-tag').innerText = `v${data.remote}`;
                    
                    const updateBtn = document.getElementById('start-update-btn');
                    if (updateBtn) {
                        updateBtn.onclick = () => this.installAppUpdate(data.download_url);
                    }
                }
            }
        } catch (error) {
            console.error("Update-Check fehlgeschlagen");
        }
    },

    async installAppUpdate(url) {
        const btn = document.getElementById('start-update-btn');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="ph ph-circle-notch ph-spin"></i> Installiere...';
        }

        try {
            const formData = new FormData();
            formData.append('url', url);
            const response = await fetch('api.php?action=install_update', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                alert('VantixDash wurde erfolgreich aktualisiert! Die Seite wird neu geladen.');
                window.location.reload();
            } else {
                throw new Error(result.message || 'Installation fehlgeschlagen');
            }
        } catch (error) {
            alert('Fehler beim Update: ' + error.message);
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = 'Jetzt aktualisieren';
            }
        }
    },

    toggleBeta(enabled) {
        localStorage.setItem('vantix_beta', enabled);
        this.checkAppUpdates();
    },

    formatDate(dateString) {
        if (!dateString || dateString === 'Nie') return 'Nie';
        const date = new Date(dateString);
        if (isNaN(date.getTime())) return dateString;
        return date.toLocaleString('de-DE', {
            day: '2-digit', month: '2-digit', year: 'numeric',
            hour: '2-digit', minute: '2-digit'
        }) + ' Uhr';
    }
};

document.addEventListener('DOMContentLoaded', () => App.init());
