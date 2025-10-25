<?php
// public/reports_employee.php
require_once __DIR__ . '/../includes/auth.php'; // Incluir el archivo de autenticación
require_once __DIR__ . '/../config/settings.php'; // Para getDbConnection() y roles

// Requerir que el usuario esté logueado y tenga rol de 'admin' o 'assistant'
requireRole([ROLE_ADMIN, ROLE_ASSISTANT]);

$page_title = 'Reportes por Empleado';
$message = '';
$message_type = ''; // 'success' or 'danger'

$pdo = getDbConnection();
$employees_list = []; // Para el select de empleados
$report_data = [];
$employee_id_selected = $_POST['employee_id'] ?? '';
$start_date_filter = $_POST['start_date_filter'] ?? '';
$end_date_filter = $_POST['end_date_filter'] ?? '';

$report_summary = [
    'total_ingresos_usd' => 0,
    'total_deducciones_usd' => 0,
    'total_beneficios_usd' => 0,
    'neto_total_pagado_usd' => 0,
    'total_ingresos_bs' => 0,
    'total_deducciones_bs' => 0,
    'total_beneficios_bs' => 0,
    'neto_total_pagado_bs' => 0,
];

try {
    // Obtener la lista de empleados para el selector
    $stmt_employees = $pdo->query("SELECT id, full_name, cedula FROM employees ORDER BY full_name ASC");
    $employees_list = $stmt_employees->fetchAll();
} catch (PDOException $e) {
    $message = 'Error al cargar la lista de empleados: ' . htmlspecialchars($e->getMessage());
    $message_type = 'danger';
}

// Lógica para generar el reporte cuando se envía el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_report'])) {
    if (empty($employee_id_selected) || !is_numeric($employee_id_selected)) {
        $message = 'Por favor, seleccione un empleado válido.';
        $message_type = 'danger';
    } else {
        try {
            $query = "
                SELECT
                    epd.amount_usd, epd.amount_bs, epd.days_applied,
                    pc.name AS concept_name, pc.type AS concept_type,
                    pp.start_date, pp.end_date, pp.bcv_rate
                FROM
                    employee_payroll_details epd
                JOIN
                    payroll_periods pp ON epd.payroll_period_id = pp.id
                JOIN
                    payroll_concepts pc ON epd.concept_id = pc.id
                WHERE
                    epd.employee_id = :employee_id
            ";
            $params = [':employee_id' => $employee_id_selected];

            if (!empty($start_date_filter)) {
                $query .= " AND pp.start_date >= :start_date";
                $params[':start_date'] = $start_date_filter;
            }
            if (!empty($end_date_filter)) {
                $query .= " AND pp.end_date <= :end_date";
                $params[':end_date'] = $end_date_filter;
            }

            $query .= " ORDER BY pp.start_date DESC, pc.type ASC, pc.name ASC";

            $stmt_report = $pdo->prepare($query);
            $stmt_report->execute($params);
            $raw_report_data = $stmt_report->fetchAll();

            if (empty($raw_report_data)) {
                $message = 'No se encontraron datos de nómina para el empleado seleccionado en el rango de fechas especificado.';
                $message_type = 'info';
            } else {
                // Organizar los datos por período de nómina para una mejor visualización
                foreach ($raw_report_data as $row) {
                    $period_key = $row['start_date'] . ' - ' . $row['end_date'];
                    if (!isset($report_data[$period_key])) {
                        $report_data[$period_key] = [
                            'bcv_rate' => $row['bcv_rate'],
                            'concepts' => [],
                            'subtotal_ingresos_usd' => 0,
                            'subtotal_deducciones_usd' => 0,
                            'subtotal_beneficios_usd' => 0,
                            'neto_periodo_usd' => 0,
                            'subtotal_ingresos_bs' => 0,
                            'subtotal_deducciones_bs' => 0,
                            'subtotal_beneficios_bs' => 0,
                            'neto_periodo_bs' => 0,
                        ];
                    }

                    $report_data[$period_key]['concepts'][] = $row;

                    // Sumar a los subtotales del período y totales generales
                    if ($row['concept_type'] === 'ingreso') {
                        $report_data[$period_key]['subtotal_ingresos_usd'] += $row['amount_usd'];
                        $report_data[$period_key]['subtotal_ingresos_bs'] += $row['amount_bs'];
                        $report_summary['total_ingresos_usd'] += $row['amount_usd'];
                        $report_summary['total_ingresos_bs'] += $row['amount_bs'];
                    } elseif ($row['concept_type'] === 'deduccion_legal' || $row['concept_type'] === 'deduccion_personal') {
                        $report_data[$period_key]['subtotal_deducciones_usd'] += $row['amount_usd'];
                        $report_data[$period_key]['subtotal_deducciones_bs'] += $row['amount_bs'];
                        $report_summary['total_deducciones_usd'] += $row['amount_usd'];
                        $report_summary['total_deducciones_bs'] += $row['amount_bs'];
                    } elseif ($row['concept_type'] === 'beneficio') {
                        $report_data[$period_key]['subtotal_beneficios_usd'] += $row['amount_usd'];
                        $report_data[$period_key]['subtotal_beneficios_bs'] += $row['amount_bs'];
                        $report_summary['total_beneficios_usd'] += $row['amount_usd'];
                        $report_summary['total_beneficios_bs'] += $row['amount_bs'];
                    }
                }

                // Calcular neto a pagar por período y el total general
                foreach ($report_data as $period_key => &$data) {
                    $data['neto_periodo_usd'] = $data['subtotal_ingresos_usd'] + $data['subtotal_beneficios_usd'] - $data['subtotal_deducciones_usd'];
                    $data['neto_periodo_bs'] = $data['subtotal_ingresos_bs'] + $data['subtotal_beneficios_bs'] - $data['subtotal_deducciones_bs'];
                    $report_summary['neto_total_pagado_usd'] += $data['neto_periodo_usd'];
                    $report_summary['neto_total_pagado_bs'] += $data['neto_periodo_bs'];
                }
                unset($data); // Romper la referencia del último elemento

                $message = 'Reporte generado exitosamente.';
                $message_type = 'success';
            }
        } catch (PDOException $e) {
            $message = 'Error al generar el reporte: ' . htmlspecialchars($e->getMessage());
            $message_type = 'danger';
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
    <!-- Estilos específicos de la página de reportes por empleado -->
    <link href="./assets/css/reports_employee.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; // Incluir la barra de navegación ?>

    <div class="container mt-4">
        <h1 class="mb-4"><?php echo $page_title; ?></h1>

        <div class="card mb-4">
            <div class="card-header">
                Generar Reporte por Empleado
            </div>
            <div class="card-body">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>" role="alert">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="generate_report" value="1">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="employee_id" class="form-label">Seleccionar Empleado</label>
                            <select class="form-select" id="employee_id" name="employee_id" required>
                                <option value="">Seleccione un empleado</option>
                                <?php foreach ($employees_list as $employee): ?>
                                    <option value="<?php echo htmlspecialchars($employee['id']); ?>" <?php echo ($employee_id_selected == $employee['id'] ? 'selected' : ''); ?>>
                                        <?php echo htmlspecialchars($employee['full_name'] . ' (C.I.: ' . $employee['cedula'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="start_date_filter" class="form-label">Fecha de Inicio (Opcional)</label>
                            <input type="date" class="form-control" id="start_date_filter" name="start_date_filter" value="<?php echo htmlspecialchars($start_date_filter); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="end_date_filter" class="form-label">Fecha de Fin (Opcional)</label>
                            <input type="date" class="form-control" id="end_date_filter" name="end_date_filter" value="<?php echo htmlspecialchars($end_date_filter); ?>">
                        </div>
                    </div>
                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-file-earmark-bar-graph me-2"></i> Generar Reporte
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if (!empty($report_data)): ?>
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    Reporte Detallado
                    <button type="button" class="btn btn-info btn-sm" onclick="printEmployeePDF()">
                        <i class="bi bi-printer me-1"></i> Descargar PDF
                    </button>
                </div>
                <div class="card-body">
                    <h2 class="mb-4">Detalles por Período</h2>
            <?php foreach ($report_data as $period_key => $data): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        Período: <?php echo htmlspecialchars($period_key); ?> (Tasa BCV: <?php echo number_format($data['bcv_rate'], 4); ?> Bs/$)
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead>
                                    <tr>
                                        <th>Concepto</th>
                                        <th>Tipo</th>
                                        <th>Monto ($)</th>
                                        <th>Monto (Bs)</th>
                                        <th>Días Aplicados</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($data['concepts'] as $concept_detail): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($concept_detail['concept_name']); ?></td>
                                            <td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $concept_detail['concept_type']))); ?></td>
                                            <td><?php echo number_format($concept_detail['amount_usd'], 2); ?></td>
                                            <td><?php echo number_format($concept_detail['amount_bs'], 2); ?></td>
                                            <td><?php echo !is_null($concept_detail['days_applied']) ? htmlspecialchars($concept_detail['days_applied']) : 'N/A'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="table-info">
                                        <td colspan="2" class="text-end"><strong>Subtotal Ingresos:</strong></td>
                                        <td><strong><?php echo number_format($data['subtotal_ingresos_usd'], 2); ?></strong></td>
                                        <td><strong><?php echo number_format($data['subtotal_ingresos_bs'], 2); ?></strong></td>
                                        <td></td>
                                    </tr>
                                    <tr class="table-success">
                                        <td colspan="2" class="text-end"><strong>Subtotal Beneficios:</strong></td>
                                        <td><strong><?php echo number_format($data['subtotal_beneficios_usd'], 2); ?></strong></td>
                                        <td><strong><?php echo number_format($data['subtotal_beneficios_bs'], 2); ?></strong></td>
                                        <td></td>
                                    </tr>
                                    <tr class="table-danger">
                                        <td colspan="2" class="text-end"><strong>Subtotal Deducciones:</strong></td>
                                        <td><strong><?php echo number_format($data['subtotal_deducciones_usd'], 2); ?></strong></td>
                                        <td><strong><?php echo number_format($data['subtotal_deducciones_bs'], 2); ?></strong></td>
                                        <td></td>
                                    </tr>
                                    <tr class="table-primary">
                                        <td colspan="2" class="text-end"><strong>NETO A PAGAR:</strong></td>
                                        <td><strong><?php echo number_format($data['neto_periodo_usd'], 2); ?></strong></td>
                                        <td><strong><?php echo number_format($data['neto_periodo_bs'], 2); ?></strong></td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="report-summary">
                <h4 class="text-center">Resumen Total del Reporte</h4>
                <div class="row">
                    <div class="col-md-6">
                        <strong>Total Ingresos Acumulados:</strong> <span>$<?php echo number_format($report_summary['total_ingresos_usd'], 2); ?></span> (Bs <?php echo number_format($report_summary['total_ingresos_bs'], 2); ?>)
                    </div>
                    <div class="col-md-6">
                        <strong>Total Beneficios Acumulados:</strong> <span>$<?php echo number_format($report_summary['total_beneficios_usd'], 2); ?></span> (Bs <?php echo number_format($report_summary['total_beneficios_bs'], 2); ?>)
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <strong>Total Deducciones Acumuladas:</strong> <span>$<?php echo number_format($report_summary['total_deducciones_usd'], 2); ?></span> (Bs <?php echo number_format($report_summary['total_deducciones_bs'], 2); ?>)
                    </div>
                    <div class="col-md-6">
                        <strong>NETO TOTAL PAGADO:</strong> <span class="text-primary">$<?php echo number_format($report_summary['neto_total_pagado_usd'], 2); ?></span> (<span class="text-primary">Bs <?php echo number_format($report_summary['neto_total_pagado_bs'], 2); ?></span>)
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="mt-4 text-center">
            <a href="<?php echo getBaseUrl(); ?>dashboard.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left-circle me-1"></i> Volver al Dashboard
            </a>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; // Incluir el pie de página ?>
    <!-- Incluir Bootstrap JS (Bundle con Popper) directamente con ruta relativa -->
    <script src="./assets/js/bootstrap.bundle.min.js"></script>
    <!-- Scripts personalizados (si los hay) directamente con ruta relativa -->
    <script src="./assets/js/script.js"></script>
    <script>
        function printEmployeePDF() {
            // Crear un formulario para enviar los parámetros actuales
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?php echo getBaseUrl(); ?>generate_employee_report_pdf.php';
            form.target = '_blank';

            // Agregar los parámetros del formulario actual
            const employeeId = document.getElementById('employee_id').value;
            const startDate = document.getElementById('start_date_filter').value;
            const endDate = document.getElementById('end_date_filter').value;

            if (employeeId) {
                const inputEmployee = document.createElement('input');
                inputEmployee.type = 'hidden';
                inputEmployee.name = 'employee_id';
                inputEmployee.value = employeeId;
                form.appendChild(inputEmployee);
            }

            if (startDate) {
                const inputStart = document.createElement('input');
                inputStart.type = 'hidden';
                inputStart.name = 'start_date_filter';
                inputStart.value = startDate;
                form.appendChild(inputStart);
            }

            if (endDate) {
                const inputEnd = document.createElement('input');
                inputEnd.type = 'hidden';
                inputEnd.name = 'end_date_filter';
                inputEnd.value = endDate;
                form.appendChild(inputEnd);
            }

            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }
    </script>
</body>
</html>
