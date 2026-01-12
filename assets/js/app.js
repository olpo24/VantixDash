/**
 * VantixDash - Haupt-JavaScript (Native Version)
 */

document.addEventListener('DOMContentLoaded', function() {
    
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const updateContainer = document.getElementById('update-container');
    const updateBtn = document.getElementById('start-update-btn');
    const versionSpan = document.getElementById('new-version-number');

    /**
     * Update-Prüfung beim Laden
     */
    if (updateContainer) {
        fetch('api.php?action=check_update&beta=false')
            .then(res => res.json())
            .then(data => {
                if (data.success && data.update_available) {
                    versionSpan.innerText = data.remote;
                    updateBtn.setAttribute('data-url', data.download_url);
                    updateContainer.style.display = 'block'; // Natives Anzeigen
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

            // Button-Status: Loading (Nativ)
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
                    updateBtn.style.backgroundColor = '#28a745'; // Erfolgsgrün
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
        updateBtn.style.backgroundColor = '#dc3545'; // Fehlerrot
        updateBtn.style.color = '#fff';
    }
});
