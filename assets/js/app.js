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
                
                // Key im Erfolgs-Modal anzeigen
                document.getElementById('generatedKeyDisplay').innerText = result.api_key;
                document.getElementById('keySuccessModal').showModal();
                
                this.loadSites(); // UI im Hintergrund aktualisieren
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
     * Prüft auf System-Updates via GitHub (unter Berücksichtigung des Beta-Toggles)
     */
    async checkUpdates() {
        const statusDiv = document.getElementById('update-status');
        if (!statusDiv) return;

        // 1. Prüfen, ob der Beta-Toggle aktiv ist
        const betaToggle = document.getElementById('beta-toggle');
        const isBeta = betaToggle ? betaToggle.checked : false;

        // Optisches Feedback, dass geprüft wird
        statusDiv.innerHTML = '<i class="ph ph-circle-notch ph-spin me-2"></i> Prüfe auf Updates...';

        try {
            // 2. Beta-Parameter und Zeitstempel (gegen Cache) an API senden
            const response = await fetch(`api.php?action=check_update&beta=${isBeta}&t=${Date.now()}`);
            const data = await response.json();
            
            // 3. Fallback für den Modus-Text (falls mode mal fehlen sollte)
            const modeName = data.mode || (isBeta ? 'Beta' : 'Stable');

            if (data.update_available) {
                statusDiv.className = "alert alert-warning border-0 d-flex align-items-center justify-content-between";
                statusDiv.innerHTML = `
                    <div>
                        <strong>${modeName}-Update verfügbar!</strong> <br>
                        <small>Version ${data.remote} ist bereit (Installiert: ${data.local})</small>
                    </div>`;
                
                // Download-URL für den install-Prozess zwischenspeichern
                const pendingInput = document.getElementById('pending-download-url');
                if (pendingInput) pendingInput.value = data.download_url;

                const actions = document.getElementById('update-actions');
                if (actions) actions.style.display = 'block';
            } else {
                statusDiv.className = "alert alert-success border-0";
                // Hier wird jetzt data.mode genutzt:
                statusDiv.innerHTML = `<i class="ph ph-check-circle me-2"></i> VantixDash ${modeName} ist auf dem neuesten Stand (v${data.local})`;
                
                const actions = document.getElementById('update-actions');
                if (actions) actions.style.display = 'none';
            }
        } catch (e) {
            console.error("Update-Fehler:", e);
            statusDiv.innerHTML = '<i class="ph ph-warning me-2"></i> Fehler bei der Update-Prüfung.';
        }
    },

   /**
     * Installiert das System-Update
     */
    async runUpdate() {
        // Die URL aus dem versteckten Feld holen, das in checkUpdates() befüllt wurde
        const downloadUrl = document.getElementById('pending-download-url')?.value;
        
        if (!downloadUrl) {
            alert("Fehler: Keine Download-URL gefunden. Bitte prüfe erst erneut auf Updates.");
            return;
        }

        if (!confirm("Update jetzt installieren? Deine Seiten-Daten bleiben erhalten.")) return;
        
        const btn = document.getElementById('start-update-btn');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="ph ph-circle-notch ph-spin me-2"></i> Installiere...';
        }

        try {
            // Die URL als POST-Parameter senden
            const formData = new FormData();
            formData.append('url', downloadUrl);

            const response = await fetch('api.php?action=install_update', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                alert("Update erfolgreich abgeschlossen!");
                window.location.reload();
            } else {
                alert("Fehler beim Entpacken: " + data.message);
            }
        } catch (e) {
            console.error(e);
            alert("Verbindung zur API fehlgeschlagen.");
        } finally {
            if (btn) btn.disabled = false;
        }
    },

// Startpunkt
document.addEventListener('DOMContentLoaded', () => App.init());
