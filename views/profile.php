<?php
if (!isset($_SESSION['logged_in'])) exit;

// Services müssen hier verfügbar gemacht werden
use VantixDash\User\UserService;
use VantixDash\User\TwoFactorService;

if (!isset($userService)) {
    $userService = new UserService($configService);
}
if (!isset($twoFactorService)) {
    $twoFactorService = new TwoFactorService($configService);
}

$user_data = $userService->getUserData(); 
$csrf_token = $_SESSION['csrf_token'];
?>

<div class="profile-container">
    <div class="header-action">
        <div>
            <h2><i class="ph ph-user-circle"></i> Profil & Sicherheit</h2>
            <p class="text-muted">Verwalte deinen Zugang und die Zwei-Faktor-Authentisierung</p>
        </div>
    </div>

    <div class="grid-two-cols">
        
        <div class="card">
            <div class="card-header"><h3>Allgemeine Informationen</h3></div>
            <form id="profile-form">
                <div class="form-group">
                    <label>Benutzername</label>
                    <input type="text" name="username" value="<?php echo htmlspecialchars($user_data['username']); ?>" required>
                </div>
                <div class="form-group">
                    <label>E-Mail Adresse</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                </div>
                <button type="submit" class="btn-primary">Änderungen speichern</button>
            </form>
        </div>

        <div class="card">
            <div class="card-header"><h3>Passwort ändern</h3></div>
            <form id="password-form">
                <div class="form-group">
                    <label>Neues Passwort</label>
                    <input type="password" name="new_password" placeholder="Mind. 8 Zeichen" required>
                </div>
                <div class="form-group">
                    <label>Passwort bestätigen</label>
                    <input type="password" name="confirm_password" required>
                </div>
                <button type="submit" class="btn-secondary">Passwort aktualisieren</button>
            </form>
        </div>
    </div>

    <div class="card mt-20">
        <div class="card-header">
            <h3><i class="ph ph-shield-check"></i> Zwei-Faktor-Authentisierung (2FA)</h3>
        </div>
        <div class="card-body" id="2fa-status-container">
            <?php if ($user_data['2fa_enabled']): ?>
                <div class="status-box active">
                    <div>
                        <strong><i class="ph ph-check-circle"></i> 2FA ist aktiv</strong>
                        <p class="text-muted">Dein Konto ist optimal geschützt.</p>
                    </div>
                    <button onclick="disable2FAHandler()" class="btn-danger">Deaktivieren</button>
                </div>
            <?php else: ?>
                <div>
                    <p>Erhöhe die Sicherheit deines Kontos mit einer Authenticator-App (TOTP).</p>
                    <button onclick="start2FASetup()" class="btn-primary">Jetzt einrichten</button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div id="2fa-setup-modal" class="modal">
    <div class="modal-content card">
        <div>
            <h3>2FA Einrichten</h3>
            <button onclick="close2FAModal()" class="icon-btn"><i class="ph ph-x"></i></button>
        </div>
        <div>
            <p>Scanne den QR-Code mit Google Authenticator oder Authy.</p>
            
            <div id="qr-loading"><i class="ph ph-circle-notch ph-spin"></i></div>
            <img id="2fa-qr-img" src="" alt="QR Code">
            
            <p>Code: <strong id="2fa-secret-text"></strong></p>
            
            <div>
                <label>6-stelliger Bestätigungscode:</label>
                <input type="text" id="2fa-verify-code" placeholder="000000" maxlength="6">
            </div>
            
            <div>
                <button onclick="confirm2FA()" class="btn-primary">Aktivieren</button>
                <button onclick="close2FAModal()" class="btn-secondary">Abbrechen</button>
            </div>
        </div>
    </div>
</div>

<script>
// 1. Profil-Daten Update
document.getElementById('profile-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const result = await window.apiCall('update_profile', 'POST', new FormData(e.target));
    if (result && result.success) {
        showToast('Profil erfolgreich aktualisiert!', 'success');
    }
});

// 2. Passwort Update
document.getElementById('password-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    
    if(fd.get('new_password') !== fd.get('confirm_password')) {
        showToast('Die Passwörter stimmen nicht überein!', 'error');
        return;
    }

    const result = await window.apiCall('update_password', 'POST', fd);
    if (result && result.success) {
        showToast('Passwort erfolgreich geändert!', 'success');
        e.target.reset();
    }
});

// 3. 2FA Setup starten
async function start2FASetup() {
    const modal = document.getElementById('2fa-setup-modal');
    modal.style.display = 'flex';
    
    const result = await window.apiCall('setup_2fa');
    if (result && result.success) {
        document.getElementById('qr-loading').style.display = 'none';
        const qrImg = document.getElementById('2fa-qr-img');
        qrImg.src = result.qrCodeUrl;
        qrImg.style.display = 'block';
        document.getElementById('2fa-secret-text').innerText = result.secret;
    } else {
        close2FAModal();
    }
}

// 4. 2FA Verifizieren & Aktivieren
async function confirm2FA() {
    const code = document.getElementById('2fa-verify-code').value;
    if(code.length < 6) {
        showToast('Bitte gib den 6-stelligen Code ein.', 'warning');
        return;
    }
    
    const fd = new FormData();
    fd.append('code', code);
    
    const result = await window.apiCall('verify_2fa', 'POST', fd);
    if (result && result.success) {
        showToast('2FA erfolgreich aktiviert!', 'success');
        setTimeout(() => location.reload(), 1000);
    }
}

// 5. 2FA Deaktivieren
async function disable2FAHandler() {
    const confirmed = await window.showConfirm(
        '2FA deaktivieren?',
        'Möchtest du die Zwei-Faktor-Authentisierung wirklich deaktivieren? Dein Konto ist danach weniger sicher.',
        { okText: 'Ja, deaktivieren', isDanger: true }
    );
    
    if (confirmed) {
        const result = await window.apiCall('disable_2fa', 'POST');
        if (result && result.success) {
            showToast('2FA wurde deaktiviert.', 'info');
            setTimeout(() => location.reload(), 1000);
        }
    }
}

function close2FAModal() {
    document.getElementById('2fa-setup-modal').style.display = 'none';
}
</script>
