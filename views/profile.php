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
        <div class="card-body">
            <div class="two-fa-status">
                <?php if (!empty($user_data['2fa_enabled'])): ?>
                    <p class="status-text online">2FA ist aktuell <strong>aktiviert</strong>.</p>
                    <button onclick="toggle2FA(false)" class="danger-button">2FA deaktivieren</button>
                <?php else: ?>
                    <p class="status-text text-muted">Erhöhe die Sicherheit deines Kontos durch einen zweiten Faktor (Authenticator App).</p>
                    <button onclick="setup2FA()" class="primary-button">2FA jetzt einrichten</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
