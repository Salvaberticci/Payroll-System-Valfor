<?php
// public/reports_paid.php
require_once __DIR__ . '/../includes/auth.php'; // Incluir el archivo de autenticación y settings.php

// Requerir que el usuario esté logueado y tenga rol de 'admin' o 'assistant'
requireRole([ROLE_ADMIN, ROLE_ASSISTANT]);

$page_title = 'Reportes de Pagos de Nómina';
$message = '';
$message_type = ''; // 'success' or 'danger'

$pdo = getDbConnection();
$report_data = [];
$start_date_filter = $_POST['start_date_filter'] ?? '';
$end_date_filter = $_POST['end_date_filter'] ?? '';

$report_summary = [
    'total_neto_pagado_usd' => 0,
    'total_neto_pagado_bs' => 0,
    'total_periodos_pagados' => 0,
];

// Lógica para generar el reporte cuando se envía el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_report'])) {
    try {
        $query = "
            SELECT
                pp.id AS period_id,
                pp.start_date,
                pp.end_date,
                pp.bcv_rate,
                SUM(CASE WHEN pc.type = 'ingreso' THEN epd.amount_usd ELSE 0 END) AS total_ingresos_usd,
                SUM(CASE WHEN pc.type = 'beneficio' THEN epd.amount_usd ELSE 0 END) AS total_beneficios_usd,
                SUM(CASE WHEN pc.type IN ('deduccion_legal', 'deduccion_personal') THEN epd.amount_usd ELSE 0 END) AS total_deducciones_usd,
                SUM(CASE WHEN pc.type = 'ingreso' THEN epd.amount_bs ELSE 0 END) AS total_ingresos_bs,
                SUM(CASE WHEN pc.type = 'beneficio' THEN epd.amount_bs ELSE 0 END) AS total_beneficios_bs,
                SUM(CASE WHEN pc.type IN ('deduccion_legal', 'deduccion_personal') THEN epd.amount_bs ELSE 0 END) AS total_deducciones_bs
            FROM
                payroll_periods pp
            JOIN
                employee_payroll_details epd ON pp.id = epd.payroll_period_id
            JOIN
                payroll_concepts pc ON epd.concept_id = pc.id
            WHERE
                pp.status = 'paid'
        ";
        $params = [];

        if (!empty($start_date_filter)) {
            $query .= " AND pp.start_date >= :start_date";
            $params[':start_date'] = $start_date_filter;
        }
        if (!empty($end_date_filter)) {
            $query .= " AND pp.end_date <= :end_date";
            $params[':end_date'] = $end_date_filter;
        }

        $query .= " GROUP BY pp.id, pp.start_date, pp.end_date, pp.bcv_rate ORDER BY pp.start_date DESC";

        $stmt_report = $pdo->prepare($query);
        $stmt_report->execute($params);
        $report_data = $stmt_report->fetchAll();

        if (empty($report_data)) {
            $message = 'No se encontraron nóminas pagadas en el rango de fechas especificado.';
            $message_type = 'info';
        } else {
            // Calcular el neto a pagar por cada período y el resumen total
            foreach ($report_data as &$row) {
                $row['neto_pagado_usd'] = $row['total_ingresos_usd'] + $row['total_beneficios_usd'] - $row['total_deducciones_usd'];
                $row['neto_pagado_bs'] = $row['total_ingresos_bs'] + $row['total_beneficios_bs'] - $row['total_deducciones_bs'];

                $report_summary['total_neto_pagado_usd'] += $row['neto_pagado_usd'];
                $report_summary['total_neto_pagado_bs'] += $row['neto_pagado_bs'];
                $report_summary['total_periodos_pagados']++;
            }
            unset($row); // Romper la referencia del último elemento

            $message = 'Reporte de pagos generado exitosamente.';
            $message_type = 'success';
        }
    } catch (PDOException $e) {
        $message = 'Error al generar el reporte de pagos: ' . htmlspecialchars($e->getMessage());
        $message_type = 'danger';
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
    <!-- Estilos específicos de la página de reportes de pagos -->
    <link href="./assets/css/reports_paid.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; // Incluir la barra de navegación ?>

    <div class="container mt-4">
        <h1 class="mb-4"><?php echo $page_title; ?></h1>

        <div class="card mb-4">
            <div class="card-header">
                Generar Reporte de Pagos
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
                            <label for="start_date_filter" class="form-label">Fecha de Inicio (Opcional)</label>
                            <input type="date" class="form-control" id="start_date_filter" name="start_date_filter" value="<?php echo htmlspecialchars($start_date_filter); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="end_date_filter" class="form-label">Fecha de Fin (Opcional)</label>
                            <input type="date" class="form-control" id="end_date_filter" name="end_date_filter" value="<?php echo htmlspecialchars($end_date_filter); ?>">
                        </div>
                    </div>
                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-currency-dollar me-2"></i> Generar Reporte de Pagos
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if (!empty($report_data)): ?>
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    Nóminas Pagadas por Período
                    <button type="button" class="btn btn-info btn-sm" onclick="printPaidPDF()">
                        <i class="bi bi-printer me-1"></i> Descargar PDF
                    </button>
                </div>
                <div class="card-body">
                    <h2 class="mb-4">Detalles de Pagos</h2>
            <div class="table-responsive mb-4">
                <table class="table table-hover table-striped">
                    <thead>
                        <tr>
                            <th>Período</th>
                            <th>Tasa BCV</th>
                            <th>Total Ingresos ($)</th>
                            <th>Total Beneficios ($)</th>
                            <th>Total Deducciones ($)</th>
                            <th>Neto Pagado ($)</th>
                            <th>Neto Pagado (Bs)</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report_data as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['start_date'] . ' al ' . $row['end_date']); ?></td>
                                <td><?php echo number_format($row['bcv_rate'], 4); ?></td>
                                <td><?php echo number_format($row['total_ingresos_usd'], 2); ?></td>
                                <td><?php echo number_format($row['total_beneficios_usd'], 2); ?></td>
                                <td><?php echo number_format($row['total_deducciones_usd'], 2); ?></td>
                                <td><strong><?php echo number_format($row['neto_pagado_usd'], 2); ?></strong></td>
                                <td><strong><?php echo number_format($row['neto_pagado_bs'], 2); ?></strong></td>
                                <td>
                                    <a href="<?php echo getBaseUrl(); ?>payroll_details.php?period_id=<?php echo $row['period_id']; ?>" class="btn btn-sm btn-info text-white" title="Ver Detalles">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="report-summary">
                <h4 class="text-center">Resumen Total de Nóminas Pagadas</h4>
                <div class="row">
                    <div class="col-md-6">
                        <strong>Total Neto Pagado ($):</strong> <span class="text-success">$<?php echo number_format($report_summary['total_neto_pagado_usd'], 2); ?></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Total Neto Pagado (Bs):</strong> <span class="text-success">Bs <?php echo number_format($report_summary['total_neto_pagado_bs'], 2); ?></span>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <strong>Total Períodos Pagados:</strong> <span><?php echo htmlspecialchars($report_summary['total_periodos_pagados']); ?></span>
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
        function printPaidPDF() {
            // Crear un formulario para enviar los parámetros actuales
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?php echo getBaseUrl(); ?>generate_paid_report_pdf.php';
            form.target = '_blank';

            // Agregar los parámetros del formulario actual
            const startDate = document.getElementById('start_date_filter').value;
            const endDate = document.getElementById('end_date_filter').value;

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
s