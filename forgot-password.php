<?php
declare(strict_types=1);
require_once __DIR__ . '/services/ConfigService.php';

$config = new ConfigService();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputEmail = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $storedEmail = $config->get('email');

    // Nur senden, wenn Email übereinstimmt und 2FA nicht gerade das System sperrt
    if (!empty($inputEmail) && strtolower($inputEmail) === strtolower((string)$storedEmail)) {
        $token = bin2hex(random_bytes(32));
        $config->setResetToken($token);
        
        $resetLink = "https://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset-password.php?token=" . $token;
        
        $subject = "VantixDash - Passwort zurücksetzen";
        $headers = [
            'From' => 'no-reply@' . $_SERVER['HTTP_HOST'],
            'Content-Type' => 'text/plain; charset=utf-8'
        ];
        
        $mailContent = "Hallo,\n\num Ihr Passwort zurückzusetzen, klicken Sie bitte auf den folgenden Link:\n";
        $mailContent .= $resetLink . "\n\nDieser Link ist 30 Minuten gültig.";
        
        // Hinweis: mail() setzt einen konfigurierten SMTP-Server voraus
        mail($inputEmail, $subject, $mailContent, $headers);
    }
    
    $message = "Falls diese E-Mail-Adresse registriert ist, wurde ein Reset-Link versendet.";
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8"><title>Passwort vergessen</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
    <div class="login-card">
        <h2>Passwort vergessen</h2>
        <?php if ($message): ?>
            <p class="alert alert-info"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>
        <form method="POST">
            <input type="email" name="email" placeholder="E-Mail Adresse" required>
            <button type="submit" class="btn-primary">Link anfordern</button>
        </form>
        <p><a href="login.php">Zurück zum Login</a></p>
    </div>
</body>
</html>
