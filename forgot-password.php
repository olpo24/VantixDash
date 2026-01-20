<?php
declare(strict_types=1);
session_start();

/**
 * 1. AUTOLOADER & NAMESPACES
 */
require_once __DIR__ . '/autoload.php';

use VantixDash\Config\ConfigService;
use VantixDash\Config\ConfigRepository;
use VantixDash\User\UserService;
use VantixDash\User\PasswordService;
use VantixDash\RateLimiter;

$repository = new ConfigRepository();
$configService = new ConfigService($repository);
$userService = new UserService($configService);
$passwordService = new PasswordService($configService);
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
        $storedEmail = $userService->getEmail();

        // Sicherheit: Immer die gleiche Erfolgsmeldung zeigen (Privacy)
        if (!empty($email) && strtolower($email) === strtolower($storedEmail)) {
            $token = $passwordService->createResetToken();
            
            // HIER würde in einer echten Umgebung die E-Mail gesendet werden
            $resetLink = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]/reset-password.php?token=$token";
            
            // Wir simulieren den Versand für das Dashboard
            $success = 'Wenn die E-Mail-Adresse existiert, wurde ein Reset-Link versendet.';
            
            // Debug-Hinweis für Entwicklung (später entfernen!)
            // error_log("Passwort Reset Link: " . $resetLink);
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
    </head>
<body class="login-page">
    <div class="login-card">
        <div class="login-header">
            <h2>VantixDash</h2>
            <p>Passwort zurücksetzen</p>
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
