<?php
/**
 * views/add_site.php
 * Native Version zum Hinzufügen von WordPress-Instanzen
 */

// Falls die Variable nicht existiert (Direktaufruf verhindern)
if (!isset($siteService)) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_site'])) {
    // CSRF Check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Sicherheits-Token ungültig.';
    } else {
        $name = trim($_POST['site_name']);
        $url  = trim($_POST['site_url']);

        if (empty($name) || empty($url)) {
            $error = 'Bitte fülle alle Felder aus.';
        } else {
            // Wir nutzen den Service, den index.php bereitgestellt hat
            $result = $siteService->addSite($name, $url);
            
            if ($result) {
                $success = 'Seite erfolgreich hinzugefügt!';
                // Optional: Weiterleitung nach 1 Sekunde
                echo "<script>setTimeout(() => { window.location.href='index.php?view=manage_sites'; }, 1000);</script>";
            } else {
                $error = 'Fehler beim Speichern der Seite.';
            }
        }
    }
}
?>

<div class="add-site-container">
    <div class="header-action">
        <h2>Neue WordPress-Seite hinzufügen</h2>
        <a href="index.php?view=manage_sites" class="secondary-button">Abbrechen</a>
    </div>

    <?php if ($error): ?>
        <div class="alert error"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert success"><?php echo $success; ?></div>
    <?php endif; ?>

    <div class="card">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <div class="form-group">
                <label for="site_name">Name der Webseite</label>
                <input type="text" id="site_name" name="site_name" placeholder="z.B. Mein Blog" required autofocus>
            </div>

            <div class="form-group">
                <label for="site_url">URL (inkl. https://)</label>
                <input type="url" id="site_url" name="site_url" placeholder="https://meine-seite.de" required>
                <small>Wichtig: Das Vantix-Child Plugin muss auf dieser Seite installiert sein.</small>
            </div>

            <div class="form-actions">
                <button type="submit" name="add_site" class="main-button">Speichern & Verbindung prüfen</button>
            </div>
        </form>
    </div>
</div>

<style>
.add-site-container { max-width: 600px; margin: 0 auto; }
.secondary-button { text-decoration: none; color: var(--text-muted); font-size: 0.9rem; }
.form-actions { margin-top: 25px; border-top: 1px solid var(--border-color); padding-top: 20px; }
</style>
