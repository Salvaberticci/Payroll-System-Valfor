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
    <style>
        /* Estilos para la modal personalizada */
        .custom-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 1050;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .custom-modal-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .custom-modal-content {
            position: relative;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            margin: 20px;
        }

        .custom-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #dee2e6;
            background-color: #f8f9fa;
            border-radius: 8px 8px 0 0;
        }

        .custom-modal-title {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 500;
            color: #212529;
        }

        .custom-modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            line-height: 1;
            color: #6c757d;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            transition: background-color 0.2s;
        }

        .custom-modal-close:hover {
            background-color: #e9ecef;
            color: #495057;
        }

        .custom-modal-body {
            padding: 1.5rem;
        }

        .custom-modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
            padding: 1rem 1.5rem;
            border-top: 1px solid #dee2e6;
            background-color: #f8f9fa;
            border-radius: 0 0 8px 8px;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #212529;
        }

        .form-input, .form-select {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 1rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: #80bdff;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }

        .form-input:invalid, .form-select:invalid {
            border-color: #dc3545;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: 1px solid transparent;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
            color: white;
        }

        .btn-primary:hover {
            background-color: #0056b3;
            border-color: #004085;
        }

        .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #545b62;
            border-color: #4e555b;
        }

        .btn:disabled {
            opacity: 0.65;
            cursor: not-allowed;
        }

        /* Responsive */
        @media (max-width: 576px) {
            .custom-modal-content {
                width: 95%;
                margin: 10px;
            }

            .custom-modal-header, .custom-modal-body, .custom-modal-footer {
                padding: 1rem;
            }

            .custom-modal-footer {
                flex-direction: column;
            }

            .custom-modal-footer .btn {
                width: 100%;
                margin-bottom: 0.5rem;
            }
        }

        /* Animación de entrada */
        .custom-modal-content {
            animation: modalFadeIn 0.3s ease-out;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: scale(0.9) translateY(-20px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        /* Estilos de error */
        .error-message {
            color: #dc3545;
            font-size: 0.875rem;
            margin-top: 0.25rem;
            display: block;
        }

        .form-group.error input, .form-group.error select {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }

        /* Estilos para mensajes de alerta */
        .alert-message {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1060;
            min-width: 300px;
            max-width: 500px;
            padding: 0;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            animation: slideInRight 0.3s ease-out;
        }

        .alert-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .alert-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
        }

        .alert-close {
            background: none;
            border: none;
            font-size: 1.25rem;
            line-height: 1;
            color: inherit;
            cursor: pointer;
            padding: 0;
            margin-left: 1rem;
            opacity: 0.7;
            transition: opacity 0.2s;
        }

        .alert-close:hover {
            opacity: 1;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* Responsive para mensajes */
        @media (max-width: 576px) {
            .alert-message {
                left: 10px;
                right: 10px;
                min-width: auto;
            }
        }

        /* Estilos para modal de eliminación */
        .delete-modal-content {
            max-width: 450px;
        }

        .delete-modal-header {
            background-color: #f8d7da;
            border-bottom: 1px solid #f5c6cb;
        }

        .delete-modal-header .custom-modal-title {
            color: #721c24;
        }

        .delete-modal-body {
            text-align: center;
            padding: 2rem 1.5rem;
        }

        .delete-warning-icon {
            margin-bottom: 1rem;
        }

        .warning-icon {
            font-size: 3rem;
            display: block;
        }

        .delete-message {
            font-size: 1.1rem;
            font-weight: 500;
            margin-bottom: 1rem;
            color: #495057;
        }

        .delete-details {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 1rem;
            margin: 1rem 0;
            text-align: left;
        }

        .delete-details p {
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .delete-details p:last-child {
            margin-bottom: 0;
        }

        .delete-warning {
            font-size: 0.875rem;
            color: #856404;
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 4px;
            padding: 0.75rem;
            margin-top: 1rem;
        }

        .delete-modal-footer {
            background-color: #f8f9fa;
            border-top: 1px solid #dee2e6;
            justify-content: center;
            gap: 1rem;
        }

        .delete-modal-footer .btn {
            min-width: 120px;
        }

        .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background-color: #c82333;
            border-color: #bd2130;
        }

        .delete-icon {
            margin-right: 0.5rem;
        }

        .loading-spinner {
            display: inline-block;
            width: 1rem;
            height: 1rem;
            border: 2px solid #ffffff;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 1s ease-in-out infinite;
            margin-right: 0.5rem;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Responsive para modal de eliminación */
        @media (max-width: 576px) {
            .delete-modal-content {
                width: 95%;
                margin: 10px;
            }

            .delete-modal-body {
                padding: 1.5rem 1rem;
            }

            .delete-modal-footer {
                flex-direction: column;
            }

            .delete-modal-footer .btn {
                width: 100%;
                margin-bottom: 0.5rem;
            }
        }
    </style>
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
                                            <button type="button" class="btn btn-sm btn-warning me-1" title="Editar Nómina" onclick="openEditModal(<?php echo $period['id']; ?>, '<?php echo htmlspecialchars($period['start_date']); ?>', '<?php echo htmlspecialchars($period['end_date']); ?>', '<?php echo htmlspecialchars($period['bcv_rate']); ?>', '<?php echo htmlspecialchars($period['days_in_period']); ?>', '<?php echo htmlspecialchars($period['status']); ?>')">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-danger" title="Eliminar Nómina" onclick="openDeleteModal(<?php echo $period['id']; ?>, '<?php echo htmlspecialchars($period['start_date']); ?>', '<?php echo htmlspecialchars($period['end_date']); ?>', '<?php echo htmlspecialchars($display_status); ?>')">
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

    <!-- Modal personalizado para editar nómina -->
    <div id="editPayrollModal" class="custom-modal" style="display: none;">
        <div class="custom-modal-overlay" onclick="closeEditModal()"></div>
        <div class="custom-modal-content">
            <div class="custom-modal-header">
                <h2 class="custom-modal-title">Editar Nómina</h2>
                <button type="button" class="custom-modal-close" onclick="closeEditModal()" aria-label="Cerrar modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="editPayrollForm" class="custom-modal-body">
                <input type="hidden" id="edit_period_id" name="period_id">

                <div class="form-group">
                    <label for="edit_start_date" class="form-label">Fecha de Inicio</label>
                    <input type="date" class="form-input" id="edit_start_date" name="start_date" required>
                </div>

                <div class="form-group">
                    <label for="edit_end_date" class="form-label">Fecha de Fin</label>
                    <input type="date" class="form-input" id="edit_end_date" name="end_date" required>
                </div>

                <div class="form-group">
                    <label for="edit_bcv_rate" class="form-label">Tasa BCV</label>
                    <input type="number" step="0.01" class="form-input" id="edit_bcv_rate" name="bcv_rate" placeholder="Ej: 101.08" required>
                </div>

                <div class="form-group">
                    <label for="edit_days_in_period" class="form-label">Días en el Período</label>
                    <input type="number" step="0.5" class="form-input" id="edit_days_in_period" name="days_in_period" placeholder="Ej: 15" required>
                </div>

                <div class="form-group">
                    <label for="edit_status" class="form-label">Estado</label>
                    <select class="form-select" id="edit_status" name="status" required>
                        <option value="pendiente">Pendiente</option>
                        <option value="calculado">Calculado</option>
                        <option value="pagado">Pagado</option>
                        <option value="cerrado">Cerrado</option>
                    </select>
                </div>
            </form>
            <div class="custom-modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="submitEditForm()">Guardar Cambios</button>
            </div>
        </div>
    </div>

    <!-- Modal de confirmación para eliminar nómina -->
    <div id="deletePayrollModal" class="custom-modal" style="display: none;">
        <div class="custom-modal-overlay" onclick="closeDeleteModal()"></div>
        <div class="custom-modal-content delete-modal-content">
            <div class="custom-modal-header delete-modal-header">
                <h2 class="custom-modal-title">Confirmar Eliminación</h2>
                <button type="button" class="custom-modal-close" onclick="closeDeleteModal()" aria-label="Cerrar modal">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="custom-modal-body delete-modal-body">
                <div class="delete-warning-icon">
                    <span class="warning-icon">⚠️</span>
                </div>
                <p class="delete-message">
                    ¿Está seguro de que desea eliminar este período de nómina?
                </p>
                <div class="delete-details">
                    <p><strong>Período:</strong> <span id="delete_period_range"></span></p>
                    <p><strong>Estado:</strong> <span id="delete_period_status"></span></p>
                </div>
                <p class="delete-warning">
                    <strong>Advertencia:</strong> Esta acción no se puede deshacer. Se eliminarán todos los detalles de nómina asociados a este período.
                </p>
                <input type="hidden" id="delete_period_id">
            </div>
            <div class="custom-modal-footer delete-modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancelar</button>
                <button type="button" class="btn btn-danger" onclick="confirmDeletePayroll()">
                    <span class="delete-icon">🗑️</span> Eliminar Nómina
                </button>
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

        // Variables globales para la modal
        let currentEditingRow = null;

        function openEditModal(periodId, startDate, endDate, bcvRate, daysInPeriod, status) {
            // Limpiar errores previos
            clearFormErrors();

            // Rellenar el formulario
            document.getElementById('edit_period_id').value = periodId;
            document.getElementById('edit_start_date').value = startDate;
            document.getElementById('edit_end_date').value = endDate;
            document.getElementById('edit_bcv_rate').value = bcvRate;
            document.getElementById('edit_days_in_period').value = daysInPeriod;
            document.getElementById('edit_status').value = status;

            // Mostrar la modal
            document.getElementById('editPayrollModal').style.display = 'flex';

            // Enfocar el primer campo
            document.getElementById('edit_start_date').focus();

            // Prevenir scroll del body
            document.body.style.overflow = 'hidden';
        }

        function closeEditModal() {
            document.getElementById('editPayrollModal').style.display = 'none';
            document.body.style.overflow = 'auto';
            clearFormErrors();
        }

        function clearFormErrors() {
            // Remover clases de error
            const formGroups = document.querySelectorAll('.custom-modal-body .form-group');
            formGroups.forEach(group => {
                group.classList.remove('error');
                const errorMsg = group.querySelector('.error-message');
                if (errorMsg) {
                    errorMsg.remove();
                }
            });
        }

        function showFieldError(fieldId, message) {
            const field = document.getElementById(fieldId);
            const formGroup = field.closest('.form-group');

            formGroup.classList.add('error');

            // Remover mensaje de error existente
            const existingError = formGroup.querySelector('.error-message');
            if (existingError) {
                existingError.remove();
            }

            // Agregar nuevo mensaje de error
            const errorElement = document.createElement('span');
            errorElement.className = 'error-message';
            errorElement.textContent = message;
            formGroup.appendChild(errorElement);
        }

        function validateForm() {
            let isValid = true;
            clearFormErrors();

            const startDate = document.getElementById('edit_start_date').value;
            const endDate = document.getElementById('edit_end_date').value;
            const bcvRate = parseFloat(document.getElementById('edit_bcv_rate').value);
            const daysInPeriod = parseFloat(document.getElementById('edit_days_in_period').value);
            const status = document.getElementById('edit_status').value;

            if (!startDate) {
                showFieldError('edit_start_date', 'La fecha de inicio es requerida.');
                isValid = false;
            }

            if (!endDate) {
                showFieldError('edit_end_date', 'La fecha de fin es requerida.');
                isValid = false;
            }

            if (startDate && endDate && startDate >= endDate) {
                showFieldError('edit_end_date', 'La fecha de fin debe ser posterior a la fecha de inicio.');
                isValid = false;
            }

            if (isNaN(bcvRate) || bcvRate <= 0) {
                showFieldError('edit_bcv_rate', 'La tasa BCV debe ser un número mayor que cero.');
                isValid = false;
            }

            if (isNaN(daysInPeriod) || daysInPeriod <= 0) {
                showFieldError('edit_days_in_period', 'Los días en el período deben ser un número mayor que cero.');
                isValid = false;
            }

            if (!status) {
                showFieldError('edit_status', 'El estado es requerido.');
                isValid = false;
            }

            return isValid;
        }

        function submitEditForm() {
            if (!validateForm()) {
                return;
            }

            // Deshabilitar botón mientras se procesa
            const submitBtn = document.querySelector('.custom-modal-footer .btn-primary');
            const originalText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Guardando...';

            const formData = new FormData();
            formData.append('period_id', document.getElementById('edit_period_id').value);
            formData.append('start_date', document.getElementById('edit_start_date').value);
            formData.append('end_date', document.getElementById('edit_end_date').value);
            formData.append('bcv_rate', document.getElementById('edit_bcv_rate').value);
            formData.append('days_in_period', document.getElementById('edit_days_in_period').value);
            formData.append('status', document.getElementById('edit_status').value);


            // Enviar datos via AJAX
            fetch('<?php echo getBaseUrl(); ?>update_payroll.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Error en la respuesta del servidor');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    closeEditModal();
                    showSuccessMessage(data.message);
                    // Recargar la página para actualizar la tabla
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showErrorMessage(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showErrorMessage('Error al actualizar la nómina. Por favor, inténtelo de nuevo.');
            })
            .finally(() => {
                // Rehabilitar botón
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            });
        }

        function showSuccessMessage(message) {
            showMessage(message, 'success');
        }

        function showErrorMessage(message) {
            showMessage(message, 'error');
        }

        function showMessage(message, type) {
            // Remover mensaje existente
            const existingMessage = document.querySelector('.alert-message');
            if (existingMessage) {
                existingMessage.remove();
            }

            // Crear nuevo mensaje
            const messageDiv = document.createElement('div');
            messageDiv.className = `alert-message alert-${type}`;
            messageDiv.innerHTML = `
                <div class="alert-content">
                    <span>${message}</span>
                    <button type="button" class="alert-close" onclick="this.parentElement.parentElement.remove()">&times;</button>
                </div>
            `;

            // Insertar al inicio del contenedor principal
            const container = document.querySelector('.container');
            container.insertBefore(messageDiv, container.firstChild);

            // Auto-remover después de 5 segundos
            setTimeout(() => {
                if (messageDiv.parentElement) {
                    messageDiv.remove();
                }
            }, 5000);
        }

        // Manejar tecla Escape para cerrar modal
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && document.getElementById('editPayrollModal').style.display === 'flex') {
                closeEditModal();
            }
        });

        // Funciones para modal de eliminación
        function openDeleteModal(periodId, startDate, endDate, status) {
            document.getElementById('delete_period_id').value = periodId;
            document.getElementById('delete_period_range').textContent = startDate + ' al ' + endDate;
            document.getElementById('delete_period_status').textContent = status;

            document.getElementById('deletePayrollModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';

            // Enfocar el botón de cancelar por defecto
            document.querySelector('#deletePayrollModal .btn-secondary').focus();
        }

        function closeDeleteModal() {
            document.getElementById('deletePayrollModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function confirmDeletePayroll() {
            const periodId = document.getElementById('delete_period_id').value;

            if (!periodId) {
                showErrorMessage('ID de período no válido.');
                return;
            }

            // Deshabilitar botones mientras se procesa
            const deleteBtn = document.querySelector('#deletePayrollModal .btn-danger');
            const cancelBtn = document.querySelector('#deletePayrollModal .btn-secondary');
            const originalDeleteText = deleteBtn.innerHTML;
            const originalCancelText = cancelBtn.textContent;

            deleteBtn.disabled = true;
            cancelBtn.disabled = true;
            deleteBtn.innerHTML = '<span class="loading-spinner"></span> Eliminando...';

            // Enviar solicitud de eliminación
            fetch('<?php echo getBaseUrl(); ?>delete_payroll.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'period_id=' + encodeURIComponent(periodId)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Error en la respuesta del servidor');
                }
                return response.json();
            })
            .then(data => {
                closeDeleteModal();

                if (data.success) {
                    showSuccessMessage(data.message);
                    // Recargar la página para actualizar la tabla
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    showErrorMessage(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                closeDeleteModal();
                showErrorMessage('Error al eliminar la nómina. Por favor, inténtelo de nuevo.');
            })
            .finally(() => {
                // Rehabilitar botones
                deleteBtn.disabled = false;
                cancelBtn.disabled = false;
                deleteBtn.innerHTML = originalDeleteText;
            });
        }

        // Manejar envío del formulario con Enter
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && document.getElementById('editPayrollModal').style.display === 'flex') {
                e.preventDefault();
                submitEditForm();
            }
        });

        // Manejar tecla Escape para ambas modales
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                if (document.getElementById('editPayrollModal').style.display === 'flex') {
                    closeEditModal();
                } else if (document.getElementById('deletePayrollModal').style.display === 'flex') {
                    closeDeleteModal();
                }
            }
        });
    </script>
</body>
</html>
