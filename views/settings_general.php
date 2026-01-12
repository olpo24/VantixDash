<?php
/**
 * views/settings_general.php
 * Native Version ohne Bootstrap, optimiert für JSON-Config und AJAX-Updates
 */

// Falls diese Datei direkt aufgerufen wird
if (!isset($configService)) {
    header('Location: ../index.php');
    exit;
}

$message = '';

// Speichern der allgemeinen Einstellungen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_general'])) {
    // CSRF Schutz
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = '<div class="alert error">Ungültiger Sicherheits-Token.</div>';
    } else {
        $configService->set('github_token', $_POST['github_token']);
        // Hier könnten weitere allgemeine Einstellungen folgen
        
        if ($configService->save()) {
            $message = '<div class="alert success">Einstellungen erfolgreich gespeichert!</div>';
        } else {
            $message = '<div class="alert error">Fehler beim Speichern der Konfiguration.</div>';
        }
    }
}

$currentVersion = $configService->getVersion();
?>

<div class="settings-container">
    <h2>Allgemeine Einstellungen</h2>
    
    <?php echo $message; ?>

    <div id="update-container" class="update-banner" style="display: none;">
        <div class="update-content">
            <div class="update-text">
                <strong>System-Update verfügbar!</strong>
                <span>Version <span id="new-version-number" class="badge"></span> ist bereit zur Installation.</span>
            </div>
            <button id="start-update-btn" class="main-button" data-url="">
                Jetzt installieren
            </button>
        </div>
    </div>

    <div class="settings-card">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <div class="form-group">
                <label>Aktuelle System-Version</label>
                <div class="version-info">
                    <strong>v<?php echo htmlspecialchars($currentVersion); ?></strong>
                </div>
            </div>

            <div class="form-group">
                <label for="github_token">GitHub Personal Access Token</label>
                <input type="password" id="github_token" name="github_token" 
                       value="<?php echo htmlspecialchars($configService->get('github_token', '')); ?>" 
                       placeholder="ghp_xxxxxxxxxxxx">
                <small>Wird benötigt, um Updates aus privaten Repositories zu laden oder API-Limits zu umgehen.</small>
            </div>

            <div class="form-actions">
                <button type="submit" name="save_general" class="main-button">
                    Änderungen speichern
                </button>
            </div>
        </form>
    </div>
</div>

<style>
/* Minimales Styling passend zum Dashboard-Look */
.settings-container { max-width: 800px; margin: 0 auto; padding: 20px; }
.settings-card { background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
.form-group { margin-bottom: 20px; }
.form-group label { display: block; margin-bottom: 8px; font-weight: bold; font-size: 0.9em; }
.form-group input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: inherit; }
.form-group small { color: #666; font-size: 0.8em; display: block; margin-top: 5px; }
.alert { padding: 15px; border-radius: 4px; margin-bottom: 20px; }
.alert.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.alert.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
.update-banner { background: #e3f2fd; border: 1px solid #2196f3; padding: 15px 20px; border-radius: 8px; margin-bottom: 25px; }
.update-content { display: flex; justify-content: space-between; align-items: center; }
.update-text strong { display: block; color: #0d47a1; }
.badge { background: #2196f3; color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.9em; }
.version-info { background: #f8f9fa; padding: 10px; border-radius: 4px; border: 1px solid #eee; display: inline-block; }
</style>
