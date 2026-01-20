<?php
/**
 * settings_general.php
 * Verwaltung der globalen Dashboard-Einstellungen und Updates.
 */

// Services müssen verfügbar sein
use VantixDash\Config\SettingsService;

if (!isset($settingsService)) {
    $settingsService = new SettingsService($configService);
}

$currentVersion = $settingsService->getVersion();
?>

<div>
    <div>
        <h1>Einstellungen</h1>
        <p class="text-muted small">Verwalte dein VantixDash System und Updates</p>
    </div>

    <div class="card">
        <div>
            <div>
                <i class="ph ph-arrows-clockwise"></i>
            </div>
            <div>
                <h2>System-Update</h2>
                <p class="text-muted small">Aktuelle Version: 
                    <span class="badge">v<?php echo htmlspecialchars($currentVersion); ?></span>
                </p>
            </div>
        </div>

        <div id="app-update-banner">
            <div>
                <i class="ph ph-sparkle"></i>
                <div>
                    <span>Update verfügbar!</span>
                    <span id="new-version-tag"></span>
                </div>
            </div>
            <div id="update-container" class="update-banner">
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

        <div>
            <div>
                <div>
                    <div>Beta-Kanal</div>
                    <div class="text-muted small">Erhalte Vorab-Versionen (instabiler)</div>
                </div>
                <label class="switch">
                    <input type="checkbox" id="beta-toggle" onchange="toggleBeta(this.checked)">
                    <span class="slider round"></span>
                </label>
            </div>
            
            <button class="btn" onclick="checkAppUpdates()">
                <i class="ph ph-magnifying-glass"></i> Nach Updates suchen
            </button>
        </div>
    </div>

    <div class="card">
        <h3>Gefahrenzone</h3>
        <p class="text-muted small">Diese Aktionen können nicht rückgängig gemacht werden.</p>
        <div>
            <button class="btn" onclick="if(confirm('Alle Cache-Daten löschen?')) location.reload();">
                Cache leeren
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const isBeta = localStorage.getItem('vantix_beta') === 'true';
    const toggle = document.getElementById('beta-toggle');
    if (toggle) toggle.checked = isBeta;
    
    // Automatisch nach Updates suchen, wenn die Einstellungsseite geladen wird
    if (typeof App !== 'undefined' && typeof App.checkAppUpdates === 'function') {
        App.checkAppUpdates();
    }
});

function toggleBeta(enabled) {
    localStorage.setItem('vantix_beta', enabled ? 'true' : 'false');
    showToast(enabled ? 'Beta-Kanal aktiviert' : 'Beta-Kanal deaktiviert', 'info');
}

function checkAppUpdates() {
    if (typeof App !== 'undefined' && typeof App.checkAppUpdates === 'function') {
        App.checkAppUpdates();
    } else {
        showToast('Update-Funktion noch nicht geladen', 'warning');
    }
}
</script>
