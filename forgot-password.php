<?php
require_once __DIR__ . '/services/ConfigService.php';
$config = new ConfigService();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $storedEmail = $config->get('email');

    // Wir geben immer die gleiche Nachricht aus (Privacy), 
    // senden die Mail aber nur, wenn die Adresse stimmt.
    if ($email === $storedEmail && !empty($email)) {
        $token = bin2hex(random_bytes(32));
        $config->setResetToken($token);
        
        $resetLink = "https://" . $_SERVER['HTTP_HOST'] . "/reset-password.php?token=" . $token;
        
        $subject = "VantixDash Passwort zurücksetzen";
        $message = "Klicken Sie hier, um Ihr Passwort zu ändern: " . $resetLink;
        mail($email, $subject, $message, "From: no-reply@" . $_SERVER['HTTP_HOST']);
    }
    $message = "Falls die E-Mail existiert, wurde ein Link gesendet.";
}
?>
