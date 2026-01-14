<?php
declare(strict_types=1);
session_start();

/**
 * 1. AUTOLOADER & NAMESPACES
 */
require_once __DIR__ . '/autoload.php';

use VantixDash\ConfigService;
use VantixDash\RateLimiter;

$configService = new ConfigService();
$rateLimiter = new RateLimiter();
$error = '';
$success = '';

// Wenn der User bereits eingeloggt ist
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: index.php');
    exit;
}

// Erfolgsmeldungen abfangen (z.B. nach Passwort-Reset oder Session-Timeout)
if (isset($_GET['reset']) && $_GET['reset'] === '1') {
    $success = 'Dein Passwort wurde erfolgreich geändert. Bitte logge dich neu ein.';
} elseif (isset($_GET['timeout']) && $_GET['timeout'] === '1') {
    $error = 'Deine Session ist abgelaufen. Bitte melde dich erneut an.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // RATE LIMITING CHECK: Max 5 Versuche in 5 Minuten (300 Sek)
    if (!$rateLimiter->checkLimit($_SERVER['REMOTE_ADDR'] . '_login', 5, 300)) {
        $error = 'Zu viele Fehlversuche. Bitte warten Sie 5 Minuten.';
    } else {
        $action = $_POST['action'] ?? 'login';

        // SCHRITT 1: Benutzername und Passwort prüfen
        if ($action === 'login') {
            $user = $_POST['username'] ?? '';
            $pass = $_POST['password'] ?? '';

            $storedUser = $configService->get('username');
            $storedHash = $configService->get('password_hash');

            if ($user === $storedUser && password_verify($pass, (string)$storedHash)) {
                // Passwort korrekt! Prüfen, ob 2FA aktiv ist
                if ($configService->get('2fa_enabled')) {
                    $_SESSION['auth_pending'] = true; 
                    $_SESSION['temp_user'] = $user;
                } else {
                    // KEIN 2FA -> Login abschließen
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
            
            // GoogleAuthenticator ist eine externe Lib ohne Namespace
            require_once __DIR__ . '/libs/GoogleAuthenticator.php';
            $ga = new \PHPGangsta_GoogleAuthenticator();
            
            $secret = (string)$configService->get('2fa_secret', '');

            if ($ga->verifyCode($secret, $code, 2)) {
                session_regenerate_id(true);
                $_SESSION['logged_in'] = true;
                $_SESSION['username'] = $_SESSION['temp_user'] ?? '';
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                unset($_SESSION['auth_pending'], $_SESSION['temp_user']);
                header('Location: index.php');
                exit;
            } else {
                $error = 'Ungültiger 2FA Code';
            }
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
    <style>
        .login-footer {
            margin-top: 20px;
            text-align: center;
            border-top: 1px solid var(--border-color, #eee);
            padding-top: 15px;
        }
        .forgot-link {
            color: #666;
            text-decoration: none;
            font-size: 0.85rem;
            transition: color 0.2s;
        }
        .forgot-link:hover {
            color: var(--primary-color, #333);
            text-decoration: underline;
        }
        .alert.success {
            background-color: rgba(40, 167, 69, 0.1);
            color: #28a745;
            border: 1px solid #28a745;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .alert.error {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border: 1px solid #dc3545;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body class="login-page">
    <div class="login-card card">
        <h2 style="text-align: center; margin-bottom: 20px;">VantixDash</h2>

        <?php if ($error): ?>
            <div class="alert error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if (!isset($_SESSION['auth_pending'])): ?>
            <form method="POST">
                <input type="hidden" name="action" value="login">
                <div class="form-group">
                    <label>Benutzername</label>
                    <input type="text" name="username" style="width:100%; padding:10px; border-radius:5px; border:1px solid #ddd;" required autofocus>
                </div>
                <div class="form-group" style="margin-top: 15px;">
                    <label>Passwort</label>
                    <input type="password" name="password" style="width:100%; padding:10px; border-radius:5px; border:1px solid #ddd;" required>
                </div>
                <button type="submit" class="btn-primary" style="width:100%; margin-top: 20px; padding: 12px; cursor: pointer;">Anmelden</button>
            </form>

            <div class="login-footer">
                <a href="forgot-password.php" class="forgot-link">Passwort vergessen?</a>
            </div>

        <?php else: ?>
            <form method="POST">
                <input type="hidden" name="action" value="verify_2fa">
                <p style="text-align: center; margin-bottom: 20px;">Bitte gib den 6-stelligen Code aus deiner Authenticator-App ein:</p>
                <div class="form-group">
                    <input type="text" name="2fa_code" placeholder="000000" maxlength="6" 
                           style="width: 100%; font-size: 2rem; text-align: center; letter-spacing: 5px; padding: 10px; border-radius: 8px; border: 1px solid #ddd;" autofocus required>
                </div>
                <button type="submit" class="btn-primary" style="width:100%; margin-top: 20px; padding: 12px; cursor: pointer;">Verifizieren & Einloggen</button>
                <a href="logout.php" style="display:block; margin-top:15px; text-align:center; color: #666; text-decoration: none;">Abbrechen</a>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
