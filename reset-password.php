<?php
declare(strict_types=1);

/**
 * reset-password.php - Token-Validierung und Passwort-Update
 */

require_once __DIR__ . '/services/Logger.php';
require_once __DIR__ . '/services/ConfigService.php';

$logger = new Logger();
$config = new ConfigService();
$message = '';
$error = false;

// 1. Token aus Request extrahieren (POST bevorzugt für Formular-Submits)
$token = $_POST['token'] ?? $_GET['token'] ?? '';

// 2. Token-Format validieren (Strikte Prüfung auf 64 Hex-Zeichen)
if (empty($token) || !preg_match('/^[a-f0-9]{64}$/i', (string)$token)) {
    $logger->warning("Passwort-Reset mit ungültigem Token-Format abgebrochen.");
    die("Ungültiger Sicherheits-Token.");
}

// 3. Token-Gültigkeit prüfen
// Hinweis: Der ConfigService sollte intern hash_equals() nutzen!
if (!$config->verifyResetToken($token)) {
    $logger->warning("Abgelaufener oder manipulierter Reset-Token verwendet.");
    die("Dieser Link ist abgelaufen oder ungültig. Bitte fordern Sie einen neuen an.");
}

// 4. Passwort-Änderung verarbeiten
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'])) {
    $password = $_POST['new_password'];
    $confirm  = $_POST['confirm_password'];

    // Striktere Validierung
    if (strlen($password) < 8) {
        $message = "Das Passwort muss aus Sicherheitsgründen mindestens 8 Zeichen lang sein.";
        $error = true;
    } elseif ($password !== $confirm) {
        $message = "Die Passwort-Wiederholung stimmt nicht überein.";
        $error = true;
    } else {
        // Passwort sicher hashen
        $hash = password_hash($password, PASSWORD_DEFAULT);
        
        if ($config->updatePassword($hash)) {
            // WICHTIG: Token sofort nach Erfolg entwerten
            $config->clearResetToken();
            
            $logger->info("Passwort erfolgreich über Reset-Link zurückgesetzt.");
            $message = "Erfolg! Ihr Passwort wurde geändert. Sie werden gleich zum Login weitergeleitet.";
            
            // Session sicherheitshalber bereinigen
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }

            // Nach 3 Sekunden zum Login
            header("Refresh: 3; url=login.php");
        } else {
            $logger->error("Kritischer Fehler: Passwort-Update im ConfigService fehlgeschlagen.");
            $message = "Ein Systemfehler ist aufgetreten. Bitte kontaktieren Sie den Administrator.";
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
    <style>
        .login-card { max-width: 400px; margin: 10vh auto; }
        .alert-success { border-left: 4px solid #28a745; background: rgba(40, 167, 69, 0.1); color: #28a745; }
        .alert-danger { border-left: 4px solid #dc3545; background: rgba(220, 53, 69, 0.1); color: #dc3545; }
    </style>
</head>
<body class="login-page">
    <div class="login-card">
        <div class="login-header" style="text-align: center; margin-bottom: 30px;">
            <i class="ph ph-key" style="font-size: 3.5rem; color: var(--primary-color);"></i>
            <h2 style="margin-top: 15px;">Neues Passwort</h2>
            <p style="color: var(--text-muted); font-size: 0.9rem;">Sichern Sie Ihren Zugang mit einem starken Passwort.</p>
        </div>

        <?php if ($message): ?>
            <div class="alert <?php echo $error ? 'alert-danger' : 'alert-success'; ?>" style="margin-bottom: 25px; padding: 15px; border-radius: 8px; font-size: 0.9rem;">
                <i class="ph <?php echo $error ? 'ph-warning' : 'ph-check-circle'; ?>"></i> 
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if (!$message || $error): ?>
        <form method="POST" autocomplete="off">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            
            <div class="form-group" style="margin-bottom: 18px;">
                <label for="new_password" style="display:block; margin-bottom:8px; font-size:0.85rem; font-weight: 500;">Neues Passwort</label>
                <input type="password" id="new_password" name="new_password" required minlength="8" autofocus
                       placeholder="••••••••"
                       style="width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px; background: var(--card-bg); color: var(--text-color);">
            </div>

            <div class="form-group" style="margin-bottom: 25px;">
                <label for="confirm_password" style="display:block; margin-bottom:8px; font-size:0.85rem; font-weight: 500;">Passwort bestätigen</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="8"
                       placeholder="••••••••"
                       style="width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px; background: var(--card-bg); color: var(--text-color);">
            </div>

            <button type="submit" class="btn-primary" style="width: 100%; padding: 14px; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; background: var(--primary-color); color: white;">
                Passwort jetzt speichern
            </button>
        </form>
        <?php else: ?>
            <div style="text-align: center; margin-top: 20px;">
                <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 20px;">Sie werden automatisch weitergeleitet...</p>
                <a href="login.php" class="btn-primary" style="text-decoration: none; display: inline-block; padding: 12px 25px; border-radius: 8px; background: var(--primary-color); color: white;">
                    Sofort zum Login
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
