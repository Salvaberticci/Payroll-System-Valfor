<?php
// public/check_session.php
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

// Verificar si el usuario está autenticado
echo json_encode(['authenticated' => isUserLoggedIn()]);
exit();
?>