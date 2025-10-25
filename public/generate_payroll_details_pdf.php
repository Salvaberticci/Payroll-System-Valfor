<?php
// public/generate_payroll_details_pdf.php
require_once __DIR__ . '/../includes/auth.php'; // Incluir el archivo de autenticación
require_once __DIR__ . '/../config/settings.php'; // Para getDbConnection() y roles

// Requerir que el usuario esté logueado y tenga rol de 'admin' o 'assistant'
requireRole([ROLE_ADMIN, ROLE_ASSISTANT]);

require_once __DIR__ . '/../vendor/autoload.php'; // Para TCPDF

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
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Configurar documento
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Sistema de Nómina VALFOR S.A.');
$pdf->SetTitle('Detalles de Nómina - Período ' . $payroll_period['start_date'] . ' al ' . $payroll_period['end_date']);
$pdf->SetSubject('Detalles de Nómina');
$pdf->SetKeywords('nómina, detalles, VALFOR');

// Configurar márgenes
$pdf->SetMargins(15, 20, 15);
$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(10);

// Configurar auto page breaks
$pdf->SetAutoPageBreak(TRUE, 15);

// Configurar fuente
$pdf->SetFont('helvetica', '', 9);

// Añadir página
$pdf->AddPage();

// Logo en la parte superior izquierda
$logo_path = __DIR__ . '/assets/img/logo.png';
if (file_exists($logo_path)) {
    $pdf->Image($logo_path, 15, 8, 35, 20, 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
}

// Título
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'Detalles de Nómina - VALFOR S.A.', 0, 1, 'C');
$pdf->Ln(5);

// Información del período
$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(0, 6, 'Período: ' . htmlspecialchars($payroll_period['start_date']) . ' al ' . htmlspecialchars($payroll_period['end_date']), 0, 1);
$pdf->Cell(0, 6, 'Tasa BCV: ' . number_format($payroll_period['bcv_rate'], 2) . ' Bs/$', 0, 1);
$pdf->Cell(0, 6, 'Días en el período: ' . htmlspecialchars($payroll_period['days_in_period']), 0, 1);
$pdf->Cell(0, 6, 'Estado: ' . htmlspecialchars(ucfirst($payroll_period['status'])), 0, 1);
$pdf->Cell(0, 6, 'Fecha de generación: ' . date('d/m/Y H:i'), 0, 1);
$pdf->Ln(5);

// Detalles por empleado
foreach ($payroll_details as $employee_name => $data) {
    // Verificar si necesitamos una nueva página
    if ($pdf->GetY() > 200) {
        $pdf->AddPage();
    }

    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 8, 'Empleado: ' . htmlspecialchars($employee_name) . ' (C.I.: ' . htmlspecialchars($data['cedula']) . ')', 0, 1);
    $pdf->Ln(2);

    // Cabecera de tabla
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetFillColor(240, 240, 240);

    $header = array('Concepto', 'Tipo', 'Monto ($)', 'Monto (Bs)', 'Días');
    $w = array(50, 30, 25, 25, 20);

    for($i = 0; $i < count($header); $i++) {
        $pdf->Cell($w[$i], 6, $header[$i], 1, 0, 'C', 1);
    }
    $pdf->Ln();

    // Datos de conceptos
    $pdf->SetFont('helvetica', '', 7);
    $pdf->SetFillColor(255, 255, 255);

    $fill = false;
    foreach ($data['concepts'] as $concept) {
        $pdf->Cell($w[0], 5, htmlspecialchars($concept['concept_name']), 'LR', 0, 'L', $fill);
        $pdf->Cell($w[1], 5, htmlspecialchars(ucfirst(str_replace('_', ' ', $concept['concept_type']))), 'LR', 0, 'C', $fill);
        $pdf->Cell($w[2], 5, number_format($concept['amount_usd'], 2), 'LR', 0, 'R', $fill);
        $pdf->Cell($w[3], 5, number_format($concept['amount_bs'], 2), 'LR', 0, 'R', $fill);
        $pdf->Cell($w[4], 5, !is_null($concept['days_applied']) ? htmlspecialchars($concept['days_applied']) : 'N/A', 'LR', 0, 'C', $fill);
        $pdf->Ln();
        $fill = !$fill;
    }

    // Subtotales
    $pdf->SetFont('helvetica', 'B', 7);
    $pdf->SetFillColor(240, 240, 240);

    $pdf->Cell($w[0] + $w[1], 5, 'Subtotal Ingresos:', 'LR', 0, 'R', 1);
    $pdf->Cell($w[2], 5, number_format($data['subtotal_ingresos_usd'], 2), 'LR', 0, 'R', 1);
    $pdf->Cell($w[3], 5, number_format($data['subtotal_ingresos_bs'], 2), 'LR', 0, 'R', 1);
    $pdf->Cell($w[4], 5, '', 'LR', 0, 'C', 1);
    $pdf->Ln();

    $pdf->Cell($w[0] + $w[1], 5, 'Subtotal Beneficios:', 'LR', 0, 'R', 1);
    $pdf->Cell($w[2], 5, number_format($data['subtotal_beneficios_usd'], 2), 'LR', 0, 'R', 1);
    $pdf->Cell($w[3], 5, number_format($data['subtotal_beneficios_bs'], 2), 'LR', 0, 'R', 1);
    $pdf->Cell($w[4], 5, '', 'LR', 0, 'C', 1);
    $pdf->Ln();

    $pdf->Cell($w[0] + $w[1], 5, 'Subtotal Deducciones:', 'LR', 0, 'R', 1);
    $pdf->Cell($w[2], 5, number_format($data['subtotal_deducciones_usd'], 2), 'LR', 0, 'R', 1);
    $pdf->Cell($w[3], 5, number_format($data['subtotal_deducciones_bs'], 2), 'LR', 0, 'R', 1);
    $pdf->Cell($w[4], 5, '', 'LR', 0, 'C', 1);
    $pdf->Ln();

    $pdf->SetFillColor(200, 255, 200);
    $pdf->Cell($w[0] + $w[1], 5, 'NETO A PAGAR:', 'LR', 0, 'R', 1);
    $pdf->Cell($w[2], 5, number_format($data['neto_empleado_usd'], 2), 'LR', 0, 'R', 1);
    $pdf->Cell($w[3], 5, number_format($data['neto_empleado_bs'], 2), 'LR', 0, 'R', 1);
    $pdf->Cell($w[4], 5, '', 'LR', 0, 'C', 1);
    $pdf->Ln();

    // Línea final
    $pdf->Cell(array_sum($w), 0, '', 'T');
    $pdf->Ln(8);
}

// Resumen total
if ($pdf->GetY() > 220) {
    $pdf->AddPage();
}

$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 10, 'Resumen Total de la Nómina', 0, 1, 'C');
$pdf->Ln(5);

$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(0, 6, 'Total Ingresos: $' . number_format($total_summary['total_ingresos_usd'], 2) . ' (Bs ' . number_format($total_summary['total_ingresos_bs'], 2) . ')', 0, 1);
$pdf->Cell(0, 6, 'Total Beneficios: $' . number_format($total_summary['total_beneficios_usd'], 2) . ' (Bs ' . number_format($total_summary['total_beneficios_bs'], 2) . ')', 0, 1);
$pdf->Cell(0, 6, 'Total Deducciones: $' . number_format($total_summary['total_deducciones_usd'], 2) . ' (Bs ' . number_format($total_summary['total_deducciones_bs'], 2) . ')', 0, 1);
$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(0, 6, 'NETO TOTAL A PAGAR: $' . number_format($total_summary['neto_a_pagar_usd'], 2) . ' (Bs ' . number_format($total_summary['neto_a_pagar_bs'], 2) . ')', 0, 1);

// Salida del PDF
$filename = 'detalles_nomina_' . $payroll_period['start_date'] . '_al_' . $payroll_period['end_date'] . '_' . date('Y-m-d_H-i-s') . '.pdf';
$pdf->Output($filename, 'I');
?>