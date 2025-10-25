<?php
// public/payroll_details.php
require_once __DIR__ . '/../includes/auth.php'; // Incluir el archivo de autenticación y settings.php

// Requerir que el usuario esté logueado y tenga rol de 'admin' o 'assistant'
requireRole([ROLE_ADMIN, ROLE_ASSISTANT]);

$page_title = 'Detalles de Nómina';
$message = '';
$message_type = ''; // 'success' or 'danger'

$pdo = getDbConnection();
$period_id = $_GET['period_id'] ?? null;
$payroll_period = null;
$payroll_details = []; // Para almacenar los detalles por empleado y concepto
$total_summary = [
    'total_ingresos_usd' => 0,
    'total_deducciones_usd' => 0,
    'total_beneficios_usd' => 0,
    'neto_a_pagar_usd' => 0,
    'total_ingresos_bs' => 0,
    'total_deducciones_bs' => 0,
    'total_beneficios_bs' => 0,
    'neto_a_pagar_bs' => 0,
];

// Lógica para procesar la acción de "pagar" la nómina
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_as_paid') {
    $period_id_to_pay = $_POST['period_id_to_pay'] ?? null;

    if ($period_id_to_pay && is_numeric($period_id_to_pay)) {
        try {
            // Verificar el estado actual del período
            $stmt_check = $pdo->prepare("SELECT status FROM payroll_periods WHERE id = :id");
            $stmt_check->bindParam(':id', $period_id_to_pay, PDO::PARAM_INT);
            $stmt_check->execute();
            $current_status = $stmt_check->fetchColumn();

            if ($current_status === 'calculated') {
                $stmt_update = $pdo->prepare("UPDATE payroll_periods SET status = 'paid' WHERE id = :id");
                $stmt_update->bindParam(':id', $period_id_to_pay, PDO::PARAM_INT);
                if ($stmt_update->execute()) {
                    $message = 'Nómina marcada como PAGADA exitosamente.';
                    $message_type = 'success';
                } else {
                    $message = 'Error al marcar la nómina como pagada.';
                    $message_type = 'danger';
                }
            } else {
                $message = 'Solo las nóminas en estado "calculado" pueden marcarse como pagadas. Estado actual: ' . htmlspecialchars($current_status);
                $message_type = 'danger';
            }
        } catch (PDOException $e) {
            $message = 'Error de base de datos al marcar nómina como pagada: ' . htmlspecialchars($e->getMessage());
            $message_type = 'danger';
        }
    } else {
        $message = 'ID de período de nómina inválido para marcar como pagado.';
        $message_type = 'danger';
    }
    // Redirigir para limpiar los parámetros POST y mostrar el mensaje
    header('Location: ' . getBaseUrl() . 'payroll_details.php?period_id=' . $period_id . '&message=' . urlencode($message) . '&type=' . urlencode($message_type));
    exit();
}


if (!$period_id || !is_numeric($period_id)) {
    $message = 'ID de período de nómina no especificado o inválido.';
    $message_type = 'danger';
} else {
    try {
        // 1. Obtener información del período de nómina
        $stmt_period = $pdo->prepare("SELECT id, start_date, end_date, bcv_rate, days_in_period, status FROM payroll_periods WHERE id = :id");
        $stmt_period->bindParam(':id', $period_id, PDO::PARAM_INT);
        $stmt_period->execute();
        $payroll_period = $stmt_period->fetch();

        if (!$payroll_period) {
            $message = 'Período de nómina no encontrado.';
            $message_type = 'danger';
        } else {
            // 2. Obtener todos los detalles de nómina para este período
            $stmt_details = $pdo->prepare("
                SELECT
                    epd.amount_usd, epd.amount_bs, epd.days_applied,
                    e.full_name, e.cedula, e.salario_base_mensual_usd,
                    pc.name AS concept_name, pc.type AS concept_type
                FROM
                    employee_payroll_details epd
                JOIN
                    employees e ON epd.employee_id = e.id
                JOIN
                    payroll_concepts pc ON epd.concept_id = pc.id
                WHERE
                    epd.payroll_period_id = :period_id
                ORDER BY
                    e.full_name ASC, pc.type ASC, pc.name ASC
            ");
            $stmt_details->bindParam(':period_id', $period_id, PDO::PARAM_INT);
            $stmt_details->execute();
            $raw_details = $stmt_details->fetchAll();

            // Organizar los detalles por empleado para una mejor visualización
            foreach ($raw_details as $detail) {
                $employee_name = $detail['full_name'];
                if (!isset($payroll_details[$employee_name])) {
                    $payroll_details[$employee_name] = [
                        'cedula' => $detail['cedula'],
                        'salario_base_mensual_usd' => $detail['salario_base_mensual_usd'],
                        'concepts' => [],
                        'subtotal_ingresos_usd' => 0,
                        'subtotal_deducciones_usd' => 0,
                        'subtotal_beneficios_usd' => 0,
                        'neto_empleado_usd' => 0,
                        'subtotal_ingresos_bs' => 0,
                        'subtotal_deducciones_bs' => 0,
                        'subtotal_beneficios_bs' => 0,
                        'neto_empleado_bs' => 0,
                    ];
                }

                $payroll_details[$employee_name]['concepts'][] = $detail;

                // Sumar a los subtotales del empleado y totales generales
                if ($detail['concept_type'] === 'ingreso') {
                    $payroll_details[$employee_name]['subtotal_ingresos_usd'] += $detail['amount_usd'];
                    $payroll_details[$employee_name]['subtotal_ingresos_bs'] += $detail['amount_bs'];
                    $total_summary['total_ingresos_usd'] += $detail['amount_usd'];
                    $total_summary['total_ingresos_bs'] += $detail['amount_bs'];
                } elseif ($detail['concept_type'] === 'deduccion_legal' || $detail['concept_type'] === 'deduccion_personal') {
                    $payroll_details[$employee_name]['subtotal_deducciones_usd'] += $detail['amount_usd'];
                    $payroll_details[$employee_name]['subtotal_deducciones_bs'] += $detail['amount_bs'];
                    $total_summary['total_deducciones_usd'] += $detail['amount_usd'];
                    $total_summary['total_deducciones_bs'] += $detail['amount_bs'];
                } elseif ($detail['concept_type'] === 'beneficio') {
                    $payroll_details[$employee_name]['subtotal_beneficios_usd'] += $detail['amount_usd'];
                    $payroll_details[$employee_name]['subtotal_beneficios_bs'] += $detail['amount_bs'];
                    $total_summary['total_beneficios_usd'] += $detail['amount_usd'];
                    $total_summary['total_beneficios_bs'] += $detail['amount_bs'];
                }
            }

            // Calcular neto a pagar por empleado y el total general
            foreach ($payroll_details as $name => &$data) {
                $data['neto_empleado_usd'] = $data['subtotal_ingresos_usd'] + $data['subtotal_beneficios_usd'] - $data['subtotal_deducciones_usd'];
                $data['neto_empleado_bs'] = $data['subtotal_ingresos_bs'] + $data['subtotal_beneficios_bs'] - $data['subtotal_deducciones_bs'];
                $total_summary['neto_a_pagar_usd'] += $data['neto_empleado_usd'];
                $total_summary['neto_a_pagar_bs'] += $data['neto_empleado_bs'];
            }
            unset($data); // Romper la referencia del último elemento

            // Opcional: Actualizar payroll_summaries si no existe o si se quiere recalcular
            // Esto se podría hacer aquí o en el script de cálculo inicial
        }
    } catch (PDOException $e) {
        $message = 'Error al cargar los detalles de la nómina: ' . htmlspecialchars($e->getMessage());
        $message_type = 'danger';
    }
}

// Obtener mensajes de la redirección después de procesar
if (isset($_GET['message']) && isset($_GET['type'])) {
    $message = htmlspecialchars($_GET['message']);
    $message_type = htmlspecialchars($_GET['type']);
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
    <!-- Estilos específicos de la página de detalles de nómina -->
    <link href="./assets/css/payroll_details.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; // Incluir la barra de navegación ?>

    <div class="container mt-4">
        <h1 class="mb-4"><?php echo $page_title; ?></h1>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?>" role="alert">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <?php if ($payroll_period): ?>
            <div class="card mb-4">
                <div class="card-header">
                    Información del Período
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Período:</strong> <?php echo htmlspecialchars($payroll_period['start_date']); ?> al <?php echo htmlspecialchars($payroll_period['end_date']); ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Tasa BCV:</strong> <?php echo number_format($payroll_period['bcv_rate'], 2); ?> Bs/$
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Días en el Período:</strong> <?php echo htmlspecialchars($payroll_period['days_in_period']); ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Estado:</strong>
                            <?php
                            $badge_class = 'bg-secondary';
                            switch ($payroll_period['status']) {
                                case 'pending': $badge_class = 'bg-warning text-dark'; break;
                                case 'calculated': $badge_class = 'bg-info'; break;
                                case 'paid': $badge_class = 'bg-success'; break;
                                case 'closed': $badge_class = 'bg-dark'; break;
                            }
                            ?>
                            <span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars(ucfirst($payroll_period['status'])); ?></span>
                        </div>
                    </div>
                    <div class="mt-3 text-end">
                        <?php if ($payroll_period['status'] === 'calculated'): ?>
                            <form method="POST" action="" style="display:inline;">
                                <input type="hidden" name="action" value="mark_as_paid">
                                <input type="hidden" name="period_id_to_pay" value="<?php echo htmlspecialchars($payroll_period['id']); ?>">
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-cash-coin me-1"></i> Marcar como Pagada
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if (empty($payroll_details)): ?>
                <div class="alert alert-info" role="alert">
                    No se encontraron detalles de nómina para este período.
                </div>
            <?php else: ?>
                <?php foreach ($payroll_details as $employee_name => $data): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            Detalles para: <?php echo htmlspecialchars($employee_name); ?> (C.I.: <?php echo htmlspecialchars($data['cedula']); ?>)
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
                                            <td><strong><?php echo number_format($data['neto_empleado_usd'], 2); ?></strong></td>
                                            <td><strong><?php echo number_format($data['neto_empleado_bs'], 2); ?></strong></td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="total-summary">
                    <h4 class="text-center">Resumen Total de la Nómina</h4>
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Total Ingresos:</strong> <span>$<?php echo number_format($total_summary['total_ingresos_usd'], 2); ?></span> (Bs <?php echo number_format($total_summary['total_ingresos_bs'], 2); ?>)
                        </div>
                        <div class="col-md-6">
                            <strong>Total Beneficios:</strong> <span>$<?php echo number_format($total_summary['total_beneficios_usd'], 2); ?></span> (Bs <?php echo number_format($total_summary['total_beneficios_bs'], 2); ?>)
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <strong>Total Deducciones:</strong> <span>$<?php echo number_format($total_summary['total_deducciones_usd'], 2); ?></span> (Bs <?php echo number_format($total_summary['total_deducciones_bs'], 2); ?>)
                        </div>
                        <div class="col-md-6">
                            <strong>NETO TOTAL A PAGAR:</strong> <span class="text-primary">$<?php echo number_format($total_summary['neto_a_pagar_usd'], 2); ?></span> (<span class="text-primary">Bs <?php echo number_format($total_summary['neto_a_pagar_bs'], 2); ?></span>)
                        </div>
                    </div>
                </div>

            <?php endif; ?>

            <div class="mt-4 text-center">
                <a href="<?php echo getBaseUrl(); ?>payroll_calc.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left-circle me-1"></i> Volver al Cálculo de Nómina
                </a>
            </div>

        <?php endif; ?>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; // Incluir el pie de página ?>
    <!-- Incluir Bootstrap JS (Bundle con Popper) directamente con ruta relativa -->
    <script src="./assets/js/bootstrap.bundle.min.js"></script>
    <!-- Scripts personalizados (si los hay) directamente con ruta relativa -->
    <script src="./assets/js/script.js"></script>
</body>
</html>
