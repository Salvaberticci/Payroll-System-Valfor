<?php
// public/generate_payroll_details_pdf.php
require_once __DIR__ . '/../includes/auth.php'; // Incluir el archivo de autenticación
require_once __DIR__ . '/../config/settings.php'; // Para getDbConnection() y roles

// Requerir que el usuario esté logueado y tenga rol de 'admin' o 'assistant'
requireRole([ROLE_ADMIN, ROLE_ASSISTANT]);

require_once __DIR__ . '/../includes/MyTCPDF.php'; // Para MyTCPDF
require_once __DIR__ . '/../includes/pdf_styles.php'; // Para estilos de PDF

$period_id = $_GET['period_id'] ?? null;

if (!$period_id || !is_numeric($period_id)) {
    die('ID de período inválido.');
}

try {
    $pdo = getDbConnection();

    // Obtener información del período
    $stmt_period = $pdo->prepare("SELECT id, start_date, end_date, bcv_rate, days_in_period, status FROM payroll_periods WHERE id = :id");
    $stmt_period->bindParam(':id', $period_id, PDO::PARAM_INT);
    $stmt_period->execute();
    $payroll_period = $stmt_period->fetch();

    if (!$payroll_period) {
        die('Período de nómina no encontrado.');
    }

    // Obtener detalles de nómina
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

    // Organizar datos por empleado
    $payroll_details = [];
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

    foreach ($payroll_details as $name => &$data) {
        $data['neto_empleado_usd'] = $data['subtotal_ingresos_usd'] + $data['subtotal_beneficios_usd'] - $data['subtotal_deducciones_usd'];
        $data['neto_empleado_bs'] = $data['subtotal_ingresos_bs'] + $data['subtotal_beneficios_bs'] - $data['subtotal_deducciones_bs'];
        $total_summary['neto_a_pagar_usd'] += $data['neto_empleado_usd'];
        $total_summary['neto_a_pagar_bs'] += $data['neto_empleado_bs'];
    }
    unset($data);

} catch (PDOException $e) {
    die('Error al cargar los datos: ' . htmlspecialchars($e->getMessage()));
}

// Crear PDF
$pdf = new MyTCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Configurar documento
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Sistema de Nómina VALFOR S.A.');
$pdf->SetTitle('Detalles de Nómina - Período ' . $payroll_period['start_date'] . ' al ' . $payroll_period['end_date']);
$pdf->SetSubject('Detalles de Nómina');
$pdf->SetKeywords('nómina, detalles, VALFOR');

// Establecer el título del reporte para el encabezado personalizado
$pdf->setReportTitle('Detalles de Nómina ');

// Configurar márgenes (el encabezado y pie de página personalizados ya manejan sus propios márgenes)
$pdf->SetMargins(15, 30, 15); // Ajustar el margen superior para dejar espacio al encabezado
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

// Configurar auto page breaks
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

// Configurar fuente
$pdf->SetFont('helvetica', '', 9);

// Añadir página
$pdf->AddPage();

// Información del período
$pdf->SetFont('helvetica', '', 10);
$pdf->SetTextColorArray(COLOR_TEXT_DARK);
$pdf->Cell(0, 10, 'Período: ' . htmlspecialchars($payroll_period['start_date']) . ' al ' . htmlspecialchars($payroll_period['end_date']), 0, 1);
$pdf->Cell(0, 6, 'Tasa BCV: ' . number_format($payroll_period['bcv_rate'], 2) . ' Bs/$', 0, 1);
$pdf->Cell(0, 6, 'Días en el período: ' . htmlspecialchars($payroll_period['days_in_period']), 0, 1);
$pdf->Cell(0, 6, 'Estado: ' . htmlspecialchars(ucfirst($payroll_period['status'])), 0, 1);
$pdf->Cell(0, 10, 'Fecha de generación: ' . date('d/m/Y H:i'), 0, 1);
$pdf->Ln(8);

// Detalles por empleado
foreach ($payroll_details as $employee_name => $data) {
    // Verificar si necesitamos una nueva página (la clase MyTCPDF maneja el encabezado automáticamente)
    if ($pdf->GetY() > 250) {
        $pdf->AddPage();
    }

    $pdf->SetFont('helvetica', 'B', 11);
    $pdf->SetTextColorArray(COLOR_PRIMARY);
    $pdf->Cell(0, 8, 'Empleado: ' . htmlspecialchars($employee_name) . ' (C.I.: ' . htmlspecialchars($data['cedula']) . ')', 0, 1);
    $pdf->Ln(4);

    // Cabecera de tabla
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetFillColorArray(COLOR_PRIMARY);
    $pdf->SetTextColorArray(COLOR_TEXT_LIGHT);

    $header = array('Concepto', 'Tipo', 'Monto ($)', 'Monto (Bs)', 'Días');
    $w = array(50, 30, 25, 25, 20);

    for($i = 0; $i < count($header); $i++) {
        $pdf->Cell($w[$i], 7, $header[$i], 1, 0, 'C', 1);
    }
    $pdf->Ln();

    // Datos de conceptos
    $pdf->SetFont('helvetica', '', 7);
    $pdf->SetTextColorArray(COLOR_TEXT_DARK);

    $fill = false;
    foreach ($data['concepts'] as $concept) {
        $pdf->SetFillColorArray($fill ? COLOR_SECONDARY : array(255, 255, 255));
        $pdf->Cell($w[0], 6, htmlspecialchars($concept['concept_name']), 'LBR', 0, 'L', $fill);
        $pdf->Cell($w[1], 6, htmlspecialchars(ucfirst(str_replace('_', ' ', $concept['concept_type']))), 'BR', 0, 'C', $fill);
        $pdf->Cell($w[2], 6, number_format($concept['amount_usd'], 2), 'BR', 0, 'R', $fill);
        $pdf->Cell($w[3], 6, number_format($concept['amount_bs'], 2), 'BR', 0, 'R', $fill);
        $pdf->Cell($w[4], 6, !is_null($concept['days_applied']) ? htmlspecialchars($concept['days_applied']) : 'N/A', 'BR', 0, 'C', $fill);
        $pdf->Ln();
        $fill = !$fill;
    }

    // Subtotales
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetTextColorArray(COLOR_TEXT_DARK);

    $pdf->SetFillColorArray(COLOR_SECONDARY);
    $pdf->Cell($w[0] + $w[1], 6, 'Subtotal Ingresos:', 'LBR', 0, 'R', 1);
    $pdf->Cell($w[2], 6, number_format($data['subtotal_ingresos_usd'], 2), 'BR', 0, 'R', 1);
    $pdf->Cell($w[3], 6, number_format($data['subtotal_ingresos_bs'], 2), 'BR', 0, 'R', 1);
    $pdf->Cell($w[4], 6, '', 'BR', 0, 'C', 1);
    $pdf->Ln();

    $pdf->Cell($w[0] + $w[1], 6, 'Subtotal Beneficios:', 'LBR', 0, 'R', 1);
    $pdf->Cell($w[2], 6, number_format($data['subtotal_beneficios_usd'], 2), 'BR', 0, 'R', 1);
    $pdf->Cell($w[3], 6, number_format($data['subtotal_beneficios_bs'], 2), 'BR', 0, 'R', 1);
    $pdf->Cell($w[4], 6, '', 'BR', 0, 'C', 1);
    $pdf->Ln();

    $pdf->Cell($w[0] + $w[1], 6, 'Subtotal Deducciones:', 'LBR', 0, 'R', 1);
    $pdf->Cell($w[2], 6, number_format($data['subtotal_deducciones_usd'], 2), 'BR', 0, 'R', 1);
    $pdf->Cell($w[3], 6, number_format($data['subtotal_deducciones_bs'], 2), 'BR', 0, 'R', 1);
    $pdf->Cell($w[4], 6, '', 'BR', 0, 'C', 1);
    $pdf->Ln();

    $pdf->SetFillColorArray(COLOR_PRIMARY);
    $pdf->SetTextColorArray(COLOR_TEXT_LIGHT);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell($w[0] + $w[1], 7, 'NETO A PAGAR:', 'LBR', 0, 'R', 1);
    $pdf->Cell($w[2], 7, number_format($data['neto_empleado_usd'], 2), 'BR', 0, 'R', 1);
    $pdf->Cell($w[3], 7, number_format($data['neto_empleado_bs'], 2), 'BR', 0, 'R', 1);
    $pdf->Cell($w[4], 7, '', 'BR', 0, 'C', 1);
    $pdf->Ln();

    $pdf->Ln(8);
}

// Resumen total
if ($pdf->GetY() > 250) {
    $pdf->AddPage();
}

$pdf->SetFont('helvetica', 'B', 14);
$pdf->SetTextColorArray(COLOR_PRIMARY);
$pdf->Cell(0, 12, 'Resumen Total de la Nómina', 0, 1, 'C');
$pdf->Ln(5);

$pdf->SetFont('helvetica', '', 10);
$pdf->SetTextColorArray(COLOR_TEXT_DARK);
$pdf->SetFillColorArray(COLOR_SECONDARY);

$pdf->Cell(90, 8, 'Total Ingresos:', 'LBR', 0, 'R', 1);
$pdf->Cell(45, 8, '$' . number_format($total_summary['total_ingresos_usd'], 2), 'BR', 0, 'R', 1);
$pdf->Cell(45, 8, 'Bs ' . number_format($total_summary['total_ingresos_bs'], 2), 'BR', 1, 'R', 1);

$pdf->Cell(90, 8, 'Total Beneficios:', 'LBR', 0, 'R', 0);
$pdf->Cell(45, 8, '$' . number_format($total_summary['total_beneficios_usd'], 2), 'BR', 0, 'R', 0);
$pdf->Cell(45, 8, 'Bs ' . number_format($total_summary['total_beneficios_bs'], 2), 'BR', 1, 'R', 0);

$pdf->Cell(90, 8, 'Total Deducciones:', 'LBR', 0, 'R', 1);
$pdf->Cell(45, 8, '$' . number_format($total_summary['total_deducciones_usd'], 2), 'BR', 0, 'R', 1);
$pdf->Cell(45, 8, 'Bs ' . number_format($total_summary['total_deducciones_bs'], 2), 'BR', 1, 'R', 1);

$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetTextColorArray(COLOR_TEXT_LIGHT);
$pdf->SetFillColorArray(COLOR_PRIMARY);
$pdf->Cell(90, 10, 'NETO TOTAL A PAGAR:', 'LBR', 0, 'R', 1);
$pdf->Cell(45, 10, '$' . number_format($total_summary['neto_a_pagar_usd'], 2), 'BR', 0, 'R', 1);
$pdf->Cell(45, 10, 'Bs ' . number_format($total_summary['neto_a_pagar_bs'], 2), 'BR', 1, 'R', 1);

// Salida del PDF
$filename = 'detalles_nomina_' . $payroll_period['start_date'] . '_al_' . $payroll_period['end_date'] . '_' . date('Y-m-d_H-i-s') . '.pdf';
$pdf->Output($filename, 'I');
?>
