<?php
session_start();
$configPath = __DIR__ . '/data/config.php';
$config = include $configPath;

require_once __DIR__ . '/libs/GoogleAuthenticator.php';
$ga = new PHPGangsta_GoogleAuthenticator();

$message = '';
$error = '';
$showOTP = false;

// Hilfsfunktion zum Speichern der Config
function saveConfig($path, $config) {
    file_put_contents($path, "<?php\nreturn " . var_export($config, true) . ";");
}

// 1. LOGIK: PASSWORT ZURÜCKSETZEN VIA TOKEN
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    if ($config['reset_token'] && hash_equals($config['reset_token'], $token) && time() < $config['token_expires']) {
        $newPlainPassword = bin2hex(random_bytes(4)); 
        $config['password_hash'] = password_hash($newPlainPassword, PASSWORD_DEFAULT);
        $config['reset_token'] = null;
        $config['token_expires'] = null;
        $config['2fa_enabled'] = false; // Zur Sicherheit 2FA bei Reset deaktivieren
        saveConfig($configPath, $config);
        $message = "Erfolg! Dein neues Passwort lautet: <strong class='fs-5'>$newPlainPassword</strong><br>Bitte logge dich ein und ändere es sofort.";
    } else {
        $error = "Ungültiger oder abgelaufener Reset-Link.";
    }
}

// 2. LOGIK: LOGIN & RESET-ANFRAGE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- LOGIN VERARBEITUNG ---
    if (isset($_POST['login'])) {
        $user = $_POST['user'] ?? '';
        $pass = $_POST['pass'] ?? '';
        $otp  = $_POST['otp_code'] ?? '';

        if ($user === $config['username'] && password_verify($pass, $config['password_hash'])) {
            
            // Prüfung: Ist 2FA aktiv?
            if (!empty($config['2fa_enabled']) && $config['2fa_enabled'] === true) {
                if (empty($otp)) {
                    // Passwort war korrekt, aber Code fehlt noch -> Feld anzeigen
                    $showOTP = true;
                } else {
                    // Code wurde eingegeben -> Verifizieren
                    if ($ga->verifyCode($config['2fa_secret'], $otp, 2)) {
                        $_SESSION['authenticated'] = true;
                    } else {
                        $error = 'Falscher Authenticator-Code.';
                        $showOTP = true; // Feld weiterhin anzeigen
                    }
                }
            } else {
                // Kein 2FA aktiv -> Direkt rein
                $_SESSION['authenticated'] = true;
            }

            if (isset($_SESSION['authenticated'])) {
                header("Location: index.php");
                exit;
            }
        } else {
            $error = 'Ungültige Zugangsdaten.';
        }
    }
    
    // --- RESET ANFRAGE ---
    if (isset($_POST['request_reset'])) {
        if ($_POST['email'] === $config['email']) {
            $token = bin2hex(random_bytes(32));
            $config['reset_token'] = $token;
            $config['token_expires'] = time() + 1800; 
            saveConfig($configPath, $config);

            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
            $resetLink = $protocol . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] . "?token=" . $token;

            $subject = "VantixDash Passwort-Reset";
            $mailBody = "Klicke auf den folgenden Link, um dein Passwort zurückzusetzen (30 Min gültig):\n\n" . $resetLink;
            $headers = "From: no-reply@" . $_SERVER['HTTP_HOST'];

            if (mail($config['email'], $subject, $mailBody, $headers)) {
                $message = "Ein Reset-Link wurde an deine E-Mail gesendet.";
            } else {
                $error = "Mail-Versand fehlgeschlagen.";
            }
        } else {
            $message = "Falls die E-Mail korrekt war, wurde ein Link gesendet.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VantixDash | Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #f4f7f6; display: flex; align-items: center; justify-content: center; height: 100vh; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .login-card { width: 100%; max-width: 400px; border: none; border-radius: 16px; box-shadow: 0 15px 35px rgba(0,0,0,0.1); overflow: hidden; }
        .card-header-custom { background: #0d6efd; color: white; padding: 30px; text-align: center; }
        .form-control:focus { box-shadow: none; border-color: #0d6efd; }
        .otp-input { letter-spacing: 12px; font-weight: bold; font-size: 1.5rem; text-align: center; }
    </style>
</head>
<body>

<div class="card login-card">
    <div class="card-header-custom">
        <h3 class="mb-0 fw-bold">Vantix<span class="opacity-75">Dash</span></h3>
        <p class="small mb-0 opacity-75">Secure Access Control</p>
    </div>
    
    <div class="card-body p-4">
        <?php if($message): ?> <div class="alert alert-success small"><?php echo $message; ?></div> <?php endif; ?>
        <?php if($error): ?> <div class="alert alert-danger small"><?php echo $error; ?></div> <?php endif; ?>

        <div id="login-container" style="<?php echo isset($_GET['forgot']) ? 'display:none' : ''; ?>">
            <form method="POST">
                
                <?php if(!$showOTP): ?>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted text-uppercase">Benutzername</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-person"></i></span>
                            <input type="text" name="user" class="form-control border-start-0 ps-0" required autofocus>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted text-uppercase">Passwort</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-key"></i></span>
                            <input type="password" name="pass" class="form-control border-start-0 ps-0" required>
                        </div>
                    </div>
                    <button type="submit" name="login" class="btn btn-primary w-100 py-2 fw-bold shadow-sm">
                        Einloggen <i class="bi bi-arrow-right ms-2"></i>
                    </button>
                    <div class="text-center mt-3">
                        <a href="?forgot=1" class="text-muted small text-decoration-none">Passwort vergessen?</a>
                    </div>
                <?php else: ?>
                    <input type="hidden" name="user" value="<?php echo htmlspecialchars($_POST['user']); ?>">
                    <input type="hidden" name="pass" value="<?php echo htmlspecialchars($_POST['pass']); ?>">
                    
                    <div class="text-center mb-4">
                        <div class="display-6 text-primary mb-2"><i class="bi bi-shield-lock"></i></div>
                        <h6 class="fw-bold">2FA Bestätigung</h6>
                        <p class="small text-muted">Bitte gib den 6-stelligen Code aus deiner Authenticator-App ein.</p>
                    </div>

                    <div class="mb-4">
                        <input type="text" name="otp_code" class="form-control otp-input" 
                               maxlength="6" autocomplete="off" autofocus placeholder="000000">
                    </div>

                    <button type="submit" name="login" class="btn btn-dark w-100 py-2 fw-bold shadow-sm">
                        Code verifizieren
                    </button>
                    <div class="text-center mt-3">
                        <a href="login.php" class="text-muted small text-decoration-none">Abbrechen</a>
                    </div>
                <?php endif; ?>

            </form>
        </div>

        <div id="reset-container" style="<?php echo isset($_GET['forgot']) ? '' : 'display:none'; ?>">
            <div class="mb-4 text-center">
                <h6 class="fw-bold">Passwort zurücksetzen</h6>
                <p class="small text-muted">Wir senden dir einen Link an deine hinterlegte E-Mail.</p>
            </div>
            <form method="POST">
                <div class="mb-4">
                    <label class="form-label small fw-bold text-muted text-uppercase">E-Mail Adresse</label>
                    <input type="email" name="email" class="form-control" required placeholder="name@beispiel.de">
                </div>
                <button type="submit" name="request_reset" class="btn btn-primary w-100 mb-2 py-2 fw-bold">Link anfordern</button>
                <div class="text-center mt-2">
                    <a href="login.php" class="text-muted small text-decoration-none">Zurück zum Login</a>
                </div>
            </form>
        </div>
    </div>
</div>

</body>
</html>
