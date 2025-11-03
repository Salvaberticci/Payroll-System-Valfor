<?php
// public/payroll_concepts.php
require_once __DIR__ . '/../includes/auth.php'; // Incluir el archivo de autenticación
require_once __DIR__ . '/../config/settings.php'; // Para getDbConnection() y roles

// Requerir que el usuario esté logueado y tenga rol de 'admin'
requireRole([ROLE_ADMIN]);

$page_title = 'Gestión de Conceptos de Nómina';
$message = '';
$message_type = ''; // 'success' or 'danger'

$pdo = getDbConnection();

$concept = [
    'id' => null,
    'name' => '',
    'type' => '',
    'calculation_type' => '',
    'default_value' => '',
    'applies_to_all' => 1, // Por defecto, aplica a todos
    'is_active' => 1 // Por defecto, activo
];

// Lógica para cargar datos si se está editando un concepto
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $concept_id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("SELECT id, name, type, calculation_type, default_value, applies_to_all, is_active FROM payroll_concepts WHERE id = :id");
        $stmt->bindParam(':id', $concept_id, PDO::PARAM_INT);
        $stmt->execute();
        $fetched_concept = $stmt->fetch();

        if ($fetched_concept) {
            $concept = $fetched_concept;
            $page_title = 'Editar Concepto de Nómina';
        } else {
            $message = 'Concepto no encontrado.';
            $message_type = 'danger';
        }
    } catch (PDOException $e) {
        $message = 'Error al cargar los datos del concepto: ' . htmlspecialchars($e->getMessage());
        $message_type = 'danger';
    }
}

// Lógica para procesar el formulario cuando se envía (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $concept_id = $_POST['id'] ?? null;
    $name = trim($_POST['name'] ?? '');
    $type = $_POST['type'] ?? '';
    $calculation_type = $_POST['calculation_type'] ?? '';
    $default_value = trim($_POST['default_value'] ?? '');
    $applies_to_all = isset($_POST['applies_to_all']) ? 1 : 0;
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Actualizar los datos del array $concept para que el formulario los retenga en caso de error
    $concept['name'] = $name;
    $concept['type'] = $type;
    $concept['calculation_type'] = $calculation_type;
    $concept['default_value'] = $default_value;
    $concept['applies_to_all'] = $applies_to_all;
    $concept['is_active'] = $is_active;

    // Validaciones
    if (empty($name) || empty($type) || empty($calculation_type)) {
        $message = 'Por favor, complete todos los campos obligatorios (Nombre, Tipo, Tipo de Cálculo).';
        $message_type = 'danger';
    } elseif (!in_array($type, ['ingreso', 'deduccion_legal', 'deduccion_personal', 'beneficio'])) {
        $message = 'Tipo de concepto inválido.';
        $message_type = 'danger';
    } elseif (!in_array($calculation_type, ['fixed_value', 'percentage_of_salary', 'per_day_value', 'manual_input'])) {
        $message = 'Tipo de cálculo inválido.';
        $message_type = 'danger';
    } elseif (!empty($default_value) && !is_numeric(str_replace(',', '.', $default_value))) {
        $message = 'El valor por defecto debe ser un número válido.';
        $message_type = 'danger';
    } else {
        // Convertir default_value a float si no está vacío
        $default_value_float = !empty($default_value) ? (float)str_replace(',', '.', $default_value) : null;

        try {
            if ($concept_id) {
                // Actualizar concepto existente
                $stmt = $pdo->prepare("UPDATE payroll_concepts SET name = :name, type = :type, calculation_type = :calculation_type, default_value = :default_value, applies_to_all = :applies_to_all, is_active = :is_active WHERE id = :id");
                $stmt->bindParam(':id', $concept_id, PDO::PARAM_INT);
            } else {
                // Insertar nuevo concepto
                $stmt = $pdo->prepare("INSERT INTO payroll_concepts (name, type, calculation_type, default_value, applies_to_all, is_active) VALUES (:name, :type, :calculation_type, :default_value, :applies_to_all, :is_active)");
            }

            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':type', $type);
            $stmt->bindParam(':calculation_type', $calculation_type);
            $stmt->bindParam(':default_value', $default_value_float);
            $stmt->bindParam(':applies_to_all', $applies_to_all, PDO::PARAM_INT);
            $stmt->bindParam(':is_active', $is_active, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $message = $concept_id ? 'Concepto actualizado exitosamente.' : 'Nuevo concepto añadido exitosamente.';
                $message_type = 'success';
                // Si se añadió uno nuevo, limpiar el formulario para añadir otro
                if (!$concept_id) {
                    $concept = [
                        'id' => null, 'name' => '', 'type' => '', 'calculation_type' => '',
                        'default_value' => '', 'applies_to_all' => 1, 'is_active' => 1
                    ];
                }
            } else {
                $message = $concept_id ? 'Error al actualizar el concepto.' : 'Error al añadir el nuevo concepto.';
                $message_type = 'danger';
            }
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') { // Código para error de integridad (nombre duplicado)
                $message = 'Error: El nombre del concepto ya existe.';
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
    <!-- Estilos específicos de la página de conceptos de nómina -->
    <link href="./assets/css/payroll_concepts.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; // Incluir la barra de navegación ?>

    <div class="container mt-4">
        <h1 class="mb-4"><?php echo $page_title; ?></h1>

        <div class="card mb-4">
            <div class="card-header">
                Detalles del Concepto
            </div>
            <div class="card-body">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>" role="alert">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($concept['id']); ?>">

                    <div class="mb-3">
                        <label for="name" class="form-label">Nombre del Concepto</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($concept['name']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="type" class="form-label">Tipo</label>
                        <select class="form-select" id="type" name="type" required>
                            <option value="">Seleccione un tipo</option>
                            <option value="ingreso" <?php echo ($concept['type'] === 'ingreso' ? 'selected' : ''); ?>>Ingreso</option>
                            <option value="deduccion_legal" <?php echo ($concept['type'] === 'deduccion_legal' ? 'selected' : ''); ?>>Deducción Legal</option>
                            <option value="deduccion_personal" <?php echo ($concept['type'] === 'deduccion_personal' ? 'selected' : ''); ?>>Deducción Personal</option>
                            <option value="beneficio" <?php echo ($concept['type'] === 'beneficio' ? 'selected' : ''); ?>>Beneficio</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="calculation_type" class="form-label">Tipo de Cálculo</label>
                        <select class="form-select" id="calculation_type" name="calculation_type" required>
                            <option value="">Seleccione un tipo de cálculo</option>
                            <option value="fixed_value" <?php echo ($concept['calculation_type'] === 'fixed_value' ? 'selected' : ''); ?>>Valor Fijo</option>
                            <option value="percentage_of_salary" <?php echo ($concept['calculation_type'] === 'percentage_of_salary' ? 'selected' : ''); ?>>Porcentaje del Salario</option>
                            <option value="per_day_value" <?php echo ($concept['calculation_type'] === 'per_day_value' ? 'selected' : ''); ?>>Valor por Día</option>
                            <option value="manual_input" <?php echo ($concept['calculation_type'] === 'manual_input' ? 'selected' : ''); ?>>Entrada Manual</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="default_value" class="form-label">Valor por Defecto (opcional)</label>
                        <input type="text" class="form-control" id="default_value" name="default_value" value="<?php echo htmlspecialchars($concept['default_value']); ?>" placeholder="Ej: 0.04 para 4% o 50.00 para $50">
                        <div class="form-text">Para porcentajes, use decimales (ej. 0.04 para 4%). Para valores fijos, use el monto.</div>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="applies_to_all" name="applies_to_all" <?php echo ($concept['applies_to_all'] ? 'checked' : ''); ?>>
                        <label class="form-check-label" for="applies_to_all">Aplica a todos los empleados por defecto</label>
                    </div>

                    <div class="mb-4 form-check">
                        <input type="checkbox" class="form-check-input" id="is_active" name="is_active" <?php echo ($concept['is_active'] ? 'checked' : ''); ?>>
                        <label class="form-check-label" for="is_active">Concepto Activo</label>
                    </div>

                    <div class="d-flex justify-content-between">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-save me-1"></i> <?php echo ($concept['id'] ? 'Actualizar' : 'Guardar'); ?> Concepto
                        </button>
                        <a href="<?php echo getBaseUrl(); ?>payroll_concepts.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left-circle me-1"></i> Volver al Listado
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mt-5">
            <div class="card-header d-flex justify-content-between align-items-center">
                Conceptos de Nómina Existentes
                <button type="button" class="btn btn-info btn-sm" onclick="printPayrollConceptsPDF()">
                    <i class="bi bi-file-earmark-pdf me-1"></i> Descargar PDF
                </button>
            </div>
            <div class="card-body">
                <?php
                $concepts = [];
                try {
                    $stmt = $pdo->query("SELECT id, name, type, calculation_type, default_value, applies_to_all, is_active FROM payroll_concepts ORDER BY name ASC");
                    $concepts = $stmt->fetchAll();
                } catch (PDOException $e) {
                    echo '<div class="alert alert-danger" role="alert">Error al cargar los conceptos: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }

                if (empty($concepts)): ?>
                    <div class="alert alert-info" role="alert">
                        No hay conceptos de nómina registrados aún.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Tipo</th>
                                    <th>Cálculo</th>
                                    <th>Valor Defecto</th>
                                    <th>Aplica a Todos</th>
                                    <th>Activo</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($concepts as $c): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($c['name']); ?></td>
                                        <td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $c['type']))); ?></td>
                                        <td><?php
                                            $calculation_type_spanish = [
                                                'fixed_value' => 'Valor Fijo',
                                                'percentage_of_salary' => 'Porcentaje del Salario',
                                                'per_day_value' => 'Valor por Día',
                                                'manual_input' => 'Entrada Manual'
                                            ];
                                            echo htmlspecialchars($calculation_type_spanish[$c['calculation_type']] ?? $c['calculation_type']);
                                        ?></td>
                                        <td><?php echo !is_null($c['default_value']) ? htmlspecialchars(number_format($c['default_value'], 4)) : 'N/A'; ?></td>
                                        <td>
                                            <?php if ($c['applies_to_all']): ?>
                                                <span class="badge bg-success">Sí</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">No</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($c['is_active']): ?>
                                                <span class="badge bg-success">Sí</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">No</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="d-flex gap-1">
                                            <a href="<?php echo getBaseUrl(); ?>payroll_concepts.php?id=<?php echo $c['id']; ?>" class="btn btn-sm btn-info text-white" title="Editar">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-danger d-flex align-items-center justify-content-center" title="Eliminar" onclick="showDeleteModal(<?php echo $c['id']; ?>, '<?php echo htmlspecialchars($c['name']); ?>')" style="width: 38px; height: 31px;">
                                                <i class="bi bi-trash"></i>
                                            </button>
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

    <!-- Modal de confirmación de eliminación (solo JavaScript) -->
    <div id="deleteConceptModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 9999;">
        <div style="position: absolute; top: 70%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); max-width: 400px; width: 90%;">
            <div style="border-bottom: 1px solid #dee2e6; padding-bottom: 10px; margin-bottom: 15px;">
                <h5 style="margin: 0; color: #212529;">Confirmar Eliminación</h5>
            </div>
            <div style="margin-bottom: 20px;">
                <p id="deleteConceptMessage" style="margin: 0; color: #6c757d;">¿Está seguro de que desea eliminar este concepto? Esta acción no se puede deshacer.</p>
            </div>
            <div style="text-align: right;">
                <button type="button" id="cancelDeleteBtn" style="background: #6c757d; color: white; border: none; padding: 8px 16px; border-radius: 4px; margin-right: 10px; cursor: pointer;">Cancelar</button>
                <button type="button" id="confirmDeleteBtn" style="background: #dc3545; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;">Eliminar</button>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; // Incluir el pie de página ?>
    <!-- Incluir Bootstrap JS (Bundle con Popper) directamente con ruta relativa -->
    <script src="./assets/js/bootstrap.bundle.min.js"></script>
    <!-- Scripts personalizados (si los hay) directamente con ruta relativa -->
    <script src="./assets/js/script.js"></script>
    <script>
        let conceptToDelete = null;

        function printPayrollConceptsPDF() {
            window.open('<?php echo getBaseUrl(); ?>generate_payroll_concepts_pdf.php', '_blank');
        }

        function showDeleteModal(conceptId, conceptName) {
            conceptToDelete = conceptId;
            document.getElementById('deleteConceptMessage').textContent =
                '¿Está seguro de que desea eliminar el concepto "' + conceptName + '"? Esta acción no se puede deshacer.';
            document.getElementById('deleteConceptModal').style.display = 'block';
            document.body.style.overflow = 'hidden'; // Prevenir scroll del body
        }
    
        function hideDeleteModal() {
            document.getElementById('deleteConceptModal').style.display = 'none';
            document.body.style.overflow = ''; // Restaurar scroll del body
            conceptToDelete = null;
        }
    
        function confirmDelete() {
            if (conceptToDelete) {
                deleteConcept(conceptToDelete);
                // Modal will be hidden after AJAX response
            }
        }

        function deleteConcept(conceptId) {
            console.log('Starting delete for concept ID:', conceptId);

            // Mostrar indicador de carga
            var deleteBtn = document.getElementById('confirmDeleteBtn');
            var originalText = deleteBtn.textContent;
            deleteBtn.textContent = 'Eliminando...';
            deleteBtn.disabled = true;

            // Crear el objeto XMLHttpRequest
            var xhr = new XMLHttpRequest();

            // Configurar la solicitud
            xhr.open('POST', '<?php echo getBaseUrl(); ?>delete_payroll_concept.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

            // Manejar la respuesta
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    console.log('AJAX response received, status:', xhr.status);
                    console.log('Response text:', xhr.responseText);

                    // Restaurar botón
                    deleteBtn.textContent = originalText;
                    deleteBtn.disabled = false;

                    // Intentar parsear la respuesta JSON independientemente del status code
                    try {
                        var response = JSON.parse(xhr.responseText);
                        console.log('Parsed response:', response);

                        if (response.success) {
                            // Ocultar modal primero
                            hideDeleteModal();
                            // Mostrar mensaje de éxito
                            showAlert(response.message, 'success');
                            // Recargar la página para actualizar la tabla
                            setTimeout(function() {
                                window.location.reload();
                            }, 1500);
                        } else {
                            // Ocultar modal en caso de error de negocio (409, etc.)
                            hideDeleteModal();
                            showAlert('Error: ' + response.message, 'danger');
                        }
                    } catch (e) {
                        console.error('Error parsing JSON response:', e);
                        hideDeleteModal();
                        showAlert('Error al procesar la respuesta del servidor', 'danger');
                    }
                }
            };

            // Enviar la solicitud
            xhr.send('concept_id=' + encodeURIComponent(conceptId));
        }

        function showAlert(message, type) {
            // Crear elemento de alerta usando solo JavaScript/CSS
            var alertDiv = document.createElement('div');
            alertDiv.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px; padding: 15px; border-radius: 5px; color: white; font-weight: bold; box-shadow: 0 2px 4px rgba(0,0,0,0.2);';
            alertDiv.style.backgroundColor = type === 'success' ? '#28a745' : '#dc3545';
            alertDiv.setAttribute('role', 'alert');
            alertDiv.innerHTML = message + '<button type="button" onclick="this.parentNode.remove()" style="float: right; background: none; border: none; color: white; font-size: 20px; cursor: pointer; margin-left: 10px;">&times;</button>';
    
            // Agregar al body
            document.body.appendChild(alertDiv);
    
            // Auto-remover después de 5 segundos
            setTimeout(function() {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }

        // Inicializar eventos cuando el DOM esté listo
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('confirmDeleteBtn').addEventListener('click', confirmDelete);
            document.getElementById('cancelDeleteBtn').addEventListener('click', hideDeleteModal);
    
            // Cerrar modal al hacer clic fuera de él
            document.getElementById('deleteConceptModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    hideDeleteModal();
                }
            });
    
            // Cerrar modal con tecla Escape
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && document.getElementById('deleteConceptModal').style.display === 'block') {
                    hideDeleteModal();
                }
            });
        });
    </script>
</body>
</html>
