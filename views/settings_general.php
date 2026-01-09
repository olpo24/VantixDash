<div class="card shadow-sm p-4 border-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="h5 fw-bold mb-1">System-Aktualisierung</h3>
            <p class="text-muted small mb-0">Aktuelle Version: v<?php echo (include 'version.php')['version']; ?></p>
        </div>
        <div class="form-check form-switch bg-light p-2 px-3 rounded border">
            <input class="form-check-input" type="checkbox" id="beta-toggle" onchange="App.checkUpdates()">
            <label class="form-check-label small fw-bold ms-2" for="beta-toggle">Beta-Kanal</label>
        </div>
    </div>

    <div id="update-status" class="alert alert-info border-0 py-3">
        <i class="ph ph-circle-notch ph-spin me-2"></i> Initialisiere Update-Check...
    </div>

    <input type="hidden" id="pending-download-url">

    <div id="update-actions" style="display: none;" class="mt-3">
        <div class="card bg-light border-0 p-3 mb-3">
            <p class="small mb-0 text-muted">
                <i class="ph ph-info me-1"></i> 
                Das Update überschreibt Systemdateien. Deine <strong>config.php</strong> und der <strong>data/</strong> Ordner sind geschützt.
            </p>
        </div>
        <button id="start-update-btn" class="btn btn-primary px-4" onclick="App.runUpdate()">
            <i class="ph ph-download-simple me-2"></i> Update jetzt installieren
        </button>
    </div>
</div>

<script>
    // Automatischer Check beim Laden der Seite
    document.addEventListener('DOMContentLoaded', () => {
        if (typeof App !== 'undefined') {
            App.checkUpdates();
        }
    });
</script>
