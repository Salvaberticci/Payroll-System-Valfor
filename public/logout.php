<?php
// public/logout.php
require_once __DIR__ . '/../config/settings.php'; // Solo para asegurar que la sesión se inicie si no lo está y para getBaseUrl()

// Destruir todas las variables de sesión
$_SESSION = array();

// Si se desea destruir la cookie de sesión, también se debe borrar.
// Nota: Esto destruirá la sesión, y no solo los datos de sesión!
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finalmente, destruir la sesión.
session_destroy();

// Redirigir al formulario de login (que ahora es index.php)
header('Location: index.php');
exit();
