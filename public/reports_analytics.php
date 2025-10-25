<?php
// public/reports_analytics.php
require_once __DIR__ . '/../includes/auth.php'; // Incluir el archivo de autenticación
require_once __DIR__ . '/../config/settings.php'; // Para getDbConnection() y roles

// Requerir que el usuario esté logueado y tenga rol de 'admin' o 'assistant'
requireRole([ROLE_ADMIN, ROLE_ASSISTANT]);

$page_title = 'Reportes Analíticos';
$message = '';
$message_type = ''; // 'success' or 'danger'

$pdo = getDbConnection();
$start_date_filter = $_POST['start_date_filter'] ?? '';
$end_date_filter = $_POST['end_date_filter'] ?? '';

$analytics_summary = [
    'total_ingresos_usd' => 0,
    'total_deducciones_usd' => 0,
    'total_beneficios_usd' => 0,
    'neto_total_pagado_usd' => 0,
    'total_ingresos_bs' => 0,
    'total_deducciones_bs' => 0,
    'total_beneficios_bs' => 0,
    'neto_total_pagado_bs' => 0,
    'total_empleados_activos_periodo' => 0,
    'total_periodos_calculados' => 0,
];

// Lógica para generar el reporte cuando se envía el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_report'])) {
    try {
        // Consulta para obtener los totales agregados por tipo de concepto
        $query_totals = "
            SELECT
                pc.type AS concept_type,
                SUM(epd.amount_usd) AS total_usd,
                SUM(epd.amount_bs) AS total_bs
            FROM
                employee_payroll_details epd
            JOIN
                payroll_concepts pc ON epd.concept_id = pc.id
            JOIN
                payroll_periods pp ON epd.payroll_period_id = pp.id
            WHERE 1=1
        ";
        $params_totals = [];

        if (!empty($start_date_filter)) {
            $query_totals .= " AND pp.start_date >= :start_date";
            $params_totals[':start_date'] = $start_date_filter;
        }
        if (!empty($end_date_filter)) {
            $query_totals .= " AND pp.end_date <= :end_date";
            $params_totals[':end_date'] = $end_date_filter;
        }
        $query_totals .= " GROUP BY pc.type";

        $stmt_totals = $pdo->prepare($query_totals);
        $stmt_totals->execute($params_totals);
        $raw_totals = $stmt_totals->fetchAll();

        foreach ($raw_totals as $row) {
            if ($row['concept_type'] === 'ingreso') {
                $analytics_summary['total_ingresos_usd'] += $row['total_usd'];
                $analytics_summary['total_ingresos_bs'] += $row['total_bs'];
            } elseif ($row['concept_type'] === 'deduccion_legal' || $row['concept_type'] === 'deduccion_personal') {
                $analytics_summary['total_deducciones_usd'] += $row['total_usd'];
                $analytics_summary['total_deducciones_bs'] += $row['total_bs'];
            } elseif ($row['concept_type'] === 'beneficio') {
                $analytics_summary['total_beneficios_usd'] += $row['total_usd'];
                $analytics_summary['total_beneficios_bs'] += $row['total_bs'];
            }
        }

        // Calcular el neto total a pagar
        $analytics_summary['neto_total_pagado_usd'] = $analytics_summary['total_ingresos_usd'] + $analytics_summary['total_beneficios_usd'] - $analytics_summary['total_deducciones_usd'];
        $analytics_summary['neto_total_pagado_bs'] = $analytics_summary['total_ingresos_bs'] + $analytics_summary['total_beneficios_bs'] - $analytics_summary['total_deducciones_bs'];

        // Contar empleados activos en los períodos seleccionados (aproximado)
        $query_employees_count = "
            SELECT COUNT(DISTINCT epd.employee_id) AS num_employees
            FROM employee_payroll_details epd
            JOIN payroll_periods pp ON epd.payroll_period_id = pp.id
            WHERE 1=1
        ";
        $params_employees_count = [];
        if (!empty($start_date_filter)) {
            $query_employees_count .= " AND pp.start_date >= :start_date";
            $params_employees_count[':start_date'] = $start_date_filter;
        }
        if (!empty($end_date_filter)) {
            $query_employees_count .= " AND pp.end_date <= :end_date";
            $params_employees_count[':end_date'] = $end_date_filter;
        }
        $stmt_employees_count = $pdo->prepare($query_employees_count);
        $stmt_employees_count->execute($params_employees_count);
        $analytics_summary['total_empleados_activos_periodo'] = $stmt_employees_count->fetchColumn();

        // Contar períodos calculados
        $query_periods_count = "
            SELECT COUNT(id) AS num_periods
            FROM payroll_periods
            WHERE status IN ('calculated', 'paid')
        ";
        $params_periods_count = [];
        if (!empty($start_date_filter)) {
            $query_periods_count .= " AND start_date >= :start_date";
            $params_periods_count[':start_date'] = $start_date_filter;
        }
        if (!empty($end_date_filter)) {
            $query_periods_count .= " AND end_date <= :end_date";
            $params_periods_count[':end_date'] = $end_date_filter;
        }
        $stmt_periods_count = $pdo->prepare($query_periods_count);
        $stmt_periods_count->execute($params_periods_count);
        $analytics_summary['total_periodos_calculados'] = $stmt_periods_count->fetchColumn();

        $message = 'Reporte analítico generado exitosamente.';
        $message_type = 'success';

    } catch (PDOException $e) {
        $message = 'Error al generar el reporte analítico: ' . htmlspecialchars($e->getMessage());
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
    <!-- Estilos específicos de la página de reportes analíticos -->
    <link href="./assets/css/reports_analytics.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; // Incluir la barra de navegación ?>

    <div class="container mt-4">
        <h1 class="mb-4"><?php echo $page_title; ?></h1>

        <div class="card mb-4">
            <div class="card-header">
                Generar Reporte Analítico
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
                            <i class="bi bi-graph-up me-2"></i> Generar Reporte Analítico
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_report']) && $message_type === 'success'): ?>
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    Resumen Analítico del Período
                    <button type="button" class="btn btn-info btn-sm" onclick="printAnalyticsPDF()">
                        <i class="bi bi-printer me-1"></i> Descargar PDF
                    </button>
                </div>
                <div class="card-body">
                    <div class="report-summary">
                <div class="row">
                    <div class="col-md-6">
                        <strong>Total Ingresos ($):</strong> <span>$<?php echo number_format($analytics_summary['total_ingresos_usd'], 2); ?></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Total Ingresos (Bs):</strong> <span>Bs <?php echo number_format($analytics_summary['total_ingresos_bs'], 2); ?></span>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <strong>Total Beneficios ($):</strong> <span>$<?php echo number_format($analytics_summary['total_beneficios_usd'], 2); ?></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Total Beneficios (Bs):</strong> <span>Bs <?php echo number_format($analytics_summary['total_beneficios_bs'], 2); ?></span>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <strong>Total Deducciones ($):</strong> <span>$<?php echo number_format($analytics_summary['total_deducciones_usd'], 2); ?></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Total Deducciones (Bs):</strong> <span>Bs <?php echo number_format($analytics_summary['total_deducciones_bs'], 2); ?></span>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-6">
                        <strong>NETO TOTAL PAGADO ($):</strong> <span class="text-primary">$<?php echo number_format($analytics_summary['neto_total_pagado_usd'], 2); ?></span>
                    </div>
                    <div class="col-md-6">
                        <strong>NETO TOTAL PAGADO (Bs):</strong> <span class="text-primary">Bs <?php echo number_format($analytics_summary['neto_total_pagado_bs'], 2); ?></span>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-6">
                        <strong>Empleados Activos en Período:</strong> <span><?php echo htmlspecialchars($analytics_summary['total_empleados_activos_periodo']); ?></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Períodos de Nómina Calculados:</strong> <span><?php echo htmlspecialchars($analytics_summary['total_periodos_calculados']); ?></span>
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
        function printAnalyticsPDF() {
            // Crear un formulario para enviar los parámetros actuales
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?php echo getBaseUrl(); ?>generate_analytics_pdf.php';
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
