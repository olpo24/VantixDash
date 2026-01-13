<?php
if (!isset($_SESSION['logged_in'])) exit;
$user_data = $configService->getUserData(); 
$csrf_token = $_SESSION['csrf_token'];
?>

<div class="profile-container">
    <div class="header-action">
        <div>
            <h2><i class="ph ph-user-circle"></i> Profil & Sicherheit</h2>
            <p class="text-muted">Verwalte deinen Zugang und die Zwei-Faktor-Authentisierung</p>
        </div>
    </div>

    <div class="grid-two-cols" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
        
        <div class="card">
            <div class="card-header"><h3>Allgemeine Informationen</h3></div>
            <form id="profile-form" style="padding: 20px;">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px;">Benutzername</label>
                    <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($user_data['username']); ?>" required style="width: 100%; padding: 8px; border: 1px solid var(--border-color); border-radius: 4px;">
                </div>
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px;">E-Mail Adresse</label>
                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user_data['email']); ?>" required style="width: 100%; padding: 8px; border: 1px solid var(--border-color); border-radius: 4px;">
                </div>
                <button type="submit" class="primary-button">Änderungen speichern</button>
            </form>
        </div>

        <div class="card">
            <div class="card-header"><h3>Passwort ändern</h3></div>
            <form id="password-form" style="padding: 20px;">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px;">Neues Passwort</label>
                    <input type="password" name="new_password" class="form-control" placeholder="Mind. 8 Zeichen" required style="width: 100%; padding: 8px; border: 1px solid var(--border-color); border-radius: 4px;">
                </div>
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px;">Passwort bestätigen</label>
                    <input type="password" name="confirm_password" class="form-control" required style="width: 100%; padding: 8px; border: 1px solid var(--border-color); border-radius: 4px;">
                </div>
                <button type="submit" class="secondary-button">Passwort aktualisieren</button>
            </form>
        </div>
    </div>

    <div class="card mt-20" style="margin-top: 20px;">
        <div class="card-header">
            <h3><i class="ph ph-shield-check"></i> Zwei-Faktor-Authentisierung (2FA)</h3>
        </div>
        <div class="card-body" id="2fa-status-container" style="padding: 20px;">
            <?php if ($user_data['2fa_enabled']): ?>
                <div class="status-box active" style="background: rgba(46, 204, 113, 0.1); padding: 15px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; border: 1px solid #27ae60;">
                    <div>
                        <strong style="color: #27ae60; display: block;"><i class="ph ph-check-circle"></i> 2FA ist aktiv</strong>
                        <small class="text-muted">Dein Konto wird beim Login durch einen zusätzlichen Code geschützt.</small>
                    </div>
                    <button onclick="disable2FA()" class="danger-button">Deaktivieren</button>
                </div>
            <?php else: ?>
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <p style="margin: 0;">Erhöhe die Sicherheit deines Kontos mit einer Authenticator-App.</p>
                    <button onclick="start2FASetup()" class="primary-button">Jetzt einrichten</button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div id="2fa-setup-modal" class="modal-overlay" style="display:none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 1000;">
    <div class="modal-content card" style="max-width: 400px; width: 90%; background: white; border-radius: 12px; overflow: hidden;">
        <div class="modal-header" style="padding: 15px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0;">2FA Einrichten</h3>
            <button onclick="close2FAModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>
        <div class="modal-body" style="padding: 20px; text-align: center;">
            <p style="font-size: 0.9rem; color: var(--text-muted);">Scanne den QR-Code mit einer App wie Google Authenticator oder Authy.</p>
            
            <div id="qr-loading" style="padding: 20px;"><i class="ph ph-circle-notch animate-spin"></i></div>
            <img id="2fa-qr-img" src="" alt="QR Code" style="margin: 15px auto; display: none; border: 1px solid #eee; padding: 10px;">
            
            <p style="font-size: 0.75rem; margin-bottom: 20px;">Manueller Code: <strong id="2fa-secret-text"></strong></p>
            
            <div style="text-align: left; margin-top: 15px;">
                <label style="font-size: 0.85rem; font-weight: 600;">Bestätigungscode:</label>
                <input type="text" id="2fa-verify-code" placeholder="000 000" maxlength="6" 
                       style="font-size: 1.5rem; text-align: center; letter-spacing: 5px; width: 100%; padding: 10px; margin-top: 5px; border: 1px solid var(--border-color); border-radius: 6px;">
            </div>
            
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button onclick="confirm2FA()" class="primary-button" style="flex: 1;">Aktivieren</button>
                <button onclick="close2FAModal()" class="ghost-button" style="flex: 1;">Abbrechen</button>
            </div>
        </div>
    </div>
</div>

<script>
/**
 * Hilfsfunktion für Fetch-Requests mit CSRF Schutz
 */
async function secureApiRequest(action, formData = new FormData()) {
    // CSRF-Token immer anhängen, falls nicht vorhanden
    if (!formData.has('csrf_token')) {
        formData.append('csrf_token', '<?php echo $csrf_token; ?>');
    }

    const response = await fetch(`api.php?action=${action}`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '<?php echo $csrf_token; ?>'
        },
        body: formData
    });
    return await response.json();
}

// 1. Profil-Daten Update
document.getElementById('profile-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const result = await secureApiRequest('update_profile', new FormData(e.target));
    alert(result.success ? 'Profil erfolgreich aktualisiert!' : 'Fehler: ' + result.message);
});

// 2. Passwort Update
document.getElementById('password-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    if(fd.get('new_password') !== fd.get('confirm_password')) {
        alert('Die Passwörter stimmen nicht überein!');
        return;
    }
    const result = await secureApiRequest('update_password', fd);
    alert(result.success ? 'Passwort erfolgreich geändert!' : 'Fehler: ' + result.message);
    if(result.success) e.target.reset();
});

// 3. 2FA Setup starten
async function start2FASetup() {
    const modal = document.getElementById('2fa-setup-modal');
    modal.style.display = 'flex';
    
    try {
        const response = await fetch('api.php?action=setup_2fa');
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('qr-loading').style.display = 'none';
            const qrImg = document.getElementById('2fa-qr-img');
            qrImg.src = data.qrCodeUrl;
            qrImg.style.display = 'block';
            document.getElementById('2fa-secret-text').innerText = data.secret;
        }
    } catch (e) {
        alert('Fehler beim Laden des 2FA Setups.');
        close2FAModal();
    }
}

// 4. 2FA Verifizieren & Aktivieren
async function confirm2FA() {
    const code = document.getElementById('2fa-verify-code').value;
    if(code.length < 6) {
        alert('Bitte gib den 6-stelligen Code ein.');
        return;
    }
    
    const fd = new FormData();
    fd.append('code', code);
    
    const result = await secureApiRequest('verify_2fa', fd);
    if (result.success) {
        alert('2FA wurde erfolgreich aktiviert!');
        location.reload();
    } else {
        alert(result.message || 'Ungültiger Code.');
    }
}

// 5. 2FA Deaktivieren
async function disable2FA() {
    if (!confirm('Möchtest du die Zwei-Faktor-Authentisierung wirklich deaktivieren?')) return;
    
    const result = await secureApiRequest('disable_2fa');
    if (result.success) {
        location.reload();
    } else {
        alert('Fehler beim Deaktivieren.');
    }
}

function close2FAModal() {
    document.getElementById('2fa-setup-modal').style.display = 'none';
}
</script>
