<?php
// autoload.php - ERWEITERT

spl_autoload_register(function ($class) {
    $prefix = 'VantixDash\\';
    $base_dir = __DIR__ . '/services/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    
    // Namespace-Trenner (\) durch Verzeichnistrenner (/) ersetzen
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});
