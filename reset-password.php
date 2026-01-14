<?php
require_once __DIR__ . '/services/ConfigService.php';
$config = new ConfigService();
$token = $_GET['token'] ?? $_POST['token'] ?? '';

if (!$config->verifyResetToken($token)) {
    die("Ungültiger oder abgelaufener Token.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPass = $_POST['password'] ?? '';
    if (strlen($newPass) >= 8) {
        $config->updatePassword(password_hash($newPass, PASSWORD_DEFAULT));
        // Token löschen nach Erfolg
        $config->set('reset_token', null);
        $config->save();
        header('Location: login.php?reset=success');
        exit;
    }
}
?>
