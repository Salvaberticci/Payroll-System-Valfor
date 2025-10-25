<?php
// public/delete_employee.php
require_once __DIR__ . '/../includes/auth.php'; // Incluir el archivo de autenticación
require_once __DIR__ . '/../config/settings.php'; // Para getDbConnection() y roles

// Requerir que el usuario esté logueado y tenga rol de 'admin' o 'assistant'
requireRole([ROLE_ADMIN, ROLE_ASSISTANT]);

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['employee_id'])) {
    $employee_id = $_POST['employee_id'];

    try {
        $pdo = getDbConnection();

        // Primero obtener la información del empleado para eliminar la foto si existe
        $stmt = $pdo->prepare("SELECT photo_path FROM employees WHERE id = :id");
        $stmt->bindParam(':id', $employee_id, PDO::PARAM_INT);
        $stmt->execute();
        $employee = $stmt->fetch();

        if ($employee) {
            // Eliminar la foto del servidor si existe
            if (!empty($employee['photo_path']) && file_exists(__DIR__ . '/' . $employee['photo_path'])) {
                unlink(__DIR__ . '/' . $employee['photo_path']);
            }

            // Eliminar el empleado de la base de datos
            $stmt = $pdo->prepare("DELETE FROM employees WHERE id = :id");
            $stmt->bindParam(':id', $employee_id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $message = 'Empleado eliminado exitosamente.';
                $message_type = 'success';
            } else {
                $message = 'Error al eliminar el empleado.';
                $message_type = 'danger';
            }
        } else {
            $message = 'Empleado no encontrado.';
            $message_type = 'danger';
        }
    } catch (PDOException $e) {
        $message = 'Error de base de datos: ' . htmlspecialchars($e->getMessage());
        $message_type = 'danger';
    }
} else {
    $message = 'Solicitud inválida.';
    $message_type = 'danger';
}

// Redirigir de vuelta a la lista de empleados con el mensaje
header('Location: ' . getBaseUrl() . 'employees.php?message=' . urlencode($message) . '&type=' . $message_type);
exit();
?>