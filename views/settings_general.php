<div class="card shadow-sm border-0">
    <div class="card-body p-4">
        <h5 class="fw-bold mb-4">Allgemeine Einstellungen</h5>
        <div class="container-fluid py-4">
    <div class="row">
        <div class="col-md-8">
            <div class="card shadow-sm p-4 border-0">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h3 class="h5 fw-bold mb-1">System-Aktualisierung</h3>
                        <p class="text-muted small mb-0">Verwalte Versionen und Update-Kanäle.</p>
                    </div>
                    <div class="form-check form-switch bg-light p-2 px-3 rounded border">
                        <input class="form-check-input" type="checkbox" id="beta-toggle" onchange="App.checkUpdates()">
                        <label class="form-check-label small fw-bold ms-2" for="beta-toggle">Beta-Kanal</label>
                    </div>
                </div>

                <div id="update-status" class="alert alert-info border-0 py-3">
                    </div>

                <div id="update-actions" style="display: none;" class="mt-3">
                    <input type="hidden" id="pending-download-url">
                    <div class="card bg-light border-0 p-3 mb-3">
                        <small class="text-muted d-block mb-2">Sicherheitshinweis:</small>
                        <p class="small mb-0">Es wird empfohlen, vor jedem Update ein Backup der <code>data/sites.json</code> zu erstellen.</p>
                    </div>
                    <button id="start-update-btn" class="btn btn-primary px-4" onclick="App.runUpdate()">
                        <i class="ph ph-download-simple me-2"></i> Update jetzt ausführen
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Initialer Check beim Laden der Seite
    document.addEventListener('DOMContentLoaded', () => {
        if (typeof App !== 'undefined') {
            App.checkUpdates();
        }
    });
</script>
    </div>
</div>
