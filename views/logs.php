<?php
declare(strict_types=1);
?>
<div class="header-action">
    <h1>System-Logs</h1>
    <div class="flex-gap">
        <button class="ghost-button" onclick="window.clearLogs()">
            <i class="ph ph-trash"></i> Logs leeren
        </button>
        <a href="index.php" class="ghost-button">
            <i class="ph ph-arrow-left"></i> Zurück
        </div>
    </div>
</div>

<div class="card table-card">
    <div id="log-content">
        <div style="text-align:center; padding:2rem;">
            <i class="ph ph-circle-notch ph-spin" style="font-size:2rem;"></i>
            <p>Lade Logs...</p>
        </div>
    </div>
</div>

<script>
// Da die logs.php dynamisch geladen wird, stellen wir sicher, dass die Lade-Funktion bereitsteht
window.loadLogs = async () => {
    const logContent = document.getElementById('log-content');
    const result = await apiCall('get_logs');
    
    if (result && result.success) {
        if (result.data.length === 0) {
            logContent.innerHTML = '<p style="padding:20px; text-align:center; color:var(--text-muted);">Keine Logs vorhanden.</p>';
            return;
        }

        let html = '<table class="native-table"><thead><tr><th>Zeit</th><th>Level</th><th>Nachricht</th></tr></thead><tbody>';
        result.data.forEach(log => {
            const date = new Date(log.timestamp * 1000).toLocaleString('de-DE');
            const levelClass = log.level.toLowerCase() === 'error' ? 'status-badge offline' : 'status-badge info';
            html += `<tr>
                <td style="white-space:nowrap; font-size:0.85rem;">${date}</td>
                <td><span class="${levelClass}">${log.level}</span></td>
                <td style="font-size:0.85rem;">${log.message}</td>
            </tr>`;
        });
        html += '</tbody></table>';
        logContent.innerHTML = html;
    }
};

// Initiales Laden
window.loadLogs();
</script>
