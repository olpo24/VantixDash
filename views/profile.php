<?php
if (!isset($_SESSION['logged_in'])) exit;
// Annahme: User-Daten kommen aus deiner config oder einer users.json
$user_data = $configService->getUserData(); 
?>

<div class="profile-container">
    <div class="header-action">
        <h2><i class="ph ph-user-circle"></i> Profil & Sicherheit</h2>
    </div>

    <div class="grid-two-cols">
        <div class="card">
            <div class="card-header"><h3>Allgemeine Informationen</h3></div>
            <form id="profile-form" class="standard-form">
                <div class="form-group">
                    <label>Benutzername</label>
                    <input type="text" name="username" value="<?php echo htmlspecialchars($user_data['username']); ?>" required>
                </div>
                <div class="form-group">
                    <label>E-Mail Adresse</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" required>
                </div>
                <button type="submit" class="primary-button">Änderungen speichern</button>
            </form>
        </div>

        <div class="card">
            <div class="card-header"><h3>Passwort ändern</h3></div>
            <form id="password-form" class="standard-form">
                <div class="form-group">
                    <label>Neues Passwort</label>
                    <input type="password" name="new_password" placeholder="Mind. 8 Zeichen">
                </div>
                <div class="form-group">
                    <label>Passwort bestätigen</label>
                    <input type="password" name="confirm_password">
                </div>
                <button type="submit" class="secondary-button">Passwort aktualisieren</button>
            </form>
        </div>
    </div>

    <div class="card mt-20">
    <div class="card-header">
        <h3><i class="ph ph-shield-check"></i> Zwei-Faktor-Authentisierung (2FA)</h3>
    </div>
    <div class="card-body" id="2fa-container" style="padding: 20px;">
        <?php if ($user_data['2fa_enabled']): ?>
            <div class="status-box active" style="background: rgba(46, 204, 113, 0.1); padding: 15px; border-radius: 8px; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <strong style="color: #27ae60; display: block;">Aktiviert</strong>
                    <small class="text-muted">Dein Konto ist durch einen zusätzlichen Code geschützt.</small>
                </div>
                <button onclick="disable2FA()" class="danger-button">Deaktivieren</button>
            </div>
        <?php else: ?>
            <p>Schütze dein Dashboard mit einer Authenticator-App (z.B. Google Authenticator oder Authy).</p>
            <button onclick="start2FASetup()" class="primary-button">2FA aktivieren</button>
        <?php endif; ?>
    </div>
</div>

<div id="2fa-setup-modal" class="modal-overlay" style="display:none;">
    <div class="modal-content card" style="max-width: 400px; text-align: center;">
        <div class="modal-header"><h3>2FA Einrichten</h3></div>
        <div class="modal-body" style="padding: 20px;">
            <p>1. Scanne diesen QR-Code mit deiner App:</p>
            <img id="2fa-qr-img" src="" alt="QR Code" style="margin: 15px 0; border: 10px solid white;">
            <p style="font-size: 0.8rem; margin-bottom: 20px;">Oder manuell: <code id="2fa-secret-text"></code></p>
            
            <p>2. Gib den 6-stelligen Code aus der App ein:</p>
            <input type="text" id="2fa-verify-code" placeholder="000000" maxlength="6" 
                   style="font-size: 1.5rem; text-align: center; letter-spacing: 5px; width: 100%; margin: 10px 0;">
            
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button onclick="confirm2FA()" class="primary-button" style="flex: 1;">Verifizieren</button>
                <button onclick="close2FAModal()" class="ghost-button">Abbrechen</button>
            </div>
        </div>
    </div>
</div>
</div>
<script>
document.getElementById('profile-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const response = await fetch('api.php?action=update_profile', {
        method: 'POST',
        body: formData
    });
    const result = await response.json();
    alert(result.success ? 'Profil aktualisiert!' : 'Fehler beim Speichern.');
});

document.getElementById('password-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    if(formData.get('new_password') !== formData.get('confirm_password')) {
        alert('Passwörter stimmen nicht überein!');
        return;
    }
    const response = await fetch('api.php?action=update_password', {
        method: 'POST',
        body: formData
    });
    const result = await response.json();
    alert(result.success ? 'Passwort geändert!' : result.message);
    if(result.success) e.target.reset();
});
</script>
