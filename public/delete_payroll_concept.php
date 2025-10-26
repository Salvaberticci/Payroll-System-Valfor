<?php
// public/delete_payroll_concept.php
require_once __DIR__ . '/../includes/auth.php'; // Incluir el archivo de autenticación
require_once __DIR__ . '/../config/settings.php'; // Para getDbConnection() y roles

// Requerir que el usuario esté logueado y tenga rol de 'admin'
requireRole([ROLE_ADMIN]);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

$concept_id = $_POST['concept_id'] ?? null;

if (!$concept_id || !is_numeric($concept_id)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de concepto inválido']);
    exit();
}

$pdo = getDbConnection();

try {
    // Verificar que el concepto existe
    $stmt_check = $pdo->prepare("SELECT id FROM payroll_concepts WHERE id = :id");
    $stmt_check->bindParam(':id', $concept_id, PDO::PARAM_INT);
    $stmt_check->execute();
    $exists = $stmt_check->fetchColumn();

    if (!$exists) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Concepto de nómina no encontrado']);
        exit();
    }

    // Nota: Se permite eliminar conceptos incluso si están siendo usados en nóminas activas
    // La eliminación en cascada se manejará a través de las restricciones de clave foránea

    // Eliminar el concepto
    $stmt_delete = $pdo->prepare("DELETE FROM payroll_concepts WHERE id = :id");
    $stmt_delete->bindParam(':id', $concept_id, PDO::PARAM_INT);
    $stmt_delete->execute();

    echo json_encode(['success' => true, 'message' => 'Concepto eliminado exitosamente']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al eliminar el concepto: ' . $e->getMessage()]);
}
?>