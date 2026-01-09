<?php
/**
 * VantixDash - Secure Login
 * Features: Rate-Limiting, 2FA, Session Fixation Protection, Data Hygiene
 */

session_start();
require_once 'libs/GoogleAuthenticator.php';

$dataDir = __DIR__ . '/data';
$configFile = $dataDir . '/config.php';
$attemptsFile = $dataDir . '/login_attempts.json';

// 1. Verzeichnisse & Config prüfen
if (!is_dir($dataDir)) mkdir($dataDir, 0755, true);

if (file_exists($configFile)) {
    $config = include $configFile;
} else {
    die("Kritischer Fehler: Konfigurationsdatei nicht gefunden.");
}

// Mapping der Baseline-Variablen aus der Config
$dashboard_user     = $config['dashboard_user'] ?? '';
$dashboard_password = $config['dashboard_password'] ?? '';
$google_2fa_secret  = $config['google_2fa_secret'] ?? '';
$is_2fa_enabled     = $config['2fa_enabled'] ?? true;

// 2. Rate-Limiting & Datenpflege
$attempts = file_exists($attemptsFile) ? json_decode(file_get_contents($attemptsFile), true) : [];
$now = time();
$ip = $_SERVER['REMOTE_ADDR'];

// Datenhygiene: Einträge älter als 24h löschen
$attempts = array_filter($attempts, function($a) use ($now) {
    return ($now - $a['last_attempt']) < 86400;
});

// Brute-Force Sperre prüfen (5 Versuche, 15 Min Sperre)
if (isset($attempts[$ip]) && $attempts[$ip]['count'] >= 5 && ($now - $attempts[$ip]['last_attempt']) < 900) {
    $remaining = 15 - floor(($now - $attempts[$ip]['last_attempt']) / 60);
    die("Sicherheits-Sperre: Zu viele Fehlversuche. Bitte in $remaining Minuten erneut versuchen.");
}

$error = '';

// 3. Login-Logik
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userInput = $_POST['username'] ?? '';
    $passInput = $_POST['password'] ?? '';
    $codeInput = $_POST['code'] ?? '';

    // Passwort & Benutzer verifizieren
    if ($userInput === $dashboard_user && password_verify($passInput, $dashboard_password)) {
        
        $authenticated = true;

        // 2FA Check (falls aktiviert)
        if ($is_2fa_enabled) {
            $ga = new PHPGangsta_GoogleAuthenticator();
            if (!$ga->verifyCode($google_2fa_secret, $codeInput, 2)) {
                $authenticated = false;
                $error = "Ungültiger 2FA-Code.";
            }
        }

        if ($authenticated) {
            // ERFOLG: Sperre für diese IP aufheben
            unset($attempts[$ip]);
            file_put_contents($attemptsFile, json_encode($attempts));

            // SESSION-HÄRTUNG
            session_regenerate_id(true); // Schutz gegen Session Fixation
            $_SESSION['logged_in'] = true;
            $_SESSION['username'] = $userInput;
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            session_write_close();
            header('Location: index.php');
            exit;
        }
    } else {
        $error = "Zugangsdaten ungültig.";
    }

    // Fehler-Handling: Counter erhöhen & Delay
    if ($error) {
        if (!isset($attempts[$ip])) {
            $attempts[$ip] = ['count' => 0, 'last_attempt' => 0];
        }
        $attempts[$ip]['count']++;
        $attempts[$ip]['last_attempt'] = $now;
        file_put_contents($attemptsFile, json_encode($attempts));
        
        sleep(1); // Tarpitting gegen Bots
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
    <style>
        body.login-page {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: #f4f7fa;
            margin: 0;
            font-family: 'Inter', -apple-system, sans-serif;
        }
        .login-card {
            background: white;
            padding: 2.5rem;
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            width: 100%;
            max-width: 400px;
        }
        .login-card h2 { margin: 0 0 0.5rem 0; font-weight: 800; color: #1e293b; }
        .login-card p { color: #64748b; margin-bottom: 2rem; font-size: 0.9rem; }
        .form-group { margin-bottom: 1.25rem; }
        .form-group label { display: block; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.5rem; color: #475569; }
        .form-group input { 
            width: 100%; padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 1rem;
            transition: all 0.2s; box-sizing: border-box;
        }
        .form-group input:focus { outline: none; border-color: #2563eb; ring: 2px solid #dbeafe; }
        .alert { 
            background: #fef2f2; color: #991b1b; padding: 0.75rem; border-radius: 8px; 
            margin-bottom: 1.5rem; font-size: 0.85rem; border: 1px solid #fee2e2;
        }
        .btn-primary { 
            width: 100%; background: #2563eb; color: white; border: none; padding: 0.75rem; 
            border-radius: 8px; font-weight: 600; cursor: pointer; transition: background 0.2s;
            font-size: 1rem;
        }
        .btn-primary:hover { background: #1d4ed8; }
    </style>
</head>
<body class="login-page">

    <div class="login-card">
        <h2>VantixDash</h2>
        <p>Sicherer Login erforderlich</p>

        <?php if ($error): ?>
            <div class="alert"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div class="form-group">
                <label>Benutzername</label>
                <input type="text" name="username" required autofocus>
            </div>
            <div class="form-group">
                <label>Passwort</label>
                <input type="password" name="password" required>
            </div>
            
            <?php if ($is_2fa_enabled): ?>
            <div class="form-group">
                <label>2FA-Code</label>
                <input type="text" name="code" placeholder="000000" maxlength="6" pattern="\d*" required>
            </div>
            <?php endif; ?>

            <button type="submit" class="btn-primary">Anmelden</button>
        </form>
    </div>

</body>
</html>
