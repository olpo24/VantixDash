/**
 * VantixDash - Main Application JS
 */

document.addEventListener('DOMContentLoaded', () => {

    /**
     * TOAST NOTIFICATION SYSTEM
     */
    const showToast = (message, type = 'info', duration = 4000) => {
        // Container erstellen, falls nicht vorhanden
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        
        // Icon basierend auf Typ wählen (Phosphor Icons)
        const icons = {
            info: 'ph-info',
            success: 'ph-check-circle',
            warning: 'ph-warning',
            error: 'ph-warning-octagon'
        };
        
        toast.innerHTML = `
            <i class="ph ${icons[type] || 'ph-bell'}" style="font-size: 1.2rem;"></i>
            <span>${message}</span>
        `;

        container.appendChild(toast);

        // Entfernen nach Zeit oder Klick
        const removeToast = () => {
            toast.classList.add('fade-out');
            setTimeout(() => toast.remove(), 300);
        };

        toast.onclick = removeToast;
        setTimeout(removeToast, duration);
    };

    /**
     * ZENTRALER LOADING-HANDLER
     */
    const setLoading = (isLoading) => {
        const buttons = document.querySelectorAll('button:not(.close-btn)');
        const mainContent = document.querySelector('.content-wrapper');

        buttons.forEach(btn => {
            if (isLoading) {
                if (!btn.dataset.originalHtml) btn.dataset.originalHtml = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<i class="ph ph-circle-notch ph-spin"></i>';
            } else {
                if (btn.dataset.originalHtml) btn.innerHTML = btn.dataset.originalHtml;
                btn.disabled = false;
            }
        });

        document.body.style.cursor = isLoading ? 'wait' : 'default';
        if (mainContent) mainContent.style.opacity = isLoading ? '0.7' : '1';
    };

    /**
     * ZENTRALER API-HANDLER
     */
    const apiCall = async (action, method = 'GET', data = null) => {
        setLoading(true);
        const url = `api.php?action=${action}`;
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        const options = {
            method: method,
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            }
        };

        if (method === 'POST' && data) {
            options.body = data instanceof FormData ? data : new URLSearchParams(data);
        }

        try {
            const response = await fetch(url, options);
            if (!response.ok) {
                let errorMsg = 'Ein unbekannter Fehler ist aufgetreten.';
                try {
                    const errorJson = await response.json();
                    errorMsg = errorJson.message;
                } catch (e) {}
                handleHttpError(response.status, errorMsg);
                return null;
            }
            return await response.json();
        } catch (error) {
            console.error("API Error:", error);
            showToast('Netzwerkfehler: Server nicht erreichbar.', 'error');
            return null;
        } finally {
            setLoading(false);
        }
    };

    /**
     * Globales Handling für HTTP Status Codes
     */
    const handleHttpError = (status, message) => {
        switch (status) {
            case 401: 
                showToast('Sitzung abgelaufen. Weiterleitung zum Login...', 'warning');
                setTimeout(() => window.location.href = 'login.php?timeout=1', 1500);
                break;
            case 403: 
                showToast('Sicherheitsfehler: Zugriff verweigert.', 'error');
                break;
            case 429: 
                showToast('Zu viele Anfragen. Bitte kurz warten.', 'warning');
                break;
            default:
                showToast(message || 'Serverfehler aufgetreten.', 'error');
        }
    };

    // --- Window-Functions für die Views ---

    window.openDetails = async (id) => {
        const modal = document.getElementById('details-modal');
        const modalBody = document.getElementById('modal-body');
        modalBody.innerHTML = '<div style="text-align:center; padding:2rem;"><i class="ph ph-circle-notch ph-spin" style="font-size:2rem;"></i><p>Lade Details...</p></div>';
        modal.style.display = 'flex';

        const result = await apiCall(`refresh_site&id=${id}`);
        if (result && result.success && result.data) {
            const site = result.data;
            document.getElementById('modal-title').innerText = `Details: ${site.name}`;
            
            let html = `
                <div class="site-info-grid" style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:20px; font-size:0.9rem;">
                    <div class="card" style="padding:10px; background:var(--bg-color);"><strong>WordPress:</strong> ${site.wp_version}</div>
                    <div class="card" style="padding:10px; background:var(--bg-color);"><strong>PHP:</strong> ${site.php}</div>
                </div>
            `;
            // ... (Rest der HTML Generierung wie gehabt)
            modalBody.innerHTML = html;
        }
    };

    window.closeModal = () => document.getElementById('details-modal').style.display = 'none';

    window.refreshSite = async (id) => {
        const row = document.querySelector(`tr[data-id="${id}"]`);
        if (!row) return;
        const result = await apiCall(`refresh_site&id=${id}`);
        if (result && result.success) {
            showToast(`${result.data.name} erfolgreich aktualisiert.`, 'success');
            // DOM Update Logik...
        }
    };

    window.confirm2FA = async () => {
        const code = document.getElementById('2fa-verify-code').value;
        const formData = new FormData();
        formData.append('code', code);
        const result = await apiCall('verify_2fa', 'POST', formData);
        if (result && result.success) {
            showToast('2FA erfolgreich aktiviert!', 'success');
            setTimeout(() => location.reload(), 1000);
        }
    };

    window.disable2FA = async () => {
        if (!confirm('2FA wirklich deaktivieren?')) return; // Confirm bleibt für kritische Löschungen oft besser
        const result = await apiCall('disable_2fa');
        if (result && result.success) {
            showToast('2FA deaktiviert.', 'info');
            location.reload();
        }
    };

    window.clearLogs = async () => {
        if (!confirm('Alle Logs löschen?')) return;
        const result = await apiCall('clear_logs');
        if (result && result.success) {
            showToast('Logs wurden geleert.', 'success');
            window.loadLogs();
        }
    };

    window.onclick = (e) => { if (e.target.classList.contains('modal')) e.target.style.display = 'none'; };
});
