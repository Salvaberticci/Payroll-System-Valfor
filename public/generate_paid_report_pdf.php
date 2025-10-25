<?php
// public/generate_paid_report_pdf.php
require_once __DIR__ . '/../includes/auth.php'; // Incluir el archivo de autenticación
require_once __DIR__ . '/../config/settings.php'; // Para getDbConnection() y roles

// Requerir que el usuario esté logueado y tenga rol de 'admin' o 'assistant'
requireRole([ROLE_ADMIN, ROLE_ASSISTANT]);

require_once __DIR__ . '/../vendor/autoload.php'; // Para TCPDF

$start_date_filter = $_POST['start_date_filter'] ?? '';
$end_date_filter = $_POST['end_date_filter'] ?? '';

$pdo = getDbConnection();

$report_data = [];
$report_summary = [
    'total_neto_pagado_usd' => 0,
    'total_neto_pagado_bs' => 0,
    'total_periodos_pagados' => 0,
];

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

    // Calcular el neto a pagar por cada período y el resumen total
    foreach ($report_data as &$row) {
        $row['neto_pagado_usd'] = $row['total_ingresos_usd'] + $row['total_beneficios_usd'] - $row['total_deducciones_usd'];
        $row['neto_pagado_bs'] = $row['total_ingresos_bs'] + $row['total_beneficios_bs'] - $row['total_deducciones_bs'];

        $report_summary['total_neto_pagado_usd'] += $row['neto_pagado_usd'];
        $report_summary['total_neto_pagado_bs'] += $row['neto_pagado_bs'];
        $report_summary['total_periodos_pagados']++;
    }
    unset($row);

} catch (PDOException $e) {
    die('Error al generar el reporte de pagos: ' . htmlspecialchars($e->getMessage()));
}

// Crear PDF
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Configurar documento
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Sistema de Nómina VALFOR S.A.');
$pdf->SetTitle('Reporte de Pagos de Nómina');
$pdf->SetSubject('Análisis de Nóminas Pagadas');
$pdf->SetKeywords('pagos, nómina, pagos realizados, VALFOR');

// Configurar márgenes
$pdf->SetMargins(15, 25, 15);
$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(10);

// Configurar auto page breaks
$pdf->SetAutoPageBreak(TRUE, 15);

// Configurar fuente
$pdf->SetFont('helvetica', '', 10);

// Añadir página
$pdf->AddPage();

// Logo en la parte superior izquierda
$logo_path = __DIR__ . '/assets/img/logo.png';
if (file_exists($logo_path)) {
    $pdf->Image($logo_path, 15, 10, 30, 15, 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
}

// Título
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, 'Reporte de Pagos de Nómina - VALFOR S.A.', 0, 1, 'C');
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

// Tabla de pagos
if (!empty($report_data)) {
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 10, 'Nóminas Pagadas por Período', 0, 1);
    $pdf->Ln(3);

    $pdf->SetFont('helvetica', 'B', 7);
    $pdf->SetFillColor(240, 240, 240);

    $header = array('Período', 'Tasa BCV', 'Ingresos ($)', 'Beneficios ($)', 'Deducciones ($)', 'Neto ($)', 'Neto (Bs)');
    $w = array(30, 20, 25, 25, 25, 25, 25);

    for($i = 0; $i < count($header); $i++) {
        $pdf->Cell($w[$i], 7, $header[$i], 1, 0, 'C', 1);
    }
    $pdf->Ln();

    // Datos de pagos
    $pdf->SetFont('helvetica', '', 6);
    $pdf->SetFillColor(255, 255, 255);

    $fill = false;
    foreach ($report_data as $row) {
        $period_text = htmlspecialchars($row['start_date'] . ' al ' . $row['end_date']);

        $pdf->Cell($w[0], 6, $period_text, 'LR', 0, 'L', $fill);
        $pdf->Cell($w[1], 6, number_format($row['bcv_rate'], 4), 'LR', 0, 'C', $fill);
        $pdf->Cell($w[2], 6, number_format($row['total_ingresos_usd'], 2), 'LR', 0, 'R', $fill);
        $pdf->Cell($w[3], 6, number_format($row['total_beneficios_usd'], 2), 'LR', 0, 'R', $fill);
        $pdf->Cell($w[4], 6, number_format($row['total_deducciones_usd'], 2), 'LR', 0, 'R', $fill);
        $pdf->Cell($w[5], 6, number_format($row['neto_pagado_usd'], 2), 'LR', 0, 'R', $fill);
        $pdf->Cell($w[6], 6, number_format($row['neto_pagado_bs'], 2), 'LR', 0, 'R', $fill);
        $pdf->Ln();
        $fill = !$fill;
    }

    // Línea final
    $pdf->Cell(array_sum($w), 0, '', 'T');
    $pdf->Ln(10);

    // Resumen total
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Resumen Total de Nóminas Pagadas', 0, 1, 'C');
    $pdf->Ln(3);

    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, 'Total Neto Pagado: $' . number_format($report_summary['total_neto_pagado_usd'], 2) . ' (Bs ' . number_format($report_summary['total_neto_pagado_bs'], 2) . ')', 0, 1);
    $pdf->Cell(0, 6, 'Total Períodos Pagados: ' . $report_summary['total_periodos_pagados'], 0, 1);
} else {
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 10, 'No se encontraron nóminas pagadas en el período especificado.', 0, 1, 'C');
}

// Salida del PDF
$filename = 'reporte_pagos_' . date('Y-m-d_H-i-s') . '.pdf';
$pdf->Output($filename, 'I');
?>