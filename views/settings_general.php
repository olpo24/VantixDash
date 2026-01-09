<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <h5 class="fw-bold mb-4">Allgemeine Einstellungen</h5>
        <div class="card shadow-sm p-4">
    <h3 class="h5 fw-bold mb-3"><i class="ph ph-git-branch me-2"></i>System Update</h3>
    <div id="update-status" class="alert alert-info border-0">
        Prüfe auf Updates...
    </div>
    <div id="update-actions" style="display: none;">
        <p class="small text-muted">Ein neues Update ist verfügbar. Bitte sichere deine Daten, bevor du fortfährst.</p>
        <button id="start-update-btn" class="btn btn-primary" onclick="App.runUpdate()">
            <i class="ph ph-download-simple me-2"></i> Jetzt installieren
        </button>
    </div>
</div>

<script>
    // Sofort beim Laden prüfen
    document.addEventListener('DOMContentLoaded', () => App.checkUpdates());
</script>
    </div>
</div>
