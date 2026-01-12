<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <div>
        <h1 style="font-size: 1.5rem; font-weight: 800;">Dashboard</h1>
        <p class="text-muted small">Übersicht deiner WordPress-Umgebungen</p>
    </div>
    <button id="refresh-all-btn" class="btn btn-primary" onclick="App.loadSites()">
        <i class="ph ph-arrows-clockwise"></i> Jetzt aktualisieren
    </button>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2.5rem;">
    
    <div class="card" style="display: flex; align-items: center; gap: 1rem;">
        <div style="background: #eff6ff; color: #3b82f6; padding: 1rem; border-radius: 12px; font-size: 1.5rem;">
            <i class="ph ph-globe"></i>
        </div>
        <div>
            <div style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Seiten</div>
            <div style="font-size: 1.25rem; font-weight: 800;" id="stat-total-sites">0</div>
        </div>
    </div>

    <div class="card" style="display: flex; align-items: center; gap: 1rem;">
        <div style="background: #fffbeb; color: #d97706; padding: 1rem; border-radius: 12px; font-size: 1.5rem;">
            <i class="ph ph-warning-circle"></i>
        </div>
        <div>
            <div style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Updates</div>
            <div style="font-size: 0.9rem; font-weight: 700;" id="stat-detailed-updates">Lade...</div>
        </div>
    </div>

    <div class="card" style="display: flex; align-items: center; gap: 1rem;">
        <div style="background: #f0fdf4; color: #16a34a; padding: 1rem; border-radius: 12px; font-size: 1.5rem;">
            <i class="ph ph-check-circle"></i>
        </div>
        <div>
            <div style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase;">System</div>
            <div style="font-size: 1.25rem; font-weight: 800; color: #16a34a;">Bereit</div>
        </div>
    </div>

    <div class="card" style="display: flex; align-items: center; gap: 1rem;">
        <div style="background: #f8fafc; color: #64748b; padding: 1rem; border-radius: 12px; font-size: 1.5rem;">
            <i class="ph ph-clock"></i>
        </div>
        <div>
            <div style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Letzter Check</div>
            <div style="font-size: 0.85rem; font-weight: 700;" id="last-update-time">--:--</div>
        </div>
    </div>

</div>

<div class="card" style="padding: 0; overflow: hidden;">
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th style="padding-left: 1.5rem;">Website / URL</th>
                    <th>WP Version</th>
                    <th>PHP</th>
                    <th>Updates</th>
                    <th>Check</th>
                    <th style="padding-right: 1.5rem;">Aktionen</th>
                </tr>
            </thead>
            <tbody id="sites-tbody">
                <tr>
                    <td colspan="6" style="text-align: center; padding: 4rem; color: var(--text-muted);">
                        <i class="ph ph-circle-notch ph-spin" style="font-size: 2rem; display: block; margin: 0 auto 1rem;"></i>
                        Verbindung zum VantixDash System wird hergestellt...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<dialog id="siteDetailsModal" class="modal-base">
    <div class="modal-header">
        <div>
            <h2 id="modal-site-name" class="modal-title">Webseite Details</h2>
            <p id="modal-site-url" class="modal-subtitle">URL wird geladen...</p>
        </div>
        <button class="btn-close" onclick="document.getElementById('siteDetailsModal').close()">
            <i class="ph ph-x"></i>
        </button>
    </div>
    
    <div class="modal-body">
        <div class="details-grid">
            <div class="detail-item">
                <label>WordPress</label>
                <div id="modal-wp-version">-</div>
            </div>
            <div class="detail-item">
                <label>PHP Version</label>
                <div id="modal-php-version">-</div>
            </div>
            <div class="detail-item">
                <label>IP Adresse</label>
                <div id="modal-ip-address">-</div>
            </div>
        </div>
        
        <div id="modal-updates-section" class="mt-4" style="display: none;">
            <h3 class="section-title">Verfügbare Updates</h3>
            <div id="plugin-list-container"></div>
            <div id="theme-list-container" style="margin-top: 1rem;"></div>
        </div>
    </div>

    <div class="modal-footer">
        <button class="btn-secondary" onclick="document.getElementById('siteDetailsModal').close()">
            Schließen
        </button>
        <button id="modal-login-btn" class="btn btn-primary">
            <i class="ph ph-sign-in"></i> WP-Admin Login
        </button>
    </div>
</dialog>
