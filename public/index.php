<?php
// public/index.php (Ahora es la página de login)
require_once __DIR__ . '/../includes/auth.php'; // Incluir auth.php para funciones de sesión y base url

// Si el usuario ya está logueado, redireccionar al dashboard
if (isUserLoggedIn()) {
    header('Location: ' . getBaseUrl() . 'dashboard.php'); // Redirige al nuevo dashboard
    exit();
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validaciones básicas de entrada
    if (empty($username) || empty($password)) {
        $error_message = 'Por favor, ingrese su usuario y contraseña.';
    } else {
        $pdo = getDbConnection(); // Obtener la conexión a la base de datos

        // Preparar la consulta para evitar inyección SQL
        $stmt = $pdo->prepare("SELECT id, username, password, role FROM users WHERE username = :username");
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Credenciales válidas: iniciar sesión
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_role'] = $user['role'];

            // Redireccionar al dashboard después del login exitoso
            header('Location: ' . getBaseUrl() . 'dashboard.php');
            exit();
        } else {
            $error_message = 'Usuario o contraseña incorrectos.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Sistema de Nómina</title>
    <!-- Incluir Bootstrap CSS (desde CDN para asegurar la versión correcta) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Estilos nuevos para el login -->
    <link href="./assets/css/new_login.css" rel="stylesheet">
</head>
<body>
    <div class="login-wrapper">
        <div class="login-image-section">
            <!-- La imagen de fondo se establece por CSS -->
        </div>
        <div class="login-form-section">
            <div class="login-logo">
                <img src="./assets/img/logo.png" alt="Logo">
            </div>
            <div class="login-header">
                <h1>¡Hola! Estas de vuelta</h1>
            </div>

            <?php if ($error_message): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Nombre de usuario</label>
                    <input type="text" class="form-control" id="username" name="username" required autocomplete="username" placeholder="Nombre de usuario">
                </div>
                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
                </div>
                <div class="form-options">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="rememberMe">
                        <label class="form-check-label" for="rememberMe">Recordarme</label>
                    </div>
                    <div class="forgot-password">
                        <a href="#" data-bs-toggle="modal" data-bs-target="#forgotPasswordModal">¿Olvidaste tu contraseña?</a>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Iniciar Sesión</button>
            </form>
        </div>
    </div>

    <!-- Modal para "¿Olvidaste tu contraseña?" -->
    <div class="modal fade" id="forgotPasswordModal" tabindex="-1" aria-labelledby="forgotPasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="forgotPasswordModalLabel">
                        <i class="bi bi-key-fill me-2"></i>Recuperación de Contraseña
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <i class="bi bi-shield-lock text-primary" style="font-size: 3rem;"></i>
                    </div>
                    <h6 class="text-center mb-3">¿Olvidaste tu contraseña?</h6>
                    <p class="text-muted mb-3">
                        Para recuperar el acceso a tu cuenta, debes contactar al administrador del sistema.
                    </p>
                    <div class="alert alert-info" role="alert">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Importante:</strong> El administrador generará una nueva contraseña temporal que podrás usar para acceder y cambiar posteriormente.
                    </div>
                    <div class="d-grid">
                        <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">
                            <i class="bi bi-check-circle me-2"></i>Entendido
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Incluir Bootstrap JS (Bundle con Popper) desde CDN para asegurar la versión correcta) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <!-- Script personalizado -->
    <script src="./assets/js/script.js"></script>
    <!-- Script para depuración (comentado) -->
    <!-- <script>
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                var forgotPasswordModal = new bootstrap.Modal(document.getElementById('forgotPasswordModal'));
                // forgotPasswordModal.show(); // Comentado para que el modal solo se abra al hacer clic
            }, 500);
        });
    </script> -->
</body>
</html>
