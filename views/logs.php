<div class="dashboard-container">
    <div class="header-action">
        <div>
            <h2><i class="ph ph-scroll"></i> System-Logs</h2>
            <p class="text-muted">Die letzten Aktivitäten und Fehlermeldungen</p>
        </div>
        <div style="display: flex; gap: 10px;">
            <button onclick="loadLogs()" class="ghost-button">
                <i class="ph ph-arrows-clockwise"></i> Aktualisieren
            </button>
            <button onclick="clearLogs()" class="ghost-button" style="color: var(--danger-color);">
                <i class="ph ph-trash"></i> Leeren
            </button>
        </div>
    </div>

    <div class="card">
        <pre id="log-viewer" style="background: #1e1e1e; color: #d4d4d4; padding: 1.5rem; border-radius: 8px; font-family: 'Fira Code', monospace; font-size: 0.85rem; overflow-x: auto; white-space: pre-wrap; min-height: 400px; max-height: 600px;"></pre>
    </div>
</div>

<script>
/**
 * Wartet, bis die app.js die Funktionen am window-Objekt registriert hat
 */
function initLogs() {
    if (typeof window.loadLogs === 'function') {
        window.loadLogs();
    } else {
        // Falls app.js noch nicht bereit ist, in 50ms erneut prüfen
        setTimeout(initLogs, 50);
    }
}

// Startet den Prozess, sobald das HTML bereit ist
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initLogs);
} else {
    initLogs();
}
</script>
