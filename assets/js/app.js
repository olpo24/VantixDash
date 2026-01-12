/**
 * VantixDash - Haupt-JavaScript (Native Version)
 */

const App = {
    sites: [],
    
    /**
     * Lädt alle Webseiten vom Server
     */
    loadSites() {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        
        fetch('api.php?action=get_sites', {
            headers: { 'X-CSRF-TOKEN': csrfToken }
        })
        .then(res => res.json())
        .then(data => {
            this.sites = data;
            if (typeof TableManager !== 'undefined') {
                TableManager.renderDashboardTable(data);
            }
            this.updateStats(data);
        })
        .catch(err => console.error('Fehler beim Laden der Seiten', err));
    },

    /**
     * Aktualisiert die Dashboard-Statistiken
     */
    updateStats(sites) {
        const totalSites = document.getElementById('stat-total-sites');
        const detailedUpdates = document.getElementById('stat-detailed-updates');
        const lastUpdate = document.getElementById('last-update-time');
        
        if (totalSites) totalSites.innerText = sites.length;
        
        let coreCount = 0, pluginCount = 0, themeCount = 0;
        sites.forEach(s => {
            const u = s.updates || {};
            coreCount += parseInt(u.core) || 0;
            pluginCount += parseInt(u.plugins) || 0;
            themeCount += parseInt(u.themes) || 0;
        });
        
        if (detailedUpdates) {
            const parts = [];
            if (coreCount > 0) parts.push(`${coreCount} Core`);
            if (pluginCount > 0) parts.push(`${pluginCount} Plugins`);
            if (themeCount > 0) parts.push(`${themeCount} Themes`);
            detailedUpdates.innerText = parts.length > 0 ? parts.join(' • ') : 'Keine';
        }
        
        if (lastUpdate) {
            lastUpdate.innerText = new Date().toLocaleTimeString('de-DE', { 
                hour: '2-digit', 
                minute: '2-digit' 
            });
        }
    },

    /**
     * Aktualisiert eine einzelne Webseite
     */
    refreshSite(siteId, event) {
        if (event) event.stopPropagation();
        
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const formData = new URLSearchParams();
        formData.append('id', siteId);
        
        fetch('api.php?action=refresh_site', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': csrfToken
            },
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                this.loadSites();
            } else {
                alert('Fehler beim Aktualisieren');
            }
        })
        .catch(err => console.error('Fehler', err));
    },

    /**
     * Login zu einer WordPress-Seite
     */
    loginToSite(siteId) {
        const site = this.sites.find(s => s.id === siteId);
        if (!site) return;
        
        alert('Login-Funktion für: ' + site.name);
        // Hier die eigentliche Login-Logik implementieren
    },

    /**
     * Löscht eine Webseite
     */
    deleteSite(siteId) {
        if (!confirm('Webseite wirklich löschen?')) return;
        
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const formData = new URLSearchParams();
        formData.append('id', siteId);
        
        fetch('api.php?action=delete_site', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': csrfToken
            },
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Fehler beim Löschen');
            }
        })
        .catch(err => console.error('Fehler', err));
    },

    /**
     * Prüft auf VantixDash System-Updates
     */
    checkAppUpdates() {
        const beta = localStorage.getItem('vantix_beta') === 'true';
        const banner = document.getElementById('app-update-banner');
        const versionTag = document.getElementById('new-version-tag');
        
        fetch(`api.php?action=check_update&beta=${beta}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.update_available) {
                if (banner) {
                    banner.style.display = 'flex';
                    if (versionTag) {
                        versionTag.innerHTML = `<span class="badge" style="background: #dcfce7; color: #166534;">v${data.remote}</span>`;
                    }
                }
                
                // Update-Container für settings_general
                const updateContainer = document.getElementById('update-container');
                const updateBtn = document.getElementById('start-update-btn');
                const versionSpan = document.getElementById('new-version-number');
                
                if (updateContainer && updateBtn && versionSpan) {
                    versionSpan.innerText = data.remote;
                    updateBtn.setAttribute('data-url', data.download_url);
                    updateContainer.style.display = 'block';
                }
            } else if (banner) {
                banner.style.display = 'none';
            }
        })
        .catch(err => console.error('Update-Check fehlgeschlagen', err));
    },

    /**
     * Formatiert ein Datum
     */
    formatDate(dateStr) {
        if (!dateStr || dateStr === 'Nie') return 'Nie';
        const date = new Date(dateStr);
        return date.toLocaleDateString('de-DE', { 
            day: '2-digit', 
            month: '2-digit', 
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }
};

/**
 * Utils-Objekt für Hilfsfunktionen
 */
const Utils = {
    escapeHTML(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
};

/**
 * Globale Funktionen für Settings-Views
 */
function toggleBeta(checked) {
    localStorage.setItem('vantix_beta', checked ? 'true' : 'false');
    App.checkAppUpdates();
}

function checkAppUpdates() {
    App.checkAppUpdates();
}

/**
 * Initialisierung beim Laden
 */
document.addEventListener('DOMContentLoaded', function() {
    
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const updateContainer = document.getElementById('update-container');
    const updateBtn = document.getElementById('start-update-btn');
    const versionSpan = document.getElementById('new-version-number');

    /**
     * Update-Prüfung beim Laden (für settings_general)
     */
    if (updateContainer) {
        fetch('api.php?action=check_update&beta=false')
            .then(res => res.json())
            .then(data => {
                if (data.success && data.update_available) {
                    versionSpan.innerText = data.remote;
                    updateBtn.setAttribute('data-url', data.download_url);
                    updateContainer.style.display = 'block';
                }
            })
            .catch(err => console.error('Update-Check fehlgeschlagen', err));
    }

    /**
     * Update-Installation
     */
    if (updateBtn) {
        updateBtn.addEventListener('click', function() {
            const url = this.getAttribute('data-url');

            if (!confirm(`Update auf v${versionSpan.innerText} jetzt starten?`)) return;

            // Button-Status: Loading
            updateBtn.disabled = true;
            updateBtn.innerText = 'Wird installiert...';
            updateBtn.style.opacity = '0.6';
            updateBtn.style.cursor = 'not-allowed';

            const formData = new URLSearchParams();
            formData.append('url', url);

            fetch('api.php?action=install_update', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    updateBtn.innerText = 'Erfolgreich! Lade neu...';
                    updateBtn.style.backgroundColor = '#28a745';
                    updateBtn.style.color = '#fff';
                    
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    alert('Fehler: ' + data.message);
                    resetButton();
                }
            })
            .catch(err => {
                alert('Netzwerkfehler beim Update.');
                resetButton();
            });
        });
    }

    function resetButton() {
        updateBtn.disabled = false;
        updateBtn.innerText = 'Erneut versuchen';
        updateBtn.style.opacity = '1';
        updateBtn.style.backgroundColor = '#dc3545';
        updateBtn.style.color = '#fff';
    }
});
