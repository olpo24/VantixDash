/**
 * assets/js/app.js
 * Hauptsteuerung für das VantixDash Dashboard
 */

const Utils = {
    /**
     * Verhindert XSS durch Escaping von HTML-Tags
     */
    escapeHTML(str) {
        if (!str) return "";
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
};

const App = {
    sites: [],

    /**
     * Startet die Anwendung
     */
    init() {
        console.log("VantixDash App wird initialisiert...");
        
        // Event Listener für "Seite hinzufügen"
        const addForm = document.getElementById('addSiteForm');
        if (addForm) {
            addForm.addEventListener('submit', (e) => this.handleAddSite(e));
        }

        // Event Listener für "Seite bearbeiten"
        const editForm = document.getElementById('editSiteForm');
        if (editForm) {
            editForm.addEventListener('submit', (e) => this.handleUpdateSite(e));
        }

        // Initiales Laden der Daten (für das Dashboard)
        this.loadSites();
    },

    /**
     * Lädt die Seiten-Daten von der api.php
     */
    async loadSites() {
        if (typeof TableManager !== 'undefined') {
            TableManager.setLoading(true);
        }

        try {
            const response = await fetch('api.php?action=get_sites');
            if (!response.ok) throw new Error("API-Abruf fehlgeschlagen");
            
            this.sites = await response.json();
            
            // Statistiken in den Karten oben aktualisieren
            this.updateStats();
            
            // Bestimmen, welche Ansicht gerade aktiv ist
            const urlParams = new URLSearchParams(window.location.search);
            const view = urlParams.get('view') || 'dashboard';
            
            if (typeof TableManager !== 'undefined') {
                if (view === 'dashboard') {
                    TableManager.renderDashboardTable(this.sites);
                } else if (view === 'manage_sites') {
                    TableManager.renderManagementTable(this.sites);
                }
            }
        } catch (error) {
            console.error("Fehler beim Laden der Seiten:", error);
        } finally {
            if (typeof TableManager !== 'undefined') {
                TableManager.setLoading(false);
            }
        }
    },

    /**
     * Berechnet die Statistiken und befüllt die Dashboard-Karten
     */
    updateStats() {
        // 1. Gesamtzahl der Seiten
        const totalSitesEl = document.getElementById('stat-total-sites');
        if (totalSitesEl) {
            totalSitesEl.innerText = this.sites.length;
        }

        // 2. Updates zählen (Plugins, Themes, Core)
        let plugins = 0, themes = 0, core = 0;
        this.sites.forEach(site => {
            if (site.updates) {
                plugins += (parseInt(site.updates.plugins) || 0);
                themes += (parseInt(site.updates.themes) || 0);
                core += (parseInt(site.updates.core) || 0);
            }
        });

        const detailedUpdatesEl = document.getElementById('stat-detailed-updates');
        if (detailedUpdatesEl) {
            const total = plugins + themes + core;
            if (total > 0) {
                detailedUpdatesEl.innerHTML = `
                    <span style="color: #d97706;">${plugins} Plugins</span>, 
                    <span style="color: #2563eb;">${themes} Themes</span>`;
            } else {
                detailedUpdatesEl.innerText = "Alle aktuell";
            }
        }

        // 3. Letzter Check (Uhrzeit aktualisieren)
        const lastCheckEl = document.getElementById('last-update-time');
        if (lastCheckEl) {
            const now = new Date();
            lastCheckEl.innerText = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }
    },

    /**
     * Neue Webseite hinzufügen
     */
    async handleAddSite(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        const submitBtn = form.querySelector('button[type="submit"]');
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="ph ph-circle-notch ph-spin"></i> Generiere...';

        try {
            const response = await fetch('api.php?action=add_site', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                document.getElementById('addSiteModal').close();
                form.reset();
                
                document.getElementById('generatedKeyDisplay').innerText = result.api_key;
                document.getElementById('keySuccessModal').showModal();
                
                this.loadSites();
            } else {
                alert('Fehler: ' + result.message);
            }
        } catch (err) {
            alert('Verbindung zur api.php fehlgeschlagen.');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Key generieren';
        }
    },

    /**
     * Webseite aktualisieren (Editieren)
     */
    async handleUpdateSite(e) {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        const submitBtn = form.querySelector('button[type="submit"]');

        submitBtn.disabled = true;

        try {
            const response = await fetch('api.php?action=update_site', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                document.getElementById('editSiteModal').close();
                
                if (result.api_key) {
                    document.getElementById('generatedKeyDisplay').innerText = result.api_key;
                    document.getElementById('keySuccessModal').showModal();
                } else {
                    window.location.reload();
                }
            } else {
                alert('Fehler: ' + result.message);
            }
        } catch (err) {
            alert('Speichern fehlgeschlagen.');
        } finally {
            submitBtn.disabled = false;
        }
    },

    /**
     * Seite löschen
     */
    async deleteSite(siteId) {
        if (!confirm("Diese Webseite wirklich aus dem Dashboard entfernen?")) return;

        try {
            const formData = new FormData();
            formData.append('id', siteId);

            const response = await fetch('api.php?action=delete_site', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            if (result.success) {
                window.location.reload();
            } else {
                alert("Fehler beim Löschen: " + result.message);
            }
        } catch (error) {
            console.error("Lösch-Fehler:", error);
        }
    },

    /**
     * Einzelne Seite manuell aktualisieren
     */
    async refreshSite(siteId, event) {
        if (event) event.stopPropagation();

        const icon = event ? event.currentTarget.querySelector('i') : null;
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
                this.updateStats(); // Auch Stats nach Einzelrefresh erneuern
                if (typeof TableManager !== 'undefined') {
                    TableManager.renderDashboardTable(this.sites);
                }
            } else {
                alert('Fehler: ' + result.message);
            }
        } catch (err) {
            console.error(err);
            alert('Verbindung zur API fehlgeschlagen.');
        } finally {
            if (icon) icon.classList.remove('ph-spin');
        }
    },

    /**
     * WordPress Auto-Login
     */
    async loginToSite(siteId) {
        const site = this.sites.find(s => s.id === siteId);
        if (!site) return;

        try {
            const response = await fetch(`${site.url}/wp-json/vantixdash/v1/login`, {
                method: 'GET',
                headers: {
                    'X-Vantix-Secret': site.api_key
                }
            });

            const data = await response.json();

            if (data.login_url) {
                window.open(data.login_url, '_blank');
            } else {
                alert("Login fehlgeschlagen. Bitte API-Key prüfen.");
            }
        } catch (error) {
            console.error("Verbindungsfehler beim Login:", error);
            alert("Die WordPress-Seite konnte nicht erreicht werden.");
        }
    },

    /**
     * Prüft auf System-Updates via GitHub
     */
    async checkUpdates() {
        const statusDiv = document.getElementById('update-status');
        const betaToggle = document.getElementById('beta-toggle');
        
        if (!statusDiv) return;

        const isBeta = (betaToggle && betaToggle.checked) ? 'true' : 'false';
        localStorage.setItem('beta_enabled', isBeta);

        statusDiv.innerHTML = '<i class="ph ph-circle-notch ph-spin me-2"></i> Prüfe auf Updates...';
        statusDiv.className = "alert alert-info border-0";

        try {
            const response = await fetch(`api.php?action=check_update&beta=${isBeta}&t=${Date.now()}`);
            if (!response.ok) throw new Error("Server-Fehler (HTTP " + response.status + ")");

            const data = await response.json();
            if (!data.success) throw new Error(data.message || "Fehler beim Update-Check");

            const modeName = data.mode || (isBeta === 'true' ? 'Beta' : 'Stable');
            const modeLower = modeName.toLowerCase();

            if (data.update_available) {
                statusDiv.className = "alert alert-warning border-0 d-flex align-items-center justify-content-between update-available-pulse";
                statusDiv.innerHTML = `
                    <div>
                        <div class="mb-1">
                            <span class="badge-${modeLower} me-2">${modeName}</span>
                            <strong style="color: #856404;">Update verfügbar!</strong>
                        </div>
                        <small class="text-dark">Version <strong>${data.remote}</strong> ist bereit (Installiert: ${data.local})</small>
                    </div>`;
                
                const pendingInput = document.getElementById('pending-download-url');
                if (pendingInput) pendingInput.value = data.download_url;

                const actions = document.getElementById('update-actions');
                if (actions) actions.style.display = 'block';
            } else {
                statusDiv.className = "alert alert-success border-0 d-flex align-items-center";
                statusDiv.innerHTML = `
                    <i class="ph ph-check-circle me-3" style="font-size: 1.5rem;"></i>
                    <div>
                        <span class="badge-${modeLower} me-2">${modeName}</span>
                        <span>VantixDash ist auf dem neuesten Stand (v${data.local})</span>
                    </div>`;
                
                const actions = document.getElementById('update-actions');
                if (actions) actions.style.display = 'none';
            }
        } catch (e) {
            console.error("Update-Fehler:", e);
            statusDiv.className = "alert alert-danger border-0";
            statusDiv.innerHTML = `<i class="ph ph-warning me-2"></i> <strong>Fehler:</strong> ${e.message}`;
        }
    },

    /**
     * Installiert das System-Update
     */
    async runUpdate() {
        const urlInput = document.getElementById('pending-download-url');
        if (!urlInput || !urlInput.value) {
            alert("Fehler: Keine Update-Informationen gefunden.");
            return;
        }

        if (!confirm("VantixDash jetzt aktualisieren?")) return;
        
        const btn = document.getElementById('start-update-btn');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="ph ph-circle-notch ph-spin me-2"></i> Installiere...';
        }

        try {
            const formData = new FormData();
            formData.append('url', urlInput.value);

            const response = await fetch('api.php?action=install_update', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                alert("Update erfolgreich! Seite wird neu geladen.");
                window.location.reload();
            } else {
                alert("Update fehlgeschlagen: " + data.message);
            }
        } catch (e) {
            alert("Verbindung zur API fehlgeschlagen.");
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="ph ph-download-simple me-2"></i> Update jetzt installieren';
            }
        }
    }
};

// Startpunkt
document.addEventListener('DOMContentLoaded', () => App.init());
