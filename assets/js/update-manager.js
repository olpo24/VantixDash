/**
 * VantixDash - Update Manager
 * Handles automatic updates from GitHub
 */

const UpdateManager = {
    currentVersion: null,
    latestRelease: null,
    
    /**
     * Initialize Update Manager
     */
    init() {
        this.checkOnPageLoad();
        this.attachEventListeners();
    },
    
    /**
     * Check for updates on settings page load
     */
    checkOnPageLoad() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('view') === 'settings_general') {
            this.checkForUpdates();
        }
    },
    
    /**
     * Attach button event listeners
     */
    attachEventListeners() {
        // Manual check button
        const checkBtn = document.getElementById('check-updates-btn');
        if (checkBtn) {
            checkBtn.addEventListener('click', () => this.checkForUpdates());
        }
        
        // Install update button
        const installBtn = document.getElementById('install-update-btn');
        if (installBtn) {
            installBtn.addEventListener('click', () => this.installUpdate());
        }
    },
    
    /**
     * Check for available updates
     */
    async checkForUpdates() {
        const channel = localStorage.getItem('vantix_update_channel') || 'stable';
        const banner = document.getElementById('update-banner');
        const btn = document.getElementById('check-updates-btn');
        
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="ph ph-circle-notch ph-spin"></i> Prüfe...';
        }
        
        try {
            const result = await apiCall(`check_updates&channel=${channel}`, 'GET', null, true);
            
            if (result.error) {
                showToast('Update-Check fehlgeschlagen: ' + result.message, 'error');
                return;
            }
            
            this.currentVersion = result.current;
            
            if (result.update_available) {
                this.latestRelease = result;
                this.showUpdateBanner(result);
            } else {
                if (banner) banner.classList.remove('show');
                showToast(result.message || 'Du nutzt bereits die neueste Version', 'success');
            }
            
        } catch (error) {
            showToast('Fehler beim Update-Check', 'error');
            console.error(error);
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="ph ph-magnifying-glass"></i> Nach Updates suchen';
            }
        }
    },
    
    /**
     * Display update banner
     */
    showUpdateBanner(releaseInfo) {
        const banner = document.getElementById('update-banner');
        const versionTag = document.getElementById('new-version-number');
        const betaBadge = document.getElementById('beta-badge');
        
        if (!banner) return;
        
        if (versionTag) {
            versionTag.textContent = releaseInfo.latest;
        }
        
        // Channel badges
        if (betaBadge) {
            if (releaseInfo.is_dev) {
                betaBadge.textContent = 'DEV';
                betaBadge.style.display = 'inline-flex';
                betaBadge.style.background = 'rgba(239, 68, 68, 0.2)';
                betaBadge.style.color = '#dc2626';
            } else if (releaseInfo.is_beta) {
                betaBadge.textContent = 'BETA';
                betaBadge.style.display = 'inline-flex';
                betaBadge.style.background = 'rgba(245, 158, 11, 0.2)';
                betaBadge.style.color = '#d97706';
            } else {
                betaBadge.style.display = 'none';
            }
        }
        
        banner.classList.add('show');
        
        // Log update info
        console.log('Update verfügbar:', releaseInfo);
    },
    
    /**
     * Install the update
     */
    async installUpdate() {
        if (!this.latestRelease || !this.latestRelease.download_url) {
            showToast('Keine Update-URL gefunden', 'error');
            return;
        }
        
        const channel = localStorage.getItem('vantix_update_channel') || 'stable';
        let warningText = `VantixDash wird jetzt auf Version ${this.latestRelease.latest} aktualisiert.`;
        
        if (channel === 'dev') {
            warningText += '\n\n⚠️ ACHTUNG: Dies ist ein instabiler Development-Build!';
        } else if (channel === 'beta') {
            warningText += '\n\n⚠️ Dies ist eine Beta-Version.';
        }
        
        warningText += '\n\nEin Backup wird automatisch erstellt. Fortfahren?';
        
        const confirmed = await showConfirm(
            'Update installieren',
            warningText,
            { okText: 'Jetzt updaten', isDanger: channel === 'dev' }
        );
        
        if (!confirmed) return;
        
        const btn = document.getElementById('install-update-btn');
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="ph ph-circle-notch ph-spin"></i> Installiere...';
        }
        
        try {
            const formData = new FormData();
            formData.append('download_url', this.latestRelease.download_url);
            
            const result = await apiCall('install_update', 'POST', formData);
            
            if (result.success) {
                showToast('Update erfolgreich! Seite wird neu geladen...', 'success');
                
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            }
            
        } catch (error) {
            showToast('Update-Installation fehlgeschlagen', 'error');
            console.error(error);
            
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '<i class="ph ph-download-simple"></i> Update installieren';
            }
        }
    }
};

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    UpdateManager.init();
});

// Expose to window for inline onclick handlers
window.checkForUpdates = () => UpdateManager.checkForUpdates();
window.installUpdate = () => UpdateManager.installUpdate();
