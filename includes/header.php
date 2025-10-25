<?php
// includes/header.php
// Contiene la barra de navegación. Se incluye dentro del <body> de cada página.
// Los enlaces CSS y JS se gestionan directamente en cada archivo de página (public/index.php, public/dashboard.php, etc.)
?>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="<?php echo getBaseUrl(); ?>dashboard.php">
                <i class="bi bi-wallet-fill me-2"></i>Sistema de Nómina
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo getBaseUrl(); ?>dashboard.php">Inicio</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo getBaseUrl(); ?>employees.php">Empleados</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo getBaseUrl(); ?>payroll_calc.php">Nómina</a>
                    </li>
                    <li class="nav-item dropdown">
                        <!-- Volvemos a usar data-bs-toggle, y el JS incrustado lo manejará -->
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Reportes
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="<?php echo getBaseUrl(); ?>reports_employee.php">Por Empleado</a></li>
                            <li><a class="dropdown-item" href="<?php echo getBaseUrl(); ?>reports_discounts.php">Descuentos</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo getBaseUrl(); ?>reports_analytics.php">Analíticos</a></li>
                            <li><a class="dropdown-item" href="<?php echo getBaseUrl(); ?>reports_paid.php">Pagos</a></li>
                        </ul>
                    </li>
                    <?php if (getUserRole() === ROLE_ADMIN): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo getBaseUrl(); ?>users.php">Usuarios</a>
                    </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#">Manual de Usuario</a>
                    </li>
                    <li class="nav-item">
                        <div style="background-color: white; height: 20px; width: 80px; display: flex; align-items: center; justify-content: center; margin-left: 10px; margin-top: 10px;">
                            <img src="<?php echo getBaseUrl(); ?>assets/img/logo.png" alt="VALFOR S.A." style="height: 70px;">
                        </div>
                    </li>
                    <li class="nav-item dropdown">
                        <!-- Volvemos a usar data-bs-toggle, y el JS incrustado lo manejará -->
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle me-1"></i> <?php echo htmlspecialchars($_SESSION['username'] ?? 'Invitado'); ?> (<?php echo htmlspecialchars(getUserRole()); ?>)
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="<?php echo getBaseUrl(); ?>logout.php"><i class="bi bi-box-arrow-right me-2"></i> Cerrar Sesión</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <!-- Contenedor principal para el contenido de la página, con animación -->
    <main class="page-content">

    <script>
        // JavaScript incrustado directamente en el header para el despliegue del menú
        document.addEventListener('DOMContentLoaded', function() {
            // Selecciona todos los elementos que tienen data-bs-toggle="dropdown"
            const dropdownToggles = document.querySelectorAll('[data-bs-toggle="dropdown"]');

            dropdownToggles.forEach(toggle => {
                toggle.addEventListener('click', function(event) {
                    event.preventDefault(); // Evita que el enlace navegue a '#'

                    // Encuentra el menú desplegable asociado (el siguiente elemento hermano <ul> con clase dropdown-menu)
                    const dropdownMenu = this.nextElementSibling;

                    if (dropdownMenu && dropdownMenu.classList.contains('dropdown-menu')) {
                        // Cierra cualquier otro menú desplegable abierto
                        document.querySelectorAll('.dropdown-menu.show').forEach(openMenu => {
                            if (openMenu !== dropdownMenu) { // No cierres el menú actual si ya está abierto
                                openMenu.classList.remove('show');
                                const openToggle = openMenu.previousElementSibling;
                                if (openToggle) {
                                    openToggle.setAttribute('aria-expanded', 'false');
                                }
                            }
                        });

                        // Alterna la visibilidad del menú actual
                        dropdownMenu.classList.toggle('show');
                        // Actualiza el atributo aria-expanded para accesibilidad
                        this.setAttribute('aria-expanded', dropdownMenu.classList.contains('show'));
                    }
                });
            });

            // Cierra el menú desplegable si se hace clic fuera de él
            document.addEventListener('click', function(event) {
                dropdownToggles.forEach(toggle => {
                    const dropdownMenu = toggle.nextElementSibling;
                    if (dropdownMenu && dropdownMenu.classList.contains('dropdown-menu') && dropdownMenu.classList.contains('show')) {
                        // Si el clic no fue dentro del toggle ni dentro del menú desplegable
                        if (!toggle.contains(event.target) && !dropdownMenu.contains(event.target)) {
                            dropdownMenu.classList.remove('show');
                            toggle.setAttribute('aria-expanded', 'false');
                        }
                    }
                });
            });
        });
    </script>
