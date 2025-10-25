<?php
// public/generate_discounts_report_pdf.php
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
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Configurar documento
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Sistema de Nómina VALFOR S.A.');
$pdf->SetTitle('Reporte de Descuentos');
$pdf->SetSubject('Análisis de Descuentos de Nómina');
$pdf->SetKeywords('descuentos, nómina, deducciones, VALFOR');

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
$pdf->Cell(0, 10, 'Reporte de Descuentos - VALFOR S.A.', 0, 1, 'C');
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

// Tabla de descuentos
if (!empty($report_data)) {
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 10, 'Resumen de Descuentos por Período y Concepto', 0, 1);
    $pdf->Ln(3);

    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetFillColor(240, 240, 240);

    $header = array('Período', 'Concepto', 'Tipo', 'Total ($)', 'Total (Bs)');
    $w = array(35, 50, 30, 30, 30);

    for($i = 0; $i < count($header); $i++) {
        $pdf->Cell($w[$i], 7, $header[$i], 1, 0, 'C', 1);
    }
    $pdf->Ln();

    // Datos de descuentos
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetFillColor(255, 255, 255);

    $fill = false;
    foreach ($report_data as $row) {
        $period_text = htmlspecialchars($row['start_date'] . ' al ' . $row['end_date']);
        $concept_name = htmlspecialchars($row['concept_name']);
        $concept_type = ucfirst(str_replace('_', ' ', $row['concept_type']));

        $pdf->Cell($w[0], 6, $period_text, 'LR', 0, 'L', $fill);
        $pdf->Cell($w[1], 6, $concept_name, 'LR', 0, 'L', $fill);
        $pdf->Cell($w[2], 6, $concept_type, 'LR', 0, 'C', $fill);
        $pdf->Cell($w[3], 6, number_format($row['total_amount_usd'], 2), 'LR', 0, 'R', $fill);
        $pdf->Cell($w[4], 6, number_format($row['total_amount_bs'], 2), 'LR', 0, 'R', $fill);
        $pdf->Ln();
        $fill = !$fill;
    }

    // Línea final
    $pdf->Cell(array_sum($w), 0, '', 'T');
    $pdf->Ln(10);

    // Resumen total
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Totales Acumulados de Descuentos', 0, 1, 'C');
    $pdf->Ln(3);

    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 6, 'Total Deducciones: $' . number_format($report_summary['total_deducciones_usd'], 2) . ' (Bs ' . number_format($report_summary['total_deducciones_bs'], 2) . ')', 0, 1);
} else {
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 10, 'No se encontraron datos de descuentos en el período especificado.', 0, 1, 'C');
}

// Salida del PDF
$filename = 'reporte_descuentos_' . date('Y-m-d_H-i-s') . '.pdf';
$pdf->Output($filename, 'I');
?>