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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // RATE LIMITING: Max 3 Versuche in 15 Minuten für Passwort-Resets
    if (!$rateLimiter->checkLimit($_SERVER['REMOTE_ADDR'] . '_pw_reset', 3, 900)) {
        $error = 'Zu viele Versuche. Bitte warten Sie 15 Minuten.';
    } else {
        $email = $_POST['email'] ?? '';
        $storedEmail = $configService->getString('email');

        // Sicherheit: Immer die gleiche Erfolgsmeldung zeigen (Privacy)
        if (!empty($email) && strtolower($email) === strtolower($storedEmail)) {
            $token = bin2hex(random_bytes(32));
            if ($configService->setResetToken($token)) {
                // HIER würde in einer echten Umgebung die E-Mail gesendet werden
                // Für VantixDash Log:
                $resetLink = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]/reset-password.php?token=$token";
                
                // Wir simulieren den Versand für das Dashboard
                $success = 'Wenn die E-Mail-Adresse existiert, wurde ein Reset-Link versendet.';
                
                // Debug-Hinweis für Entwicklung (später entfernen!)
                // error_log("Passwort Reset Link: " . $resetLink);
            } else {
                $error = 'Fehler beim Generieren des Tokens.';
            }
        } else {
            $success = 'Wenn die E-Mail-Adresse existiert, wurde ein Reset-Link versendet.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Passwort vergessen - VantixDash</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
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
        .login-header h2 { color: #222e3c; margin: 0; font-size: 1.5rem; }
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
        .btn-action {
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
        .btn-action:hover { background: #2f64b1; }
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
            <p style="color: #6c757d; font-size: 0.9rem; margin-top: 0.5rem;">Passwort zurücksetzen</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <div class="login-footer">
                <a href="login.php">← Zurück zum Login</a>
            </div>
        <?php else: ?>
            <form method="POST">
                <div class="form-group">
                    <label>E-Mail-Adresse</label>
                    <input type="email" name="email" class="form-control" placeholder="Deine E-Mail" required autofocus>
                </div>
                <button type="submit" class="btn-action">Reset-Link senden</button>
            </form>

            <div class="login-footer">
                <a href="login.php">Doch wieder eingefallen? Login</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
