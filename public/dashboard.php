<?php
// public/dashboard.php (Dashboard principal)
require_once __DIR__ . '/../includes/auth.php'; // Incluir el archivo de autenticación

// Requerir que el usuario esté logueado para acceder a esta página
requireLogin();

// Definir el título de la página
$page_title = 'Dashboard - Sistema de Nómina';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <!-- Incluir Bootstrap CSS directamente con ruta relativa -->
    <link href="./assets/css/bootstrap.min.css" rel="stylesheet">
    <!-- Iconos de Bootstrap -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Estilos específicos de la página del dashboard directamente con ruta relativa -->
    <link href="./assets/css/dashboard.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; // Incluir la barra de navegación ?>

    <div class="container mt-4">
        <h1 class="mb-4">Bienvenido, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Usuario'); ?>!</h1>
        <p class="lead">Tu rol es: <strong><?php echo htmlspecialchars(getUserRole()); ?></strong></p>

        <?php if (isset($_GET['error']) && $_GET['error'] === 'access_denied'): ?>
                <div class="alert alert-warning" role="alert">
                    No tienes permiso para acceder a esa sección.
                </div>
            <?php endif; ?>

        <hr class="my-4">

        <h2 class="mb-4">Opciones Rápidas:</h2>
        <div class="row row-cols-1 row-cols-md-3 row-cols-lg-3 g-4">
            <?php $card_count = 0; ?>
            <?php if (getUserRole() === ROLE_ADMIN || getUserRole() === ROLE_ASSISTANT): ?>
                <div class="col">
                    <a href="<?php echo getBaseUrl(); ?>employees.php" class="card h-100 text-decoration-none dashboard-card">
                        <div class="card-body d-flex flex-column justify-content-center align-items-center">
                            <i class="bi bi-people-fill display-4 text-primary mb-3"></i>
                            <h5 class="card-title text-primary">Gestión de Empleados</h5>
                        </div>
                    </a>
                </div>
                <?php $card_count++; ?>
                <div class="col">
                    <a href="<?php echo getBaseUrl(); ?>payroll_calc.php" class="card h-100 text-decoration-none dashboard-card">
                        <div class="card-body d-flex flex-column justify-content-center align-items-center">
                            <i class="bi bi-calculator-fill display-4 text-primary mb-3"></i>
                            <h5 class="card-title text-primary">Calcular Nómina</h5>
                        </div>
                    </a>
                </div>
                <?php $card_count++; ?>
                <div class="col">
                    <a href="<?php echo getBaseUrl(); ?>payroll_concepts.php" class="card h-100 text-decoration-none dashboard-card">
                        <div class="card-body d-flex flex-column justify-content-center align-items-center">
                            <i class="bi bi-cash-stack display-4 text-primary mb-3"></i>
                            <h5 class="card-title text-primary">Conceptos de Nómina</h5>
                        </div>
                    </a>
                </div>
                <?php $card_count++; ?>
            <?php endif; ?>
            <?php if (getUserRole() === ROLE_ADMIN): ?>
                <div class="col">
                    <a href="<?php echo getBaseUrl(); ?>users.php" class="card h-100 text-decoration-none dashboard-card">
                        <div class="card-body d-flex flex-column justify-content-center align-items-center">
                            <i class="bi bi-person-gear display-4 text-primary mb-3"></i>
                            <h5 class="card-title text-primary">Gestión de Usuarios</h5>
                        </div>
                    </a>
                </div>
                <?php $card_count++; ?>
                <div class="col">
                    <a href="<?php echo getBaseUrl(); ?>system_config.php" class="card h-100 text-decoration-none dashboard-card">
                        <div class="card-body d-flex flex-column justify-content-center align-items-center">
                            <i class="bi bi-gear display-4 text-primary mb-3"></i>
                            <h5 class="card-title text-primary">Configuración del Sistema</h5>
                        </div>
                    </a>
                </div>
                <?php $card_count++; ?>
            <?php endif; ?>
            <div class="col">
                <a href="<?php echo getBaseUrl(); ?>reports_employee.php" class="card h-100 text-decoration-none dashboard-card">
                    <div class="card-body d-flex flex-column justify-content-center align-items-center">
                        <i class="bi bi-file-earmark-bar-graph-fill display-4 text-primary mb-3"></i>
                        <h5 class="card-title text-primary">Reportes por Empleado</h5>
                    </div>
                </a>
            </div>
            <?php $card_count++; ?>
            <div class="col">
                <a href="<?php echo getBaseUrl(); ?>reports_analytics.php" class="card h-100 text-decoration-none dashboard-card">
                    <div class="card-body d-flex flex-column justify-content-center align-items-center">
                        <i class="bi bi-graph-up display-4 text-primary mb-3"></i>
                        <h5 class="card-title text-primary">Reportes Estadísticos</h5>
                    </div>
                </a>
            </div>
            <?php $card_count++; ?>
            <?php
            // Add invisible cards to fill the row and ensure uniform size
            $remainder = $card_count % 3;
            if ($remainder > 0) {
                $to_add = 3 - $remainder;
                for ($i = 0; $i < $to_add; $i++) {
                    echo '<div class="col"><div class="card dashboard-card invisible"></div></div>';
                }
            }
            ?>
        </div>

        <div class="mt-5 text-center">
            <a href="<?php echo getBaseUrl(); ?>logout.php" class="btn btn-danger btn-lg">
                <i class="bi bi-box-arrow-right me-2"></i> Cerrar Sesión
            </a>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; // Incluir el pie de página ?>
    <!-- Incluir Bootstrap JS (Bundle con Popper) directamente con ruta relativa -->
    <script src="./assets/js/bootstrap.bundle.min.js"></script>
    <!-- Scripts personalizados (si los hay) directamente con ruta relativa -->
    <script src="./assets/js/script.js"></script>
</body>
</html>
