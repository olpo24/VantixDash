<?php
if (!isset($siteService)) exit;

$error = '';
$newSiteData = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_site'])) {
    if ($_POST['csrf_token'] === $_SESSION['csrf_token']) {
        $name = trim($_POST['site_name']);
        $url  = trim($_POST['site_url']);
        if (!empty($name) && !empty($url)) {
            $newSiteData = $siteService->addSite($name, $url);
        } else {
            $error = 'Bitte alle Felder ausfüllen.';
        }
    }
}
?>

<div class="add-site-container" style="max-width: 600px; margin: 0 auto;">
    <div class="header-action">
        <h2><i class="ph ph-plus-circle"></i> Neue Seite</h2>
        <a href="index.php?view=manage_sites" class="ghost-button">Abbrechen</a>
    </div>

    <?php if ($error): ?>
        <div class="alert error"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($newSiteData): ?>
        <div class="card">
            <div class="alert success">Seite erfolgreich angelegt!</div>
            <p>Kopiere den Secret Key für das Child-Plugin:</p>
            <div class="form-group">
                <input type="text" id="api-key-input" value="<?php echo $newSiteData['api_key']; ?>" readonly>
                <button class="main-button" onclick="copyApiKey()" style="margin-top: 1rem; width: 100%;">
                    <i class="ph ph-copy"></i> Key kopieren
                </button>
            </div>
            <a href="index.php?view=manage_sites" class="ghost-button" style="width: 100%;">Zur Übersicht</a>
        </div>
    <?php else: ?>
        <div class="card">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="form-group">
                    <label>Name der Webseite</label>
                    <input type="text" name="site_name" placeholder="z.B. Kundenprojekt" required>
                </div>
                <div class="form-group">
                    <label>URL (https://...)</label>
                    <input type="url" name="site_url" placeholder="https://beispiel.de" required>
                </div>
                <button type="submit" name="add_site" class="main-button" style="width: 100%;">
                    Seite anlegen
                </button>
            </form>
        </div>
    <?php endif; ?>
</div>

<script>
function copyApiKey() {
    const input = document.getElementById("api-key-input");
    input.select();
    navigator.clipboard.writeText(input.value);
    alert("Key kopiert!");
}
</script>
