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

// Erfolgsmeldungen abfangen
if (isset($_GET['reset']) && $_GET['reset'] === '1') {
    $success = 'Dein Passwort wurde erfolgreich geändert. Bitte logge dich neu ein.';
} elseif (isset($_GET['timeout']) && $_GET['timeout'] === '1') {
    $error = 'Deine Session ist abgelaufen. Bitte melde dich erneut an.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // RATE LIMITING CHECK: Nutzt IP-basiertes Limiting
    if (!$rateLimiter->checkLimit($_SERVER['REMOTE_ADDR'] . '_login', 5, 300)) {
        $error = 'Zu viele Fehlversuche. Bitte warten Sie 5 Minuten.';
    } else {
        $action = $_POST['action'] ?? 'login';

        // SCHRITT 1: Benutzername und Passwort prüfen
        if ($action === 'login') {
            $user = $_POST['username'] ?? '';
            $pass = $_POST['password'] ?? '';

            // FIX: Nutze getString() statt get()
            $storedUser = $configService->getString('username');
            $storedHash = $configService->getString('password_hash');

            if ($user !== '' && $user === $storedUser && password_verify($pass, $storedHash)) {
                // Passwort korrekt! Prüfen, ob 2FA aktiv ist (FIX: Nutze getBool)
                if ($configService->getBool('2fa_enabled')) {
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
            require_once __DIR__ . '/libs/GoogleAuthenticator.php';
            $ga = new \PHPGangsta_GoogleAuthenticator();
            
            // FIX: Nutze getString() statt get()
            $secret = $configService->getString('2fa_secret');

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - VantixDash</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Spezifisches Styling für die Login-Seite, basierend auf deinem neuen Farbschema */
        body.login-page {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background-color: #f5f7fb;
            margin: 0;
            font-family: 'Inter', sans-serif;
        }
        .login-card {
            width: 100%;
            max-width: 400px;
            padding: 2.5rem;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.05);
        }
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-header h2 {
            color: #222e3c;
            margin: 0;
            font-size: 1.5rem;
        }
        .alert {
            padding: 0.75rem 1rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }
        .alert-error { background: #fee2e2; color: #dc2626; border: 1px solid #fecaca; }
        .alert-success { background: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0; }
        
        .form-group { margin-bottom: 1.25rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; color: #495057; font-size: 0.9rem; }
        .form-control {
            width: 100%;
            padding: 0.6rem 0.75rem;
            border: 1px solid #ced4da;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .btn-login {
            width: 100%;
            padding: 0.75rem;
            background: #3b7ddd;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            transition: background 0.2s;
        }
        .btn-login:hover { background: #2f64b1; }
        .login-footer {
            margin-top: 1.5rem;
            text-align: center;
            font-size: 0.85rem;
        }
        .login-footer a { color: #6c757d; text-decoration: none; }
        .login-footer a:hover { text-decoration: underline; }
    </style>
</head>
<body class="login-page">
    <div class="login-card">
        <div class="login-header">
            <h2>VantixDash</h2>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if (!isset($_SESSION['auth_pending'])): ?>
            <form method="POST">
                <input type="hidden" name="action" value="login">
                <div class="form-group">
                    <label>Benutzername</label>
                    <input type="text" name="username" class="form-control" required autofocus>
                </div>
                <div class="form-group">
                    <label>Passwort</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button type="submit" class="btn-login">Anmelden</button>
            </form>

            <div class="login-footer">
                <a href="forgot-password.php">Passwort vergessen?</a>
            </div>

        <?php else: ?>
            <form method="POST">
                <input type="hidden" name="action" value="verify_2fa">
                <p style="text-align: center; font-size: 0.9rem; margin-bottom: 1.5rem; color: #6c757d;">
                    Bitte gib den 6-stelligen Code aus deiner Authenticator-App ein:
                </p>
                <div class="form-group">
                    <input type="text" name="2fa_code" placeholder="000 000" maxlength="6" 
                           style="width: 100%; font-size: 1.75rem; text-align: center; letter-spacing: 4px; padding: 0.5rem; border: 1px solid #ced4da; border-radius: 4px;" 
                           autofocus required>
                </div>
                <button type="submit" class="btn-login">Verifizieren & Einloggen</button>
                <div class="login-footer">
                    <a href="logout.php">Abbrechen</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
