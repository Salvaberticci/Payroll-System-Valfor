<?php
// public/employees.php
require_once __DIR__ . '/../includes/auth.php'; // Incluir el archivo de autenticación
require_once __DIR__ . '/../config/settings.php'; // Para getDbConnection() y roles

// Requerir que el usuario esté logueado y tenga rol de 'admin' o 'assistant'
requireRole([ROLE_ADMIN, ROLE_ASSISTANT]);

$page_title = 'Gestión de Empleados';
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
    <!-- Estilos específicos de la página de empleados -->
    <link href="./assets/css/employees.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; // Incluir la barra de navegación ?>

    <div class="container mt-4">
        <h1 class="mb-4">Gestión de Empleados</h1>

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                Listado de Empleados
                <a href="<?php echo getBaseUrl(); ?>employees_form.php" class="btn btn-primary btn-sm">
                    <i class="bi bi-person-plus me-1"></i> Añadir Nuevo Empleado
                </a>
            </div>
            <div class="card-body">
                <?php
                $employees = [];
                try {
                    $pdo = getDbConnection();
                    $stmt = $pdo->query("SELECT id, cedula, full_name, fecha_ingreso, cargo, salario_base_mensual_usd, is_active FROM employees ORDER BY full_name ASC");
                    $employees = $stmt->fetchAll();
                } catch (PDOException $e) {
                    echo '<div class="alert alert-danger" role="alert">Error al cargar los empleados: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }

                if (empty($employees)): ?>
                    <div class="alert alert-info" role="alert">
                        No hay empleados registrados aún. <a href="<?php echo getBaseUrl(); ?>employees_form.php" class="alert-link">Añade el primero aquí</a>.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead>
                                <tr>
                                    <th>Cédula</th>
                                    <th>Nombre Completo</th>
                                    <th>Fecha Ingreso</th>
                                    <th>Cargo</th>
                                    <th>Salario Base ($)</th>
                                    <th>Activo</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($employees as $employee): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($employee['cedula']); ?></td>
                                        <td><?php echo htmlspecialchars($employee['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($employee['fecha_ingreso']); ?></td>
                                        <td><?php echo htmlspecialchars($employee['cargo']); ?></td>
                                        <td><?php echo number_format($employee['salario_base_mensual_usd'], 2); ?></td>
                                        <td>
                                            <?php if ($employee['is_active']): ?>
                                                <span class="badge bg-success">Sí</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">No</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="<?php echo getBaseUrl(); ?>employees_form.php?id=<?php echo $employee['id']; ?>" class="btn btn-sm btn-info text-white me-1" title="Editar">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                            <!-- Aquí podrías añadir un botón de eliminar, con confirmación JS -->
                                            <!-- <button type="button" class="btn btn-sm btn-danger" title="Eliminar" onclick="confirmDelete(<?php echo $employee['id']; ?>)">
                                                <i class="bi bi-trash"></i>
                                            </button> -->
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

   
    <?php include __DIR__ . '/../includes/footer.php'; // Incluir el pie de página ?>
