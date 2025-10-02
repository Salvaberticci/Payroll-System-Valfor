<?php
// config/settings.php

// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'payroll_db'); // Cambia esto al nombre de tu base de datos
define('DB_USER', 'root');       // Cambia esto a tu usuario de base de datos
define('DB_PASS', '');           // Cambia esto a tu contraseña de base de datos

// Roles de usuario (para facilitar la comparación en el código)
define('ROLE_ADMIN', 'admin');
define('ROLE_ASSISTANT', 'assistant');
define('ROLE_READ_ONLY', 'read_only');

// Iniciar sesión (si aún no está iniciada)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Función para obtener la conexión PDO
function getDbConnection() {
    static $pdo = null; // Usar una variable estática para evitar múltiples conexiones

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // En un entorno de producción, registrar el error y mostrar un mensaje genérico.
            // Para desarrollo, puedes mostrar el error completo.
            die('Error de conexión a la base de datos: ' . $e->getMessage());
        }
    }
    return $pdo;
}