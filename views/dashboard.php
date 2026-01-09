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
            <div style="font-size: 1.1rem; font-weight: 700;" id="last-update-time">--:--</div>
        </div>
    </div>

</div>

<div class="card" style="padding: 0; overflow: hidden;">
    <div class="table-container">
        <table>
      <thead>
    <tr>
        <th style="padding-left: 1.5rem;">Website / URL</th>
        <th style="text-align: center;">WP Version</th>
        <th style="text-align: center;">PHP</th>
        <th style="text-align: center;">Updates</th>
        <th style="text-align: center;">Check</th>
        <th style="text-align: right; padding-right: 1.5rem;">Aktionen</th>
    </tr>
</thead>
            <tbody id="sites-tbody">
                <tr>
                    <td colspan="5" style="text-align: center; padding: 4rem; color: var(--text-muted);">
                        <i class="ph ph-circle-notch ph-spin" style="font-size: 2rem; display: block; margin: 0 auto 1rem;"></i>
                        Verbindung zum VantixDash System wird hergestellt...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<dialog id="detailsModal" class="card shadow-lg" style="width: 600px; border: none; padding: 0; margin: auto;">
    <div style="padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: #f8fafc;">
        <h3 id="details-site-name" style="font-weight: 800; margin: 0;">Details</h3>
        <button onclick="this.closest('dialog').close()" style="background:none; border:none; cursor:pointer;"><i class="ph ph-x" style="font-size: 1.25rem;"></i></button>
    </div>
    
    <div id="details-modal-body" style="padding: 1.5rem; max-height: 70vh; overflow-y: auto;">
        </div>

    <div style="padding: 1.5rem; border-top: 1px solid var(--border); display: flex; gap: 1rem; justify-content: flex-end;">
        <button class="btn" style="background: var(--border);" onclick="this.closest('dialog').close()">Schließen</button>
        <button id="details-admin-link-btn" class="btn btn-primary">
            <i class="ph ph-sign-in"></i> WP-Admin Login
        </button>
    </div>
</dialog>
