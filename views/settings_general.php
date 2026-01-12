<?php
/**
 * settings_general.php
 * Verwaltung der globalen Dashboard-Einstellungen und Updates.
 */

// Sicherer Abruf der Version
$versionData = include('version.php');
$currentVersion = is_array($versionData) ? $versionData['version'] : $versionData;
?>

<div style="max-width: 800px; margin: 0 auto;">
    <div style="margin-bottom: 2rem;">
        <h1 style="font-size: 1.5rem; font-weight: 800;">Einstellungen</h1>
        <p class="text-muted small">Verwalte dein VantixDash System und Updates</p>
    </div>

    <div class="card" style="margin-bottom: 2rem;">
        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem;">
            <div style="background: #eff6ff; color: #3b82f6; padding: 0.75rem; border-radius: 10px;">
                <i class="ph ph-arrows-clockwise" style="font-size: 1.5rem;"></i>
            </div>
            <div>
                <h2 style="font-size: 1.1rem; font-weight: 700; margin: 0;">System-Update</h2>
                <p class="text-muted small" style="margin: 0;">Aktuelle Version: 
                    <span class="badge" style="background: #f1f5f9; color: #475569;">v<?php echo htmlspecialchars($currentVersion); ?></span>
                </p>
            </div>
        </div>

        <div id="app-update-banner" style="display: none; background: #fffbeb; border: 1px solid #fde68a; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <i class="ph ph-sparkle" style="color: #d97706; font-size: 1.25rem;"></i>
                <div>
                    <span style="font-weight: 700; color: #92400e;">Update verfügbar!</span>
                    <span id="new-version-tag" style="margin-left: 5px; opacity: 0.8;"></span>
                </div>
            </div>
            <div id="update-container" style="display: none;" class="update-banner">
    <div class="update-content">
        <div class="update-text">
            <strong>System-Update verfügbar</strong>
            <span>Version <span id="new-version-number"></span> ist bereit.</span>
        </div>
        <button id="start-update-btn" class="main-button">
            Update jetzt installieren
        </button>
    </div>
</div>
        </div>

        <div style="display: flex; flex-direction: column; gap: 1rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: #f8fafc; border-radius: 8px;">
                <div>
                    <div style="font-weight: 700;">Beta-Kanal</div>
                    <div class="text-muted small">Erhalte Vorab-Versionen (instabiler)</div>
                </div>
                <label class="switch">
                    <input type="checkbox" id="beta-toggle" onchange="toggleBeta(this.checked)">
                    <span class="slider round"></span>
                </label>
            </div>
            
            <button class="btn" style="width: fit-content; background: #fff; border: 1px solid var(--border);" onclick="checkAppUpdates()">
                <i class="ph ph-magnifying-glass"></i> Nach Updates suchen
            </button>
        </div>
    </div>

    <div class="card" style="border: 1px solid #fee2e2;">
        <h3 style="font-size: 1rem; font-weight: 700; color: #991b1b; margin-bottom: 1rem;">Gefahrenzone</h3>
        <p class="text-muted small">Diese Aktionen können nicht rückgängig gemacht werden.</p>
        <div style="display: flex; gap: 1rem;">
            <button class="btn" style="background: #fef2f2; color: #991b1b; border: 1px solid #fecaca;" onclick="if(confirm('Alle Cache-Daten löschen?')) location.reload();">
                Cache leeren
            </button>
        </div>
    </div>
</div>

<style>
/* Toggle Switch Style */
.switch { position: relative; display: inline-block; width: 44px; height: 22px; }
.switch input { opacity: 0; width: 0; height: 0; }
.slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .4s; border-radius: 34px; }
.slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
input:checked + .slider { background-color: #3b82f6; }
input:checked + .slider:before { transform: translateX(22px); }

.badge { padding: 0.2rem 0.5rem; border-radius: 4px; font-weight: 700; font-family: monospace; }
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const isBeta = localStorage.getItem('vantix_beta') === 'true';
    const toggle = document.getElementById('beta-toggle');
    if (toggle) toggle.checked = isBeta;
    
    // Automatisch nach Updates suchen, wenn die Einstellungsseite geladen wird
    App.checkAppUpdates();
});
</script>
