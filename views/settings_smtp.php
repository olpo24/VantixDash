<?php
declare(strict_types=1);

// Falls direkt aufgerufen, zurück zum Dashboard
if (!isset($smtpConfigService)) {
    // Service muss initialisiert werden
    use VantixDash\Mail\SmtpConfigService;
    $smtpConfigService = new SmtpConfigService($configService);
}

$statusMessage = '';
$error = false;

// Formularverarbeitung (Speichern)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_smtp'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        $statusMessage = "Sicherheitsfehler: CSRF-Token ungültig.";
        $error = true;
    } else {
        $smtpData = [
            'host'       => $_POST['smtp_host'] ?? '',
            'user'       => $_POST['smtp_user'] ?? '',
            'pass'       => $_POST['smtp_pass'] ?? '',
            'port'       => $_POST['smtp_port'] ?? 587,
            'from_email' => $_POST['smtp_from_email'] ?? '',
            'from_name'  => $_POST['smtp_from_name'] ?? ''
        ];

        if ($smtpConfigService->updateConfig($smtpData)) {
            $statusMessage = "SMTP-Einstellungen erfolgreich gespeichert.";
            $logger->info("SMTP-Konfiguration wurde aktualisiert.");
        } else {
            $statusMessage = "Fehler beim Speichern der Konfiguration.";
            $error = true;
        }
    }
}

$smtp = $smtpConfigService->getConfig();
?>

<div class="content-header">
    <div>
        <h1><i class="ph ph-envelope-simple"></i> SMTP Einstellungen</h1>
        <p class="text-muted">Konfiguriere den Mail-Versand für System-Benachrichtigungen.</p>
    </div>
</div>

<?php if ($statusMessage): ?>
    <div class="alert <?php echo $error ? 'alert-danger' : 'alert-success'; ?>" style="margin-bottom: 20px;">
        <i class="ph <?php echo $error ? 'ph-warning' : 'ph-check-circle'; ?>"></i>
        <?php echo htmlspecialchars($statusMessage); ?>
    </div>
<?php endif; ?>

<div class="card">
    <form method="POST" action="index.php?view=settings_smtp">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
            <div>
                <h3>Server-Konfiguration</h3>
                <div class="form-group" style="margin-bottom: 15px;">
                    <label>SMTP Host</label>
                    <input type="text" name="smtp_host" value="<?php echo htmlspecialchars($smtp['host']); ?>" placeholder="smtp.example.com" required>
                </div>
                <div class="form-group" style="margin-bottom: 15px;">
                    <label>Port</label>
                    <input type="number" name="smtp_port" value="<?php echo htmlspecialchars((string)$smtp['port']); ?>" placeholder="587" required>
                </div>
                <div class="form-group" style="margin-bottom: 15px;">
                    <label>Benutzername</label>
                    <input type="text" name="smtp_user" value="<?php echo htmlspecialchars($smtp['user']); ?>" required>
                </div>
                <div class="form-group" style="margin-bottom: 15px;">
                    <label>Passwort</label>
                    <input type="password" name="smtp_pass" value="<?php echo htmlspecialchars($smtp['pass']); ?>">
                </div>
            </div>

            <div>
                <h3>Absender & Test</h3>
                <div class="form-group" style="margin-bottom: 15px;">
                    <label>Absender E-Mail</label>
                    <input type="email" name="smtp_from_email" value="<?php echo htmlspecialchars($smtp['from_email']); ?>" required>
                </div>
                <div class="form-group" style="margin-bottom: 15px;">
                    <label>Absender Name</label>
                    <input type="text" name="smtp_from_name" value="<?php echo htmlspecialchars($smtp['from_name']); ?>">
                </div>

                <div style="margin-top: 40px; padding: 20px; background: var(--bg-color); border-radius: 8px; border: 1px dashed var(--border-color);">
                    <h4 style="margin-top: 0;"><i class="ph ph-paper-plane-tilt"></i> Verbindung testen</h4>
                    <p style="font-size: 0.85rem; color: var(--text-muted);">Speichere zuerst die Daten, bevor du den Test startest.</p>
                    <div style="display: flex; gap: 10px;">
                        <input type="email" id="test_email_target" placeholder="Empfänger E-Mail" style="flex: 1;">
                        <button type="button" class="btn-secondary" onclick="sendTestEmail()" id="btn-test-mail">
                            Test senden
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div style="margin-top: 30px; border-top: 1px solid var(--border-color); padding-top: 20px; text-align: right;">
            <button type="submit" name="save_smtp" class="btn-primary">
                <i class="ph ph-floppy-disk"></i> Einstellungen speichern
            </button>
        </div>
    </form>
</div>

<script>
async function sendTestEmail() {
    const target = document.getElementById('test_email_target').value;
    const btn = document.getElementById('btn-test-mail');
    
    if (!target) {
        alert('Bitte eine Empfänger-Adresse für den Test eingeben.');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="ph ph-circle-notch animate-spin"></i> Sende...';

    try {
        const formData = new FormData();
        formData.append('email', target);
        formData.append('csrf_token', '<?php echo $_SESSION['csrf_token']; ?>');

        const response = await fetch('api.php?action=test_smtp', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Erfolg! Die Test-Mail wurde versendet. Bitte prüfe dein Postfach.');
        } else {
            alert('Fehler: ' + result.message);
        }
    } catch (e) {
        alert('Ein Netzwerkfehler ist aufgetreten.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = 'Test senden';
    }
}
</script>
