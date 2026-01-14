<?php
declare(strict_types=1);

require_once __DIR__ . '/services/Logger.php';
require_once __DIR__ . '/services/ConfigService.php';

$logger = new Logger();
$config = new ConfigService();
$message = '';
$error = false;

// 1. Token aus Request extrahieren
$token = $_GET['token'] ?? $_POST['token'] ?? '';

// 2. Token-Format validieren (64 Hex-Zeichen für SHA-256)
if (empty($token) || !preg_match('/^[a-f0-9]{64}$/i', $token)) {
    $logger->warning("Passwort-Reset mit ungültigem Token-Format versucht.");
    die("Ungültiges Token-Format oder Token fehlt.");
}

// 3. Token-Gültigkeit im ConfigService prüfen
// (Prüft Hash-Vergleich und Ablaufdatum)
if (!$config->verifyResetToken($token)) {
    $logger->warning("Ungültiger oder abgelaufener Reset-Token verwendet.");
    die("Der Link ist ungültig oder bereits abgelaufen.");
}

// 4. Passwort-Änderung verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'])) {
    $password = $_POST['new_password'];
    $confirm  = $_POST['confirm_password'];

    if (strlen($password) < 8) {
        $message = "Das Passwort muss mindestens 8 Zeichen lang sein.";
        $error = true;
    } elseif ($password !== $confirm) {
        $message = "Die Passwörter stimmen nicht überein.";
        $error = true;
    } else {
        // Passwort hashen und speichern
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if ($config->updatePassword($hash)) {
            // WICHTIG: Token nach Erfolg sofort löschen!
            $config->clearResetToken();
            
            $logger->info("Passwort erfolgreich über Reset-Link geändert.");
            $message = "Passwort wurde erfolgreich geändert. Sie können sich nun einloggen.";
            // Nach 3 Sekunden zum Login umleiten
            header("Refresh: 3; url=login.php");
        } else {
            $message = "Systemfehler beim Speichern des Passworts.";
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
    <title>Passwort neu setzen | VantixDash</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.0.3/src/regular/style.css">
</head>
<body class="login-page">
    <div class="login-card">
        <div class="login-header">
            <i class="ph ph-key" style="font-size: 3rem; color: var(--primary-color);"></i>
            <h2>Neues Passwort</h2>
            <p style="color: var(--text-muted); font-size: 0.9rem;">Bitte vergeben Sie ein sicheres Passwort.</p>
        </div>

        <?php if ($message): ?>
            <div class="alert <?php echo $error ? 'alert-danger' : 'alert-success'; ?>" style="margin-bottom: 20px; padding: 15px; border-radius: 8px;">
                <i class="ph <?php echo $error ? 'ph-warning' : 'ph-check-circle'; ?>"></i> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if (!$message || $error): ?>
        <form method="POST">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            
            <div class="form-group" style="margin-bottom: 15px;">
                <label style="display:block; margin-bottom:5px; font-size:0.8rem;">Neues Passwort</label>
                <input type="password" name="new_password" required minlength="8" autofocus
                       style="width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px; background: var(--card-bg);">
            </div>

            <div class="form-group" style="margin-bottom: 25px;">
                <label style="display:block; margin-bottom:5px; font-size:0.8rem;">Passwort bestätigen</label>
                <input type="password" name="confirm_password" required minlength="8"
                       style="width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px; background: var(--card-bg);">
            </div>

            <button type="submit" class="btn-primary" style="width: 100%; padding: 12px; border-radius: 8px; font-weight: 600;">
                Passwort speichern
            </button>
        </form>
        <?php else: ?>
            <p style="text-align: center; margin-top: 20px;">
                <a href="login.php" class="btn-primary" style="text-decoration: none; display: inline-block; padding: 10px 20px; border-radius: 8px;">Zum Login</a>
            </p>
        <?php endif; ?>
    </div>
</body>
</html>
