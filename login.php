<?php
session_start();
require_once 'libs/GoogleAuthenticator.php'; // Pfad anpassen falls nötig
include 'config.php';

$dataDir = __DIR__ . '/data';
$attemptsFile = $dataDir . '/login_attempts.json';

if (!is_dir($dataDir)) mkdir($dataDir, 0755, true);

// Hilfsfunktion: Versuche laden
function getAttempts($file) {
    if (!file_exists($file)) return [];
    return json_decode(file_get_contents($file), true) ?: [];
}

$ip = $_SERVER['REMOTE_ADDR'];
$attempts = getAttempts($attemptsFile);
$now = time();
$attempts = array_filter($attempts, function($a) use ($now) {
    return ($now - $a['last_attempt']) < 86400; // Entfernt alles, was älter als 24h ist
});
// 1. Brute-Force Sperre prüfen (15 Min Sperre nach 5 Fehlern)
if (isset($attempts[$ip]) && $attempts[$ip]['count'] >= 5 && ($now - $attempts[$ip]['last_attempt']) < 900) {
    $remaining = 15 - floor(($now - $attempts[$ip]['last_attempt']) / 60);
    die("Zu viele Fehlversuche. Bitte versuche es in $remaining Minuten erneut.");
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $code = $_POST['code'] ?? '';

    // 2. Passwort verifizieren
    if (password_verify($password, $dashboard_password)) {
        
        // 3. 2FA Code verifizieren
        $ga = new PHPGangsta_GoogleAuthenticator();
        $checkResult = $ga->verifyCode($google_2fa_secret, $code, 2); // 2 = 2*30s Toleranz

        if ($checkResult) {
            // ERFOLG: Sperre aufheben & Login
            unset($attempts[$ip]);
            file_put_contents($attemptsFile, json_encode($attempts));
            
            $_SESSION['logged_in'] = true;
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            header('Location: index.php');
            exit;
        } else {
            $error = "Ungültiger 2FA Code!";
            handleFailure($ip, $attempts, $attemptsFile);
        }
    } else {
        $error = "Falsches Passwort!";
        handleFailure($ip, $attempts, $attemptsFile);
    }
}

function handleFailure($ip, &$attempts, $file) {
    if (!isset($attempts[$ip])) {
        $attempts[$ip] = ['count' => 0, 'last_attempt' => 0];
    }
    $attempts[$ip]['count']++;
    $attempts[$ip]['last_attempt'] = time();
    file_put_contents($file, json_encode($attempts));
    sleep(1); // Anti-Bot Delay
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>VantixDash Login</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.0.3/src/regular/style.css">
</head>
<body class="login-page">
    <div class="login-card">
        <h2>VantixDash</h2>
        <p>Bitte authentifizieren</p>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Passwort</label>
                <input type="password" name="password" required autofocus>
            </div>
            <div class="form-group">
                <label>2FA Code</label>
                <input type="text" name="code" placeholder="000000" required autocomplete="off">
            </div>
            <button type="submit" class="btn-primary w-100">Login</button>
        </form>
    </div>
</body>
</html>
