/**
 * VantixDash - Main Application JS
 * Fokus: Security, Live-Updates & Request-Control (Anti-Spam)
 */

document.addEventListener('DOMContentLoaded', () => {

    // Helper: Verhindert Mehrfach-Klicks auf die gleiche Funktion
    const busySites = new Set();

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
     * TOAST & UI HELPERS
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
        const icons = { info: 'ph-info', success: 'ph-check-circle', warning: 'ph-warning', error: 'ph-warning-octagon' };
        toast.innerHTML = `<i class="ph ${icons[type] || 'ph-bell'}" style="font-size: 1.2rem;"></i><span>${escapeHTML(message)}</span>`;
        container.appendChild(toast);
        const removeToast = () => { toast.classList.add('fade-out'); setTimeout(() => toast.remove(), 300); };
        toast.onclick = removeToast;
        setTimeout(removeToast, duration);
    };

    const setLoading = (isLoading) => {
        const buttons = document.querySelectorAll('button:not(.close-btn)');
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
    };

    /**
     * ZENTRALER API-HANDLER
     */
    window.apiCall = async (action, method = 'GET', data = null, silent = false) => {
        if (!silent) setLoading(true);
        const url = `api.php?action=${action}`;
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        try {
            const response = await fetch(url, {
                method: method,
                headers: { 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                body: (method === 'POST' && data) ? (data instanceof FormData ? data : new URLSearchParams(data)) : null
            });
            const result = await response.json();
            if (!response.ok) { handleHttpError(response.status, result.message); return null; }
            return result;
        } catch (error) {
            showToast('Netzwerkfehler.', 'error');
            return null;
        } finally {
            if (!silent) setLoading(false);
        }
    };

    const handleHttpError = (status, message) => {
        if (status === 401) setTimeout(() => window.location.href = 'login.php?timeout=1', 1500);
        showToast(message || 'Fehler aufgetreten', status === 422 ? 'warning' : 'error');
    };

    /**
     * DASHBOARD ACTIONS MIT DEBOUNCING / LOCKING
     */

    window.refreshSite = async (id, isBatch = false) => {
        // ANTI-SPAM: Wenn diese ID gerade verarbeitet wird, brich ab.
        if (busySites.has(id)) return;
        busySites.add(id);

        const row = getRowById(id);
        const btn = row?.querySelector('.refresh-single');
        if (btn) btn.classList.add('ph-spin');

        const result = await apiCall(`refresh_site&id=${encodeURIComponent(id)}`, 'GET', null, isBatch);
        
        if (result && result.success) {
            const site = result.data;
            if (!isBatch) showToast(`${site.name} aktualisiert.`, 'success');
            
            if (row) {
                row.querySelector('.wp-version').textContent = 'v' + site.wp_version;
                const statusInd = row.querySelector('.status-indicator');
                statusInd.className = `status-indicator status-badge ${site.status}`;
                
                row.querySelector('.update-count-core').textContent = site.updates.core;
                row.querySelector('.update-count-plugins').textContent = site.updates.plugins;
                row.querySelector('.update-count-themes').textContent = site.updates.themes;

                row.querySelectorAll('.update-pill').forEach(pill => {
                    const count = parseInt(pill.querySelector('span').textContent);
                    pill.classList.toggle('has-updates', count > 0);
                });
            }
        }

        if (btn) btn.classList.remove('ph-spin');
        
        // LOCK entfernen nach kurzem Cooldown (1 Sekunde), um "Klick-Gewitter" zu vermeiden
        setTimeout(() => busySites.delete(id), 1000); 
        return result;
    };

    window.refreshAllSites = async () => {
        const icon = document.getElementById('refresh-all-icon');
        if (icon?.classList.contains('ph-spin')) return; // Bereits am Laufen

        const rows = document.querySelectorAll('tr[data-id]');
        icon?.classList.add('ph-spin');
        showToast('Massenprüfung gestartet...', 'info');

        for (const row of rows) {
            await window.refreshSite(row.dataset.id, true);
        }

        icon?.classList.remove('ph-spin');
        showToast('Prüfung abgeschlossen.', 'success');
    };

    // Weitere Standard-Funktionen
    window.openDetails = async (id) => {
        const modal = document.getElementById('details-modal');
        const modalBody = document.getElementById('modal-body');
        if (!modal) return;
        modalBody.innerHTML = '<i class="ph ph-circle-notch ph-spin"></i>';
        modal.style.display = 'flex';
        const res = await apiCall(`refresh_site&id=${encodeURIComponent(id)}`);
        if (res?.success) {
            document.getElementById('modal-title').innerText = res.data.name;
            modalBody.innerHTML = `<div class="info-card">WP: ${escapeHTML(res.data.wp_version)} | PHP: ${escapeHTML(res.data.php)}</div>`;
        }
    };

    window.closeModal = () => document.getElementById('details-modal').style.display = 'none';
// Sidebar Toggle (Auf/Zuklappen)
const sidebarToggle = document.getElementById('sidebar-toggle');
const sidebar = document.getElementById('sidebar');
if (sidebarToggle) {
    sidebarToggle.addEventListener('click', () => {
        sidebar.classList.toggle('collapsed');
    });
}

// Submenu Toggle
document.querySelectorAll('.submenu-toggle').forEach(toggle => {
    toggle.addEventListener('click', function(e) {
        e.preventDefault();
        const submenu = this.nextElementSibling;
        const caret = this.querySelector('.caret-icon');
        
        submenu.classList.toggle('show');
        if (caret) {
            caret.style.transform = submenu.classList.contains('show') ? 'rotate(180deg)' : 'rotate(0deg)';
        }
    });
	});
	});
