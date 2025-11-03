<?php
require_once __DIR__ . '/../vendor/autoload.php'; // Para TCPDF

class MyTCPDF extends TCPDF {

    private $reportTitle = '';
    private $logoPath = '';
    private $companyName = 'VALFOR S.A.';

    public function __construct($orientation = 'P', $unit = 'mm', $format = 'A4', $unicode = true, $encoding = 'UTF-8', $diskcache = false, $pdfa = false) {
        parent::__construct($orientation, $unit, $format, $unicode, $encoding, $diskcache, $pdfa);
        $this->logoPath = __DIR__ . '/../public/assets/img/logo.png';
    }

    public function setReportTitle($title) {
        $this->reportTitle = $title;
    }

    public function setCompanyName($name) {
        $this->companyName = $name;
    }

    // Encabezado
    public function Header() {
        // Logo
        if (file_exists($this->logoPath)) {
            // Ajustar posición y tamaño del logo para que no se superponga y se vea bien
            // x=15, y=10, width=30, height=15
            $this->Image($this->logoPath, 15, 10, 30, 15, 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        }

        // Título del reporte
        $this->SetFont('helvetica', 'B', 16);
        $this->SetY(18); // Mover el título más abajo para evitar superposición con el logo
        $this->Cell(0, 10, $this->reportTitle, 0, 1, 'C');
        $this->Ln(5);

        // Línea separadora
        $this->SetLineStyle(array('width' => 0.5, 'cap' => 'butt', 'join' => 'miter', 'dash' => 0, 'color' => array(200, 200, 200)));
        $this->Line(15, 32, $this->getPageWidth() - 15, 32); // Mover la línea más abajo
    }

    // Pie de página
    public function Footer() {
        $this->SetY(-15); // Posición a 15 mm del final
        $this->SetFont('helvetica', 'I', 8); // Fuente itálica
        // Nombre de la empresa (sin número de página)
        $this->Cell(0, 10, $this->companyName, 0, 0, 'L');
    }
}
