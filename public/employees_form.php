<?php
// public/employees_form.php
require_once __DIR__ . '/../includes/auth.php'; // Incluir el archivo de autenticación
require_once __DIR__ . '/../config/settings.php'; // Para getDbConnection() y roles

// Requerir que el usuario esté logueado y tenga rol de 'admin' o 'assistant'
requireRole([ROLE_ADMIN, ROLE_ASSISTANT]);

$page_title = 'Nuevo Empleado';
$employee = [
    'id' => null,
    'cedula' => '',
    'full_name' => '',
    'fecha_ingreso' => '',
    'cargo' => '',
    'salario_base_mensual_usd' => '',
    'is_active' => 1 // Por defecto, el empleado está activo
];
$message = '';
$message_type = ''; // 'success' or 'danger'

$pdo = getDbConnection();

// Lógica para cargar datos si se está editando un empleado
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $employee_id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("SELECT id, cedula, full_name, fecha_ingreso, cargo, salario_base_mensual_usd, is_active FROM employees WHERE id = :id");
        $stmt->bindParam(':id', $employee_id, PDO::PARAM_INT);
        $stmt->execute();
        $fetched_employee = $stmt->fetch();

        if ($fetched_employee) {
            $employee = $fetched_employee;
            $page_title = 'Editar Empleado';
        } else {
            $message = 'Empleado no encontrado.';
            $message_type = 'danger';
        }
    } catch (PDOException $e) {
        $message = 'Error al cargar los datos del empleado: ' . htmlspecialchars($e->getMessage());
        $message_type = 'danger';
    }
}

// Lógica para procesar el formulario cuando se envía (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id = $_POST['id'] ?? null;
    $cedula = trim($_POST['cedula'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $fecha_ingreso = $_POST['fecha_ingreso'] ?? '';
    $cargo = trim($_POST['cargo'] ?? '');
    $salario_base_mensual_usd = str_replace(',', '.', trim($_POST['salario_base_mensual_usd'] ?? '')); // Reemplaza coma por punto si es necesario
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Actualizar los datos del array $employee para que el formulario los retenga en caso de error
    $employee['cedula'] = $cedula;
    $employee['full_name'] = $full_name;
    $employee['fecha_ingreso'] = $fecha_ingreso;
    $employee['cargo'] = $cargo;
    $employee['salario_base_mensual_usd'] = $salario_base_mensual_usd;
    $employee['is_active'] = $is_active;

    // Validaciones
    if (empty($cedula) || empty($full_name) || empty($fecha_ingreso) || empty($cargo) || !is_numeric($salario_base_mensual_usd)) {
        $message = 'Por favor, complete todos los campos obligatorios y asegúrese de que el salario sea un número válido.';
        $message_type = 'danger';
    } else {
        try {
            if ($employee_id) {
                // Actualizar empleado existente
                $stmt = $pdo->prepare("UPDATE employees SET cedula = :cedula, full_name = :full_name, fecha_ingreso = :fecha_ingreso, cargo = :cargo, salario_base_mensual_usd = :salario_base_mensual_usd, is_active = :is_active WHERE id = :id");
                $stmt->bindParam(':id', $employee_id, PDO::PARAM_INT);
                $stmt->bindParam(':cedula', $cedula);
                $stmt->bindParam(':full_name', $full_name);
                $stmt->bindParam(':fecha_ingreso', $fecha_ingreso);
                $stmt->bindParam(':cargo', $cargo);
                $stmt->bindParam(':salario_base_mensual_usd', $salario_base_mensual_usd);
                $stmt->bindParam(':is_active', $is_active, PDO::PARAM_INT);

                if ($stmt->execute()) {
                    $message = 'Empleado actualizado exitosamente.';
                    $message_type = 'success';
                } else {
                    $message = 'Error al actualizar el empleado.';
                    $message_type = 'danger';
                }
            } else {
                // Insertar nuevo empleado
                $stmt = $pdo->prepare("INSERT INTO employees (cedula, full_name, fecha_ingreso, cargo, salario_base_mensual_usd, is_active) VALUES (:cedula, :full_name, :fecha_ingreso, :cargo, :salario_base_mensual_usd, :is_active)");
                $stmt->bindParam(':cedula', $cedula);
                $stmt->bindParam(':full_name', $full_name);
                $stmt->bindParam(':fecha_ingreso', $fecha_ingreso);
                $stmt->bindParam(':cargo', $cargo);
                $stmt->bindParam(':salario_base_mensual_usd', $salario_base_mensual_usd);
                $stmt->bindParam(':is_active', $is_active, PDO::PARAM_INT);

                if ($stmt->execute()) {
                    $message = 'Nuevo empleado añadido exitosamente.';
                    $message_type = 'success';
                    // Opcional: Redirigir al listado de empleados o limpiar el formulario
                    // header('Location: ' . getBaseUrl() . 'employees.php?message=added');
                    // exit();
                    // Limpiar el formulario si se añadió uno nuevo
                    $employee = [
                        'id' => null, 'cedula' => '', 'full_name' => '', 'fecha_ingreso' => '',
                        'cargo' => '', 'salario_base_mensual_usd' => '', 'is_active' => 1
                    ];
                } else {
                    $message = 'Error al añadir el nuevo empleado.';
                    $message_type = 'danger';
                }
            }
        } catch (PDOException $e) {
            // Error por clave única (cédula duplicada) o cualquier otro error de DB
            if ($e->getCode() == '23000') { // Código para error de integridad (clave duplicada)
                $message = 'Error: La cédula ingresada ya existe para otro empleado.';
            } else {
                $message = 'Error de base de datos: ' . htmlspecialchars($e->getMessage());
            }
            $message_type = 'danger';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Sistema de Nómina</title>
    <!-- Incluir Bootstrap CSS directamente con ruta relativa -->
    <link href="./assets/css/bootstrap.min.css" rel="stylesheet">
    <!-- Iconos de Bootstrap -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Estilos específicos de la página del formulario de empleados -->
    <link href="./assets/css/employees_form.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; // Incluir la barra de navegación ?>

    <div class="container mt-4">
        <h1 class="mb-4"><?php echo $page_title; ?></h1>

        <div class="card mb-4">
            <div class="card-header">
                Datos del Empleado
            </div>
            <div class="card-body">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>" role="alert">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($employee['id']); ?>">

                    <div class="mb-3">
                        <label for="cedula" class="form-label">Cédula</label>
                        <input type="text" class="form-control" id="cedula" name="cedula" value="<?php echo htmlspecialchars($employee['cedula']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="full_name" class="form-label">Nombre Completo</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($employee['full_name']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="fecha_ingreso" class="form-label">Fecha de Ingreso</label>
                        <input type="date" class="form-control" id="fecha_ingreso" name="fecha_ingreso" value="<?php echo htmlspecialchars($employee['fecha_ingreso']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="cargo" class="form-label">Cargo</label>
                        <input type="text" class="form-control" id="cargo" name="cargo" value="<?php echo htmlspecialchars($employee['cargo']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="salario_base_mensual_usd" class="form-label">Salario Base Mensual ($)</label>
                        <input type="number" step="0.01" class="form-control" id="salario_base_mensual_usd" name="salario_base_mensual_usd" value="<?php echo htmlspecialchars($employee['salario_base_mensual_usd']); ?>" required>
                    </div>

                    <div class="mb-4 form-check">
                        <input type="checkbox" class="form-check-input" id="is_active" name="is_active" <?php echo ($employee['is_active'] ? 'checked' : ''); ?>>
                        <label class="form-check-label" for="is_active">Empleado Activo</label>
                    </div>

                    <div class="d-flex justify-content-between">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-save me-1"></i> <?php echo ($employee['id'] ? 'Actualizar' : 'Guardar'); ?> Empleado
                        </button>
                        <a href="<?php echo getBaseUrl(); ?>employees.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left-circle me-1"></i> Volver al Listado
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    
    <?php include __DIR__ . '/../includes/footer.php'; // Incluir el pie de página ?>
