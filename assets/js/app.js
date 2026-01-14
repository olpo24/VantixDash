/**
 * VantixDash - Main Application JS (Updated with Security & Live-Updates)
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
        // Schützt vor Sonderzeichen in der ID bei der DOM-Suche
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
        
        // message wird hier sicher als Text eingefügt
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
            
            // Nutzt .innerText für Sicherheit
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
    window.apiCall = async (action, method = 'GET', data = null) => {
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
            setLoading(false);
        }
    };

    const handleHttpError = (status, message) => {
        switch (status) {
            case 401: 
                showToast('Sitzung abgelaufen. Weiterleitung...', 'warning');
                setTimeout(() => window.location.href = 'login.php?timeout=1', 1500);
                break;
            case 422: // SiteRefreshException Fehler
                showToast(message, 'warning');
                break;
            default:
                showToast(message || 'Ein Fehler ist aufgetreten.', 'error');
        }
    };

    // --- Window-Functions für die Views ---

    window.refreshSite = async (id) => {
        const result = await apiCall(`refresh_site&id=${encodeURIComponent(id)}`);
        if (result && result.success) {
            const site = result.data;
            showToast(`${site.name} erfolgreich aktualisiert.`, 'success');
            
            // LIVE-UPDATE der Tabellenzeile
            const row = getRowById(id);
            if (row) {
                // Nutzt .textContent für Sicherheit gegen XSS
                const wpCell = row.querySelector('.wp-version');
                const phpCell = row.querySelector('.php-version');
                const lastCheckCell = row.querySelector('.last-check');
                
                if (wpCell) wpCell.textContent = site.wp_version;
                if (phpCell) phpCell.textContent = site.php;
                if (lastCheckCell) lastCheckCell.textContent = site.last_check;
                
                // Status-Badge Update
                const statusCell = row.querySelector('.status-badge');
                if (statusCell) {
                    statusCell.className = `status-badge status-${site.status}`;
                    statusCell.textContent = site.status.toUpperCase();
                }
            }
        }
    };

    window.openDetails = async (id) => {
        const modal = document.getElementById('details-modal');
        const modalBody = document.getElementById('modal-body');
        if (!modal || !modalBody) return;

        modalBody.innerHTML = '<div style="text-align:center; padding:2rem;"><i class="ph ph-circle-notch ph-spin" style="font-size:2rem;"></i><p>Lade Details...</p></div>';
        modal.style.display = 'flex';

        const result = await apiCall(`refresh_site&id=${encodeURIComponent(id)}`);
        if (result && result.success && result.data) {
            const site = result.data;
            document.getElementById('modal-title').innerText = `Details: ${site.name}`;
            
            modalBody.innerHTML = `
                <div class="site-info-grid" style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:20px; font-size:0.9rem;">
                    <div class="card" style="padding:10px; background:var(--bg-color);"><strong>WordPress:</strong> ${escapeHTML(site.wp_version)}</div>
                    <div class="card" style="padding:10px; background:var(--bg-color);"><strong>PHP:</strong> ${escapeHTML(site.php)}</div>
                </div>
            `;
        }
    };

    // ... Restliche Funktionen (closeModal, confirm2FA, etc.) bleiben gleich, 
    // sollten aber encodeURIComponent(id) bei API-Calls nutzen.

    window.closeModal = () => {
        const detailsModal = document.getElementById('details-modal');
        const confirmModal = document.getElementById('confirm-modal');
        if (detailsModal) detailsModal.style.display = 'none';
        if (confirmModal) confirmModal.style.display = 'none';
    };

    window.onclick = (e) => { 
        if (e.target.classList.contains('modal')) {
            e.target.style.display = 'none'; 
        }
    };
});
