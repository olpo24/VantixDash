<?php
/**
 * settings_general.php
 * Verwaltung der globalen Dashboard-Einstellungen und Updates.
 */

use VantixDash\Config\SettingsService;

if (!isset($settingsService)) {
    $settingsService = new SettingsService($configService);
}

$currentVersion = $settingsService->getVersion();
$lastUpdate = $settingsService->getLastUpdate();
?>

<div class="settings-container">
    <div class="content-header">
        <div>
            <h1><i class="ph ph-gear"></i> Allgemeine Einstellungen</h1>
            <p class="text-muted">Verwalte dein VantixDash System und Updates</p>
        </div>
    </div>

    <!-- Update Section -->
    <div class="card">
        <div class="card-header">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <i class="ph ph-download-simple" style="font-size: 2rem; color: var(--primary-color);"></i>
                <div>
                    <h2 style="margin: 0;">System-Update</h2>
                    <p class="text-muted small" style="margin: 0.25rem 0 0 0;">
                        Aktuelle Version: <span class="badge">v<?php echo htmlspecialchars($currentVersion); ?></span>
                        <span style="color: var(--text-muted); margin-left: 0.5rem;">
                            (Stand: <?php echo htmlspecialchars($lastUpdate); ?>)
                        </span>
                    </p>
                </div>
            </div>
        </div>

        <!-- Update Banner (hidden by default) -->
        <div id="update-banner" style="display: none; padding: 1.5rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 8px; margin: 1rem;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <i class="ph ph-sparkle" style="font-size: 2rem;"></i>
                    <div>
                        <strong style="font-size: 1.1rem;">Update verfügbar!</strong>
                        <p style="margin: 0.25rem 0 0 0; opacity: 0.9;">
                            Version <span id="new-version-number"></span> ist bereit zur Installation.
                            <span id="beta-badge" style="display: none; background: rgba(255,255,255,0.2); padding: 0.15rem 0.5rem; border-radius: 4px; font-size: 0.75rem; margin-left: 0.5rem;">BETA</span>
                        </p>
                    </div>
                </div>
                <button id="install-update-btn" class="btn-primary" style="background: white; color: #667eea;" onclick="installUpdate()">
                    <i class="ph ph-download-simple"></i> Update installieren
                </button>
            </div>
        </div>

        <div class="card-body">
            <!-- Update Channel Selector -->
            <div style="padding: 1rem 0; border-bottom: 1px solid var(--border-color);">
                <div style="font-weight: 600; margin-bottom: 1rem;">Update-Kanal wählen</div>
                
                <div style="display: grid; gap: 1rem;">
                    <!-- Stable Channel -->
                    <label style="display: flex; align-items: start; gap: 1rem; padding: 1rem; border: 2px solid var(--border-color); border-radius: 8px; cursor: pointer; transition: all 0.2s;" 
                           onmouseover="this.style.borderColor='var(--primary-color)'" 
                           onmouseout="if(!this.querySelector('input').checked) this.style.borderColor='var(--border-color)'">
                        <input type="radio" name="update_channel" value="stable" id="channel-stable" 
                               onchange="setUpdateChannel('stable')" 
                               style="margin-top: 0.25rem;">
                        <div style="flex: 1;">
                            <div style="font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
                                <i class="ph ph-check-circle" style="color: var(--success);"></i>
                                Stable (Empfohlen)
                            </div>
                            <div class="text-muted small">Nur getestete, stabile Versionen. Ideal für Produktivumgebungen.</div>
                        </div>
                    </label>
                    
                    <!-- Beta Channel -->
                    <label style="display: flex; align-items: start; gap: 1rem; padding: 1rem; border: 2px solid var(--border-color); border-radius: 8px; cursor: pointer; transition: all 0.2s;" 
                           onmouseover="this.style.borderColor='var(--primary-color)'" 
                           onmouseout="if(!this.querySelector('input').checked) this.style.borderColor='var(--border-color)'">
                        <input type="radio" name="update_channel" value="beta" id="channel-beta" 
                               onchange="setUpdateChannel('beta')" 
                               style="margin-top: 0.25rem;">
                        <div style="flex: 1;">
                            <div style="font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
                                <i class="ph ph-flask" style="color: var(--warning);"></i>
                                Beta (Early Access)
                            </div>
                            <div class="text-muted small">Vorab-Versionen mit neuen Features. Möglicherweise instabil.</div>
                        </div>
                    </label>
                    
                    <!-- Dev Channel -->
                    <label style="display: flex; align-items: start; gap: 1rem; padding: 1rem; border: 2px solid var(--border-color); border-radius: 8px; cursor: pointer; transition: all 0.2s;" 
                           onmouseover="this.style.borderColor='var(--danger)'" 
                           onmouseout="if(!this.querySelector('input').checked) this.style.borderColor='var(--border-color)'">
                        <input type="radio" name="update_channel" value="dev" id="channel-dev" 
                               onchange="setUpdateChannel('dev')" 
                               style="margin-top: 0.25rem;">
                        <div style="flex: 1;">
                            <div style="font-weight: 600; display: flex; align-items: center; gap: 0.5rem;">
                                <i class="ph ph-code" style="color: var(--danger);"></i>
                                Development (Unstable)
                                <span style="background: var(--danger); color: white; padding: 0.15rem 0.5rem; border-radius: 4px; font-size: 0.7rem;">EXPERIMENTAL</span>
                            </div>
                            <div class="text-muted small">Bleeding-Edge aus dem develop-Branch. Nur für Tests!</div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Check for Updates Button -->
            <div style="padding: 1.5rem 0;">
                <button id="check-updates-btn" class="btn-secondary" onclick="checkForUpdates()">
                    <i class="ph ph-magnifying-glass"></i> Jetzt nach Updates suchen
                </button>
                <p class="text-muted small" style="margin-top: 0.5rem;">
                    <i class="ph ph-info"></i> Updates werden automatisch von GitHub geladen und installiert.
                </p>
            </div>

            <!-- Update History (Optional) -->
            <details style="margin-top: 1rem;">
                <summary style="cursor: pointer; font-weight: 600; padding: 0.5rem 0;">
                    <i class="ph ph-clock-counter-clockwise"></i> Update-Historie
                </summary>
                <div style="padding: 1rem; background: var(--bg-color); border-radius: 8px; margin-top: 0.5rem;">
                    <p class="text-muted">Letzte Aktualisierung: <?php echo htmlspecialchars($lastUpdate); ?></p>
                    <p class="text-muted small">Weitere Details findest du im System-Log.</p>
                </div>
            </details>
        </div>
    </div>

    <!-- System Info Card -->
    <div class="card">
        <div class="card-header">
            <h3><i class="ph ph-info"></i> System-Informationen</h3>
        </div>
        <div class="card-body">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                <div style="padding: 1rem; background: var(--bg-color); border-radius: 8px;">
                    <div class="text-muted small">PHP Version</div>
                    <div style="font-weight: 600; margin-top: 0.25rem;"><?php echo PHP_VERSION; ?></div>
                </div>
                <div style="padding: 1rem; background: var(--bg-color); border-radius: 8px;">
                    <div class="text-muted small">VantixDash Version</div>
                    <div style="font-weight: 600; margin-top: 0.25rem;">v<?php echo htmlspecialchars($currentVersion); ?></div>
                </div>
                <div style="padding: 1rem; background: var(--bg-color); border-radius: 8px;">
                    <div class="text-muted small">Installationsort</div>
                    <div style="font-weight: 600; margin-top: 0.25rem; font-size: 0.85rem; word-break: break-all;">
                        <?php echo htmlspecialchars(dirname(__DIR__)); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Danger Zone -->
    <div class="card danger-zone">
        <h3><i class="ph ph-warning"></i> Gefahrenzone</h3>
        <p class="text-muted">Diese Aktionen können nicht rückgängig gemacht werden.</p>
        <div style="display: flex; gap: 1rem; margin-top: 1rem;">
            <button class="btn-danger-outline" onclick="clearCache()">
                <i class="ph ph-trash"></i> Cache leeren
            </button>
        </div>
    </div>
</div>

<script>
// Initialize channel selector
document.addEventListener('DOMContentLoaded', () => {
    const savedChannel = localStorage.getItem('vantix_update_channel') || 'stable';
    const channelInput = document.getElementById(`channel-${savedChannel}`);
    if (channelInput) {
        channelInput.checked = true;
        channelInput.closest('label').style.borderColor = 'var(--primary-color)';
    }
});

function setUpdateChannel(channel) {
    localStorage.setItem('vantix_update_channel', channel);
    
    // Visual feedback
    document.querySelectorAll('[name="update_channel"]').forEach(input => {
        input.closest('label').style.borderColor = 'var(--border-color)';
    });
    document.getElementById(`channel-${channel}`).closest('label').style.borderColor = 'var(--primary-color)';
    
    // Show warning for dev channel
    if (channel === 'dev') {
        showToast('⚠️ Development-Kanal aktiviert. Nur für Tests verwenden!', 'warning', 5000);
    } else if (channel === 'beta') {
        showToast('Beta-Kanal aktiviert. Du erhältst Vorab-Versionen.', 'info');
    } else {
        showToast('Stable-Kanal aktiviert.', 'success');
    }
    
    // Auto-check for updates
    checkForUpdates();
}

// Additional helper functions
function clearCache() {
    if (confirm('Möchtest du den Cache wirklich leeren? Dies kann zu kurzen Performance-Einbußen führen.')) {
        showToast('Cache wird geleert...', 'info');
        setTimeout(() => {
            location.reload();
        }, 1000);
    }
}
</script>
