<?php
declare(strict_types=1);

/**
 * reset-password.php - Token-Validierung und Passwort-Update
 */

// HTTPS-Weiterleitung
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === "off") {
    header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], true, 301);
    exit;
}

require_once __DIR__ . '/autoload.php';

use VantixDash\Logger;
use VantixDash\ConfigService;

$logger = new Logger();
$config = new ConfigService();
$message = '';
$error = false;

// 1. Token aus Request extrahieren
$token = $_POST['token'] ?? $_GET['token'] ?? '';

// 2. Token-Format validieren (Strikte Prüfung auf Hex-Zeichen)
if (empty($token) || !preg_match('/^[a-f0-9]{64}$/i', (string)$token)) {
    $logger->warning("Passwort-Reset mit ungültigem Token-Format abgebrochen.");
    die("Ungültiger Sicherheits-Token.");
}

// 3. Token-Gültigkeit prüfen
if (!$config->verifyResetToken((string)$token)) {
    $logger->warning("Abgelaufener oder manipulierter Reset-Token verwendet.");
    die("Dieser Link ist abgelaufen oder ungültig. Bitte fordern Sie einen neuen an.");
}

// 4. Passwort-Änderung verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'])) {
    $password = $_POST['new_password'];
    $confirm  = $_POST['confirm_password'];

    if (strlen($password) < 8) {
        $message = "Das Passwort muss mindestens 8 Zeichen lang sein.";
        $error = true;
    } elseif ($password !== $confirm) {
        $message = "Die Passwort-Wiederholung stimmt nicht überein.";
        $error = true;
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        
        if ($config->updatePassword($hash)) {
            // Token nach Erfolg sofort entwerten
            $config->clearResetToken();
            
            $logger->info("Passwort erfolgreich über Reset-Link zurückgesetzt.");
            $message = "Erfolg! Ihr Passwort wurde geändert. Sie werden zum Login weitergeleitet.";
            
            // Session bereinigen
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            session_destroy();

            // Nach 3 Sekunden zum Login
            header("Refresh: 3; url=login.php?reset=1");
        } else {
            $logger->error("Fehler: Passwort-Update im ConfigService fehlgeschlagen.");
            $message = "Ein Systemfehler ist aufgetreten.";
            $error = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Passwort neu setzen - VantixDash</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
</head>
<body class="login-page">
    <div class="login-card">
        <div class="login-header">
            <i class="ph ph-lock-key-open" style="font-size: 3rem; color: #3b7ddd;"></i>
            <h2 style="margin-top: 1rem;">Neues Passwort</h2>
        </div>

        <?php if ($message): ?>
            <div class="alert <?php echo $error ? 'alert-error' : 'alert-success'; ?>">
                <i class="ph <?php echo $error ? 'ph-warning-circle' : 'ph-check-circle'; ?>"></i> 
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if (!$message || $error): ?>
            <form method="POST" autocomplete="off">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                
                <div class="form-group">
                    <label for="new_password">Neues Passwort</label>
                    <input type="password" id="new_password" name="new_password" class="form-control" required minlength="8" autofocus placeholder="••••••••">
                </div>

                <div class="form-group">
                    <label for="confirm_password">Passwort bestätigen</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="8" placeholder="••••••••">
                </div>

                <button type="submit" class="btn-action">Passwort jetzt speichern</button>
            </form>
        <?php else: ?>
            <div class="login-footer">
                <p style="color: #6c757d;">Warten auf Weiterleitung...</p>
                <a href="login.php" style="color: #3b7ddd; text-decoration: none; font-weight: 600;">Sofort zum Login</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
