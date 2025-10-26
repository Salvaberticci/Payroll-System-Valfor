<?php
// public/change_payroll_status.php
require_once __DIR__ . '/../includes/auth.php'; // Incluir el archivo de autenticación
require_once __DIR__ . '/../config/settings.php'; // Para getDbConnection() y roles

// Requerir que el usuario esté logueado y tenga rol de 'admin' o 'assistant'
requireRole([ROLE_ADMIN, ROLE_ASSISTANT]);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

$period_id = $_POST['period_id'] ?? null;
$new_status = $_POST['status'] ?? null;

if (!$period_id || !is_numeric($period_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de período inválido']);
    exit();
}

$allowed_statuses = ['calculado', 'pagado'];
if (!$new_status || !in_array($new_status, $allowed_statuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Estado inválido']);
    exit();
}

$pdo = getDbConnection();

try {
    // Verificar que el período existe y obtener el estado actual
    $stmt_check = $pdo->prepare("SELECT status FROM payroll_periods WHERE id = :id");
    $stmt_check->bindParam(':id', $period_id, PDO::PARAM_INT);
    $stmt_check->execute();
    $current_status = $stmt_check->fetchColumn();

    if ($stmt_check->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Período de nómina no encontrado']);
        exit();
    }

    // Permitir cualquier cambio de estado

    // Actualizar el estado
    $stmt_update = $pdo->prepare("UPDATE payroll_periods SET status = :status WHERE id = :id");
    $stmt_update->bindParam(':status', $new_status);
    $stmt_update->bindParam(':id', $period_id, PDO::PARAM_INT);

    if ($stmt_update->execute()) {
        echo json_encode(['success' => true, 'message' => 'Estado de la nómina actualizado exitosamente a "' . $new_status . '"']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al actualizar el estado de la nómina']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
}
?>