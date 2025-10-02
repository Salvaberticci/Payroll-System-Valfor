<?php
// includes/auth.php

// Incluir la configuración (que también inicia la sesión si no está iniciada)
require_once __DIR__ . '/../config/settings.php';

/**
 * Función para verificar si un usuario está logueado.
 * @return bool True si el usuario está logueado, false de lo contrario.
 */
function isUserLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Función para obtener el rol del usuario logueado.
 * @return string|null El rol del usuario o null si no está logueado.
 */
function getUserRole() {
    return $_SESSION['user_role'] ?? null;
}

/**
 * Función para redireccionar a la página de login si el usuario no está autenticado.
 */
function requireLogin() {
    if (!isUserLoggedIn()) {
        header('Location: ' . getBaseUrl() . 'login.php');
        exit();
    }
}

/**
 * Función para verificar el rol del usuario y redireccionar si no tiene permisos.
 * @param array $allowedRoles Array de roles permitidos (ej. ['admin', 'assistant']).
 */
function requireRole($allowedRoles) {
    if (!isUserLoggedIn()) {
        requireLogin(); // Primero asegurar que esté logueado
    }

    $userRole = getUserRole();
    if (!in_array($userRole, $allowedRoles)) {
        // Podrías redireccionar a una página de "acceso denegado"
        // o simplemente al dashboard con un mensaje de error.
        header('Location: ' . getBaseUrl() . 'index.php?error=access_denied');
        exit();
    }
}

/**
 * Función para obtener la URL base de la aplicación.
 * Útil para redirecciones y enlaces.
 * Ajusta esto si tu proyecto no está en la raíz de tu dominio/localhost.
 */
function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $scriptName = $_SERVER['SCRIPT_NAME'];
    $path = str_replace(basename($scriptName), '', $scriptName);
    // Asumiendo que 'payroll_system' es la carpeta raíz de tu proyecto.
    // Si la carpeta se llama diferente o está en la raíz del host, ajusta esta línea.
    $basePath = '/payroll_system/public/'; // Ajusta esta parte según donde esté tu carpeta public
    return $protocol . $host . $basePath;
}

// Ejemplo de uso:
// requireLogin(); // Para cualquier página que requiera que el usuario esté logueado
// requireRole(['admin']); // Para páginas solo accesibles por administradores
// requireRole(['admin', 'assistant']); // Para páginas accesibles por admin y asistente