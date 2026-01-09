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
     * Prüft auf System-Updates via GitHub
     */
    async checkUpdates() {
        const statusDiv = document.getElementById('update-status');
        if (!statusDiv) return;

        try {
            const response = await fetch('api.php?action=check_update');
            const data = await response.json();
            
            if (data.update_available) {
				const pendingInput = document.getElementById('pending-download-url');
    if (pendingInput) {
        pendingInput.value = data.download_url; // Hier wird die URL gesetzt!
        console.log("Download-URL gespeichert:", data.download_url); // Zum Testen im Browser (F12)
    }
                statusDiv.className = "alert alert-warning border-0";
                statusDiv.innerHTML = `<strong>Update verfügbar!</strong> Version ${data.remote} ist bereit (Aktuell: ${data.local})`;
                const actions = document.getElementById('update-actions');
                if (actions) actions.style.display = 'block';
            } else {
                statusDiv.className = "alert alert-success border-0";
                statusDiv.innerHTML = `VantixDash ist auf dem neuesten Stand (v${data.local})`;
            }
        } catch (e) {
            statusDiv.innerHTML = "Fehler bei der Update-Prüfung.";
        }
    },

/**
     * Installiert das System-Update
     */
    async runUpdate() {
        // 1. Das versteckte Eingabefeld suchen
        const urlInput = document.getElementById('pending-download-url');
        
        if (!urlInput) {
            console.error("HTML-Fehler: Das Feld 'pending-download-url' fehlt in der settings_general.php");
            alert("Systemfehler: Das notwendige HTML-Feld für das Update wurde nicht gefunden.");
            return;
        }

        const downloadUrl = urlInput.value;
        
        // 2. Prüfen, ob eine URL im Feld gespeichert ist
        if (!downloadUrl || downloadUrl.trim() === "") {
            console.warn("Update-Abbruch: Keine Download-URL vorhanden.");
            alert("Fehler: Keine Update-Informationen gefunden.\nBitte lade die Seite neu oder ändere kurz den Beta-Status, um die Suche neu zu starten.");
            return;
        }

        // 3. Bestätigung vom Nutzer einholen
        if (!confirm("VantixDash jetzt aktualisieren?\n\nDeine Konfiguration und deine Seiten-Daten bleiben erhalten. Systemdateien werden überschrieben.")) {
            return;
        }
        
        // 4. Button-Status visuell anpassen
        const btn = document.getElementById('start-update-btn');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="ph ph-circle-notch ph-spin me-2"></i> Update wird ausgeführt...';
        }

        try {
            // 5. POST-Anfrage an die API senden
            const formData = new FormData();
            formData.append('url', downloadUrl);

            const response = await fetch('api.php?action=install_update', {
                method: 'POST',
                body: formData
            });
            
            // Text-Antwort holen, falls PHP einen Fehler (kein JSON) sendet
            const textResponse = await response.text();
            let data;
            
            try {
                data = JSON.parse(textResponse);
            } catch (jsonErr) {
                console.error("PHP-Antwort ist kein gültiges JSON:", textResponse);
                throw new Error("Der Server hat keine gültige Antwort gesendet.");
            }
            
            if (data.success) {
                alert("Update erfolgreich abgeschlossen! Die Seite wird nun neu geladen.");
                window.location.reload();
            } else {
                alert("Update fehlgeschlagen: " + (data.message || "Unbekannter Fehler"));
            }
        } catch (e) {
            console.error("Update-Fehler:", e);
            alert("Verbindung zur API fehlgeschlagen oder Serverfehler.\nDetails findest du in der Browser-Konsole.");
        } finally {
            // Button wieder freigeben, falls es kein Reload gab
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="ph ph-download-simple me-2"></i> Update jetzt installieren';
            }
        }
    },
};

// Startpunkt
document.addEventListener('DOMContentLoaded', () => App.init());
