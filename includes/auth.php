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
 * Maneja tanto solicitudes normales como AJAX.
 */
function requireLogin() {
    if (!isUserLoggedIn()) {
        if (isAjaxRequest()) {
            // Para solicitudes AJAX, devolver error JSON
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Sesión expirada. Por favor, inicie sesión nuevamente.']);
            exit();
        } else {
            // Para solicitudes normales, redireccionar
            header('Location: ' . getBaseUrl() . 'index.php');
            exit();
        }
    }
}

/**
 * Función para verificar el rol del usuario y redireccionar si no tiene permisos.
 * @param array $allowedRoles Array de roles permitidos (ej. ['admin', 'assistant']).
 */
function requireRole($allowedRoles) {
    if (!isUserLoggedIn()) {
        requireLogin(); // Primero asegurar que esté logueado
        return; // No continuar si requireLogin ya manejó la respuesta
    }

    $userRole = getUserRole();
    if (!in_array($userRole, $allowedRoles)) {
        if (isAjaxRequest()) {
            // Para solicitudes AJAX, devolver error JSON
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para realizar esta acción.']);
            exit();
        } else {
            // Para solicitudes normales, redireccionar al dashboard con error
            header('Location: ' . getBaseUrl() . 'dashboard.php?error=access_denied');
            exit();
        }
    }
}

/**
 * Función para detectar si la solicitud es AJAX.
 * @return bool True si es una solicitud AJAX, false de lo contrario.
 */
function isAjaxRequest() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
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