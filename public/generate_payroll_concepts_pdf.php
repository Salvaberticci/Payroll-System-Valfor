<?php
// public/generate_payroll_concepts_pdf.php
require_once __DIR__ . '/../includes/auth.php'; // Incluir el archivo de autenticación
require_once __DIR__ . '/../config/settings.php'; // Para getDbConnection() y roles

// Requerir que el usuario esté logueado y tenga rol de 'admin' o 'assistant'
requireRole([ROLE_ADMIN, ROLE_ASSISTANT]);

require_once __DIR__ . '/../includes/MyTCPDF.php'; // Para MyTCPDF
require_once __DIR__ . '/../includes/pdf_styles.php'; // Para estilos de PDF

$pdo = getDbConnection();

$concepts = [];
try {
    $stmt = $pdo->query("SELECT id, name, type, calculation_type, default_value, applies_to_all, is_active FROM payroll_concepts ORDER BY name ASC");
    $concepts = $stmt->fetchAll();
} catch (PDOException $e) {
    die('Error al cargar los conceptos de nómina: ' . htmlspecialchars($e->getMessage()));
}

// Crear PDF
$pdf = new MyTCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Configurar documento
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Sistema de Nómina VALFOR S.A.');
$pdf->SetTitle('Reporte de Conceptos de Nómina');
$pdf->SetSubject('Conceptos de Nómina Existentes');
$pdf->SetKeywords('nómina, conceptos, VALFOR');

// Establecer el título del reporte para el encabezado personalizado
$pdf->setReportTitle('Conceptos de Nómina Existentes');

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

// Tabla de conceptos de nómina
if (!empty($concepts)) {
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetTextColorArray(COLOR_PRIMARY);
    $pdf->Cell(0, 10, 'Listado de Conceptos de Nómina', 0, 1);
    $pdf->Ln(5);

    $pdf->SetFillColorArray(COLOR_PRIMARY);
    $pdf->SetTextColorArray(COLOR_TEXT_LIGHT);
    $pdf->SetFont('helvetica', 'B', 8);

    $header = array('Nombre', 'Tipo', 'Cálculo', 'Valor Defecto', 'Aplica a Todos', 'Activo');
    $w = array(45, 30, 40, 25, 20, 20); // Ajustar anchos de columna

    for($i = 0; $i < count($header); $i++) {
        $pdf->Cell($w[$i], 8, $header[$i], 1, 0, 'C', 1);
    }
    $pdf->Ln();

    // Datos de conceptos
    $pdf->SetFont('helvetica', '', 7);
    $pdf->SetTextColorArray(COLOR_TEXT_DARK);

    $fill = false;
    foreach ($concepts as $c) {
        $pdf->SetFillColorArray($fill ? COLOR_SECONDARY : array(255, 255, 255));
        $calculation_type_spanish = [
            'fixed_value' => 'Valor Fijo',
            'percentage_of_salary' => 'Porcentaje del Salario',
            'per_day_value' => 'Valor por Día',
            'manual_input' => 'Entrada Manual'
        ];

        $pdf->Cell($w[0], 7, htmlspecialchars($c['name']), 'LBR', 0, 'L', $fill);
        $pdf->Cell($w[1], 7, htmlspecialchars(ucfirst(str_replace('_', ' ', $c['type']))), 'BR', 0, 'C', $fill);
        $pdf->Cell($w[2], 7, htmlspecialchars($calculation_type_spanish[$c['calculation_type']] ?? $c['calculation_type']), 'BR', 0, 'C', $fill);
        $pdf->Cell($w[3], 7, !is_null($c['default_value']) ? htmlspecialchars(number_format($c['default_value'], 4)) : 'N/A', 'BR', 0, 'R', $fill);
        $pdf->Cell($w[4], 7, ($c['applies_to_all'] ? 'Sí' : 'No'), 'BR', 0, 'C', $fill);
        $pdf->Cell($w[5], 7, ($c['is_active'] ? 'Sí' : 'No'), 'BR', 0, 'C', $fill);
        $pdf->Ln();
        $fill = !$fill;
    }
} else {
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetTextColorArray(COLOR_TEXT_DARK);
    $pdf->Cell(0, 10, 'No se encontraron conceptos de nómina registrados.', 0, 1, 'C');
}

// Salida del PDF
$filename = 'reporte_conceptos_nomina_' . date('Y-m-d_H-i-s') . '.pdf';
$pdf->Output($filename, 'I');
?>
