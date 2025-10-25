<?php
// public/generate_analytics_pdf.php
require_once __DIR__ . '/../includes/auth.php'; // Incluir el archivo de autenticación
require_once __DIR__ . '/../config/settings.php'; // Para getDbConnection() y roles

// Requerir que el usuario esté logueado y tenga rol de 'admin' o 'assistant'
requireRole([ROLE_ADMIN, ROLE_ASSISTANT]);

require_once __DIR__ . '/../vendor/autoload.php'; // Para TCPDF

$start_date_filter = $_POST['start_date_filter'] ?? '';
$end_date_filter = $_POST['end_date_filter'] ?? '';

$pdo = getDbConnection();

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

} catch (PDOException $e) {
    die('Error al generar el reporte: ' . htmlspecialchars($e->getMessage()));
}

// Crear PDF
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Configurar documento
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Sistema de Nómina VALFOR S.A.');
$pdf->SetTitle('Reporte Analítico de Nómina');
$pdf->SetSubject('Análisis de Nómina');
$pdf->SetKeywords('análisis, nómina, VALFOR');

// Configurar márgenes
$pdf->SetMargins(15, 20, 15);
$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(10);

// Configurar auto page breaks
$pdf->SetAutoPageBreak(TRUE, 15);

// Configurar fuente
$pdf->SetFont('helvetica', '', 10);

// Añadir página
$pdf->AddPage();

// Título
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, 'Reporte Analítico de Nómina - VALFOR S.A.', 0, 1, 'C');
$pdf->Ln(5);

// Información del período
$pdf->SetFont('helvetica', '', 10);
if (!empty($start_date_filter) || !empty($end_date_filter)) {
    $period_text = 'Período: ';
    if (!empty($start_date_filter)) {
        $period_text .= 'Desde ' . htmlspecialchars($start_date_filter);
    }
    if (!empty($end_date_filter)) {
        if (!empty($start_date_filter)) {
            $period_text .= ' ';
        }
        $period_text .= 'Hasta ' . htmlspecialchars($end_date_filter);
    }
    $pdf->Cell(0, 6, $period_text, 0, 1);
}
$pdf->Cell(0, 6, 'Fecha de generación: ' . date('d/m/Y H:i'), 0, 1);
$pdf->Ln(5);

// Resumen analítico
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 10, 'Resumen Analítico', 0, 1, 'L');
$pdf->Ln(2);

// Crear tabla de resumen
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor(240, 240, 240);

$header = array('Concepto', 'Total USD ($)', 'Total Bs');
$w = array(80, 50, 50);

for($i = 0; $i < count($header); $i++) {
    $pdf->Cell($w[$i], 8, $header[$i], 1, 0, 'C', 1);
}
$pdf->Ln();

// Datos del resumen
$pdf->SetFont('helvetica', '', 9);
$pdf->SetFillColor(255, 255, 255);

$data_rows = array(
    array('Total Ingresos', number_format($analytics_summary['total_ingresos_usd'], 2), number_format($analytics_summary['total_ingresos_bs'], 2)),
    array('Total Beneficios', number_format($analytics_summary['total_beneficios_usd'], 2), number_format($analytics_summary['total_beneficios_bs'], 2)),
    array('Total Deducciones', number_format($analytics_summary['total_deducciones_usd'], 2), number_format($analytics_summary['total_deducciones_bs'], 2)),
    array('Neto Total Pagado', number_format($analytics_summary['neto_total_pagado_usd'], 2), number_format($analytics_summary['neto_total_pagado_bs'], 2))
);

$fill = false;
foreach($data_rows as $row) {
    $pdf->Cell($w[0], 7, $row[0], 'LR', 0, 'L', $fill);
    $pdf->Cell($w[1], 7, $row[1], 'LR', 0, 'R', $fill);
    $pdf->Cell($w[2], 7, $row[2], 'LR', 0, 'R', $fill);
    $pdf->Ln();
    $fill = !$fill;
}

// Línea final
$pdf->Cell(array_sum($w), 0, '', 'T');
$pdf->Ln(10);

// Estadísticas adicionales
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(0, 8, 'Estadísticas del Período', 0, 1);
$pdf->Ln(2);

$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(0, 6, 'Empleados activos en el período: ' . htmlspecialchars($analytics_summary['total_empleados_activos_periodo']), 0, 1);
$pdf->Cell(0, 6, 'Períodos de nómina calculados: ' . htmlspecialchars($analytics_summary['total_periodos_calculados']), 0, 1);

// Salida del PDF
$filename = 'reporte_analitico_' . date('Y-m-d_H-i-s') . '.pdf';
$pdf->Output($filename, 'I');
?>