<?php
// public/generate_employees_pdf.php
require_once __DIR__ . '/../includes/auth.php'; // Incluir el archivo de autenticación
require_once __DIR__ . '/../config/settings.php'; // Para getDbConnection() y roles

// Requerir que el usuario esté logueado y tenga rol de 'admin' o 'assistant'
requireRole([ROLE_ADMIN, ROLE_ASSISTANT]);

require_once __DIR__ . '/../includes/MyTCPDF.php'; // Para MyTCPDF
require_once __DIR__ . '/../includes/pdf_styles.php'; // Para estilos de PDF

try {
    $pdo = getDbConnection();
    $stmt = $pdo->query("SELECT id, cedula, full_name, fecha_ingreso, cargo, salario_base_mensual_usd, photo_path, is_active FROM employees ORDER BY full_name ASC");
    $employees = $stmt->fetchAll();
} catch (PDOException $e) {
    die('Error al cargar los empleados: ' . htmlspecialchars($e->getMessage()));
}

// Crear nuevo documento PDF
$pdf = new MyTCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Configurar documento
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Sistema de Nómina VALFOR S.A.');
$pdf->SetTitle('Lista de Empleados');
$pdf->SetSubject('Nómina de Empleados');
$pdf->SetKeywords('empleados, nómina, VALFOR');

// Establecer el título del reporte para el encabezado personalizado
$pdf->setReportTitle('Lista de Empleados ');

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

// Fecha de generación
$pdf->SetFont('helvetica', '', 10);
$pdf->SetTextColorArray(COLOR_TEXT_DARK);
$pdf->Cell(0, 8, 'Fecha de generación: ' . date('d/m/Y H:i'), 0, 1, 'R');
$pdf->Ln(5);

// Cabecera de tabla
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColorArray(COLOR_PRIMARY);
$pdf->SetTextColorArray(COLOR_TEXT_LIGHT);

$header = array('Cédula', 'Nombre Completo', 'Fecha Ingreso', 'Cargo', 'Salario Base ($)', 'Estado');
$w = array(25, 50, 25, 35, 30, 20);

for($i = 0; $i < count($header); $i++) {
    $pdf->Cell($w[$i], 8, $header[$i], 1, 0, 'C', 1);
}
$pdf->Ln();

// Datos de empleados
$pdf->SetFont('helvetica', '', 8);
$pdf->SetTextColorArray(COLOR_TEXT_DARK);

$fill = false;
foreach($employees as $employee) {
    $pdf->SetFillColorArray($fill ? COLOR_SECONDARY : array(255, 255, 255));
    $pdf->Cell($w[0], 7, $employee['cedula'], 'LBR', 0, 'L', $fill);
    $pdf->Cell($w[1], 7, $employee['full_name'], 'BR', 0, 'L', $fill);
    $pdf->Cell($w[2], 7, date('d/m/Y', strtotime($employee['fecha_ingreso'])), 'BR', 0, 'C', $fill);
    $pdf->Cell($w[3], 7, $employee['cargo'], 'BR', 0, 'L', $fill);
    $pdf->Cell($w[4], 7, number_format($employee['salario_base_mensual_usd'], 2), 'BR', 0, 'R', $fill);
    $pdf->Cell($w[5], 7, ($employee['is_active'] ? 'Activo' : 'Inactivo'), 'BR', 0, 'C', $fill);
    $pdf->Ln();
    $fill = !$fill;
}

// Estadísticas
$pdf->Ln(10);
$pdf->SetFont('helvetica', 'B', 12);
$pdf->SetTextColorArray(COLOR_PRIMARY);
$pdf->Cell(0, 10, 'Estadísticas de Empleados', 0, 1, 'C');
$pdf->Ln(5);

$total_employees = count($employees);
$active_employees = count(array_filter($employees, function($emp) { return $emp['is_active']; }));
$inactive_employees = $total_employees - $active_employees;

$pdf->SetFont('helvetica', '', 10);
$pdf->SetTextColorArray(COLOR_TEXT_DARK);
$pdf->SetFillColorArray(COLOR_SECONDARY);

$pdf->Cell(90, 8, "Total de empleados:", 'LBR', 0, 'R', 1);
$pdf->Cell(90, 8, $total_employees, 'BR', 1, 'R', 1);

$pdf->Cell(90, 8, "Empleados activos:", 'LBR', 0, 'R', 0);
$pdf->Cell(90, 8, $active_employees, 'BR', 1, 'R', 0);

$pdf->Cell(90, 8, "Empleados inactivos:", 'LBR', 0, 'R', 1);
$pdf->Cell(90, 8, $inactive_employees, 'BR', 1, 'R', 1);

// Salida del PDF
$pdf->Output('lista_empleados_' . date('Y-m-d_H-i-s') . '.pdf', 'I');
?>
