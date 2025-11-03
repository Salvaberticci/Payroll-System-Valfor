<?php
// public/system_config.php (Configuración del Sistema - Solo para Administradores)
require_once __DIR__ . '/../includes/auth.php';

// Requerir que el usuario sea administrador
requireRole(['admin']);

// Definir el título de la página
$page_title = 'Configuración del Sistema - Sistema de Nómina';

// Función para obtener información del sistema
function getSystemInfo() {
    $info = [];

    // Versión del software
    $info['software_version'] = '1.0.0';

    // Versión de PHP
    $info['php_version'] = PHP_VERSION;

    // Versión de MySQL/MariaDB
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->query("SELECT VERSION() as version");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $info['mysql_version'] = $result['version'];
    } catch (Exception $e) {
        $info['mysql_version'] = 'No disponible';
    }

    // Sistema operativo del servidor
    $info['server_os'] = php_uname('s') . ' ' . php_uname('r');

    // Uptime del servidor (aproximado)
    if (function_exists('shell_exec') && strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
        $uptime = shell_exec('uptime -p 2>/dev/null');
        $info['server_uptime'] = $uptime ? trim($uptime) : 'No disponible';
    } else {
        $info['server_uptime'] = 'No disponible en Windows';
    }

    // Memoria usada por PHP
    $info['php_memory_usage'] = round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB';

    // Espacio en disco
    $disk_total = disk_total_space('/') / 1024 / 1024 / 1024;
    $disk_free = disk_free_space('/') / 1024 / 1024 / 1024;
    $disk_used = $disk_total - $disk_free;
    $info['disk_usage'] = [
        'total' => round($disk_total, 2) . ' GB',
        'used' => round($disk_used, 2) . ' GB',
        'free' => round($disk_free, 2) . ' GB',
        'percentage' => round(($disk_used / $disk_total) * 100, 1) . '%'
    ];

    // Estadísticas de la base de datos
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->query("SHOW TABLE STATUS");
        $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total_records = 0;
        foreach ($tables as $table) {
            $total_records += $table['Rows'];
        }
        $info['db_stats'] = [
            'tables_count' => count($tables),
            'total_records' => number_format($total_records)
        ];
    } catch (Exception $e) {
        $info['db_stats'] = [
            'tables_count' => 'No disponible',
            'total_records' => 'No disponible'
        ];
    }

    return $info;
}

// Función para crear respaldo de la base de datos
function createDatabaseBackup() {
    try {
        $pdo = getDbConnection();

        // Obtener todas las tablas
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $backup_content = "-- Payroll System Database Backup\n";
        $backup_content .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n";
        $backup_content .= "-- Software Version: 1.0.0\n\n";

        $backup_content .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
        $backup_content .= "SET FOREIGN_KEY_CHECKS = 0;\n";
        $backup_content .= "START TRANSACTION;\n";
        $backup_content .= "SET time_zone = \"+00:00\";\n\n";

        foreach ($tables as $table) {
            // Estructura de la tabla con verificación de existencia
            $backup_content .= "--\n-- Table structure for table `$table`\n--\n\n";
            $backup_content .= "CREATE TABLE IF NOT EXISTS `$table` (\n";

            // Obtener información de columnas
            $stmt = $pdo->query("DESCRIBE `$table`");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $column_defs = [];
            $primary_keys = [];
            $unique_keys = [];
            $indexes = [];

            foreach ($columns as $column) {
                $col_def = "`{$column['Field']}` {$column['Type']}";

                if ($column['Null'] === 'NO') {
                    $col_def .= ' NOT NULL';
                }

                if (!empty($column['Default'])) {
                    if ($column['Default'] === 'CURRENT_TIMESTAMP') {
                        $col_def .= ' DEFAULT CURRENT_TIMESTAMP';
                    } else {
                        $col_def .= ' DEFAULT ' . $pdo->quote($column['Default']);
                    }
                }

                if (!empty($column['Extra'])) {
                    $col_def .= ' ' . strtoupper($column['Extra']);
                }

                $column_defs[] = $col_def;

                if ($column['Key'] === 'PRI') {
                    $primary_keys[] = "`{$column['Field']}`";
                }

                if ($column['Key'] === 'UNI') {
                    $unique_keys[] = "`{$column['Field']}`";
                }
            }

            // Agregar definiciones de columnas
            $backup_content .= implode(",\n", $column_defs) . "\n";

            // Agregar claves primarias
            if (!empty($primary_keys)) {
                $backup_content .= ",\nPRIMARY KEY (" . implode(", ", $primary_keys) . ")\n";
            }

            // Agregar claves únicas
            if (!empty($unique_keys)) {
                foreach ($unique_keys as $uk) {
                    $backup_content .= ",\nUNIQUE KEY {$uk} ({$uk})\n";
                }
            }

            $backup_content .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

            // Datos de la tabla
            $stmt = $pdo->query("SELECT * FROM `$table`");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($rows)) {
                $backup_content .= "--\n-- Dumping data for table `$table`\n--\n\n";
                $backup_content .= "INSERT IGNORE INTO `$table` VALUES\n";

                $values = [];
                foreach ($rows as $row) {
                    $row_values = [];
                    foreach ($row as $value) {
                        if ($value === null) {
                            $row_values[] = 'NULL';
                        } else {
                            $row_values[] = $pdo->quote($value);
                        }
                    }
                    $values[] = "(" . implode(", ", $row_values) . ")";
                }

                $backup_content .= implode(",\n", $values) . ";\n\n";
            }
        }

        $backup_content .= "COMMIT;\n";
        $backup_content .= "SET FOREIGN_KEY_CHECKS = 1;\n";

        // Crear nombre del archivo
        $filename = 'payroll_backup_' . date('Y-m-d_H-i-s') . '.sql';

        // Crear directorio de respaldos si no existe
        $backup_dir = __DIR__ . '/../backups';
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }

        $filepath = $backup_dir . '/' . $filename;

        // Guardar el archivo
        if (file_put_contents($filepath, $backup_content)) {
            return [
                'success' => true,
                'filename' => $filename,
                'filepath' => $filepath,
                'size' => filesize($filepath)
            ];
        } else {
            return [
                'success' => false,
                'error' => 'No se pudo guardar el archivo de respaldo'
            ];
        }

    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Error al crear el respaldo: ' . $e->getMessage()
        ];
    }
}

// Procesar acciones POST
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_backup'])) {
        $backup_result = createDatabaseBackup();
        if ($backup_result['success']) {
            $message = "Respaldo creado exitosamente: {$backup_result['filename']} (" . round($backup_result['size'] / 1024, 2) . " KB)";
            $message_type = 'success';
        } else {
            $message = "Error al crear el respaldo: " . ($backup_result['error'] ?? 'Error desconocido');
            $message_type = 'danger';
        }
    }
}

// Obtener información del sistema
$system_info = getSystemInfo();
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
    <!-- Estilos específicos del dashboard (para consistencia) -->
    <link href="./assets/css/dashboard.css" rel="stylesheet">
    <!-- Estilos específicos de la página -->
    <link href="./assets/css/system_config.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; // Incluir la barra de navegación ?>

    <div class="container mt-4">
        <h1 class="mb-4">Configuración del Sistema</h1>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Información del Sistema -->
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-info-circle me-2"></i>Información del Sistema
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Software</h6>
                                <table class="table table-sm">
                                    <tr>
                                        <td>Versión del Sistema:</td>
                                        <td><?php echo htmlspecialchars($system_info['software_version']); ?></td>
                                    </tr>
                                    <tr>
                                        <td>Versión PHP:</td>
                                        <td><?php echo htmlspecialchars($system_info['php_version']); ?></td>
                                    </tr>
                                    <tr>
                                        <td>Base de Datos:</td>
                                        <td><?php echo htmlspecialchars($system_info['mysql_version']); ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6>Servidor</h6>
                                <table class="table table-sm">
                                    <tr>
                                        <td>Sistema Operativo:</td>
                                        <td><?php echo htmlspecialchars($system_info['server_os']); ?></td>
                                    </tr>
                                    <tr>
                                        <td>Uptime:</td>
                                        <td><?php echo htmlspecialchars($system_info['server_uptime']); ?></td>
                                    </tr>
                                    <tr>
                                        <td>Memoria PHP:</td>
                                        <td><?php echo htmlspecialchars($system_info['php_memory_usage']); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <hr>

                        <div class="row">
                            <div class="col-md-6">
                                <h6>Espacio en Disco</h6>
                                <div class="mb-3">
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar bg-<?php echo $system_info['disk_usage']['percentage'] > 90 ? 'danger' : ($system_info['disk_usage']['percentage'] > 70 ? 'warning' : 'success'); ?>"
                                             role="progressbar"
                                             style="width: <?php echo $system_info['disk_usage']['percentage']; ?>%"
                                             aria-valuenow="<?php echo $system_info['disk_usage']['percentage']; ?>"
                                             aria-valuemin="0"
                                             aria-valuemax="100">
                                            <?php echo $system_info['disk_usage']['percentage']; ?>
                                        </div>
                                    </div>
                                    <small class="text-muted">
                                        Usado: <?php echo $system_info['disk_usage']['used']; ?> /
                                        Total: <?php echo $system_info['disk_usage']['total']; ?> /
                                        Libre: <?php echo $system_info['disk_usage']['free']; ?>
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6>Base de Datos</h6>
                                <table class="table table-sm">
                                    <tr>
                                        <td>Tablas:</td>
                                        <td><?php echo htmlspecialchars($system_info['db_stats']['tables_count']); ?></td>
                                    </tr>
                                    <tr>
                                        <td>Registros Totales:</td>
                                        <td><?php echo htmlspecialchars($system_info['db_stats']['total_records']); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Acciones del Sistema -->
            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-gear me-2"></i>Acciones del Sistema
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="post" class="mb-3">
                            <button type="submit" name="create_backup" class="btn btn-primary w-100 mb-2">
                                <i class="bi bi-download me-2"></i>Crear Respaldo de Base de Datos
                            </button>
                        </form>

                        <div class="alert alert-info">
                            <small>
                                <i class="bi bi-info-circle me-1"></i>
                                Los respaldos se guardan automáticamente en el servidor y pueden ser descargados desde la lista de respaldos.
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Lista de Respaldos Recientes -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-clock-history me-2"></i>Respaldos Recientes
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php
                        $backup_dir = __DIR__ . '/../backups';
                        if (is_dir($backup_dir)) {
                            $files = glob($backup_dir . '/payroll_backup_*.sql');
                            rsort($files); // Ordenar por fecha descendente

                            if (!empty($files)) {
                                echo '<div class="list-group list-group-flush">';
                                foreach (array_slice($files, 0, 5) as $file) {
                                    $filename = basename($file);
                                    $filedate = date('d/m/Y H:i', filemtime($file));
                                    $filesize = round(filesize($file) / 1024, 2) . ' KB';
                                    echo "<a href='download_backup.php?file=" . urlencode($filename) . "' class='list-group-item list-group-item-action d-flex justify-content-between align-items-center'>";
                                    echo "<div><i class='bi bi-file-earmark-zip me-2'></i>{$filename}<br><small class='text-muted'>{$filedate}</small></div>";
                                    echo "<span class='badge bg-primary'>{$filesize}</span>";
                                    echo "</a>";
                                }
                                echo '</div>';
                            } else {
                                echo '<p class="text-muted mb-0">No hay respaldos disponibles</p>';
                            }
                        } else {
                            echo '<p class="text-muted mb-0">Directorio de respaldos no encontrado</p>';
                        }
                        ?>
                    </div>
                </div>

                <!-- Importar Base de Datos -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-upload me-2"></i>Importar Base de Datos
                        </h6>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">
                            Importe datos desde un archivo SQL para restaurar o actualizar la base de datos.
                        </p>
                        <a href="<?php echo getBaseUrl(); ?>import_database.php" class="btn btn-outline-primary w-100">
                            <i class="bi bi-file-earmark-arrow-up me-2"></i>Ir a Importar Base de Datos
                        </a>
                        <div class="alert alert-warning mt-3">
                            <small>
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                Esta acción puede sobrescribir datos existentes. Use con precaución.
                            </small>
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