<?php
// public/generate_users_pdf.php
require_once __DIR__ . '/../includes/auth.php'; // Incluir el archivo de autenticación
require_once __DIR__ . '/../config/settings.php'; // Para getDbConnection() y roles

// Requerir que el usuario esté logueado y tenga rol de 'admin' o 'assistant'
requireRole([ROLE_ADMIN, ROLE_ASSISTANT]);

require_once __DIR__ . '/../includes/MyTCPDF.php'; // Para MyTCPDF
require_once __DIR__ . '/../includes/pdf_styles.php'; // Para estilos de PDF

$pdo = getDbConnection();

$users = [];
try {
    $stmt = $pdo->query("SELECT id, username, role FROM users ORDER BY username ASC");
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    die('Error al cargar los usuarios: ' . htmlspecialchars($e->getMessage()));
}

// Crear PDF
$pdf = new MyTCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Configurar documento
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Sistema de Nómina VALFOR S.A.');
$pdf->SetTitle('Reporte de Usuarios');
$pdf->SetSubject('Listado de Usuarios del Sistema');
$pdf->SetKeywords('usuarios, sistema, VALFOR');

// Establecer el título del reporte para el encabezado personalizado
$pdf->setReportTitle('Listado de Usuarios del Sistema ');

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

// Tabla de usuarios
if (!empty($users)) {
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->SetTextColorArray(COLOR_PRIMARY);
    $pdf->Cell(0, 10, 'Listado de Usuarios', 0, 1);
    $pdf->Ln(5);

    $pdf->SetFillColorArray(COLOR_PRIMARY);
    $pdf->SetTextColorArray(COLOR_TEXT_LIGHT);
    $pdf->SetFont('helvetica', 'B', 8);

    $header = array('ID', 'Usuario', 'Rol');
    $w = array(20, 80, 80); // Ajustar anchos de columna

    for($i = 0; $i < count($header); $i++) {
        $pdf->Cell($w[$i], 8, $header[$i], 1, 0, 'C', 1);
    }
    $pdf->Ln();

    // Datos de usuarios
    $pdf->SetFont('helvetica', '', 7);
    $pdf->SetTextColorArray(COLOR_TEXT_DARK);

    $fill = false;
    foreach ($users as $user) {
        $pdf->SetFillColorArray($fill ? COLOR_SECONDARY : array(255, 255, 255));
        $role_spanish = ucfirst(str_replace('_', ' ', $user['role']));

        $pdf->Cell($w[0], 7, htmlspecialchars($user['id']), 'LBR', 0, 'C', $fill);
        $pdf->Cell($w[1], 7, htmlspecialchars($user['username']), 'BR', 0, 'L', $fill);
        $pdf->Cell($w[2], 7, htmlspecialchars($role_spanish), 'BR', 0, 'C', $fill);
        $pdf->Ln();
        $fill = !$fill;
    }
} else {
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetTextColorArray(COLOR_TEXT_DARK);
    $pdf->Cell(0, 10, 'No se encontraron usuarios registrados.', 0, 1, 'C');
}

// Salida del PDF
$filename = 'reporte_usuarios_' . date('Y-m-d_H-i-s') . '.pdf';
$pdf->Output($filename, 'I');
?>
