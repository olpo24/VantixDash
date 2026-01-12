<?php
/**
 * views/add_site.php
 * Erfolgsmeldung mit API-Key Anzeige und Kopier-Funktion
 */

if (!isset($siteService)) {
    header('Location: index.php');
    exit;
}

$error = '';
$newSiteData = null; // Speichert die Daten der neu angelegten Seite

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_site'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Sicherheits-Token ungültig.';
    } else {
        $name = trim($_POST['site_name']);
        $url  = trim($_POST['site_url']);

        if (empty($name) || empty($url)) {
            $error = 'Bitte fülle alle Felder aus.';
        } else {
            $newSiteData = $siteService->addSite($name, $url);
            if (!$newSiteData) {
                $error = 'Fehler beim Speichern der Seite.';
            }
        }
    }
}
?>

<div class="add-site-container">
    <div class="header-action">
        <h2><i class="ph ph-plus-circle"></i> Neue WordPress-Seite</h2>
        <a href="index.php?view=manage_sites" class="secondary-link">
            <i class="ph ph-arrow-left"></i> Zurück zur Übersicht
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert error">
            <i class="ph ph-warning-circle"></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <?php if ($newSiteData): ?>
        <div class="card success-card">
            <div class="success-header">
                <i class="ph ph-check-circle"></i>
                <h3>Seite erfolgreich angelegt!</h3>
                <p>Kopiere diesen Schlüssel und füge ihn in den Einstellungen des Vantix-Child Plugins auf deiner WordPress-Seite ein.</p>
            </div>

            <div class="api-key-box">
                <label>API Key für <strong><?php echo htmlspecialchars($newSiteData['name']); ?></strong></label>
                <div class="copy-wrapper">
                    <input type="text" id="api-key-input" value="<?php echo $newSiteData['api_key']; ?>" readonly>
                    <button type="button" class="copy-btn" onclick="copyApiKey()">
                        <i class="ph ph-copy" id="copy-icon"></i> <span id="copy-text">Kopieren</span>
                    </button>
                </div>
            </div>

            <div class="next-steps">
                <a href="index.php?view=manage_sites" class="main-button">
                    <i class="ph ph-list-bullets"></i> Zu meinen Seiten
                </a>
                <a href="index.php?view=add_site" class="ghost-button">
                    <i class="ph ph-plus"></i> Weitere Seite hinzufügen
                </a>
            </div>
        </div>

        <script>
        function copyApiKey() {
            const copyText = document.getElementById("api-key-input");
            copyText.select();
            copyText.setSelectionRange(0, 99999); // Für Mobilgeräte
            navigator.clipboard.writeText(copyText.value);

            // Visuelles Feedback
            const icon = document.getElementById("copy-icon");
            const text = document.getElementById("copy-text");
            
            icon.classList.replace("ph-copy", "ph-check");
            text.innerText = "Kopiert!";
            
            setTimeout(() => {
                icon.classList.replace("ph-check", "ph-copy");
                text.innerText = "Kopieren";
            }, 2000);
        }
        </script>

    <?php else: ?>
        <div class="card">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="form-group">
                    <label for="site_name">Name der Webseite</label>
                    <input type="text" id="site_name" name="site_name" placeholder="z.B. Kundenprojekt X" required autofocus>
                </div>

                <div class="form-group">
                    <label for="site_url">URL (inkl. https://)</label>
                    <input type="url" id="site_url" name="site_url" placeholder="https://meine-seite.de" required>
                    <small>Die URL ohne abschließenden Slash (/).</small>
                </div>

                <div class="form-actions">
                    <button type="submit" name="add_site" class="main-button">
                        <i class="ph ph-floppy-disk"></i> Seite anlegen & Key generieren
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</div>

<style>
.add-site-container { max-width: 650px; margin: 0 auto; }
.header-action { margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; }
.secondary-link { text-decoration: none; color: var(--text-muted); display: flex; align-items: center; gap: 5px; }

/* Erfolgs-Styling */
.success-card { border: 2px solid var(--success); text-align: center; padding: 2.5rem; }
.success-header i { font-size: 3.5rem; color: var(--success); margin-bottom: 1rem; }
.success-header h3 { margin: 0 0 0.5rem 0; font-size: 1.5rem; }

.api-key-box { background: #f1f5f9; padding: 1.5rem; border-radius: 8px; margin: 2rem 0; text-align: left; }
.api-key-box label { display: block; margin-bottom: 10px; font-size: 0.9rem; color: var(--text-muted); }

.copy-wrapper { display: flex; gap: 10px; }
.copy-wrapper input { flex: 1; padding: 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-family: monospace; font-size: 1.1rem; background: white; }

.copy-btn { background: var(--text-main); color: white; border: none; padding: 0 20px; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: 0.2s; }
.copy-btn:hover { background: #000; }

.next-steps { display: flex; justify-content: center; gap: 15px; margin-top: 1rem; }
.ghost-button { text-decoration: none; border: 1px solid var(--border-color); color: var(--text-main); padding: 10px 20px; border-radius: 8px; font-weight: 600; display: flex; align-items: center; gap: 8px; }

/* Form-Styling */
.form-group { margin-bottom: 1.5rem; text-align: left; }
.form-group label { display: block; margin-bottom: 8px; font-weight: 600; }
.form-group input { width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 1rem; }
.main-button { display: flex; align-items: center; gap: 8px; justify-content: center; width: 100%; }
</style>
