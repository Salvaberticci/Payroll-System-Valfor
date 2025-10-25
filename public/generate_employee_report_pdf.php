<?php
// public/generate_employee_report_pdf.php
require_once __DIR__ . '/../includes/auth.php'; // Incluir el archivo de autenticación
require_once __DIR__ . '/../config/settings.php'; // Para getDbConnection() y roles

// Requerir que el usuario esté logueado y tenga rol de 'admin' o 'assistant'
requireRole([ROLE_ADMIN, ROLE_ASSISTANT]);

require_once __DIR__ . '/../includes/MyTCPDF.php'; // Para MyTCPDF
require_once __DIR__ . '/../includes/pdf_styles.php'; // Para estilos de PDF

$employee_id_selected = $_POST['employee_id'] ?? '';
$start_date_filter = $_POST['start_date_filter'] ?? '';
$end_date_filter = $_POST['end_date_filter'] ?? '';

$pdo = getDbConnection();

// Obtener información del empleado
$employee_info = null;
if (!empty($employee_id_selected)) {
    try {
        $stmt = $pdo->prepare("SELECT full_name, cedula FROM employees WHERE id = :id");
        $stmt->bindParam(':id', $employee_id_selected, PDO::PARAM_INT);
        $stmt->execute();
        $employee_info = $stmt->fetch();
    } catch (PDOException $e) {
        die('Error al obtener información del empleado: ' . htmlspecialchars($e->getMessage()));
    }
}

$report_data = [];
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

    // Organizar los datos por período de nómina
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
    unset($data);

} catch (PDOException $e) {
    die('Error al generar el reporte: ' . htmlspecialchars($e->getMessage()));
}

// Crear PDF
$pdf = new MyTCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Configurar documento
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Sistema de Nómina VALFOR S.A.');
$pdf->SetTitle('Reporte por Empleado - ' . ($employee_info ? $employee_info['full_name'] : ''));
$pdf->SetSubject('Reporte Individual de Nómina');
$pdf->SetKeywords('empleado, nómina, reporte, VALFOR');

// Establecer el título del reporte para el encabezado personalizado
$pdf->setReportTitle('Reporte por Empleado ');

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

// Información del empleado
if ($employee_info) {
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetTextColorArray(COLOR_TEXT_DARK);
    $pdf->Cell(0, 8, 'Empleado: ' . htmlspecialchars($employee_info['full_name']), 0, 1);
    $pdf->Cell(0, 8, 'Cédula: ' . htmlspecialchars($employee_info['cedula']), 0, 1);
    $pdf->Ln(4);
}

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

// Procesar cada período
foreach ($report_data as $period_key => $data) {
    // Título del período
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetTextColorArray(COLOR_PRIMARY);
    $pdf->Cell(0, 10, 'Período: ' . htmlspecialchars($period_key), 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetTextColorArray(COLOR_TEXT_DARK);
    $pdf->Cell(0, 6, 'Tasa BCV: ' . number_format($data['bcv_rate'], 4) . ' Bs/$', 0, 1);
    $pdf->Ln(5);

    // Tabla de conceptos
    $pdf->SetFillColorArray(COLOR_PRIMARY);
    $pdf->SetTextColorArray(COLOR_TEXT_LIGHT);
    $pdf->SetFont('helvetica', 'B', 9);

    $header = array('Concepto', 'Tipo', 'Monto ($)', 'Monto (Bs)', 'Días');
    $w = array(60, 30, 25, 25, 15);

    for($i = 0; $i < count($header); $i++) {
        $pdf->Cell($w[$i], 8, $header[$i], 1, 0, 'C', 1); // Borde completo para el encabezado
    }
    $pdf->Ln();

    // Datos de conceptos
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetTextColorArray(COLOR_TEXT_DARK);

    $fill = false;
    foreach ($data['concepts'] as $concept) {
        $pdf->SetFillColorArray($fill ? COLOR_SECONDARY : array(255, 255, 255));
        $pdf->Cell($w[0], 7, htmlspecialchars($concept['concept_name']), 'LBR', 0, 'L', $fill); // Bordes LBR
        $pdf->Cell($w[1], 7, ucfirst(str_replace('_', ' ', $concept['concept_type'])), 'BR', 0, 'C', $fill);
        $pdf->Cell($w[2], 7, number_format($concept['amount_usd'], 2), 'BR', 0, 'R', $fill);
        $pdf->Cell($w[3], 7, number_format($concept['amount_bs'], 2), 'BR', 0, 'R', $fill);
        $pdf->Cell($w[4], 7, (!is_null($concept['days_applied']) ? htmlspecialchars($concept['days_applied']) : 'N/A'), 'BR', 0, 'C', $fill);
        $pdf->Ln();
        $fill = !$fill;
    }

    // Subtotales del período
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->SetTextColorArray(COLOR_TEXT_DARK);

    $pdf->SetFillColorArray(COLOR_SECONDARY);
    $pdf->Cell($w[0] + $w[1], 7, 'Subtotal Ingresos:', 'LBR', 0, 'R', 1);
    $pdf->Cell($w[2], 7, number_format($data['subtotal_ingresos_usd'], 2), 'BR', 0, 'R', 1);
    $pdf->Cell($w[3], 7, number_format($data['subtotal_ingresos_bs'], 2), 'BR', 0, 'R', 1);
    $pdf->Cell($w[4], 7, '', 'BR', 0, 'C', 1);
    $pdf->Ln();

    $pdf->Cell($w[0] + $w[1], 7, 'Subtotal Beneficios:', 'LBR', 0, 'R', 1);
    $pdf->Cell($w[2], 7, number_format($data['subtotal_beneficios_usd'], 2), 'BR', 0, 'R', 1);
    $pdf->Cell($w[3], 7, number_format($data['subtotal_beneficios_bs'], 2), 'BR', 0, 'R', 1);
    $pdf->Cell($w[4], 7, '', 'BR', 0, 'C', 1);
    $pdf->Ln();

    $pdf->Cell($w[0] + $w[1], 7, 'Subtotal Deducciones:', 'LBR', 0, 'R', 1);
    $pdf->Cell($w[2], 7, number_format($data['subtotal_deducciones_usd'], 2), 'BR', 0, 'R', 1);
    $pdf->Cell($w[3], 7, number_format($data['subtotal_deducciones_bs'], 2), 'BR', 0, 'R', 1);
    $pdf->Cell($w[4], 7, '', 'BR', 0, 'C', 1);
    $pdf->Ln();

    $pdf->SetFillColorArray(COLOR_PRIMARY); // Color más oscuro para el neto
    $pdf->SetTextColorArray(COLOR_TEXT_LIGHT);
    $pdf->Cell($w[0] + $w[1], 7, 'NETO A PAGAR:', 'LBR', 0, 'R', 1);
    $pdf->Cell($w[2], 7, number_format($data['neto_periodo_usd'], 2), 'BR', 0, 'R', 1);
    $pdf->Cell($w[3], 7, number_format($data['neto_periodo_bs'], 2), 'BR', 0, 'R', 1);
    $pdf->Cell($w[4], 7, '', 'BR', 0, 'C', 1);
    $pdf->Ln();

    $pdf->Ln(10);

    // Verificar si necesitamos una nueva página (la clase MyTCPDF maneja el encabezado automáticamente)
    if ($pdf->GetY() > 250) {
        $pdf->AddPage();
    }
}

// Resumen total
if (!empty($report_data)) {
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->SetTextColorArray(COLOR_PRIMARY);
    $pdf->Cell(0, 12, 'Resumen Total del Reporte', 0, 1, 'C');
    $pdf->Ln(5);

    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetTextColorArray(COLOR_TEXT_DARK);
    $pdf->SetFillColorArray(COLOR_SECONDARY);

    $pdf->Cell(90, 8, 'Total Ingresos Acumulados:', 'LBR', 0, 'R', 1);
    $pdf->Cell(45, 8, '$' . number_format($report_summary['total_ingresos_usd'], 2), 'BR', 0, 'R', 1);
    $pdf->Cell(45, 8, 'Bs ' . number_format($report_summary['total_ingresos_bs'], 2), 'BR', 1, 'R', 1);

    $pdf->Cell(90, 8, 'Total Beneficios Acumulados:', 'LBR', 0, 'R', 0);
    $pdf->Cell(45, 8, '$' . number_format($report_summary['total_beneficios_usd'], 2), 'BR', 0, 'R', 0);
    $pdf->Cell(45, 8, 'Bs ' . number_format($report_summary['total_beneficios_bs'], 2), 'BR', 1, 'R', 0);

    $pdf->Cell(90, 8, 'Total Deducciones Acumuladas:', 'LBR', 0, 'R', 1);
    $pdf->Cell(45, 8, '$' . number_format($report_summary['total_deducciones_usd'], 2), 'BR', 0, 'R', 1);
    $pdf->Cell(45, 8, 'Bs ' . number_format($report_summary['total_deducciones_bs'], 2), 'BR', 1, 'R', 1);

    $pdf->SetFillColorArray(COLOR_PRIMARY);
    $pdf->SetTextColorArray(COLOR_TEXT_LIGHT);
    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->Cell(90, 10, 'NETO TOTAL PAGADO:', 'LBR', 0, 'R', 1);
    $pdf->Cell(45, 10, '$' . number_format($report_summary['neto_total_pagado_usd'], 2), 'BR', 0, 'R', 1);
    $pdf->Cell(45, 10, 'Bs ' . number_format($report_summary['neto_total_pagado_bs'], 2), 'BR', 1, 'R', 1);
}

// Salida del PDF
$filename = 'reporte_empleado_' . ($employee_info ? preg_replace('/[^a-zA-Z0-9]/', '_', $employee_info['full_name']) : 'desconocido') . '_' . date('Y-m-d_H-i-s') . '.pdf';
$pdf->Output($filename, 'I');
?>
