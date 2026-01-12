<?php
/**
 * views/settings_profile.php
 * Kombinierte Ansicht für Profil-Daten und 2FA-Sicherheit
 * Sicherheit: Implementiert CSRF-Schutz für alle POST-Aktionen
 */

require_once __DIR__ . '/../libs/GoogleAuthenticator.php';
$ga = new PHPGangsta_GoogleAuthenticator();
$message = '';
$error = '';
$configPath = __DIR__ . '/../data/config.php';

// Zentraler CSRF-Check für alle POST-Anfragen in dieser View
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        die('Sicherheitsfehler: CSRF-Validierung fehlgeschlagen. Bitte laden Sie die Seite neu.');
    }
}

// 1. VERARBEITUNG: PROFIL-DATEN (Name, Email, Passwort)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $new_user = htmlspecialchars(trim($_POST['username']), ENT_QUOTES, 'UTF-8');
    $new_email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $new_pass = $_POST['new_password'];

    $config['username'] = $new_user;
    $config['email'] = $new_email;
    
    if (!empty($new_pass)) {
        $config['password_hash'] = password_hash($new_pass, PASSWORD_DEFAULT);
    }

    $jsonConfig = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if (file_put_contents($configPath, $jsonConfig)) {
    $message = '<div class="alert alert-success shadow-sm">Profil erfolgreich aktualisiert!</div>';
}
}

// 2. VERARBEITUNG: 2FA STATUS (Aktivieren/Deaktivieren)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_2fa'])) {
    if (isset($_POST['enable_2fa_action']) && $_POST['enable_2fa_action'] == '1') {
        // Verifizierung des Codes vor Aktivierung
        $checkResult = $ga->verifyCode($_POST['temp_secret'], $_POST['otp_code'], 2); 
        if ($checkResult) {
            $config['2fa_enabled'] = true;
            $config['2fa_secret'] = $_POST['temp_secret'];
            $message = '<div class="alert alert-success shadow-sm"><i class="bi bi-shield-check me-2"></i>2FA wurde erfolgreich aktiviert!</div>';
        } else {
            $error = 'Der eingegebene 2FA-Code war falsch. Aktivierung abgebrochen.';
        }
    } else {
        // Deaktivieren
        $config['2fa_enabled'] = false;
        $config['2fa_secret'] = null;
        $message = '<div class="alert alert-warning shadow-sm"><i class="bi bi-shield-slash me-2"></i>2FA wurde deaktiviert.</div>';
    }
    $jsonConfig = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if (file_put_contents($configPath, $jsonConfig)) {
    $message = '<div class="alert alert-success shadow-sm">Profil erfolgreich aktualisiert!</div>';
}

// Vorbereitung für das 2FA-Setup
$tempSecret = $ga->createSecret();
$qrCodeUrl = $ga->getQRCodeGoogleUrl('VantixDash (' . $config['username'] . ')', $tempSecret);
?>

<div class="row">
    <div class="col-xl-8">
        
        <?php if($message) echo $message; ?>
        <?php if($error) echo '<div class="alert alert-danger shadow-sm">'.$error.'</div>'; ?>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="mb-0 fw-bold text-dark">
                    <i class="bi bi-person-gear me-2 text-primary"></i>Profil bearbeiten
                </h5>
            </div>
            <div class="card-body p-4">
                <form method="POST" action="index.php?view=settings_profile">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted text-uppercase">Benutzername</label>
                            <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($config['username']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted text-uppercase">E-Mail Adresse</label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($config['email']); ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold text-muted text-uppercase">Neues Passwort</label>
                            <input type="password" name="new_password" class="form-control" placeholder="Leer lassen, um Passwort beizubehalten">
                        </div>
                        <div class="col-12">
                            <button type="submit" name="update_profile" class="btn btn-primary px-4 fw-bold">
                                Profil speichern
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="mb-0 fw-bold text-dark">
                    <i class="bi bi-shield-lock me-2 text-primary"></i>Zwei-Faktor-Authentisierung (2FA)
                </h5>
            </div>
            <div class="card-body p-4">
                <?php if (!$config['2fa_enabled']): ?>
                    <div class="d-flex align-items-center mb-4">
                        <div class="form-check form-switch p-0 m-0">
                            <input class="form-check-input ms-0 me-2" type="checkbox" id="2faToggle" style="width: 3rem; height: 1.5rem;" onchange="toggle2FASetup(this.checked)">
                        </div>
                        <label class="fw-bold mb-0" for="2faToggle">2FA ist aktuell <span class="text-danger">deaktiviert</span></label>
                    </div>

                    <div id="2fa-setup-area" style="display: none;" class="bg-light p-4 rounded border">
                        <form method="POST" action="index.php?view=settings_profile">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            
                            <input type="hidden" name="enable_2fa_action" value="1">
                            <input type="hidden" name="temp_secret" value="<?php echo $tempSecret; ?>">
                            
                            <div class="row align-items-center">
                                <div class="col-md-4 text-center">
                                    <img src="<?php echo $qrCodeUrl; ?>" alt="QR Code" class="img-fluid border bg-white p-2 shadow-sm rounded">
                                </div>
                                <div class="col-md-8">
                                    <h6 class="fw-bold">Authenticator konfigurieren</h6>
                                    <ol class="small text-muted ps-3">
                                        <li>Scanne den QR-Code mit Google Authenticator oder Authy.</li>
                                        <li>Gib den 6-stelligen Code zur Bestätigung hier ein:</li>
                                    </ol>
                                    <input type="text" name="otp_code" class="form-control form-control-lg text-center fw-bold mb-3" maxlength="6" placeholder="000 000" style="letter-spacing: 5px;">
                                    <button type="submit" name="update_2fa" class="btn btn-dark w-100">
                                        2FA jetzt aktivieren
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="alert alert-soft-success border d-flex align-items-center justify-content-between p-3">
                        <div>
                            <i class="bi bi-check-circle-fill text-success fs-4 me-3"></i>
                            <span class="fw-bold">2FA ist aktiv geschaltet.</span>
                        </div>
                        <form method="POST" action="index.php?view=settings_profile" onsubmit="return confirm('Möchtest du 2FA wirklich deaktivieren?');">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            
                            <input type="hidden" name="enable_2fa_action" value="0">
                            <button type="submit" name="update_2fa" class="btn btn-outline-danger btn-sm fw-bold">
                                Deaktivieren
                            </button>
                        </form>
                    </div>
                    <p class="small text-muted mt-2 mb-0">
                        <i class="bi bi-info-circle me-1"></i> Dein Konto ist durch eine zweite Ebene geschützt.
                    </p>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<script>
function toggle2FASetup(checked) {
    const area = document.getElementById('2fa-setup-area');
    area.style.display = checked ? 'block' : 'none';
    if(checked) area.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}
</script>

<style>
.alert-soft-success { background-color: #e8f5e9; border-color: #c8e6c9; color: #2e7d32; }
.form-check-input:checked { background-color: #198754; border-color: #198754; }
</style>
