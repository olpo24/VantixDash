/**
 * VantixDash - Main Application JS
 * Fokus: Security, UI-Modals & Live-Updates
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
     * DASHBOARD ACTIONS
     */
    window.refreshSite = async (id, isBatch = false) => {
        if (busySites.has(id)) return;
        busySites.add(id);

        const row = getRowById(id);
        const btnIcon = row?.querySelector('.refresh-single i');
        if (btnIcon) btnIcon.classList.add('ph-spin');

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

        if (btnIcon) btnIcon.classList.remove('ph-spin');
        setTimeout(() => busySites.delete(id), 1000); 
        return result;
    };

    window.refreshAllSites = async () => {
        const icon = document.getElementById('refresh-all-icon');
        if (icon?.classList.contains('ph-spin')) return;

        const rows = document.querySelectorAll('tr[data-id]');
        icon?.classList.add('ph-spin');
        showToast('Massenprüfung gestartet...', 'info');

        for (const row of rows) {
            await window.refreshSite(row.dataset.id, true);
        }

        icon?.classList.remove('ph-spin');
        showToast('Alle Seiten geprüft.', 'success');
    };

   /**
 * MODAL & DETAILS
 */
window.openDetails = async (id) => {
    const modal = document.getElementById('details-modal');
    const modalBody = document.getElementById('modal-body');
    if (!modal) return;

    modalBody.innerHTML = '<div class="modal-loading"><i class="ph ph-circle-notch ph-spin"></i><p>Lade Details...</p></div>';
    modal.style.display = 'flex';

    const res = await apiCall(`refresh_site&id=${encodeURIComponent(id)}`);
    if (res?.success) {
        const site = res.data;
        document.getElementById('modal-title').innerText = site.name;

        let html = `
            <div class="modal-detail-wrapper">
                <div class="detail-meta-header">
                    <span><strong>WP:</strong> ${escapeHTML(site.wp_version)}</span>
                    <span><strong>PHP:</strong> ${escapeHTML(site.php || 'N/A')}</span>
                </div>
                
                <div class="detail-grid">
                    <section>
                        <h4><i class="ph ph-plug"></i> Plugins (${site.updates.plugins})</h4>
                        ${renderUpdateList(site.plugin_list || [])}
                    </section>
                    <section>
                        <h4><i class="ph ph-palette"></i> Themes (${site.updates.themes})</h4>
                        ${renderUpdateList(site.theme_list || [])}
                    </section>
                </div>
            </div>`;
        modalBody.innerHTML = html;
    } else {
        modalBody.innerHTML = '<p class="alert alert-error">Details konnten nicht geladen werden.</p>';
    }
};

const renderUpdateList = (items) => {
    if (!items || items.length === 0) return '<p class="text-muted small">Alles aktuell.</p>';
    let list = '<ul class="modal-update-list">';
    items.forEach(item => {
        list += `
            <li>
                <span class="item-name">${escapeHTML(item.name)}</span>
                <span class="item-version">${escapeHTML(item.old_version)} <i class="ph ph-arrow-right"></i> <strong>${escapeHTML(item.new_version)}</strong></span>
            </li>`;
    });
    return list + '</ul>';
};
    window.closeModal = () => {
        document.getElementById('details-modal').style.display = 'none';
    };

    /**
     * UI NAVIGATION
     */
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('sidebar');

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            // Für Mobile Support
            sidebar.classList.toggle('show-mobile');
        });
    }

    document.querySelectorAll('.submenu-toggle').forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            const submenu = this.nextElementSibling;
            const caret = this.querySelector('.caret-icon');
            
            if (submenu) {
                submenu.classList.toggle('show');
                if (caret) {
                    caret.style.transform = submenu.classList.contains('show') ? 'rotate(180deg)' : 'rotate(0deg)';
                }
            }
        });
    });

    // Schließen des Modals bei Klick auf das Overlay
    window.addEventListener('click', (e) => {
        const modal = document.getElementById('details-modal');
        if (e.target === modal) closeModal();
    });
});
