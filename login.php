<?php
/**
 * login.php
 * Verarbeitet den Login unter Nutzung des ConfigService (JSON)
 */

session_start();
require_once __DIR__ . '/services/ConfigService.php';

// Falls bereits eingeloggt, direkt zum Dashboard
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: index.php');
    exit;
}

$configService = new ConfigService();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';

    // Daten aus dem ConfigService holen (ehemals aus der config.php)
    $storedUser = $configService->get('username');
    $storedHash = $configService->get('password_hash');
    $is2faEnabled = $configService->get('2fa_enabled', false);

    if ($user === $storedUser && password_verify($pass, $storedHash)) {
        
        // Login erfolgreich - prüfen ob 2FA nötig ist
        if ($is2faEnabled) {
            // Wir merken uns den User temporär und leiten zur 2FA-Seite weiter
            $_SESSION['temp_user_for_2fa'] = $user;
            header('Location: login_2fa.php');
            exit;
        }

        // Kein 2FA? Dann direkt rein ins Dashboard
        $_SESSION['logged_in'] = true;
        $_SESSION['username'] = $user;
        
        // CSRF-Token generieren (WICHTIG für deine Sicherheit!)
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        header('Location: index.php');
        exit;
    } else {
        $error = 'Ungültige Zugangsdaten.';
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Login - VantixDash</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; display: flex; align-items: center; justify-content: center; height: 100vh; }
        .login-card { width: 100%; max-width: 400px; padding: 2rem; border-radius: 1rem; box-shadow: 0 10px 25px rgba(0,0,0,0.05); background: white; }
    </style>
</head>
<body>

<div class="login-card">
    <h3 class="text-center mb-4 fw-bold">VantixDash</h3>
    
    <?php if($error): ?>
        <div class="alert alert-danger py-2 small text-center"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label small fw-bold">Benutzername</label>
            <input type="text" name="username" class="form-control" required autofocus>
        </div>
        <div class="mb-4">
            <label class="form-label small fw-bold">Passwort</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary w-100 fw-bold">Anmelden</button>
    </form>
</div>

</body>
</html>
