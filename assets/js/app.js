/**
 * VantixDash - Main Application JS
 * Fokus: Sicherheit, Live-Updates & Massenverarbeitung
 */

document.addEventListener('DOMContentLoaded', () => {

    /**
     * SECURITY HELPERS
     */
    const escapeHTML = (str) => {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    };

    const getRowById = (id) => {
        return document.querySelector(`tr[data-id="${CSS.escape(id)}"]`);
    };

    /**
     * TOAST NOTIFICATION SYSTEM
     */
    window.showToast = (message, type = 'info', duration = 4000) => {
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        
        const icons = {
            info: 'ph-info',
            success: 'ph-check-circle',
            warning: 'ph-warning',
            error: 'ph-warning-octagon'
        };
        
        toast.innerHTML = `
            <i class="ph ${icons[type] || 'ph-bell'}" style="font-size: 1.2rem;"></i>
            <span>${escapeHTML(message)}</span>
        `;

        container.appendChild(toast);

        const removeToast = () => {
            toast.classList.add('fade-out');
            setTimeout(() => toast.remove(), 300);
        };

        toast.onclick = removeToast;
        setTimeout(removeToast, duration);
    };

    /**
     * GLOBAL CONFIRM SYSTEM
     */
    window.showConfirm = (title, message, options = {}) => {
        return new Promise((resolve) => {
            const modal = document.getElementById('confirm-modal');
            if (!modal) {
                resolve(confirm(message));
                return;
            }

            const btnOk = document.getElementById('confirm-ok');
            const btnCancel = document.getElementById('confirm-cancel');
            
            document.getElementById('confirm-title').innerText = title;
            document.getElementById('confirm-message').innerText = message;
            
            btnOk.innerText = options.okText || 'Bestätigen';
            btnOk.className = options.isDanger ? 'btn-danger' : 'btn-primary';
            
            modal.style.display = 'flex';

            const handleResponse = (result) => {
                modal.style.display = 'none';
                btnOk.onclick = null;
                btnCancel.onclick = null;
                resolve(result);
            };

            btnOk.onclick = () => handleResponse(true);
            btnCancel.onclick = () => handleResponse(false);
        });
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
    window.apiCall = async (action, method = 'GET', data = null, silent = false) => {
        if (!silent) setLoading(true);
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
                handleHttpError(response.status, result.message || 'Serverfehler');
                return null;
            }
            return result;
        } catch (error) {
            console.error("API Error:", error);
            showToast('Netzwerkfehler: Server nicht erreichbar.', 'error');
            return null;
        } finally {
            if (!silent) setLoading(false);
        }
    };

    const handleHttpError = (status, message) => {
        switch (status) {
            case 401: 
                showToast('Sitzung abgelaufen. Weiterleitung...', 'warning');
                setTimeout(() => window.location.href = 'login.php?timeout=1', 1500);
                break;
            case 422:
                showToast(message, 'warning');
                break;
            default:
                showToast(message || 'Ein Fehler ist aufgetreten.', 'error');
        }
    };

    /**
     * DASHBOARD ACTIONS
     */

    // Einzelseite aktualisieren mit DOM-Injektion
    window.refreshSite = async (id, isBatch = false) => {
        const row = getRowById(id);
        const btn = row?.querySelector('.refresh-single');
        
        if (btn && !isBatch) btn.classList.add('ph-spin');

        const result = await apiCall(`refresh_site&id=${encodeURIComponent(id)}`, 'GET', null, isBatch);
        
        if (result && result.success) {
            const site = result.data;
            if (!isBatch) showToast(`${site.name} aktualisiert.`, 'success');
            
            if (row) {
                // Version & Status live updaten
                const wpCell = row.querySelector('.wp-version');
                const statusIndicator = row.querySelector('.status-indicator');
                
                if (wpCell) wpCell.textContent = 'v' + site.wp_version;
                if (statusIndicator) {
                    statusIndicator.className = `status-indicator status-badge ${site.status}`;
                    statusIndicator.title = `Status: ${site.status}`;
                }

                // Update-Zahlen live updaten
                if (site.updates) {
                    row.querySelector('.update-count-core').textContent = site.updates.core;
                    row.querySelector('.update-count-plugins').textContent = site.updates.plugins;
                    row.querySelector('.update-count-themes').textContent = site.updates.themes;
                    
                    // Pill-Farben anpassen
                    row.querySelectorAll('.update-pill').forEach(pill => {
                        const count = parseInt(pill.querySelector('span').textContent);
                        pill.classList.toggle('has-updates', count > 0);
                    });
                }
            }
        }
        if (btn) btn.classList.remove('ph-spin');
        return result;
    };

    // Alle Seiten nacheinander prüfen
    window.refreshAllSites = async () => {
        const rows = document.querySelectorAll('tr[data-id]');
        const icon = document.getElementById('refresh-all-icon');
        
        if (icon) icon.classList.add('ph-spin');
        showToast('Massenprüfung gestartet...', 'info');

        for (const row of rows) {
            const id = row.dataset.id;
            row.style.backgroundColor = 'var(--bg-light)'; // Optisches Feedback
            await window.refreshSite(id, true);
            row.style.backgroundColor = '';
        }

        if (icon) icon.classList.remove('ph-spin');
        showToast('Alle Seiten wurden geprüft.', 'success');
    };

    window.openDetails = async (id) => {
        const modal = document.getElementById('details-modal');
        const modalBody = document.getElementById('modal-body');
        if (!modal || !modalBody) return;

        modalBody.innerHTML = '<div class="text-center p-4"><i class="ph ph-circle-notch ph-spin" style="font-size:2rem;"></i></div>';
        modal.style.display = 'flex';

        const result = await apiCall(`refresh_site&id=${encodeURIComponent(id)}`);
        if (result && result.success) {
            const site = result.data;
            document.getElementById('modal-title').innerText = `Details: ${site.name}`;
            modalBody.innerHTML = `
                <div class="site-info-grid">
                    <div class="info-card"><strong>WP:</strong> ${escapeHTML(site.wp_version)}</div>
                    <div class="info-card"><strong>PHP:</strong> ${escapeHTML(site.php || 'N/A')}</div>
                    <div class="info-card"><strong>URL:</strong> ${escapeHTML(site.url)}</div>
                    <div class="info-card"><strong>Letzter Check:</strong> ${escapeHTML(site.last_check)}</div>
                </div>
            `;
        }
    };

    window.loginToSite = async (id) => {
        const result = await apiCall(`get_login_url&id=${encodeURIComponent(id)}`);
        if (result && result.url) {
            window.open(result.url, '_blank');
        }
    };

    window.clearLogs = async () => {
        const confirmed = await showConfirm('Logs löschen', 'Sollen alle Einträge entfernt werden?', { isDanger: true });
        if (confirmed) {
            const result = await apiCall('clear_logs');
            if (result?.success) {
                showToast('Logs geleert.', 'success');
                if (typeof window.loadLogs === 'function') window.loadLogs();
            }
        }
    };

    window.closeModal = () => {
        document.querySelectorAll('.modal-overlay').forEach(m => m.style.display = 'none');
    };

    window.onclick = (e) => { 
        if (e.target.classList.contains('modal-overlay')) closeModal(); 
    };
});
