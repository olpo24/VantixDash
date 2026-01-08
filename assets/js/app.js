/**
 * assets/js/app.js
 * Hauptsteuerung für das VantixDash Dashboard
 */

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
                    // Falls ein neuer Key generiert wurde, anzeigen
                    document.getElementById('generatedKeyDisplay').innerText = result.api_key;
                    document.getElementById('keySuccessModal').showModal();
                } else {
                    // Ohne neuen Key einfach Seite neu laden
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
            const response = await fetch('api.php?action=delete_site', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${siteId}`
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

        // Icon zum Drehen bringen
        const icon = event ? event.currentTarget.querySelector('i') : null;
        if (icon) icon.classList.add('ph-spin');

        try {
            const formData = new FormData();
            formData.append('id', siteId);

            const response = await fetch('api.php?action=refresh_site', {
                method: 'POST',
                body: formData // Wichtig: Als FormData senden!
            });
            
            const result = await response.json();

            if (result.success) {
                // Lokale Daten aktualisieren
                this.sites = this.sites.map(s => s.id === siteId ? result.site : s);
                TableManager.renderDashboardTable(this.sites);
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
            // Child-Plugin anfragen
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
    }
};

// Startpunkt
document.addEventListener('DOMContentLoaded', () => App.init());
