<?php
/**
 * views/edit_site.php
 * Bearbeiten von Namen/URL und API-Key Regeneration
 */

if (!isset($siteService)) exit;

$id = $_GET['id'] ?? '';
$sites = $siteService->getAll();
// Seite suchen
$currentSite = null;
foreach ($sites as $site) {
    if ($site['id'] === $id) {
        $currentSite = $site;
        break;
    }
}

if (!$currentSite) {
    echo '<div class="alert error">Seite nicht gefunden.</div>';
    exit;
}

$message = '';

// Logik: Daten aktualisieren
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_details'])) {
    if ($_POST['csrf_token'] === $_SESSION['csrf_token']) {
        // Hier müsste im SiteService eine Methode "updateSite" existieren.
        // Falls noch nicht vorhanden, hier die Kurzform für die Logik:
        foreach ($sites as &$s) {
            if ($s['id'] === $id) {
                $s['name'] = htmlspecialchars($_POST['site_name']);
                $s['url']  = rtrim($_POST['site_url'], '/');
                break;
            }
        }
        $siteService->save($sites); // Methode im Service muss public sein
        $message = '<div class="alert success"><i class="ph ph-check"></i> Änderungen gespeichert.</div>';
        $currentSite['name'] = $_POST['site_name'];
        $currentSite['url'] = $_POST['site_url'];
    }
}

// Logik: API Key neu generieren
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['regen_key'])) {
    if ($_POST['csrf_token'] === $_SESSION['csrf_token']) {
        $newKey = bin2hex(random_bytes(16));
        foreach ($sites as &$s) {
            if ($s['id'] === $id) {
                $s['api_key'] = $newKey;
                break;
            }
        }
        $siteService->save($sites);
        $currentSite['api_key'] = $newKey;
        $message = '<div class="alert success"><i class="ph ph-key"></i> Neuer API Key wurde generiert!</div>';
    }
}
?>

<div class="add-site-container">
    <div class="header-action">
        <h2><i class="ph ph-pencil-line"></i> Seite bearbeiten</h2>
        <a href="index.php?view=manage_sites" class="secondary-link"><i class="ph ph-arrow-left"></i> Abbrechen</a>
    </div>

    <?php echo $message; ?>

    <div class="card">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <div class="form-group">
                <label>Name der Webseite</label>
                <input type="text" name="site_name" value="<?php echo htmlspecialchars($currentSite['name']); ?>" required>
            </div>
            <div class="form-group">
                <label>URL (inkl. https://)</label>
                <input type="url" name="site_url" value="<?php echo htmlspecialchars($currentSite['url']); ?>" required>
            </div>
            <button type="submit" name="update_details" class="main-button">
                <i class="ph ph-floppy-disk"></i> Speichern
            </button>
        </form>
    </div>

    <div class="card">
        <h3><i class="ph ph-key"></i> API Schlüssel</h3>
        <p class="text-muted small">Falls die Verbindung nicht mehr funktioniert, kannst du hier einen neuen Key generieren.</p>
        
        <div class="api-key-box">
            <div class="copy-wrapper">
                <input type="text" id="api-key-input" value="<?php echo $currentSite['api_key']; ?>" readonly>
                <button type="button" class="copy-btn" onclick="copyApiKey()">
                    <i class="ph ph-copy" id="copy-icon"></i> <span id="copy-text">Kopieren</span>
                </button>
            </div>
        </div>

        <form method="POST" onsubmit="return confirm('Alter Key wird ungültig. Fortfahren?');">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <button type="submit" name="regen_key" class="ghost-button">
                <i class="ph ph-arrows-clockwise"></i> Key neu generieren
            </button>
        </form>
    </div>
</div>

<script>
function copyApiKey() {
    const copyText = document.getElementById("api-key-input");
    copyText.select();
    navigator.clipboard.writeText(copyText.value);
    
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
