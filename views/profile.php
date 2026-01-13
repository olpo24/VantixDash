<?php
if (!isset($_SESSION['logged_in'])) exit;
$user_data = $configService->getUserData(); 
?>

<div class="profile-container">
    <div class="header-action">
        <div>
            <h2><i class="ph ph-user-circle"></i> Profil & Sicherheit</h2>
            <p class="text-muted">Verwalte deinen Zugang und die Sicherheitseinstellungen</p>
        </div>
    </div>

    <div class="grid-two-cols" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
        <div class="card">
            <div class="card-header"><h3>Allgemeine Informationen</h3></div>
            <form id="profile-form" style="padding: 20px;">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
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
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
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
    
    <?php include 'parts/profile_2fa.php'; // Optional: 2FA Logik auslagern für Übersicht ?>
</div>

<script>
// Hilfsfunktion für Fetch mit CSRF-Header
async function secureFetch(url, formData) {
    return fetch(url, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '<?php echo $_SESSION['csrf_token']; ?>'
        },
        body: formData
    });
}

document.getElementById('profile-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    const response = await secureFetch('api.php?action=update_profile', formData);
    const result = await response.json();
    alert(result.success ? 'Profil aktualisiert!' : result.message);
});

document.getElementById('password-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    if(formData.get('new_password') !== formData.get('confirm_password')) {
        alert('Passwörter stimmen nicht überein!');
        return;
    }
    const response = await secureFetch('api.php?action=update_password', formData);
    const result = await response.json();
    alert(result.success ? 'Passwort geändert!' : result.message);
    if(result.success) e.target.reset();
});

// 2FA Verify muss ebenfalls secureFetch nutzen
async function confirm2FA() {
    const code = document.getElementById('2fa-verify-code').value;
    const formData = new FormData();
    formData.append('code', code);
    // CSRF Token manuell hinzufügen für 2FA
    formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');

    const response = await secureFetch('api.php?action=verify_2fa', formData);
    const data = await response.json();

    if (data.success) {
        alert('2FA erfolgreich aktiviert!');
        location.reload();
    } else {
        alert(data.message);
    }
}
</script>
