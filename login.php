<?php
session_start();
require_once 'libs/GoogleAuthenticator.php';
$dataDir = __DIR__ . '/data';
$configFile = $dataDir . '/config.php';
if (file_exists($configFile)) {
    include $configFile;
} else {
    die("Fehler: Konfigurationsdatei nicht gefunden in $configFile");
}

$attemptsFile = $dataDir . '/login_attempts.json';
if (!is_dir($dataDir)) mkdir($dataDir, 0755, true);

// 1. Datenpflege & Brute-Force Schutz
$attempts = file_exists($attemptsFile) ? json_decode(file_get_contents($attemptsFile), true) : [];
$now = time();
$ip = $_SERVER['REMOTE_ADDR'];

// Alte Versuche löschen (> 24h)
$attempts = array_filter($attempts, function($a) use ($now) {
    return ($now - $a['last_attempt']) < 86400;
});

// Sperre prüfen (5 Versuche, 15 Min Sperre)
if (isset($attempts[$ip]) && $attempts[$ip]['count'] >= 5 && ($now - $attempts[$ip]['last_attempt']) < 900) {
    $remaining = 15 - floor(($now - $attempts[$ip]['last_attempt']) / 60);
    die("Zu viele Fehlversuche. Bitte in $remaining Minuten erneut versuchen.");
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';
    $code = $_POST['code'] ?? '';

    // Passwort & Benutzer checken (Annahme: $dashboard_user & $dashboard_password in config.php)
    if ($user === $dashboard_user && password_verify($pass, $dashboard_password)) {
        
        $ga = new PHPGangsta_GoogleAuthenticator();
        if ($ga->verifyCode($google_2fa_secret, $code, 2)) {
            // ERFOLG
            unset($attempts[$ip]);
            file_put_contents($attemptsFile, json_encode($attempts));
            
            $_SESSION['logged_in'] = true;
            $_SESSION['username'] = $user;
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            
            header('Location: index.php');
            exit;
        } else {
            $error = "Ungültiger 2FA-Code.";
        }
    } else {
        $error = "Zugangsdaten ungültig.";
    }

    // Fehler-Logging für Rate Limiting
    if ($error) {
        if (!isset($attempts[$ip])) $attempts[$ip] = ['count' => 0, 'last_attempt' => 0];
        $attempts[$ip]['count']++;
        $attempts[$ip]['last_attempt'] = $now;
        file_put_contents($attemptsFile, json_encode($attempts));
        sleep(1); 
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VantixDash | Login</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.0.3/src/regular/style.css">
</head>
<body class="login-page">

    <div class="login-card">
        <h2>VantixDash</h2>
        <p>Willkommen zurück! Bitte anmelden.</p>

        <?php if ($error): ?>
            <div class="alert"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Benutzername</label>
                <input type="text" name="username" placeholder="Dein Name" required autofocus>
            </div>
            <div class="form-group">
                <label>Passwort</label>
                <input type="password" name="password" placeholder="••••••••" required>
            </div>
            <div class="form-group">
                <label>2FA-Code (Authenticator App)</label>
                <input type="text" name="code" placeholder="000000" maxlength="6" pattern="\d{6}" required autocomplete="off">
            </div>
            <button type="submit" class="btn-primary">Anmelden</button>
        </form>
    </div>

</body>
</html>
