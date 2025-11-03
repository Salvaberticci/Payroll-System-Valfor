<?php
// public/import_database.php (Importar base de datos desde archivo SQL)
require_once __DIR__ . '/../includes/auth.php';

// Requerir que el usuario sea administrador
requireRole(['admin']);

// Función para validar y procesar la importación de base de datos
function importDatabase($filePath) {
    try {
        $pdo = getDbConnection();

        // Leer el contenido del archivo
        $sql = file_get_contents($filePath);
        if ($sql === false) {
            return [
                'success' => false,
                'error' => 'No se pudo leer el archivo SQL'
            ];
        }

        // Verificar que el archivo contiene instrucciones SQL válidas
        if (empty(trim($sql))) {
            return [
                'success' => false,
                'error' => 'El archivo SQL está vacío'
            ];
        }

        // Iniciar transacción para asegurar integridad
        $pdo->beginTransaction();

        // Dividir el SQL en instrucciones individuales
        $statements = array_filter(array_map('trim', explode(';', $sql)));

        $executedStatements = 0;
        $errors = [];

        foreach ($statements as $statement) {
            if (!empty($statement)) {
                try {
                    $pdo->exec($statement);
                    $executedStatements++;
                } catch (Exception $e) {
                    $errors[] = "Error en la línea " . ($executedStatements + 1) . ": " . $e->getMessage();
                    // Continuar con las siguientes instrucciones
                }
            }
        }

        // Confirmar la transacción si no hay errores críticos
        if (empty($errors)) {
            $pdo->commit();
            return [
                'success' => true,
                'message' => "Importación completada exitosamente. Se ejecutaron {$executedStatements} instrucciones SQL.",
                'statements_executed' => $executedStatements
            ];
        } else {
            $pdo->rollBack();
            return [
                'success' => false,
                'error' => 'La importación falló debido a errores en las instrucciones SQL',
                'details' => $errors,
                'statements_executed' => $executedStatements
            ];
        }

    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return [
            'success' => false,
            'error' => 'Error general durante la importación: ' . $e->getMessage()
        ];
    }
}

// Procesar la subida del archivo
$message = '';
$message_type = '';
$import_result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar token CSRF
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        $message = 'Error de validación de seguridad. Intente nuevamente.';
        $message_type = 'danger';
    } elseif (!isset($_FILES['sql_file']) || $_FILES['sql_file']['error'] !== UPLOAD_ERR_OK) {
        $message = 'Error al subir el archivo. Verifique que haya seleccionado un archivo válido.';
        $message_type = 'danger';
    } else {
        $file = $_FILES['sql_file'];

        // Validar tipo de archivo
        $allowedTypes = ['application/sql', 'text/plain', 'application/octet-stream'];
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if ($fileExtension !== 'sql') {
            $message = 'Solo se permiten archivos con extensión .sql';
            $message_type = 'danger';
        } elseif (!in_array($file['type'], $allowedTypes) && !preg_match('/\.sql$/i', $file['name'])) {
            $message = 'Tipo de archivo no válido. Solo se permiten archivos SQL.';
            $message_type = 'danger';
        } elseif ($file['size'] > 50 * 1024 * 1024) { // 50MB máximo
            $message = 'El archivo es demasiado grande. Máximo permitido: 50MB.';
            $message_type = 'danger';
        } else {
            // Crear directorio temporal si no existe
            $temp_dir = __DIR__ . '/../temp';
            if (!is_dir($temp_dir)) {
                mkdir($temp_dir, 0755, true);
            }

            // Generar nombre único para el archivo temporal
            $temp_filename = 'import_' . session_id() . '_' . time() . '.sql';
            $temp_filepath = $temp_dir . '/' . $temp_filename;

            // Mover archivo a ubicación temporal
            if (move_uploaded_file($file['tmp_name'], $temp_filepath)) {
                // Procesar la importación
                $import_result = importDatabase($temp_filepath);

                if ($import_result['success']) {
                    $message = $import_result['message'];
                    $message_type = 'success';
                } else {
                    $message = $import_result['error'];
                    if (isset($import_result['details'])) {
                        $message .= '<br><small>Detalles: ' . implode('<br>', $import_result['details']) . '</small>';
                    }
                    $message_type = 'danger';
                }

                // Limpiar archivo temporal
                if (file_exists($temp_filepath)) {
                    unlink($temp_filepath);
                }
            } else {
                $message = 'Error al procesar el archivo subido.';
                $message_type = 'danger';
            }
        }
    }
}

// Generar token CSRF si no existe
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar Base de Datos - Sistema de Nómina</title>
    <!-- Incluir Bootstrap CSS directamente con ruta relativa -->
    <link href="./assets/css/bootstrap.min.css" rel="stylesheet">
    <!-- Iconos de Bootstrap -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Estilos específicos del dashboard (para consistencia) -->
    <link href="./assets/css/dashboard.css" rel="stylesheet">
    <!-- Estilos específicos de la página -->
    <link href="./assets/css/system_config.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; // Incluir la barra de navegación ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="<?php echo getBaseUrl(); ?>dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="<?php echo getBaseUrl(); ?>system_config.php">Configuración del Sistema</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Importar Base de Datos</li>
                    </ol>
                </nav>

                <h1 class="mb-4">
                    <i class="bi bi-upload me-2"></i>Importar Base de Datos
                </h1>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-lg-8">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-file-earmark-arrow-up me-2"></i>Subir Archivo SQL
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="post" enctype="multipart/form-data" class="mb-4">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                                    <div class="mb-3">
                                        <label for="sql_file" class="form-label">
                                            <strong>Seleccionar archivo SQL</strong>
                                        </label>
                                        <input type="file" class="form-control" id="sql_file" name="sql_file" accept=".sql" required>
                                        <div class="form-text">
                                            Solo se permiten archivos con extensión .sql (máximo 50MB)
                                        </div>
                                    </div>

                                    <div class="alert alert-warning">
                                        <i class="bi bi-exclamation-triangle me-2"></i>
                                        <strong>Advertencia:</strong> Esta acción sobrescribirá los datos existentes en la base de datos.
                                        Se recomienda crear un respaldo antes de proceder.
                                    </div>

                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <a href="<?php echo getBaseUrl(); ?>system_config.php" class="btn btn-secondary me-md-2">
                                            <i class="bi bi-arrow-left me-1"></i> Volver
                                        </a>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-upload me-2"></i>Importar Base de Datos
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <?php if ($import_result && isset($import_result['details'])): ?>
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">
                                        <i class="bi bi-info-circle me-2"></i>Detalles de la Importación
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="alert alert-info">
                                        <strong>Instrucciones ejecutadas:</strong> <?php echo $import_result['statements_executed'] ?? 0; ?>
                                    </div>
                                    <?php if (!empty($import_result['details'])): ?>
                                        <h6>Errores encontrados:</h6>
                                        <ul class="list-group">
                                            <?php foreach ($import_result['details'] as $error): ?>
                                                <li class="list-group-item list-group-item-danger">
                                                    <i class="bi bi-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="col-lg-4">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="card-title mb-0">
                                    <i class="bi bi-info-circle me-2"></i>Información Importante
                                </h6>
                            </div>
                            <div class="card-body">
                                <h6>Requisitos del archivo:</h6>
                                <ul class="list-unstyled">
                                    <li><i class="bi bi-check-circle text-success me-2"></i>Extensión .sql</li>
                                    <li><i class="bi bi-check-circle text-success me-2"></i>Tamaño máximo 50MB</li>
                                    <li><i class="bi bi-check-circle text-success me-2"></i>Instrucciones SQL válidas</li>
                                    <li><i class="bi bi-check-circle text-success me-2"></i>Codificación UTF-8</li>
                                </ul>

                                <hr>

                                <h6>Recomendaciones:</h6>
                                <ul class="list-unstyled">
                                    <li><i class="bi bi-lightbulb text-warning me-2"></i>Cree un respaldo primero</li>
                                    <li><i class="bi bi-lightbulb text-warning me-2"></i>Verifique el archivo antes de importar</li>
                                    <li><i class="bi bi-lightbulb text-warning me-2"></i>Esta acción no se puede deshacer</li>
                                </ul>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h6 class="card-title mb-0">
                                    <i class="bi bi-clock-history me-2"></i>Acciones Recientes
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="text-muted">
                                    <small>No hay importaciones recientes registradas</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; // Incluir el pie de página ?>
    <!-- Incluir Bootstrap JS (Bundle con Popper) directamente con ruta relativa -->
    <script src="./assets/js/bootstrap.bundle.min.js"></script>
    <!-- Scripts personalizados (si los hay) directamente con ruta relativa -->
    <script src="./assets/js/script.js"></script>
</body>
</html>