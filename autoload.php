<?php
/**
 * VantixDash Custom PSR-4 Autoloader
 */
spl_autoload_register(function ($class) {
    // Namespace Prefix
    $prefix = 'VantixDash\\';
    
    // Basisverzeichnis für den Namespace
    $base_dir = __DIR__ . '/services/';

    // Prüfen, ob die Klasse den Prefix nutzt
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    // Relativen Klassennamen extrahieren
    $relative_class = substr($class, $len);

    // Namespace-Trenner (\) durch Verzeichnistrenner (/) ersetzen
    // und .php anhängen
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // Wenn die Datei existiert, laden
    if (file_exists($file)) {
        require $file;
    }
});
