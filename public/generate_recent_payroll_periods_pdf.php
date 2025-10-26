<?php
// public/generate_recent_payroll_periods_pdf.php
require_once __DIR__ . '/../includes/auth.php'; // Incluir el archivo de autenticación
require_once __DIR__ . '/../config/settings.php'; // Para getDbConnection() y roles

// Requerir que el usuario esté logueado y tenga rol de 'admin' o 'assistant'
requireRole([ROLE_ADMIN, ROLE_ASSISTANT]);

require_once __DIR__ . '/../includes/MyTCPDF.php'; // Para MyTCPDF
require_once __DIR__ . '/../includes/pdf_styles.php'; // Para estilos de PDF

$pdo = getDbConnection();

$payroll_periods = [];
try {
    $stmt = $pdo->query("SELECT id, start_date, end_date, bcv_rate, days_in_period, status, created_at FROM payroll_periods WHERE status IN ('calculado', 'pagado') ORDER BY created_at DESC LIMIT 10");
    $payroll_periods = $stmt->fetchAll();
} catch (PDOException $e) {
    die('Error al cargar los períodos de nómina: ' . htmlspecialchars($e->getMessage()));
}

// Crear PDF
$pdf = new MyTCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Configurar documento
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Sistema de Nómina VALFOR S.A.');
$pdf->SetTitle('Reporte de Períodos de Nómina');
$pdf->SetSubject('Períodos de Nómina Calculados/Pagados');
$pdf->SetKeywords('nómina, períodos, VALFOR');

// Establecer el título del reporte para el encabezado personalizado
$pdf->setReportTitle('Períodos de Nómina Calculados');

// Configurar márgenes
$pdf->SetMargins(15, 30, 15);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

// Configurar auto page breaks
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

// Configurar fuente
$pdf->SetFont('helvetica', '', 10);

// Añadir página
$pdf->AddPage();

// Información general
$pdf->SetFont('helvetica', '', 10);
$pdf->SetTextColorArray(COLOR_TEXT_DARK);
$pdf->Cell(0, 10, 'Fecha de generación: ' . date('d/m/Y H:i'), 0, 1);
$pdf->Ln(8);

// Tabla de períodos de nómina
if (!empty($payroll_periods)) {
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetTextColorArray(COLOR_PRIMARY);
    $pdf->Cell(0, 10, 'Últimos 10 Períodos de Nómina Calculados/Pagados', 0, 1);
    $pdf->Ln(5);

    $pdf->SetFillColorArray(COLOR_PRIMARY);
    $pdf->SetTextColorArray(COLOR_TEXT_LIGHT);
    $pdf->SetFont('helvetica', 'B', 8);

    $header = array('ID', 'Inicio', 'Fin', 'Tasa BCV', 'Días', 'Estado', 'Fecha Cálculo');
    $w = array(15, 30, 30, 25, 15, 25, 30); // Ajustar anchos de columna

    for($i = 0; $i < count($header); $i++) {
        $pdf->Cell($w[$i], 8, $header[$i], 1, 0, 'C', 1);
    }
    $pdf->Ln();

    // Datos de períodos
    $pdf->SetFont('helvetica', '', 7);
    $pdf->SetTextColorArray(COLOR_TEXT_DARK);

    $fill = false;
    foreach ($payroll_periods as $period) {
        $pdf->SetFillColorArray($fill ? COLOR_SECONDARY : array(255, 255, 255));
        $status_text = ucfirst($period['status']);

        $pdf->Cell($w[0], 7, htmlspecialchars($period['id']), 'LBR', 0, 'C', $fill);
        $pdf->Cell($w[1], 7, htmlspecialchars($period['start_date']), 'BR', 0, 'L', $fill);
        $pdf->Cell($w[2], 7, htmlspecialchars($period['end_date']), 'BR', 0, 'L', $fill);
        $pdf->Cell($w[3], 7, number_format($period['bcv_rate'], 2), 'BR', 0, 'R', $fill);
        $pdf->Cell($w[4], 7, htmlspecialchars($period['days_in_period']), 'BR', 0, 'C', $fill);
        $pdf->Cell($w[5], 7, $status_text, 'BR', 0, 'C', $fill);
        $pdf->Cell($w[6], 7, htmlspecialchars($period['created_at']), 'BR', 0, 'L', $fill);
        $pdf->Ln();
        $fill = !$fill;
    }
} else {
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetTextColorArray(COLOR_TEXT_DARK);
    $pdf->Cell(0, 10, 'No se encontraron períodos de nómina calculados o pagados recientemente.', 0, 1, 'C');
}

// Salida del PDF
$filename = 'reporte_periodos_nomina_recientes_' . date('Y-m-d_H-i-s') . '.pdf';
$pdf->Output($filename, 'I');
?>
