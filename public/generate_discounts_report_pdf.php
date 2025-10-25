<?php
// public/generate_discounts_report_pdf.php
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
    'total_deducciones_usd' => 0,
    'total_deducciones_bs' => 0,
];

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

    // Calcular el resumen total
    foreach ($report_data as $row) {
        $report_summary['total_deducciones_usd'] += $row['total_amount_usd'];
        $report_summary['total_deducciones_bs'] += $row['total_amount_bs'];
    }

} catch (PDOException $e) {
    die('Error al generar el reporte de descuentos: ' . htmlspecialchars($e->getMessage()));
}

// Crear PDF
$pdf = new MyTCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Configurar documento
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Sistema de Nómina VALFOR S.A.');
$pdf->SetTitle('Reporte de Descuentos');
$pdf->SetSubject('Análisis de Descuentos de Nómina');
$pdf->SetKeywords('descuentos, nómina, deducciones, VALFOR');

// Establecer el título del reporte para el encabezado personalizado
$pdf->setReportTitle('Reporte de Descuentos ');

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

// Tabla de descuentos
if (!empty($report_data)) {
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetTextColorArray(COLOR_PRIMARY);
    $pdf->Cell(0, 10, 'Resumen de Descuentos por Período y Concepto', 0, 1);
    $pdf->Ln(5);

    $pdf->SetFillColorArray(COLOR_PRIMARY);
    $pdf->SetTextColorArray(COLOR_TEXT_LIGHT);
    $pdf->SetFont('helvetica', 'B', 9);

    $header = array('Período', 'Concepto', 'Tipo', 'Total ($)', 'Total (Bs)');
    $w = array(35, 50, 30, 30, 30);

    for($i = 0; $i < count($header); $i++) {
        $pdf->Cell($w[$i], 8, $header[$i], 1, 0, 'C', 1);
    }
    $pdf->Ln();

    // Datos de descuentos
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetTextColorArray(COLOR_TEXT_DARK);

    $fill = false;
    foreach ($report_data as $row) {
        $pdf->SetFillColorArray($fill ? COLOR_SECONDARY : array(255, 255, 255));
        $period_text = htmlspecialchars($row['start_date'] . ' al ' . $row['end_date']);
        $concept_name = htmlspecialchars($row['concept_name']);
        $concept_type = ucfirst(str_replace('_', ' ', $row['concept_type']));

        $pdf->Cell($w[0], 7, $period_text, 'LBR', 0, 'L', $fill);
        $pdf->Cell($w[1], 7, $concept_name, 'BR', 0, 'L', $fill);
        $pdf->Cell($w[2], 7, $concept_type, 'BR', 0, 'C', $fill);
        $pdf->Cell($w[3], 7, number_format($row['total_amount_usd'], 2), 'BR', 0, 'R', $fill);
        $pdf->Cell($w[4], 7, number_format($row['total_amount_bs'], 2), 'BR', 0, 'R', $fill);
        $pdf->Ln();
        $fill = !$fill;
    }

    $pdf->Ln(10);

    // Resumen total
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetTextColorArray(COLOR_PRIMARY);
    $pdf->Cell(0, 12, 'Totales Acumulados de Descuentos', 0, 1, 'C');
    $pdf->Ln(5);

    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetTextColorArray(COLOR_TEXT_LIGHT);
    $pdf->SetFillColorArray(COLOR_PRIMARY);
    $pdf->Cell(90, 10, 'Total Deducciones:', 'LBR', 0, 'R', 1);
    $pdf->Cell(45, 10, '$' . number_format($report_summary['total_deducciones_usd'], 2), 'BR', 0, 'R', 1);
    $pdf->Cell(45, 10, 'Bs ' . number_format($report_summary['total_deducciones_bs'], 2), 'BR', 1, 'R', 1);

} else {
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetTextColorArray(COLOR_TEXT_DARK);
    $pdf->Cell(0, 10, 'No se encontraron datos de descuentos en el período especificado.', 0, 1, 'C');
}

// Salida del PDF
$filename = 'reporte_descuentos_' . date('Y-m-d_H-i-s') . '.pdf';
$pdf->Output($filename, 'I');
?>
