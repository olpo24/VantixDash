<?php
declare(strict_types=1);

require_once __DIR__ . '/services/Logger.php';
require_once __DIR__ . '/services/ConfigService.php';
require_once __DIR__ . '/services/MailService.php';
require_once __DIR__ . '/services/RateLimiter.php';

$logger = new Logger();
$config = new ConfigService();
$mailService = new MailService($config, $logger);
$rateLimiter = new RateLimiter();

$message = '';
$error = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    /**
     * RATE LIMITING
     * Nutzt deinen bestehenden RateLimiter.
     * Limit: 3 Versuche in 600 Sekunden (10 Minuten)
     */
    if (!$rateLimiter->checkLimit($ip . '_pw_reset', 3, 600)) {
        $logger->warning("Rate-Limit für Passwort-Reset überschritten", ['ip' => $ip]);
        $message = "Zu viele Anfragen. Bitte warten Sie 10 Minuten.";
        $error = true;
    } else {
        $inputEmail = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
        $storedEmail = $config->get('email');

        // Sicherheits-Check: Nur senden, wenn Email exakt übereinstimmt
        if (!empty($inputEmail) && strtolower($inputEmail) === strtolower((string)$storedEmail)) {
            
            // 1. Token generieren (64 Zeichen Hex für SHA-256 Validierung)
            $token = bin2hex(random_bytes(32));
            $config->setResetToken($token);
            
            // 2. Reset-Link erstellen
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
            $resetLink = $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset-password.php?token=" . $token;
            
            // 3. E-Mail Inhalt
            $subject = "VantixDash - Passwort zurücksetzen";
            $htmlBody = "
                <div style='font-family: sans-serif; padding: 20px; border: 1px solid #eee; border-radius: 10px;'>
                    <h2 style='color: #4a90e2;'>Passwort zurücksetzen</h2>
                    <p>Hallo,</p>
                    <p>Klicken Sie auf den folgenden Button, um Ihr Passwort für <strong>VantixDash</strong> zu ändern:</p>
                    <p style='margin: 25px 0;'>
                        <a href='{$resetLink}' style='background-color: #4a90e2; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Passwort jetzt ändern</a>
                    </p>
                    <p style='font-size: 0.8rem; color: #777;'>Dieser Link ist 30 Minuten gültig.</p>
                </div>";
            
            $altText = "Ihr Link zum Zurücksetzen des Passworts: " . $resetLink;

            // 4. Versand via SMTP
            if ($mailService->send($inputEmail, $subject, $htmlBody, $altText)) {
                $logger->info("Passwort-Reset-E-Mail erfolgreich versendet", ['to' => $inputEmail]);
            }
        }
        
        /**
         * Immer die gleiche Erfolgsmeldung anzeigen (Security by Obscurity),
         * es sei denn, das Rate-Limit hat bereits vorher abgebrochen.
         */
        $message = "Falls diese E-Mail-Adresse registriert ist, wurde ein Reset-Link versendet. Bitte prüfen Sie auch Ihren Spam-Ordner.";
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Passwort vergessen | VantixDash</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.0.3/src/regular/style.css">
</head>
<body class="login-page">
    <div class="login-card">
        <div class="login-header">
            <i class="ph ph-lock-key-open" style="font-size: 3rem; color: var(--primary-color);"></i>
            <h2>Passwort vergessen</h2>
            <p style="color: var(--text-muted); font-size: 0.9rem;">Geben Sie Ihre E-Mail ein, um einen Link zu erhalten.</p>
        </div>

        <?php if ($message): ?>
            <div class="alert <?php echo $error ? 'alert-danger' : 'alert-info'; ?>" style="margin-bottom: 20px; padding: 15px; border-radius: 8px; font-size: 0.9rem;">
                <i class="ph <?php echo $error ? 'ph-warning' : 'ph-info'; ?>"></i> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group" style="margin-bottom: 20px;">
                <input type="email" name="email" placeholder="E-Mail Adresse" required 
                       style="width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px; background: var(--card-bg);">
            </div>
            <button type="submit" class="btn-primary" style="width: 100%; padding: 12px; border-radius: 8px; font-weight: 600;">
                Link anfordern
            </button>
        </form>

        <div class="login-footer" style="margin-top: 25px; text-align: center;">
            <p><a href="login.php" style="text-decoration: none; color: var(--text-muted); font-size: 0.9rem;">
                <i class="ph ph-arrow-left"></i> Zurück zum Login
            </a></p>
        </div>
    </div>
</body>
</html>
