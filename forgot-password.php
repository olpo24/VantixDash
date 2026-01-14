<?php
declare(strict_types=1);

/**
 * forgot_password.php - Sicherer Passwort-Reset-Flow
 */

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
     * 1. RATE LIMITING
     * Schützt vor Brute-Force Versuchen auf die Reset-Funktion.
     */
    if (!$rateLimiter->checkLimit($ip . '_pw_reset', 3, 600)) {
        $logger->warning("Rate-Limit für Passwort-Reset überschritten", ['ip' => $ip]);
        $message = "Zu viele Anfragen. Bitte warten Sie 10 Minuten.";
        $error = true;
    } else {
        // 2. EMAIL VALIDIERUNG (Statt nur Sanitize)
        $emailRaw = $_POST['email'] ?? '';
        $inputEmail = filter_var($emailRaw, FILTER_VALIDATE_EMAIL);
        
        // Immer die gleiche Meldung für den User am Ende vorbereiten
        $message = "Falls diese E-Mail-Adresse registriert ist, wurde ein Reset-Link versendet. Bitte prüfen Sie auch Ihren Spam-Ordner.";

        if ($inputEmail) {
            $storedEmail = $config->get('email');

            // 3. Sicherheits-Check: Nur senden, wenn Email exakt übereinstimmt
            if (strtolower($inputEmail) === strtolower((string)$storedEmail)) {
                
                // Token generieren (64 Zeichen Hex)
                $token = bin2hex(random_bytes(32));
                $config->setResetToken($token);
                
                // Reset-Link erstellen
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
                $resetLink = $protocol . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . "/reset-password.php?token=" . $token;
                
                // E-Mail Inhalt
                $subject = "VantixDash - Passwort zurücksetzen";
                $htmlBody = "
                    <div style='font-family: sans-serif; padding: 20px; border: 1px solid #eee; border-radius: 10px;'>
                        <h2 style='color: #4a90e2;'>Passwort zurücksetzen</h2>
                        <p>Hallo,</p>
                        <p>Klicken Sie auf den folgenden Button, um Ihr Passwort für <strong>VantixDash</strong> zu ändern:</p>
                        <p style='margin: 25px 0;'>
                            <a href='{$resetLink}' style='background-color: #4a90e2; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;'>Passwort jetzt ändern</a>
                        </p>
                        <p style='font-size: 0.8rem; color: #777;'>Dieser Link ist 30 Minuten gültig. Falls Sie dies nicht angefordert haben, können Sie diese E-Mail ignorieren.</p>
                    </div>";
                
                $altText = "Ihr Link zum Zurücksetzen des Passworts: " . $resetLink;

                // Versand via SMTP
                if ($mailService->send($inputEmail, $subject, $htmlBody, $altText)) {
                    $logger->info("Passwort-Reset-E-Mail erfolgreich versendet", ['to' => $inputEmail]);
                } else {
                    $logger->error("Fehler beim Versand der Reset-E-Mail", ['to' => $inputEmail]);
                }
            } else {
                // E-Mail stimmt nicht mit hinterlegtem Admin überein - Loggen für Security-Monitoring
                $logger->notice("Passwort-Reset für nicht-existente E-Mail angefordert", ['input' => $inputEmail]);
            }
        } else {
            // E-Mail Format war komplett ungültig
            $logger->warning("Passwort-Reset mit ungültigem E-Mail-Format versucht", ['input' => $emailRaw]);
        }
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
            <div class="alert <?php echo $error ? 'alert-danger' : 'alert-info'; ?>" style="margin-bottom: 20px; padding: 15px; border-radius: 8px; font-size: 0.9rem; border-left: 4px solid <?php echo $error ? '#ff4d4d' : '#4a90e2'; ?>;">
                <i class="ph <?php echo $error ? 'ph-warning' : 'ph-info'; ?>"></i> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" autocomplete="off">
            <div class="form-group" style="margin-bottom: 20px;">
                <label for="email" style="display:block; margin-bottom: 8px; font-size: 0.85rem; color: var(--text-muted);">E-Mail Adresse</label>
                <input type="email" id="email" name="email" placeholder="beispiel@domain.de" required 
                       style="width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px; background: var(--card-bg); color: var(--text-color);">
            </div>
            <button type="submit" class="btn-primary" style="width: 100%; padding: 12px; border-radius: 8px; font-weight: 600; cursor: pointer; border: none; background: var(--primary-color); color: white;">
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
