<?php
// public/users.php (CRUD de Gestión de Usuarios)
require_once __DIR__ . '/../includes/auth.php'; // Incluir el archivo de autenticación y settings.php

// Requerir que el usuario esté logueado y tenga rol de 'admin'
requireRole([ROLE_ADMIN]);

$page_title = 'Gestión de Usuarios';
$message = '';
$message_type = ''; // 'success' or 'danger'

$pdo = getDbConnection();

$user_data = [
    'id' => null,
    'username' => '',
    'role' => ROLE_ASSISTANT, // Default role for new users
    'password' => '', // Only for new user creation or update
    'confirm_password' => '' // Only for new user creation or update
];

// Lógica para procesar la eliminación de un usuario
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $user_id_to_delete = $_GET['id'];

    // Evitar que un administrador se elimine a sí mismo
    if ($user_id_to_delete === $_SESSION['user_id']) {
        $message = 'No puedes eliminar tu propia cuenta de usuario.';
        $message_type = 'danger';
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
            $stmt->bindParam(':id', $user_id_to_delete, PDO::PARAM_INT);
            if ($stmt->execute()) {
                $message = 'Usuario eliminado exitosamente.';
                $message_type = 'success';
            } else {
                $message = 'Error al eliminar el usuario.';
                $message_type = 'danger';
            }
        } catch (PDOException $e) {
            $message = 'Error de base de datos al eliminar usuario: ' . htmlspecialchars($e->getMessage());
            $message_type = 'danger';
        }
    }
    // Redirigir para limpiar los parámetros GET de la URL
    header('Location: ' . getBaseUrl() . 'users.php?message=' . urlencode($message) . '&type=' . urlencode($message_type));
    exit();
}

// Lógica para procesar el reseteo de contraseña
if (isset($_GET['action']) && $_GET['action'] === 'reset_password' && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $user_id_to_reset = $_GET['id'];

    try {
        // Generar una nueva contraseña temporal (8 caracteres alfanuméricos)
        $new_password = substr(md5(uniqid(mt_rand(), true)), 0, 8);
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("UPDATE users SET password = :password WHERE id = :id");
        $stmt->bindParam(':password', $hashed_password);
        $stmt->bindParam(':id', $user_id_to_reset, PDO::PARAM_INT);

        if ($stmt->execute()) {
            $message = 'Contraseña reseteada exitosamente. La nueva contraseña temporal es: <strong>' . htmlspecialchars($new_password) . '</strong><br>Por favor, informa al usuario de esta contraseña y recomiéndale cambiarla inmediatamente.';
            $message_type = 'success';
        } else {
            $message = 'Error al resetear la contraseña.';
            $message_type = 'danger';
        }
    } catch (PDOException $e) {
        $message = 'Error de base de datos al resetear contraseña: ' . htmlspecialchars($e->getMessage());
        $message_type = 'danger';
    }
    // Redirigir para limpiar los parámetros GET de la URL
    header('Location: ' . getBaseUrl() . 'users.php?message=' . urlencode($message) . '&type=' . urlencode($message_type));
    exit();
}

// Lógica para cargar datos si se está editando un usuario o si se va a añadir uno nuevo
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $user_id = $_GET['id'];
    try {
        $stmt = $pdo->prepare("SELECT id, username, role FROM users WHERE id = :id");
        $stmt->bindParam(':id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        $fetched_user = $stmt->fetch();

        if ($fetched_user) {
            $user_data['id'] = $fetched_user['id'];
            $user_data['username'] = $fetched_user['username'];
            $user_data['role'] = $fetched_user['role'];
            $page_title = 'Editar Usuario';
        } else {
            $message = 'Usuario no encontrado.';
            $message_type = 'danger';
        }
    } catch (PDOException $e) {
        $message = 'Error al cargar los datos del usuario: ' . htmlspecialchars($e->getMessage());
        $message_type = 'danger';
    }
} elseif (isset($_GET['add'])) {
    $page_title = 'Añadir Nuevo Usuario';
}

// Lógica para procesar el formulario cuando se envía (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id_post = $_POST['id'] ?? null;
    $username = trim($_POST['username'] ?? '');
    $role = $_POST['role'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Update user_data array to retain values in case of error
    $user_data['username'] = $username;
    $user_data['role'] = $role;

    // Validations
    if (empty($username) || empty($role)) {
        $message = 'Por favor, complete todos los campos obligatorios.';
        $message_type = 'danger';
    } elseif (!in_array($role, [ROLE_ADMIN, ROLE_ASSISTANT, ROLE_READ_ONLY])) { // Usar ROLE_READ_ONLY
        $message = 'Rol de usuario inválido.';
        $message_type = 'danger';
    } elseif (!$user_id_post && (empty($password) || empty($confirm_password))) { // New user, password required
        $message = 'Para un nuevo usuario, la contraseña y su confirmación son obligatorias.';
        $message_type = 'danger';
    } elseif (!$user_id_post && ($password !== $confirm_password)) { // New user, passwords must match
        $message = 'Las contraseñas no coinciden.';
        $message_type = 'danger';
    } elseif ($user_id_post && !empty($password) && ($password !== $confirm_password)) { // Existing user, password update
        $message = 'Las contraseñas no coinciden.';
        $message_type = 'danger';
    } else {
        try {
            if ($user_id_post) {
                // Update existing user
                $query = "UPDATE users SET username = :username, role = :role";
                $params = [
                    ':username' => $username,
                    ':role' => $role,
                    ':id' => $user_id_post
                ];

                if (!empty($password)) { // Only update password if provided
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $query .= ", password = :password";
                    $params[':password'] = $hashed_password;
                }
                $query .= " WHERE id = :id";
                $stmt = $pdo->prepare($query);

                if ($stmt->execute($params)) {
                    $message = 'Usuario actualizado exitosamente.';
                    $message_type = 'success';
                } else {
                    $message = 'Error al actualizar el usuario.';
                    $message_type = 'danger';
                }
            } else {
                // Insert new user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (:username, :password, :role)");
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':password', $hashed_password);
                $stmt->bindParam(':role', $role);

                if ($stmt->execute()) {
                    $message = 'Nuevo usuario añadido exitosamente.';
                    $message_type = 'success';
                    // Clear form for new entry
                    $user_data = [
                        'id' => null, 'username' => '', 'role' => ROLE_ASSISTANT,
                        'password' => '', 'confirm_password' => ''
                    ];
                } else {
                    $message = 'Error al añadir el nuevo usuario.';
                    $message_type = 'danger';
                }
            }
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') { // Duplicate entry error
                $message = 'Error: El nombre de usuario ya existe.';
            } else {
                $message = 'Error de base de datos: ' . htmlspecialchars($e->getMessage());
            $message_type = 'danger';
            }
        }
    }
}

// Obtener mensajes de la redirección después de eliminar
if (isset($_GET['message']) && isset($_GET['type'])) {
    $message = htmlspecialchars($_GET['message']);
    $message_type = htmlspecialchars($_GET['type']);
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
    <!-- Estilos específicos de la página de gestión de usuarios -->
    <link href="./assets/css/users.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; // Incluir la barra de navegación ?>

    <div class="container mt-4">
        <h1 class="mb-4"><?php echo $page_title; ?></h1>

        <div class="card mb-4">
            <div class="card-header">
                <?php echo ($user_data['id'] ? 'Editar Usuario' : 'Añadir Nuevo Usuario'); ?>
            </div>
            <div class="card-body">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?>" role="alert">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($user_data['id']); ?>">

                    <div class="mb-3">
                        <label for="username" class="form-label">Nombre de Usuario</label>
                        <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($user_data['username']); ?>" required <?php echo ($user_data['id'] ? 'readonly' : ''); ?>>
                        <?php if ($user_data['id']): ?>
                            <div class="form-text">El nombre de usuario no se puede cambiar.</div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="role" class="form-label">Rol</label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="<?php echo ROLE_ADMIN; ?>" <?php echo ($user_data['role'] === ROLE_ADMIN ? 'selected' : ''); ?>>Administrador</option>
                            <option value="<?php echo ROLE_ASSISTANT; ?>" <?php echo ($user_data['role'] === ROLE_ASSISTANT ? 'selected' : ''); ?>>Asistente</option>
                            <option value="<?php echo ROLE_READ_ONLY; ?>" <?php echo ($user_data['role'] === ROLE_READ_ONLY ? 'selected' : ''); ?>>Solo Lectura (Empleado)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Contraseña <?php echo ($user_data['id'] ? '(dejar en blanco para no cambiar)' : ''); ?></label>
                        <input type="password" class="form-control" id="password" name="password">
                    </div>

                    <div class="mb-4">
                        <label for="confirm_password" class="form-label">Confirmar Contraseña</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                    </div>

                    <div class="d-flex justify-content-between">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-save me-1"></i> <?php echo ($user_data['id'] ? 'Actualizar' : 'Guardar'); ?> Usuario
                        </button>
                        <a href="<?php echo getBaseUrl(); ?>users.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left-circle me-1"></i> Volver al Listado
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mt-5">
            <div class="card-header d-flex justify-content-between align-items-center">
                Listado de Usuarios
                <a href="<?php echo getBaseUrl(); ?>users.php?add=1" class="btn btn-primary btn-sm">
                    <i class="bi bi-person-plus me-1"></i> Añadir Nuevo Usuario
                </a>
            </div>
            <div class="card-body">
                <?php
                $users = [];
                try {
                    $stmt = $pdo->query("SELECT id, username, role FROM users ORDER BY username ASC");
                    $users = $stmt->fetchAll();
                } catch (PDOException $e) {
                    echo '<div class="alert alert-danger" role="alert">Error al cargar los usuarios: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }

                if (empty($users)): ?>
                    <div class="alert alert-info" role="alert">
                        No hay usuarios registrados aún.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Usuario</th>
                                    <th>Rol</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['id']); ?></td>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $user['role']))); ?></td>
                                        <td>
                                            <a href="<?php echo getBaseUrl(); ?>users.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-info text-white me-1" title="Editar">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-warning text-white me-1" title="Resetear Contraseña" onclick="showResetConfirm(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                <i class="bi bi-key"></i>
                                            </button>
                                            <?php if ($user['id'] !== $_SESSION['user_id']): // Prevenir que el usuario se elimine a sí mismo ?>
                                            <button type="button" class="btn btn-sm btn-danger" title="Eliminar" onclick="showDeleteConfirm(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                            <?php endif; ?>
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

    <!-- Modal de Confirmación de Reseteo de Contraseña -->
    <div class="modal fade" id="resetConfirmModal" tabindex="-1" aria-labelledby="resetConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="resetConfirmModalLabel">
                        <i class="bi bi-key-fill me-2 text-warning"></i>Confirmar Reseteo de Contraseña
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <i class="bi bi-exclamation-triangle text-warning" style="font-size: 3rem;"></i>
                    </div>
                    <p class="text-center mb-3">
                        ¿Estás seguro de que deseas resetear la contraseña del usuario <strong id="usernameToReset"></strong>?
                    </p>
                    <div class="alert alert-warning" role="alert">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Nota:</strong> Se generará una nueva contraseña temporal de 8 caracteres alfanuméricos. Deberás comunicar esta contraseña al usuario para que pueda acceder y cambiarla posteriormente.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancelar
                    </button>
                    <a href="#" id="confirmResetButton" class="btn btn-warning">
                        <i class="bi bi-key me-1"></i>Resetear Contraseña
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; // Incluir el pie de página ?>
    <!-- Incluir Bootstrap JS (Bundle con Popper) directamente con ruta relativa -->
    <script src="./assets/js/bootstrap.bundle.min.js"></script>
    <!-- Scripts personalizados (si los hay) directamente con ruta relativa -->
    <script src="./assets/js/script.js"></script>

    <!-- Modal de Confirmación de Eliminación -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteConfirmModalLabel">Confirmar Eliminación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    ¿Estás seguro de que deseas eliminar al usuario <strong id="usernameToDelete"></strong>? Esta acción no se puede deshacer.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <a href="#" id="confirmDeleteButton" class="btn btn-danger">Eliminar</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Función para mostrar el modal de confirmación de eliminación
        function showDeleteConfirm(userId, username) {
            document.getElementById('usernameToDelete').innerText = username;
            const deleteButton = document.getElementById('confirmDeleteButton');
            deleteButton.href = `<?php echo getBaseUrl(); ?>users.php?action=delete&id=${userId}`;
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
            deleteModal.show();
        }

        // Función para mostrar el modal de confirmación de reseteo de contraseña
        function showResetConfirm(userId, username) {
            document.getElementById('usernameToReset').innerText = username;
            const resetButton = document.getElementById('confirmResetButton');
            resetButton.href = `<?php echo getBaseUrl(); ?>users.php?action=reset_password&id=${userId}`;
            const resetModal = new bootstrap.Modal(document.getElementById('resetConfirmModal'));
            resetModal.show();
        }
    </script>
</body>
</html>
