<?php
// public/payroll_calc.php
require_once __DIR__ . '/../includes/auth.php'; // Incluir el archivo de autenticación
require_once __DIR__ . '/../config/settings.php'; // Para getDbConnection() y roles

// Requerir que el usuario esté logueado y tenga rol de 'admin' o 'assistant'
requireRole([ROLE_ADMIN, ROLE_ASSISTANT]);

$page_title = 'Calcular Nómina';
$message = '';
$message_type = ''; // 'success' or 'danger'

$pdo = getDbConnection();

// Obtener el último período de nómina calculado para sugerir fechas
$last_period_end_date = '';
try {
$stmt = $pdo->query("SELECT MAX(end_date) AS last_end_date FROM payroll_periods WHERE status IN ('calculated', 'pagado', 'calculado', 'paid')");
    $result = $stmt->fetch();
    if ($result && $result['last_end_date']) {
        // Sumar un día a la última fecha de fin para la nueva fecha de inicio
        $last_period_end_date = date('Y-m-d', strtotime($result['last_end_date'] . ' +1 day'));
    }
} catch (PDOException $e) {
    // Manejar el error silenciosamente o loguearlo
}

// Lógica para procesar el formulario cuando se envía (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $start_date = $_POST['start_date'] ?? '';
    $end_date = $_POST['end_date'] ?? '';
    $bcv_rate = str_replace(',', '.', trim($_POST['bcv_rate'] ?? ''));
    $days_in_period = str_replace(',', '.', trim($_POST['days_in_period'] ?? ''));
    $selected_employees = $_POST['selected_employees'] ?? [];

    // Validaciones
    if (empty($start_date) || empty($end_date) || !is_numeric($bcv_rate) || $bcv_rate <= 0 || !is_numeric($days_in_period) || $days_in_period <= 0) {
        $message = 'Por favor, complete todos los campos y asegúrese de que la Tasa BCV y los Días en el Período sean números válidos y mayores que cero.';
        $message_type = 'danger';
    } elseif ($start_date >= $end_date) {
        $message = 'La fecha de inicio debe ser anterior a la fecha de fin.';
        $message_type = 'danger';
    } else {
        try {
            // 1. Guardar el nuevo período de nómina
            $stmt = $pdo->prepare("INSERT INTO payroll_periods (start_date, end_date, bcv_rate, days_in_period, status) VALUES (:start_date, :end_date, :bcv_rate, :days_in_period, :status)");
            $stmt->bindParam(':start_date', $start_date);
            $stmt->bindParam(':end_date', $end_date);
            $stmt->bindParam(':bcv_rate', $bcv_rate);
            $stmt->bindParam(':days_in_period', $days_in_period);
            $initial_status = 'pendiente';
            $stmt->bindParam(':status', $initial_status);
            $stmt->execute();
            $payroll_period_id = $pdo->lastInsertId();

            // 2. Validar empleados seleccionados
            if (empty($selected_employees)) {
                $message = 'Debe seleccionar al menos un empleado para calcular la nómina.';
                $message_type = 'danger';
                // Eliminar el período recién creado
                $pdo->prepare("DELETE FROM payroll_periods WHERE id = :id")->execute([':id' => $payroll_period_id]);
            } else {
                // 3. Obtener los empleados seleccionados
                $placeholders = str_repeat('?,', count($selected_employees) - 1) . '?';
                $stmt_employees = $pdo->prepare("SELECT id, cedula, full_name, salario_base_mensual_usd FROM employees WHERE id IN ($placeholders) AND is_active = 1");
                $stmt_employees->execute($selected_employees);
                $employees = $stmt_employees->fetchAll();

                if (empty($employees)) {
                    $message = 'Los empleados seleccionados no existen o no están activos.';
                    $message_type = 'danger';
                    // Eliminar el período recién creado
                    $pdo->prepare("DELETE FROM payroll_periods WHERE id = :id")->execute([':id' => $payroll_period_id]);
                } else {
                    // 4. Calcular y guardar los detalles de la nómina para cada empleado
                    $total_employees_processed = 0;
                    foreach ($employees as $employee) {
                        $employee_id = $employee['id'];
                        $salario_base_mensual_usd = $employee['salario_base_mensual_usd'];

                        // Calcular salario base quincenal en USD y Bs
                        $salario_base_quincenal_usd = ($salario_base_mensual_usd / 2); // Asumiendo quincenal
                        $salario_base_quincenal_bs = $salario_base_quincenal_usd * $bcv_rate;

                        // Insertar Salario Base como concepto de ingreso
                        $stmt_insert_salary = $pdo->prepare("INSERT INTO employee_payroll_details (employee_id, payroll_period_id, concept_id, amount_usd, amount_bs) VALUES (:employee_id, :payroll_period_id, (SELECT id FROM payroll_concepts WHERE name = 'Salario Base (Quincenal)'), :amount_usd, :amount_bs)");
                        $stmt_insert_salary->bindParam(':employee_id', $employee_id, PDO::PARAM_INT);
                        $stmt_insert_salary->bindParam(':payroll_period_id', $payroll_period_id, PDO::PARAM_INT);
                        $stmt_insert_salary->bindParam(':amount_usd', $salario_base_quincenal_usd);
                        $stmt_insert_salary->bindParam(':amount_bs', $salario_base_quincenal_bs);
                        $stmt_insert_salary->execute();

                        // Lógica para deducciones legales (SSO, SPF, FAOV)
                        // Aquí se asume que las deducciones legales se calculan sobre el salario base quincenal
                        $deductions_to_insert = [];
                        $stmt_deductions = $pdo->query("SELECT id, name, default_value FROM payroll_concepts WHERE type = 'deduccion_legal' AND calculation_type = 'percentage_of_salary'");
                        while ($deduction_concept = $stmt_deductions->fetch()) {
                            $percentage = $deduction_concept['default_value'];
                            $deduction_usd = $salario_base_quincenal_usd * $percentage;
                            $deduction_bs = $deduction_usd * $bcv_rate;

                            $deductions_to_insert[] = [
                                'employee_id' => $employee_id,
                                'payroll_period_id' => $payroll_period_id,
                                'concept_id' => $deduction_concept['id'],
                                'amount_usd' => $deduction_usd,
                                'amount_bs' => $deduction_bs
                            ];
                        }

                        foreach ($deductions_to_insert as $deduction) {
                            $stmt_insert_deduction = $pdo->prepare("INSERT INTO employee_payroll_details (employee_id, payroll_period_id, concept_id, amount_usd, amount_bs) VALUES (:employee_id, :payroll_period_id, :concept_id, :amount_usd, :amount_bs)");
                            $stmt_insert_deduction->execute($deduction);
                        }

                        // Lógica para Cesta Ticket (beneficio por día)
                        $cesta_ticket_concept_id = $pdo->query("SELECT id FROM payroll_concepts WHERE name = 'Cesta Ticket'")->fetchColumn();
                        if ($cesta_ticket_concept_id) {
                            // Asumimos un valor diario para Cesta Ticket (ej. $40/30 días = $1.33 por día)
                            // Este valor debería ser configurable en payroll_concepts o settings
                            $cesta_ticket_daily_usd = (40 / 30); // Ejemplo: $40 mensuales / 30 días
                            $cesta_ticket_usd = $cesta_ticket_daily_usd * $days_in_period;
                            $cesta_ticket_bs = $cesta_ticket_usd * $bcv_rate;

                            $stmt_insert_cesta = $pdo->prepare("INSERT INTO employee_payroll_details (employee_id, payroll_period_id, concept_id, amount_usd, amount_bs, days_applied) VALUES (:employee_id, :payroll_period_id, :concept_id, :amount_usd, :amount_bs, :days_applied)");
                            $stmt_insert_cesta->bindParam(':employee_id', $employee_id, PDO::PARAM_INT);
                            $stmt_insert_cesta->bindParam(':payroll_period_id', $payroll_period_id, PDO::PARAM_INT);
                            $stmt_insert_cesta->bindParam(':concept_id', $cesta_ticket_concept_id, PDO::PARAM_INT);
                            $stmt_insert_cesta->bindParam(':amount_usd', $cesta_ticket_usd);
                            $stmt_insert_cesta->bindParam(':amount_bs', $cesta_ticket_bs);
                            $stmt_insert_cesta->bindParam(':days_applied', $days_in_period);
                            $stmt_insert_cesta->execute();
                        }

                        $total_employees_processed++;
                    }

                    // 5. Actualizar el estado del período a 'calculado'
                    $stmt_update_period = $pdo->prepare("UPDATE payroll_periods SET status = 'calculado' WHERE id = :id");
                    $stmt_update_period->bindParam(':id', $payroll_period_id, PDO::PARAM_INT);
                    $stmt_update_period->execute();

                    $message = "Nómina calculada exitosamente para $total_employees_processed empleados. Período: " . htmlspecialchars($start_date) . " al " . htmlspecialchars($end_date) . ". Tasa BCV: " . htmlspecialchars(number_format($bcv_rate, 2)) . ".";
                    $message_type = 'success';
                }
            }
        } catch (PDOException $e) {
            $message = 'Error al calcular la nómina: ' . htmlspecialchars($e->getMessage());
            $message_type = 'danger';
            // Si hay un error, intenta eliminar el período si ya se insertó
            if (isset($payroll_period_id)) {
                $pdo->prepare("DELETE FROM payroll_periods WHERE id = :id")->execute([':id' => $payroll_period_id]);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Sistema de Nómina</title>
    <!-- Incluir Bootstrap CSS directamente con ruta relativa -->
    <link href="./assets/css/bootstrap.min.css" rel="stylesheet">
    <!-- Iconos de Bootstrap -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Estilos específicos de la página de cálculo de nómina -->
    <link href="./assets/css/payroll_calc.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; // Incluir la barra de navegación ?>

    <div class="container mt-4">
        <h1 class="mb-4">Calcular Nómina</h1>

        <div class="card mb-4">
            <div class="card-header">
                Definir Período de Nómina
            </div>
            <div class="card-body">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>" role="alert">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="start_date" class="form-label">Fecha de Inicio</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($last_period_end_date); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="end_date" class="form-label">Fecha de Fin</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="bcv_rate" class="form-label">Tasa BCV</label>
                            <input type="number" step="0.01" class="form-control" id="bcv_rate" name="bcv_rate" placeholder="Ej: 101.08" required>
                        </div>
                        <div class="col-md-6">
                            <label for="days_in_period" class="form-label">Días en el Período</label>
                            <input type="number" step="0.5" class="form-control" id="days_in_period" name="days_in_period" placeholder="Ej: 15" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label class="form-label">Seleccionar Empleados</label>
                            <div class="border rounded p-3" style="max-height: 200px; overflow-y: auto;">
                                <?php
                                $employees = [];
                                try {
                                    $stmt = $pdo->query("SELECT id, cedula, full_name FROM employees WHERE is_active = 1 ORDER BY full_name ASC");
                                    $employees = $stmt->fetchAll();
                                } catch (PDOException $e) {
                                    // Manejar error silenciosamente
                                }

                                if (empty($employees)): ?>
                                    <div class="alert alert-warning" role="alert">
                                        No hay empleados activos registrados. <a href="<?php echo getBaseUrl(); ?>employees_form.php" target="_blank">Registrar empleados</a> primero.
                                    </div>
                                <?php else: ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="select_all" onchange="toggleAllEmployees(this)">
                                        <label class="form-check-label fw-bold" for="select_all">
                                            Seleccionar Todos los Empleados
                                        </label>
                                    </div>
                                    <hr>
                                    <?php foreach ($employees as $employee): ?>
                                        <div class="form-check">
                                            <input class="form-check-input employee-checkbox" type="checkbox" id="employee_<?php echo $employee['id']; ?>" name="selected_employees[]" value="<?php echo $employee['id']; ?>" checked>
                                            <label class="form-check-label" for="employee_<?php echo htmlspecialchars($employee['id']); ?>">
                                                <?php echo htmlspecialchars($employee['full_name']); ?> (C.I.: <?php echo htmlspecialchars($employee['cedula']); ?>)
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <small class="form-text text-muted">Por defecto, todos los empleados activos están seleccionados. Desmarque los que no desee incluir en este cálculo.</small>
                        </div>
                    </div>

                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-primary btn-lg" <?php echo empty($employees) ? 'disabled' : ''; ?>>
                            <i class="bi bi-calculator me-2"></i> Calcular Nómina
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mt-5">
            <div class="card-header d-flex justify-content-between align-items-center">
                Períodos de Nómina Calculados Recientemente
                <button type="button" class="btn btn-info btn-sm" onclick="printRecentPayrollPeriodsPDF()">
                    <i class="bi bi-file-earmark-pdf me-1"></i> Descagar PDF
                </button>
            </div>
            <div class="card-body">
                <?php
                $payroll_periods = [];
                try {
                    $stmt = $pdo->query("SELECT id, start_date, end_date, bcv_rate, days_in_period, status, created_at FROM payroll_periods ORDER BY created_at DESC LIMIT 10");
                    $payroll_periods = $stmt->fetchAll();
                } catch (PDOException $e) {
                    echo '<div class="alert alert-danger" role="alert">Error al cargar los períodos de nómina: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }

                if (empty($payroll_periods)): ?>
                    <div class="alert alert-info" role="alert">
                        No se han calculado períodos de nómina aún.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Inicio</th>
                                    <th>Fin</th>
                                    <th>Tasa BCV</th>
                                    <th>Días</th>
                                    <th>Estado</th>
                                    <th>Fecha Cálculo</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payroll_periods as $period): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($period['id']); ?></td>
                                        <td><?php echo htmlspecialchars($period['start_date']); ?></td>
                                        <td><?php echo htmlspecialchars($period['end_date']); ?></td>
                                        <td><?php echo number_format($period['bcv_rate'], 2); ?></td>
                                        <td><?php echo htmlspecialchars($period['days_in_period']); ?></td>
                                        <td>
                                            <?php
                                            $badge_class = 'bg-secondary';
                                            $display_status = '';
                                            $status_from_db = $period['status'];

                                            if (empty($status_from_db)) {
                                                $display_status = 'Pendiente'; // Asumimos que un estado vacío es 'Pendiente'
                                                $badge_class = 'bg-warning text-dark';
                                            } else {
                                                switch ($status_from_db) {
                                                    case 'pending':
                                                    case 'pendiente':
                                                        $badge_class = 'bg-warning text-dark';
                                                        $display_status = 'Pendiente';
                                                        break;
                                                    case 'calculated':
                                                    case 'calculado':
                                                        $badge_class = 'bg-info';
                                                        $display_status = 'Calculado';
                                                        break;
                                                    case 'paid':
                                                    case 'pagado':
                                                        $badge_class = 'bg-success';
                                                        $display_status = 'Pagado';
                                                        break;
                                                    case 'closed':
                                                    case 'cerrado':
                                                        $badge_class = 'bg-dark';
                                                        $display_status = 'Cerrado';
                                                        break;
                                                    default:
                                                        $display_status = 'Desconocido: ' . htmlspecialchars($status_from_db); // Fallback para depuración
                                                        break;
                                                }
                                            }
                                            ?>
                                            <span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($display_status); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($period['created_at']); ?></td>
                                        <td>
                                            <a href="<?php echo getBaseUrl(); ?>payroll_details.php?period_id=<?php echo $period['id']; ?>" class="btn btn-sm btn-primary me-1" title="Ver Detalles">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="<?php echo getBaseUrl(); ?>payroll_details.php?period_id=<?php echo $period['id']; ?>&action=edit" class="btn btn-sm btn-warning me-1" title="Editar Nómina">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <?php if ($period['status'] === 'calculado'): ?>
                                                <button type="button" class="btn btn-sm btn-info me-1" title="Cambiar a Pendiente" onclick="changePayrollStatus(<?php echo $period['id']; ?>, 'pendiente')">
                                                    <i class="bi bi-arrow-counterclockwise"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-sm btn-danger" title="Eliminar Nómina" onclick="confirmDeletePeriod(<?php echo $period['id']; ?>)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; // Incluir el pie de página ?>
    <!-- Incluir Bootstrap JS (Bundle con Popper) directamente con ruta relativa -->
    <script src="./assets/js/bootstrap.bundle.min.js"></script>
    <!-- Scripts personalizados (si los hay) directamente con ruta relativa -->
    <script src="./assets/js/script.js"></script>
    <script>
        function toggleAllEmployees(checkbox) {
            const employeeCheckboxes = document.querySelectorAll('.employee-checkbox');
            employeeCheckboxes.forEach(cb => {
                cb.checked = checkbox.checked;
            });
        }

        // Si algún checkbox individual se desmarca, desmarcar "Seleccionar Todos"
        document.addEventListener('DOMContentLoaded', function() {
            const employeeCheckboxes = document.querySelectorAll('.employee-checkbox');
            const selectAllCheckbox = document.getElementById('select_all');

            employeeCheckboxes.forEach(cb => {
                cb.addEventListener('change', function() {
                    const allChecked = Array.from(employeeCheckboxes).every(cb => cb.checked);
                    const someChecked = Array.from(employeeCheckboxes).some(cb => cb.checked);

                    selectAllCheckbox.checked = allChecked;
                    selectAllCheckbox.indeterminate = someChecked && !allChecked;
                });
            });
        });

        function printRecentPayrollPeriodsPDF() {
            window.open('<?php echo getBaseUrl(); ?>generate_recent_payroll_periods_pdf.php', '_blank');
        }
    </script>
</body>
</html>
