<?php
// public/reports_discounts.php
require_once __DIR__ . '/../includes/auth.php'; // Incluir el archivo de autenticación
require_once __DIR__ . '/../config/settings.php'; // Para getDbConnection() y roles

// Requerir que el usuario esté logueado y tenga rol de 'admin', 'assistant' o 'solo lectura'
requireRole([ROLE_ADMIN, ROLE_ASSISTANT, ROLE_READ_ONLY]);

$page_title = 'Reportes de Descuentos';
$message = '';
$message_type = ''; // 'success' or 'danger'

$pdo = getDbConnection();
$report_data = [];
$start_date_filter = $_POST['start_date_filter'] ?? '';
$end_date_filter = $_POST['end_date_filter'] ?? '';

$report_summary = [
    'total_deducciones_usd' => 0,
    'total_deducciones_bs' => 0,
];

// Lógica para generar el reporte cuando se envía el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_report'])) {
    try {
        $query = "
            SELECT
                pc.name AS concept_name,
                pc.type AS concept_type,
                pp.start_date,
                pp.end_date,
                SUM(epd.amount_usd) AS total_amount_usd,
                SUM(epd.amount_bs) AS total_amount_bs
            FROM
                employee_payroll_details epd
            JOIN
                payroll_concepts pc ON epd.concept_id = pc.id
            JOIN
                payroll_periods pp ON epd.payroll_period_id = pp.id
            WHERE
                pc.type IN ('deduccion_legal', 'deduccion_personal')
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

        $query .= " GROUP BY pc.name, pc.type, pp.start_date, pp.end_date ORDER BY pp.start_date DESC, pc.name ASC";

        $stmt_report = $pdo->prepare($query);
        $stmt_report->execute($params);
        $report_data = $stmt_report->fetchAll();

        if (empty($report_data)) {
            $message = 'No se encontraron datos de descuentos en el rango de fechas especificado.';
            $message_type = 'info';
        } else {
            // Calcular el resumen total
            foreach ($report_data as $row) {
                $report_summary['total_deducciones_usd'] += $row['total_amount_usd'];
                $report_summary['total_deducciones_bs'] += $row['total_amount_bs'];
            }
            $message = 'Reporte de descuentos generado exitosamente.';
            $message_type = 'success';
        }
    } catch (PDOException $e) {
        $message = 'Error al generar el reporte de descuentos: ' . htmlspecialchars($e->getMessage());
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
    <!-- Estilos específicos de la página de reportes de descuentos -->
    <link href="./assets/css/reports_discounts.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; // Incluir la barra de navegación ?>

    <div class="container mt-4">
        <h1 class="mb-4"><?php echo $page_title; ?></h1>

        <div class="card mb-4">
            <div class="card-header">
                Generar Reporte de Descuentos
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
                            <i class="bi bi-file-earmark-spreadsheet me-2"></i> Generar Reporte de Descuentos
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <?php if (!empty($report_data)): ?>
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    Resumen de Descuentos por Período y Concepto
                    <button type="button" class="btn btn-info btn-sm" onclick="printDiscountsPDF()">
                        <i class="bi bi-printer me-1"></i> Descargar PDF
                    </button>
                </div>
                <div class="card-body">
                    <h2 class="mb-4">Detalles de Descuentos</h2>
            <div class="table-responsive mb-4">
                <table class="table table-hover table-striped">
                    <thead>
                        <tr>
                            <th>Período</th>
                            <th>Concepto</th>
                            <th>Tipo de Concepto</th>
                            <th>Total Descuento ($)</th>
                            <th>Total Descuento (Bs)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report_data as $row): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['start_date'] . ' al ' . $row['end_date']); ?></td>
                                <td><?php echo htmlspecialchars($row['concept_name']); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $row['concept_type']))); ?></td>
                                <td><?php echo number_format($row['total_amount_usd'], 2); ?></td>
                                <td><?php echo number_format($row['total_amount_bs'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="report-summary">
                <h4 class="text-center">Totales Acumulados de Descuentos</h4>
                <div class="row">
                    <div class="col-md-6">
                        <strong>Total Deducciones ($):</strong> <span class="text-danger">$<?php echo number_format($report_summary['total_deducciones_usd'], 2); ?></span>
                    </div>
                    <div class="col-md-6">
                        <strong>Total Deducciones (Bs):</strong> <span class="text-danger">Bs <?php echo number_format($report_summary['total_deducciones_bs'], 2); ?></span>
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
        function printDiscountsPDF() {
            // Crear un formulario para enviar los parámetros actuales
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?php echo getBaseUrl(); ?>generate_discounts_report_pdf.php';
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
