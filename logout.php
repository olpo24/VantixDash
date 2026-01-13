<?php
/**
 * logout.php
 * Beendet die Sitzung sicher und leitet zum Login weiter.
 */

session_start();

// 1. Alle Session-Variablen löschen
$_SESSION = array();

// 2. Das Session-Cookie im Browser ungültig machen
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

// 3. Die Session auf dem Server komplett zerstören
session_destroy();

// 4. Zurück zum Login leiten
header("Location: login.php?logged_out=1");
exit;
