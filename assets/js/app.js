/**
 * VantixDash - Haupt-JavaScript
 * Verwaltet Update-Prüfungen und Installationen
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // 1. CSRF-Token zentral abrufen
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    // 2. Elemente für das Update-System
    const updateContainer = document.getElementById('update-container');
    const updateBtn = document.getElementById('start-update-btn');
    const versionSpan = document.getElementById('new-version-number');

    /**
     * FUNKTION: Update-Prüfung beim Laden der Seite
     */
    function checkForUpdates() {
        // Hinweis: beta=true/false könnte man auch aus einem Config-Attribut lesen
        fetch('api.php?action=check_update&beta=false')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.update_available) {
                    if (updateContainer && versionSpan && updateBtn) {
                        versionSpan.innerText = data.remote;
                        updateBtn.setAttribute('data-url', data.download_url);
                        updateContainer.classList.remove('d-none');
                    }
                }
            })
            .catch(error => console.error('Fehler bei Update-Prüfung:', error));
    }

    /**
     * FUNKTION: Update-Installation starten
     */
    if (updateBtn) {
        updateBtn.addEventListener('click', function() {
            const downloadUrl = this.getAttribute('data-url');

            if (!downloadUrl) {
                alert('Keine Download-URL gefunden.');
                return;
            }

            if (!confirm('Möchtest du das Update v' + versionSpan.innerText + ' jetzt installieren?\nAlle Systemdateien werden überschrieben. Deine Einstellungen bleiben erhalten.')) {
                return;
            }

            // UI-Status: Laden
            updateBtn.disabled = true;
            updateBtn.innerHTML = `
                <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
                Installiere Update...
            `;

            // FormData für den POST-Request vorbereiten
            const formData = new URLSearchParams();
            formData.append('url', downloadUrl);

            fetch('api.php?action=install_update', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Erfolg: Button grün färben und Seite neu laden
                    updateBtn.classList.replace('btn-primary', 'btn-success');
                    updateBtn.innerHTML = '<i class="bi bi-check-circle me-2"></i> Update erfolgreich!';
                    
                    // Kurze Pause für das visuelle Feedback, dann Reload
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    // Fehlerfall
                    alert('Update fehlgeschlagen: ' + data.message);
                    resetUpdateBtn(updateBtn);
                }
            })
            .catch(error => {
                console.error('Install Error:', error);
                alert('Ein kritischer Netzwerkfehler ist aufgetreten.');
                resetUpdateBtn(updateBtn);
            });
        });
    }

    // Hilfsfunktion: Button zurücksetzen
    function resetUpdateBtn(btn) {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-cloud-download me-2"></i> Erneut versuchen';
        btn.classList.add('btn-danger');
    }

    // Init: Update-Check sofort ausführen
    if (updateContainer) {
        checkForUpdates();
    }
});
