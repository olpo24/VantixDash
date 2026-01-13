PHP

<?php
declare(strict_types=1);
session_start();

require_once 'services/ConfigService.php';
require_once 'libs/GoogleAuthenticator.php';

$configService = new ConfigService();
$error = '';

// Wenn der User bereits eingeloggt ist
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'login';

    // SCHRITT 1: Benutzername und Passwort prüfen
    if ($action === 'login') {
        $user = $_POST['username'] ?? '';
        $pass = $_POST['password'] ?? '';

        $storedUser = $configService->get('username');
        $storedHash = $configService->get('password_hash');

        if ($user === $storedUser && password_verify($pass, $storedHash)) {
            // Passwort korrekt! Prüfen, ob 2FA aktiv ist
            if ($configService->get('2fa_enabled')) {
                // Zwischenstatus für 2FA
                $_SESSION['auth_pending'] = true; 
                $_SESSION['temp_user'] = $user;
            } else {
                // KEIN 2FA -> Login abschließen
                
                // FIX: Session ID erneuern (verhindert Session Fixation)
                session_regenerate_id(true);
                
                $_SESSION['logged_in'] = true;
                $_SESSION['username'] = $user;
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                
                header('Location: index.php');
                exit;
            }
        } else {
            $error = 'Ungültige Zugangsdaten';
        }
    }

    // SCHRITT 2: 2FA Code verifizieren
    if ($action === 'verify_2fa') {
        if (!isset($_SESSION['auth_pending'])) {
            header('Location: login.php');
            exit;
        }

        $code = $_POST['2fa_code'] ?? '';
        $ga = new PHPGangsta_GoogleAuthenticator();
        $secret = (string)$configService->get('2fa_secret', '');

        if ($ga->verifyCode($secret, $code, 2)) {
            // 2FA korrekt! Login abschließen
            
            // FIX: Session ID erneuern
            session_regenerate_id(true);
            
            $_SESSION['logged_in'] = true;
            $_SESSION['username'] = $_SESSION['temp_user'] ?? '';
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            
            // Temporäre Daten aufräumen
            unset($_SESSION['auth_pending'], $_SESSION['temp_user']);
            
            header('Location: index.php');
            exit;
        } else {
            $error = 'Ungültiger 2FA Code';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Login - VantixDash</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
    <div class="login-card card">
        <h2>VantixDash</h2>

        <?php if ($error): ?>
            <div class="alert error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (!isset($_SESSION['auth_pending'])): ?>
            <form method="POST">
                <input type="hidden" name="action" value="login">
                <div class="form-group">
                    <label>Benutzername</label>
                    <input type="text" name="username" required autofocus>
                </div>
                <div class="form-group">
                    <label>Passwort</label>
                    <input type="password" name="password" required>
                </div>
                <button type="submit" class="primary-button full-width">Anmelden</button>
            </form>
        <?php else: ?>
            <form method="POST">
                <input type="hidden" name="action" value="verify_2fa">
                <p>Bitte gib den 6-stelligen Code aus deiner Authenticator-App ein:</p>
                <div class="form-group">
                    <input type="text" name="2fa_code" placeholder="000000" maxlength="6" 
                           style="font-size: 2rem; text-align: center; letter-spacing: 5px;" autofocus required>
                </div>
                <button type="submit" class="primary-button full-width">Verifizieren & Einloggen</button>
                <a href="logout.php" class="text-link" style="display:block; margin-top:15px; text-align:center;">Abbrechen</a>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
