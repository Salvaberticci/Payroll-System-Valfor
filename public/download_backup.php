<?php
// public/download_backup.php (Descargar respaldo de base de datos)
require_once __DIR__ . '/../includes/auth.php';

// Requerir que el usuario sea administrador
requireRole(['admin']);

if (!isset($_GET['file'])) {
    header('Location: ' . getBaseUrl() . 'system_config.php?error=missing_file');
    exit();
}

$filename = basename($_GET['file']); // Prevenir directory traversal
$backup_dir = __DIR__ . '/../backups';
$filepath = $backup_dir . '/' . $filename;

// Verificar que el archivo existe y está en el directorio correcto
if (!file_exists($filepath) || strpos(realpath($filepath), realpath($backup_dir)) !== 0) {
    header('Location: ' . getBaseUrl() . 'system_config.php?error=file_not_found');
    exit();
}

// Verificar que es un archivo .sql
if (pathinfo($filepath, PATHINFO_EXTENSION) !== 'sql') {
    header('Location: ' . getBaseUrl() . 'system_config.php?error=invalid_file');
    exit();
}

// Configurar headers para descarga
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Leer y enviar el archivo
readfile($filepath);
exit();
?>