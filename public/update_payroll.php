<?php
// public/update_payroll.php
require_once __DIR__ . '/../includes/auth.php'; // Incluir el archivo de autenticación
require_once __DIR__ . '/../config/settings.php'; // Para getDbConnection() y roles

// Requerir que el usuario esté logueado y tenga rol de 'admin' o 'assistant'
requireRole([ROLE_ADMIN, ROLE_ASSISTANT]);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

$period_id = $_POST['period_id'] ?? null;
$start_date = $_POST['start_date'] ?? '';
$end_date = $_POST['end_date'] ?? '';
$days_in_period = str_replace(',', '.', trim($_POST['days_in_period'] ?? ''));

// Obtener tasa BCV desde la API
$bcv_rate = getBcvRateFromApi();
$status = $_POST['status'] ?? '';

$message = '';
$message_type = 'danger';

if (!$period_id || !is_numeric($period_id)) {
    $message = 'ID de período inválido.';
} elseif (empty($start_date) || empty($end_date) || $bcv_rate === false || $bcv_rate <= 0 || !is_numeric($days_in_period) || $days_in_period <= 0) {
    $message = 'Por favor, complete todos los campos requeridos. Error al obtener la tasa BCV desde la API.';
} elseif ($start_date >= $end_date) {
    $message = 'La fecha de inicio debe ser anterior a la fecha de fin.';
} else {
    $pdo = getDbConnection();

    try {
        // Verificar que el período existe y está en estado 'calculado'
        $stmt_check = $pdo->prepare("SELECT status FROM payroll_periods WHERE id = :id");
        $stmt_check->bindParam(':id', $period_id, PDO::PARAM_INT);
        $stmt_check->execute();
        $current_status = $stmt_check->fetchColumn();

        if (!$current_status) {
            $message = 'Período de nómina no encontrado.';
        } else {
            // Iniciar transacción
            $pdo->beginTransaction();

            // Actualizar el período de nómina
            $stmt_update = $pdo->prepare("UPDATE payroll_periods SET start_date = :start_date, end_date = :end_date, bcv_rate = :bcv_rate, days_in_period = :days_in_period, status = :status WHERE id = :id");
            $stmt_update->bindParam(':start_date', $start_date);
            $stmt_update->bindParam(':end_date', $end_date);
            $stmt_update->bindParam(':bcv_rate', $bcv_rate);
            $stmt_update->bindParam(':days_in_period', $days_in_period);
            $stmt_update->bindParam(':status', $status);
            $stmt_update->bindParam(':id', $period_id, PDO::PARAM_INT);
            $stmt_update->execute();

            // Eliminar detalles existentes para recalcular
            $stmt_delete_details = $pdo->prepare("DELETE FROM employee_payroll_details WHERE payroll_period_id = :period_id");
            $stmt_delete_details->bindParam(':period_id', $period_id, PDO::PARAM_INT);
            $stmt_delete_details->execute();

            // Obtener empleados que estaban en este período
            $stmt_employees = $pdo->prepare("
                SELECT DISTINCT e.id, e.cedula, e.full_name, e.salario_base_mensual_usd
                FROM employees e
                JOIN employee_payroll_details epd ON e.id = epd.employee_id
                WHERE epd.payroll_period_id = :period_id AND e.is_active = 1
            ");
            $stmt_employees->bindParam(':period_id', $period_id, PDO::PARAM_INT);
            $stmt_employees->execute();
            $employees = $stmt_employees->fetchAll();

            // Recalcular la nómina para cada empleado
            foreach ($employees as $employee) {
                $employee_id = $employee['id'];
                $salario_base_mensual_usd = $employee['salario_base_mensual_usd'];

                // Calcular salario base quincenal en USD y Bs
                $salario_base_quincenal_usd = ($salario_base_mensual_usd / 2); // Asumiendo quincenal
                $salario_base_quincenal_bs = $salario_base_quincenal_usd * $bcv_rate;

                // Insertar Salario Base como concepto de ingreso
                $stmt_insert_salary = $pdo->prepare("INSERT INTO employee_payroll_details (employee_id, payroll_period_id, concept_id, amount_usd, amount_bs) VALUES (:employee_id, :payroll_period_id, (SELECT id FROM payroll_concepts WHERE name = 'Salario Base (Quincenal)'), :amount_usd, :amount_bs)");
                $stmt_insert_salary->bindParam(':employee_id', $employee_id, PDO::PARAM_INT);
                $stmt_insert_salary->bindParam(':payroll_period_id', $period_id, PDO::PARAM_INT);
                $stmt_insert_salary->bindParam(':amount_usd', $salario_base_quincenal_usd);
                $stmt_insert_salary->bindParam(':amount_bs', $salario_base_quincenal_bs);
                $stmt_insert_salary->execute();

                // Lógica para deducciones legales (SSO, SPF, FAOV)
                $deductions_to_insert = [];
                $stmt_deductions = $pdo->query("SELECT id, name, default_value FROM payroll_concepts WHERE type = 'deduccion_legal' AND calculation_type = 'percentage_of_salary'");
                while ($deduction_concept = $stmt_deductions->fetch()) {
                    $percentage = $deduction_concept['default_value'];
                    $deduction_usd = $salario_base_quincenal_usd * $percentage;
                    $deduction_bs = $deduction_usd * $bcv_rate;

                    $deductions_to_insert[] = [
                        'employee_id' => $employee_id,
                        'payroll_period_id' => $period_id,
                        'concept_id' => $deduction_concept['id'],
                        'amount_usd' => $deduction_usd,
                        'amount_bs' => $deduction_bs
                    ];
                }

                foreach ($deductions_to_insert as $deduction) {
                    $stmt_insert_deduction = $pdo->prepare("INSERT INTO employee_payroll_details (employee_id, payroll_period_id, concept_id, amount_usd, amount_bs) VALUES (:employee_id, :payroll_period_id, :concept_id, :amount_usd, :amount_bs)");
                    $stmt_insert_deduction->execute($deduction);
                }

                // Lógica para Cesta Ticket
                $cesta_ticket_concept_id = $pdo->query("SELECT id FROM payroll_concepts WHERE name = 'Cesta Ticket'")->fetchColumn();
                if ($cesta_ticket_concept_id) {
                    $cesta_ticket_daily_usd = (40 / 30); // Ejemplo: $40 mensuales / 30 días
                    $cesta_ticket_usd = $cesta_ticket_daily_usd * $days_in_period;
                    $cesta_ticket_bs = $cesta_ticket_usd * $bcv_rate;

                    $stmt_insert_cesta = $pdo->prepare("INSERT INTO employee_payroll_details (employee_id, payroll_period_id, concept_id, amount_usd, amount_bs, days_applied) VALUES (:employee_id, :payroll_period_id, :concept_id, :amount_usd, :amount_bs, :days_applied)");
                    $stmt_insert_cesta->bindParam(':employee_id', $employee_id, PDO::PARAM_INT);
                    $stmt_insert_cesta->bindParam(':payroll_period_id', $period_id, PDO::PARAM_INT);
                    $stmt_insert_cesta->bindParam(':concept_id', $cesta_ticket_concept_id, PDO::PARAM_INT);
                    $stmt_insert_cesta->bindParam(':amount_usd', $cesta_ticket_usd);
                    $stmt_insert_cesta->bindParam(':amount_bs', $cesta_ticket_bs);
                    $stmt_insert_cesta->bindParam(':days_applied', $days_in_period);
                    $stmt_insert_cesta->execute();
                }
            }

            // Confirmar transacción
            $pdo->commit();

            $message = 'Nómina actualizada exitosamente. Período: ' . htmlspecialchars($start_date) . ' al ' . htmlspecialchars($end_date) . '.';
            $message_type = 'success';
        }
    } catch (PDOException $e) {
        // Revertir transacción en caso de error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $message = 'Error al actualizar la nómina: ' . htmlspecialchars($e->getMessage());
    }
}

// Responder con JSON para AJAX
echo json_encode([
    'success' => $message_type === 'success',
    'message' => $message
]);
exit();
?>