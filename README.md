# Sistema de Nómina

## Descripción

Sistema de gestión de nómina desarrollado en PHP para empresas. Permite calcular salarios quincenales, gestionar empleados, definir conceptos de pago y generar reportes analíticos. Soporta cálculos en USD y Bolívar Venezolano (Bs) utilizando la tasa de cambio del BCV.

## Características

- **Autenticación de Usuarios:** Sistema de login con roles (admin, assistant, read_only) para controlar accesos.
- **Gestión de Empleados:** Agregar, editar y listar empleados con información como cédula, nombre, fecha de ingreso, cargo y salario base mensual.
- **Conceptos de Nómina:** Definir y administrar diferentes conceptos de pago: ingresos, deducciones legales (SSO, SPF, FAOV), deducciones personales y beneficios (como Cesta Ticket).
- **Cálculo de Nómina:** Calcular períodos quincenales especificando fechas de inicio/fin, tasa BCV y días en el período. Automáticamente calcula deducciones legales y beneficios por empleado.
- **Reportes y Análisis:** Generar reportes por empleado, analíticos con totales agregados, reportes de descuentos y pagos realizados.
- **Interfaz Web Responsiva:** Basada en Bootstrap 5 para una experiencia de usuario moderna y adaptable a dispositivos móviles.

## Requisitos del Sistema

- PHP 8.2 o superior
- MySQL 5.7+ o MariaDB 10.0+
- Servidor web (Apache recomendado)
- XAMPP (para desarrollo local en Windows)
- Navegador web moderno con soporte para HTML5 y CSS3

## Instalación y Configuración Local con XAMPP

Sigue estos pasos para ejecutar el sistema en tu máquina local usando XAMPP:

### Paso 1: Instalar XAMPP
- Descarga e instala XAMPP desde el sitio oficial: [https://www.apachefriends.org/](https://www.apachefriends.org/).
- Asegúrate de seleccionar los componentes Apache, MySQL y PHP durante la instalación.

### Paso 2: Preparar el Proyecto
- Copia o clona la carpeta completa del proyecto (`payroll_system`) en el directorio `C:/xampp/htdocs/`.
- La estructura final debería ser: `C:/xampp/htdocs/payroll_system/`.

### Paso 3: Configurar la Base de Datos
- Inicia el Panel de Control de XAMPP y activa los módulos "Apache" y "MySQL".
- Abre tu navegador y ve a `http://localhost/phpmyadmin`.
- Crea una nueva base de datos llamada `payroll_db` (puedes usar la codificación UTF-8).
- Selecciona la base de datos `payroll_db` y ve a la pestaña "Importar".
- Elige el archivo `config/payroll_db.sql` desde tu proyecto y haz clic en "Continuar" para importar el esquema y datos iniciales.

### Paso 4: Configurar la Conexión a la Base de Datos
- Abre el archivo `config/settings.php` en un editor de texto.
- Verifica y ajusta las constantes de conexión si es necesario:
  ```php
  define('DB_HOST', 'localhost');
  define('DB_NAME', 'payroll_db');
  define('DB_USER', 'root');  // Cambia si usas un usuario diferente
  define('DB_PASS', '');      // Cambia si tienes contraseña
  ```
- Por defecto, XAMPP usa 'root' sin contraseña, pero ajusta según tu configuración.

### Paso 5: Ejecutar el Sistema
- Asegúrate de que Apache y MySQL estén ejecutándose en el Panel de Control de XAMPP.
- Abre tu navegador y ve a: `http://localhost/payroll_system/public/index.php`.
- Inicia sesión con las credenciales por defecto:
  - Usuario: `admin`
  - Contraseña: `admin`
- Una vez dentro, puedes cambiar la contraseña del usuario admin desde la gestión de usuarios (solo accesible para admin).

## Uso del Sistema

### Navegación General
- **Página de Login:** Acceso inicial al sistema.
- **Dashboard:** Página principal con enlaces rápidos a las diferentes secciones según el rol del usuario.

### Funcionalidades por Rol

#### Administrador (admin)
- Acceso completo a todas las funciones.
- Gestionar usuarios del sistema.
- Calcular nómina y definir conceptos.

#### Asistente (assistant)
- Gestionar empleados.
- Calcular nómina.
- Ver reportes y conceptos.

#### Solo Lectura (read_only)
- Ver reportes y listados sin posibilidad de modificar datos.

### Operaciones Comunes
1. **Agregar Empleados:** Ve a "Gestión de Empleados" > "Añadir Nuevo Empleado" e ingresa los datos requeridos.
2. **Calcular Nómina:** En "Calcular Nómina", define el período, tasa BCV y días, luego calcula.
3. **Ver Reportes:** Accede a "Reportes por Empleado" o "Reportes Estadísticos" para análisis.
4. **Administrar Conceptos:** En "Conceptos de Nómina", añade o edita tipos de pago.

## Estructura del Proyecto

```
payroll_system/
├── config/
│   ├── payroll_db.sql    # Esquema y datos iniciales de la BD
│   └── settings.php      # Configuraciones de conexión y constantes
├── includes/
│   ├── auth.php          # Funciones de autenticación y autorización
│   ├── header.php        # Cabecera común de las páginas
│   └── footer.php        # Pie de página común
└── public/
    ├── index.php         # Página de login
    ├── dashboard.php     # Dashboard principal
    ├── employees.php     # Gestión de empleados
    ├── employees_form.php # Formulario para empleados
    ├── payroll_calc.php  # Cálculo de nómina
    ├── payroll_concepts.php # Gestión de conceptos
    ├── payroll_details.php # Detalles de períodos calculados
    ├── reports_analytics.php # Reportes analíticos
    ├── reports_employee.php # Reportes por empleado
    ├── reports_discounts.php # Reportes de descuentos
    ├── reports_paid.php  # Reportes de pagos realizados
    ├── users.php         # Gestión de usuarios (solo admin)
    ├── logout.php        # Cerrar sesión
    └── assets/           # Recursos estáticos
        ├── css/          # Hojas de estilo
        ├── js/           # Scripts JavaScript
        └── img/          # Imágenes (logo, etc.)
```

## Tecnologías Utilizadas

- **Backend:** PHP 8.2+ con PDO para conexiones a BD
- **Base de Datos:** MySQL/MariaDB
- **Frontend:** HTML5, CSS3, Bootstrap 5, JavaScript
- **Seguridad:** Sesiones PHP, hash de contraseñas (password_hash)
- **Arquitectura:** MVC simplificado, separación de lógica y presentación

## Consideraciones de Seguridad

- Las contraseñas se almacenan hasheadas usando `password_hash()`.
- Validación de entrada en formularios para prevenir inyección SQL y XSS.
- Control de acceso basado en roles para proteger funcionalidades sensibles.
- **Importante:** Cambia las credenciales por defecto y configura un usuario MySQL dedicado en producción.

## Solución de Problemas

- **Error de conexión a BD:** Verifica que MySQL esté ejecutándose y que las credenciales en `settings.php` sean correctas.
- **Página en blanco:** Asegúrate de que PHP esté habilitado y que la ruta del proyecto sea correcta.
- **Errores de permisos:** Verifica que los archivos tengan permisos de lectura/escritura adecuados.
- **Problemas con fechas:** Asegúrate de que el formato de fecha sea compatible (YYYY-MM-DD).

## Contribución

Si deseas contribuir al proyecto:
1. Haz fork del repositorio.
2. Crea una rama para tu feature (`git checkout -b feature/nueva-funcionalidad`).
3. Realiza tus cambios y haz commit (`git commit -am 'Agrega nueva funcionalidad'`).
4. Push a la rama (`git push origin feature/nueva-funcionalidad`).
5. Abre un Pull Request.

## Licencia

Este proyecto está bajo la Licencia MIT. Consulta el archivo LICENSE para más detalles.

## Soporte

Para soporte o preguntas, por favor contacta al desarrollador o abre un issue en el repositorio.