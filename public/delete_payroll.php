<?php
// public/delete_payroll.php
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

if (!$period_id || !is_numeric($period_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de período inválido']);
    exit();
}

$pdo = getDbConnection();

try {
    // Verificar que el período existe y está en estado 'calculado'
    $stmt_check = $pdo->prepare("SELECT status FROM payroll_periods WHERE id = :id");
    $stmt_check->bindParam(':id', $period_id, PDO::PARAM_INT);
    $stmt_check->execute();
    $status = $stmt_check->fetchColumn();

    if (!$status) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Período de nómina no encontrado']);
        exit();
    }

    // Iniciar transacción para asegurar integridad
    $pdo->beginTransaction();

    // Eliminar detalles de nómina asociados
    $stmt_delete_details = $pdo->prepare("DELETE FROM employee_payroll_details WHERE payroll_period_id = :period_id");
    $stmt_delete_details->bindParam(':period_id', $period_id, PDO::PARAM_INT);
    $stmt_delete_details->execute();

    // Eliminar el período de nómina
    $stmt_delete_period = $pdo->prepare("DELETE FROM payroll_periods WHERE id = :id");
    $stmt_delete_period->bindParam(':id', $period_id, PDO::PARAM_INT);
    $stmt_delete_period->execute();

    // Confirmar transacción
    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Nómina eliminada exitosamente']);

} catch (PDOException $e) {
    // Revertir transacción en caso de error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al eliminar la nómina: ' . $e->getMessage()]);
}
?>