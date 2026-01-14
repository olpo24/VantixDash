/**
 * VantixDash - Main Application JS
 */

document.addEventListener('DOMContentLoaded', () => {

    /**
     * ZENTRALER API-HANDLER
     */
    const apiCall = async (action, method = 'GET', data = null) => {
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
            const result = await response.json();

            if (!response.ok) {
                handleHttpError(response.status, result.message);
                return null;
            }

            return result;
        } catch (error) {
            console.error("API Error:", error);
            showToast('Netzwerkfehler oder Server nicht erreichbar', 'error');
            return null;
        }
    };

    /**
     * Globales Handling für HTTP Status Codes
     */
    const handleHttpError = (status, message) => {
        switch (status) {
            case 401: 
                alert('Sitzung abgelaufen. Bitte logge dich erneut ein.');
                window.location.href = 'login.php?timeout=1';
                break;
            case 403: 
                showToast('Sicherheitsfehler: CSRF Token ungültig. Bitte Seite neu laden.', 'error');
                break;
            case 429: 
                showToast('Zu viele Anfragen. Bitte warte kurz.', 'warning');
                break;
            default:
                showToast(message || 'Ein Fehler ist aufgetreten.', 'error');
        }
    };

    const showToast = (message, type = 'info') => {
        console.log(`[${type.toUpperCase()}] ${message}`);
        if(type === 'error') alert(message);
    };

    /**
     * MODAL FUNKTIONEN
     */
    window.openDetails = async (id) => {
        const modal = document.getElementById('details-modal');
        const modalBody = document.getElementById('modal-body');
        const modalTitle = document.getElementById('modal-title');
        
        modalBody.innerHTML = '<div style="text-align:center; padding:2rem;"><i class="ph ph-circle-notch animate-spin" style="font-size:2rem;"></i><p>Lade Details...</p></div>';
        modal.style.display = 'flex';

        const result = await apiCall(`refresh_site&id=${id}`);

        if (result && result.success && result.data) {
            const site = result.data;
            modalTitle.innerText = `Details: ${site.name}`;

            let html = `
                <div class="site-info-grid" style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:20px; font-size:0.9rem;">
                    <div class="card" style="padding:10px; background:var(--bg-color);"><strong>WordPress:</strong> ${site.wp_version}</div>
                    <div class="card" style="padding:10px; background:var(--bg-color);"><strong>PHP:</strong> ${site.php}</div>
                </div>
            `;

            html += `<h4 style="margin-bottom:10px; display:flex; align-items:center; gap:8px;"><i class="ph ph-plug"></i> Plugins (${site.updates.plugins})</h4>`;
            if (site.plugin_list && site.plugin_list.length > 0) {
                html += `<div class="plugin-list" style="display:grid; gap:8px; margin-bottom:20px;">`;
                site.plugin_list.forEach(plugin => {
                    html += `
                        <div class="item-row" style="padding:10px; border:1px solid var(--border-color); border-radius:8px; display:flex; justify-content:space-between; align-items:center;">
                            <div><div style="font-weight:600;">${plugin.name}</div><div style="font-size:0.8rem; color:var(--text-muted);">${plugin.old_version}</div></div>
                            <div style="text-align:right;"><span class="badge" style="background:rgba(255,107,107,0.1); color:#ff6b6b; padding:4px 8px; border-radius:5px; font-size:0.8rem; font-weight:600;"><i class="ph ph-arrow-right"></i> ${plugin.new_version}</span></div>
                        </div>`;
                });
                html += `</div>`;
            } else { html += `<p style="color:var(--text-muted); font-size:0.9rem; margin-bottom:20px;">Alle Plugins aktuell.</p>`; }

            modalBody.innerHTML = html;
        }
    };

    window.closeModal = () => document.getElementById('details-modal').style.display = 'none';

    /**
     * SITE REFRESH LOGIK
     */
    window.refreshSite = async (id) => {
        const row = document.querySelector(`tr[data-id="${id}"]`);
        if (!row) return;

        const btn = row.querySelector('.refresh-single');
        const icon = btn.querySelector('i');
        
        icon.classList.add('ph-spin');
        btn.disabled = true;

        const result = await apiCall(`refresh_site&id=${id}`);

        if (result && result.success && result.data) {
            const site = result.data;
            row.querySelector('.status-indicator').className = `status-indicator ${site.status}`;
            
            const pills = row.querySelectorAll('.update-pill');
            const updateCounts = [site.updates.core, site.updates.plugins, site.updates.themes];

            updateCounts.forEach((count, idx) => {
                if (pills[idx]) {
                    pills[idx].querySelector('span').innerText = count;
                    count > 0 ? pills[idx].classList.add('has-updates') : pills[idx].classList.remove('has-updates');
                }
            });

            if (row.cells[3]) row.cells[3].innerHTML = `<span class="text-muted" style="font-size: 0.9rem;">v${site.wp_version}</span>`;
        }

        icon.classList.remove('ph-spin');
        btn.disabled = false;
    };

    window.refreshAllSites = async () => {
        const rows = document.querySelectorAll('tbody tr[data-id]');
        const refreshIcon = document.getElementById('refresh-all-icon');
        if (rows.length === 0) return;

        if (refreshIcon) refreshIcon.classList.add('ph-spin');
        const promises = Array.from(rows).map(row => window.refreshSite(row.getAttribute('data-id')));

        await Promise.all(promises);
        if (refreshIcon) refreshIcon.classList.remove('ph-spin');
    };

    /**
     * REMOTE LOGIN
     */
    window.loginToSite = async (id) => {
        const result = await apiCall(`login_site&id=${id}`);
        if (result && result.success && result.login_url) {
            window.open(result.login_url, '_blank');
        }
    };

    /**
     * 2FA SETUP LOGIK
     */
    window.start2FASetup = async () => {
        const result = await apiCall('setup_2fa');
        if (result && result.success) {
            document.getElementById('2fa-qr-img').src = result.qrCodeUrl;
            document.getElementById('2fa-secret-text').innerText = result.secret;
            document.getElementById('2fa-setup-modal').style.display = 'flex';
        }
    };

    window.confirm2FA = async () => {
        const code = document.getElementById('2fa-verify-code').value;
        const formData = new FormData();
        formData.append('code', code);

        const result = await apiCall('verify_2fa', 'POST', formData);
        if (result && result.success) {
            alert('2FA erfolgreich aktiviert!');
            location.reload();
        }
    };

    window.disable2FA = async () => {
        if (!confirm('2FA wirklich deaktivieren?')) return;
        const result = await apiCall('disable_2fa');
        if (result && result.success) location.reload();
    };

    window.close2FAModal = () => document.getElementById('2fa-setup-modal').style.display = 'none';

    /**
     * LOG-VIEWER LOGIK
     */
    window.loadLogs = async () => {
        const viewer = document.getElementById('log-viewer');
        if (!viewer) return;
        
        viewer.innerText = 'Lade Logs...';
        const result = await apiCall('get_logs');
        
        if (result && result.success) {
            viewer.innerText = result.logs || 'Keine Einträge.';
            viewer.scrollTop = viewer.scrollHeight; // Auto-Scroll nach unten
        }
    };

    window.clearLogs = async () => {
        if (!confirm('Möchtest du alle Log-Einträge wirklich löschen?')) return;
        const result = await apiCall('clear_logs');
        if (result && result.success) {
            window.loadLogs();
        }
    };

    // Globaler Klick-Handler für Modals
    window.onclick = (event) => {
        if (event.target.classList.contains('modal')) event.target.style.display = 'none';
    };
});
