<?php
// Automatische Ermittlung der Dashboard-URL
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$current_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . str_replace('/index.php', '', $_SERVER['SCRIPT_NAME']);
$current_url = rtrim($current_url, '/');
?>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 fw-bold"><i class="bi bi-plugin me-2 text-primary"></i>Child Plugin Generator</h5>
    </div>
    <div class="card-body p-4">
        <p class="text-muted">Generiere hier dein individuelles VantixDash Child Plugin. Die angegebene URL wird im Plugin als einzige erlaubte Quelle (CORS) für den Auto-Login hinterlegt.</p>
        
        <div class="row">
            <div class="col-md-8">
                <div class="mb-4">
                    <label class="form-label fw-bold">Erlaubte Dashboard-URL (CORS Origin)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-shield-check"></i></span>
                        <input type="text" id="dashboard-origin-url" class="form-control" value="<?php echo $current_url; ?>" placeholder="https://dein-dashboard.de">
                    </div>
                    <div class="form-text">Kein abschließender Schrägstrich (Slash) am Ende!</div>
                </div>

                <div class="alert alert-info border-0 shadow-sm d-flex align-items-center">
                    <i class="bi bi-info-circle-fill fs-4 me-3"></i>
                    <div>
                        <strong>Sicherheitshinweis:</strong> Das Plugin erlaubt den Zugriff nur von dieser URL. Wenn du dein Dashboard auf eine andere Domain umziehst, musst du das Plugin neu generieren.
                    </div>
                </div>

                <button onclick="downloadCustomPlugin()" class="btn btn-primary btn-lg px-5">
                    <i class="bi bi-download me-2"></i> Plugin (.zip) generieren & herunterladen
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function downloadCustomPlugin() {
    let url = document.getElementById('dashboard-origin-url').value.trim();
    if(!url) {
        alert("Bitte gib eine gültige URL an.");
        return;
    }
    // Falls der User doch einen Slash am Ende macht, entfernen wir ihn sicherheitshalber
    if(url.endsWith('/')) {
        url = url.slice(0, -1);
    }
    
    // Download aufrufen mit URL als Parameter
    window.location.href = 'download_plugin.php?origin=' + encodeURIComponent(url);
}
</script>
