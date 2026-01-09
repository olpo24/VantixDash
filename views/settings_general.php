<?php
/**
 * views/settings_general.php
 * Einstellungsseite mit Update-Verwaltung
 */
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-md-8">
            <h1 class="h3 mb-4">Einstellungen</h1>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 py-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0">
                            <i class="ph ph-wrench me-2"></i>System-Update
                        </h5>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="beta-toggle" onchange="App.checkAppUpdates()">
                            <label class="form-check-label small text-muted" for="beta-toggle">Beta-Kanal</label>
                        </div>
                    </div>
                </div>
                <div class="card-body pt-0">
                    <p class="text-muted small mb-4">
                        Halte dein Dashboard aktuell, um neue Funktionen und Sicherheitsupdates zu erhalten.
                    </p>

                    <div id="update-status" class="alert alert-light border d-flex align-items-center py-3">
                        <i class="ph ph-info me-3 fs-4"></i>
                        <div>
                            Klicke auf <strong>Updates prüfen</strong>, um die Version abzugleichen.
                        </div>
                    </div>

                    <input type="hidden" id="pending-download-url" value="">

                    <div id="update-actions" style="display: none;" class="mt-3">
                        <div class="card bg-light border-0">
                            <div class="card-body d-flex align-items-center justify-content-between">
                                <span class="small">Das Update steht bereit zum Download.</span>
                                <button id="start-update-btn" onclick="App.runUpdate()" class="btn btn-warning shadow-sm">
                                    <i class="ph ph-download-simple me-2"></i>Jetzt installieren
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-top">
                        <button onclick="App.checkUpdates()" class="btn btn-outline-primary btn-sm">
                            <i class="ph ph-arrows-clockwise me-2"></i>Updates jetzt prüfen
                        </button>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="card-title mb-0">Allgemeine Konfiguration</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Hier kannst du weitere globale Einstellungen für VantixDash vornehmen.</p>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card bg-primary text-white border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h6>Dashboard Info</h6>
                    <hr class="border-white opacity-25">
                    <div class="small">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Lokal:</span>
                            <span class="fw-bold">v<?php 
                                $v = include 'version.php'; 
                                echo $v['version']; 
                            ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>PHP Version:</span>
                            <span class="fw-bold"><?php echo PHP_VERSION; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Kleiner Helfer, um den Beta-Status im Browser-Speicher zu behalten
    document.addEventListener('DOMContentLoaded', function() {
        const betaToggle = document.getElementById('beta-toggle');
        if (localStorage.getItem('beta_enabled') === 'true') {
            betaToggle.checked = true;
        }
    });
</script>
