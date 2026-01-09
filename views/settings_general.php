<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h5 class="card-title mb-0">System-Update</h5>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="beta-toggle" onchange="App.checkUpdates()">
                <label class="form-check-label small text-muted" for="beta-toggle">Beta-Kanal nutzen</label>
            </div>
        </div>

        <div id="update-status" class="alert alert-info border-0">
            <i class="ph ph-info me-2"></i> Klicke auf "Prüfen", um nach Updates zu suchen.
        </div>

        <input type="hidden" id="pending-download-url" value="">

        <div id="update-actions" style="display: none;" class="mt-3">
            <button id="start-update-btn" onclick="App.runUpdate()" class="btn btn-primary w-100">
                <i class="ph ph-download-simple me-2"></i> Update jetzt installieren
            </button>
        </div>
        
        <button onclick="App.checkUpdates()" class="btn btn-outline-secondary btn-sm mt-2">
            <i class="ph ph-arrows-clockwise me-1"></i> Jetzt prüfen
        </button>
    </div>
</div>
