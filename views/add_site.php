<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h4 mb-1 fw-bold text-dark">Neue Seite hinzufügen</h2>
            <p class="text-muted small mb-0">Registriere eine neue WordPress-Instanz in deinem VantixDash.</p>
        </div>
        <a href="index.php?view=dashboard" class="btn btn-outline-secondary btn-sm shadow-sm">
            <i class="bi bi-arrow-left me-1"></i> Zurück zum Dashboard
        </a>
    </div>

    <div id="notice-container"></div>

    <div class="card shadow-sm border-0 overflow-hidden" id="add-site-card">
        <div class="card-header bg-white py-3">
            <h5 class="card-title mb-0 small fw-bold text-uppercase text-muted">Seiten-Details</h5>
        </div>
        <div class="card-body p-4">
            <form id="add-site-form">
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-secondary">NAME DER SEITE</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-tag text-muted"></i></span>
                            <input type="text" id="site-name" class="form-control border-start-0 ps-0" placeholder="z.B. Mein Portfolio" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-secondary">URL (MIT HTTPS://)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-link-45deg text-muted"></i></span>
                            <input type="url" id="site-url" class="form-control border-start-0 ps-0" placeholder="https://meine-seite.de" required>
                        </div>
                    </div>
                    <div class="col-12 mt-4 pt-2">
                        <button type="submit" class="btn btn-primary px-4 py-2 shadow-sm">
                            <i class="bi bi-shield-plus me-2"></i> Seite registrieren & Key generieren
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
