<?php
// public/generate_employees_pdf.php
require_once __DIR__ . '/../includes/auth.php'; // Incluir el archivo de autenticación
require_once __DIR__ . '/../config/settings.php'; // Para getDbConnection() y roles

// Requerir que el usuario esté logueado y tenga rol de 'admin' o 'assistant'
requireRole([ROLE_ADMIN, ROLE_ASSISTANT]);

require_once __DIR__ . '/../vendor/autoload.php'; // Para TCPDF

try {
    $pdo = getDbConnection();
    $stmt = $pdo->query("SELECT id, cedula, full_name, fecha_ingreso, cargo, salario_base_mensual_usd, photo_path, is_active FROM employees ORDER BY full_name ASC");
    $employees = $stmt->fetchAll();
} catch (PDOException $e) {
    die('Error al cargar los empleados: ' . htmlspecialchars($e->getMessage()));
}

// Crear nuevo documento PDF
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Configurar documento
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Sistema de Nómina VALFOR S.A.');
$pdf->SetTitle('Lista de Empleados');
$pdf->SetSubject('Nómina de Empleados');
$pdf->SetKeywords('empleados, nómina, VALFOR');

// Configurar márgenes
$pdf->SetMargins(15, 20, 15);
$pdf->SetHeaderMargin(10);
$pdf->SetFooterMargin(10);

// Configurar auto page breaks
$pdf->SetAutoPageBreak(TRUE, 15);

// Configurar fuente
$pdf->SetFont('helvetica', '', 10);

// Añadir página
$pdf->AddPage();

// Título
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, 'Lista de Empleados - VALFOR S.A.', 0, 1, 'C');
$pdf->Ln(5);

// Fecha de generación
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 8, 'Fecha de generación: ' . date('d/m/Y H:i'), 0, 1, 'R');
$pdf->Ln(5);

// Cabecera de tabla
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor(0, 123, 255);
$pdf->SetTextColor(255, 255, 255);

$header = array('Cédula', 'Nombre Completo', 'Fecha Ingreso', 'Cargo', 'Salario Base ($)', 'Estado');
$w = array(25, 50, 25, 35, 30, 20);

for($i = 0; $i < count($header); $i++) {
    $pdf->Cell($w[$i], 8, $header[$i], 1, 0, 'C', 1);
}
$pdf->Ln();

// Datos de empleados
$pdf->SetFont('helvetica', '', 8);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFillColor(255, 255, 255);

$fill = false;
foreach($employees as $employee) {
    $pdf->Cell($w[0], 6, $employee['cedula'], 'LR', 0, 'L', $fill);
    $pdf->Cell($w[1], 6, $employee['full_name'], 'LR', 0, 'L', $fill);
    $pdf->Cell($w[2], 6, date('d/m/Y', strtotime($employee['fecha_ingreso'])), 'LR', 0, 'C', $fill);
    $pdf->Cell($w[3], 6, $employee['cargo'], 'LR', 0, 'L', $fill);
    $pdf->Cell($w[4], 6, number_format($employee['salario_base_mensual_usd'], 2), 'LR', 0, 'R', $fill);
    $pdf->Cell($w[5], 6, ($employee['is_active'] ? 'Activo' : 'Inactivo'), 'LR', 0, 'C', $fill);
    $pdf->Ln();
    $fill = !$fill;
}

// Línea final
$pdf->Cell(array_sum($w), 0, '', 'T');

// Estadísticas
$pdf->Ln(10);
$pdf->SetFont('helvetica', 'B', 10);
$total_employees = count($employees);
$active_employees = count(array_filter($employees, function($emp) { return $emp['is_active']; }));
$inactive_employees = $total_employees - $active_employees;

$pdf->Cell(0, 8, "Total de empleados: $total_employees", 0, 1);
$pdf->Cell(0, 8, "Empleados activos: $active_employees", 0, 1);
$pdf->Cell(0, 8, "Empleados inactivos: $inactive_employees", 0, 1);

// Salida del PDF
$pdf->Output('lista_empleados_' . date('Y-m-d_H-i-s') . '.pdf', 'I');
?>