<?php
// public/generate_paid_report_pdf.php
require_once __DIR__ . '/../includes/auth.php'; // Incluir el archivo de autenticación
require_once __DIR__ . '/../config/settings.php'; // Para getDbConnection() y roles

// Requerir que el usuario esté logueado y tenga rol de 'admin' o 'assistant'
requireRole([ROLE_ADMIN, ROLE_ASSISTANT]);

require_once __DIR__ . '/../includes/MyTCPDF.php'; // Para MyTCPDF
require_once __DIR__ . '/../includes/pdf_styles.php'; // Para estilos de PDF

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
$pdf = new MyTCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Configurar documento
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Sistema de Nómina VALFOR S.A.');
$pdf->SetTitle('Reporte de Pagos de Nómina');
$pdf->SetSubject('Análisis de Nóminas Pagadas');
$pdf->SetKeywords('pagos, nómina, pagos realizados, VALFOR');

// Establecer el título del reporte para el encabezado personalizado
$pdf->setReportTitle('Reporte de Pagos de Nómina ');

// Configurar márgenes (el encabezado y pie de página personalizados ya manejan sus propios márgenes)
$pdf->SetMargins(15, 30, 15); // Ajustar el margen superior para dejar espacio al encabezado
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

// Configurar auto page breaks
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

// Configurar fuente
$pdf->SetFont('helvetica', '', 10);

// Añadir página
$pdf->AddPage();

// Información del período
$pdf->SetFont('helvetica', '', 10);
$pdf->SetTextColorArray(COLOR_TEXT_DARK);
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
$pdf->Cell(0, 10, 'Fecha de generación: ' . date('d/m/Y H:i'), 0, 1);
$pdf->Ln(8);

// Tabla de pagos
if (!empty($report_data)) {
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetTextColorArray(COLOR_PRIMARY);
    $pdf->Cell(0, 10, 'Nóminas Pagadas por Período', 0, 1);
    $pdf->Ln(5);

    $pdf->SetFillColorArray(COLOR_PRIMARY);
    $pdf->SetTextColorArray(COLOR_TEXT_LIGHT);
    $pdf->SetFont('helvetica', 'B', 8);

    $header = array('Período', 'Tasa BCV', 'Ingresos ($)', 'Beneficios ($)', 'Deducciones ($)', 'Neto ($)', 'Neto (Bs)');
    $w = array(30, 20, 25, 25, 25, 25, 25);

    for($i = 0; $i < count($header); $i++) {
        $pdf->Cell($w[$i], 8, $header[$i], 1, 0, 'C', 1);
    }
    $pdf->Ln();

    // Datos de pagos
    $pdf->SetFont('helvetica', '', 7);
    $pdf->SetTextColorArray(COLOR_TEXT_DARK);

    $fill = false;
    foreach ($report_data as $row) {
        $pdf->SetFillColorArray($fill ? COLOR_SECONDARY : array(255, 255, 255));
        $period_text = htmlspecialchars($row['start_date'] . ' al ' . $row['end_date']);

        $pdf->Cell($w[0], 7, $period_text, 'LBR', 0, 'L', $fill);
        $pdf->Cell($w[1], 7, number_format($row['bcv_rate'], 4), 'BR', 0, 'C', $fill);
        $pdf->Cell($w[2], 7, number_format($row['total_ingresos_usd'], 2), 'BR', 0, 'R', $fill);
        $pdf->Cell($w[3], 7, number_format($row['total_beneficios_usd'], 2), 'BR', 0, 'R', $fill);
        $pdf->Cell($w[4], 7, number_format($row['total_deducciones_usd'], 2), 'BR', 0, 'R', $fill);
        $pdf->Cell($w[5], 7, number_format($row['neto_pagado_usd'], 2), 'BR', 0, 'R', $fill);
        $pdf->Cell($w[6], 7, number_format($row['neto_pagado_bs'], 2), 'BR', 0, 'R', $fill);
        $pdf->Ln();
        $fill = !$fill;
    }

    $pdf->Ln(10);

    // Resumen total
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetTextColorArray(COLOR_PRIMARY);
    $pdf->Cell(0, 12, 'Resumen Total de Nóminas Pagadas', 0, 1, 'C');
    $pdf->Ln(5);

    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetTextColorArray(COLOR_TEXT_LIGHT);
    $pdf->SetFillColorArray(COLOR_PRIMARY);
    $pdf->Cell(90, 10, 'Total Neto Pagado:', 'LBR', 0, 'R', 1);
    $pdf->Cell(45, 10, '$' . number_format($report_summary['total_neto_pagado_usd'], 2), 'BR', 0, 'R', 1);
    $pdf->Cell(45, 10, 'Bs ' . number_format($report_summary['total_neto_pagado_bs'], 2), 'BR', 1, 'R', 1);

    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetTextColorArray(COLOR_TEXT_DARK);
    $pdf->SetFillColorArray(COLOR_SECONDARY);
    $pdf->Cell(90, 8, 'Total Períodos Pagados:', 'LBR', 0, 'R', 1);
    $pdf->Cell(90, 8, $report_summary['total_periodos_pagados'], 'BR', 1, 'R', 1);

} else {
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetTextColorArray(COLOR_TEXT_DARK);
    $pdf->Cell(0, 10, 'No se encontraron nóminas pagadas en el período especificado.', 0, 1, 'C');
}

// Salida del PDF
$filename = 'reporte_pagos_' . date('Y-m-d_H-i-s') . '.pdf';
$pdf->Output($filename, 'I');
?>
