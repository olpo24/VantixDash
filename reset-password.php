<?php
declare(strict_types=1);
require_once __DIR__ . '/services/ConfigService.php';

$config = new ConfigService();
$token = $_GET['token'] ?? $_POST['token'] ?? '';
$error = '';

if (!$config->verifyResetToken($token)) {
    die("Der Link ist ungültig oder abgelaufen.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm']  ?? '';

    if (strlen($password) < 8) {
        $error = "Das Passwort muss mindestens 8 Zeichen lang sein.";
    } elseif ($password !== $confirm) {
        $error = "Passwörter stimmen nicht überein.";
    } else {
        // Erfolg: Passwort updaten und Token vernichten
        $config->updatePassword(password_hash($password, PASSWORD_DEFAULT));
        $config->clearResetToken();
        header('Location: login.php?reset=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8"><title>Neues Passwort vergeben</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="login-page">
    <div class="login-card">
        <h2>Neues Passwort</h2>
        <?php if ($error): ?>
            <p class="alert alert-danger"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            <input type="password" name="password" placeholder="Neues Passwort" required>
            <input type="password" name="confirm" placeholder="Passwort bestätigen" required>
            <button type="submit" class="btn-primary">Passwort speichern</button>
        </form>
    </div>
</body>
</html>
