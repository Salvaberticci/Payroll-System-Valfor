<?php
// config/settings.php

// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'payroll_db'); // Cambia esto al nombre de tu base de datos
define('DB_USER', 'root');       // Cambia esto a tu usuario de base de datos
define('DB_PASS', '');           // Cambia esto a tu contraseña de base de datos

// Roles de usuario (para facilitar la comparación en el código)
define('ROLE_ADMIN', 'admin');
define('ROLE_ASSISTANT', 'asistente');
define('ROLE_READ_ONLY', 'solo lectura');

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

// Función para obtener la tasa BCV desde la API
function getBcvRateFromApi() {
    $apiUrl = "https://ve.dolarapi.com/v1/dolares/oficial";
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Timeout de 10 segundos
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Para desarrollo, deshabilitar verificación SSL si es necesario

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) {
        // Error de conexión o respuesta no válida
        error_log("Error al obtener tasa BCV desde API: " . ($error ?: "HTTP Code: $httpCode"));
        return false;
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Error al decodificar JSON de API BCV: " . json_last_error_msg());
        return false;
    }

    if (!isset($data['promedio']) || !is_numeric($data['promedio'])) {
        error_log("Respuesta de API BCV no contiene 'promedio' válido");
        return false;
    }

    return (float) $data['promedio'];
}