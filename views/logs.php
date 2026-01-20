<div class="dashboard-container">
    <div class="header-action">
        <div>
            <h2><i class="ph ph-scroll"></i> System-Logs</h2>
            <p class="text-muted">Die letzten Aktivitäten und Fehlermeldungen</p>
        </div>
        <div>
            <button onclick="window.loadLogs()" class="ghost-button">
                <i class="ph ph-arrows-clockwise"></i> Aktualisieren
            </button>
            <button onclick="window.clearLogs()" class="ghost-button" style="color: var(--danger);">
                <i class="ph ph-trash"></i> Leeren
            </button>
        </div>
    </div>

    <div class="card">
        <pre id="log-viewer"></pre>
    </div>
</div>

<script>
/**
 * Definiert die Log-Lade-Logik speziell für diesen View.
 * Wird am window-Objekt registriert, damit app.js (z.B. nach clearLogs) darauf zugreifen kann.
 */
window.loadLogs = async () => {
    const viewer = document.getElementById('log-viewer');
    if (!viewer) return;

    viewer.innerHTML = '<span>// Lade System-Logs...</span>';

    try {
        // Nutzt den zentralen apiCall aus app.js
        const result = await apiCall('get_logs');
        
        if (result && result.success) {
            if (!result.data || result.data.length === 0) {
                viewer.innerHTML = '<span>[INFO]</span> Keine Log-Einträge gefunden.';
                return;
            }

            // Formatiere die Logs für den Viewer
            const formattedLogs = result.data.map(log => {
                const date = new Date(log.timestamp * 1000).toLocaleString('de-DE');
                const levelColor = log.level === 'ERROR' ? '#f44336' : (log.level === 'WARNING' ? '#ff9800' : '#569cd6');
                
                return `[${date}] <span style="color: ${levelColor}; font-weight: bold;">${log.level.padEnd(7)}</span> ${log.message}`;
            }).join('\n');

            viewer.innerHTML = formattedLogs;
            
            // Automatisch zum Ende scrollen
            viewer.scrollTop = viewer.scrollHeight;
        }
    } catch (e) {
        viewer.innerHTML = '<span>[FEHLER]</span> Logs konnten nicht geladen werden.';
        console.error(e);
    }
};

/**
 * Initialisierung
 */
function initLogs() {
    // Prüfen, ob die API-Funktion aus app.js verfügbar ist
    if (typeof window.apiCall === 'function') {
        window.loadLogs();
    } else {
        setTimeout(initLogs, 50);
    }
}

initLogs();
</script>
