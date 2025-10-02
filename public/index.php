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
    <!-- Incluir Bootstrap CSS -->
    <link href="./assets/css/bootstrap.min.css" rel="stylesheet">
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
                        <a href="#">¿Olvidaste tu contraseña?</a>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Iniciar Sesión</button>
                <div class="create-account">
                    <span>¿No tiene una cuenta? <a href="#">Crear Cuenta</a></span>
                </div>
            </form>
        </div>
    </div>

    <!-- Incluir Bootstrap JS (Bundle con Popper) -->
    <script src="./assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
