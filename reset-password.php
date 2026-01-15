<?php
declare(strict_types=1);

/**
 * reset-password.php - Token-Validierung und Passwort-Update
 */

require_once __DIR__ . '/autoload.php';

use VantixDash\Logger;
use VantixDash\Config\ConfigService;
use VantixDash\Config\ConfigRepository;
use VantixDash\User\PasswordService;

$logger = new Logger();
$repository = new ConfigRepository();
$configService = new ConfigService($repository);
$passwordService = new PasswordService($configService);

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
if (!$passwordService->verifyResetToken((string)$token)) {
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
        if ($passwordService->updatePassword($password)) {
            // Token nach Erfolg sofort entwerten
            $passwordService->clearResetToken();
            
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
            $logger->error("Fehler: Passwort-Update im PasswordService fehlgeschlagen.");
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
        .form-group label { display: block; margin-bottom: 0.5rem; color: #495057; font-size: 0.85rem; font-weight: 500; }
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
    </style>
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
