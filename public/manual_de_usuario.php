<?php
// public/manual_de_usuario.php
require_once __DIR__ . '/../includes/auth.php'; // Incluir el archivo de autenticación

// Requerir que el usuario esté logueado
requireLogin();

// Definir el título de la página
$page_title = 'Manual de Usuario - Sistema de Nómina';
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
    <!-- Estilos específicos del manual de usuario -->
    <link href="./assets/css/manual_de_usuario_content.css" rel="stylesheet">
    <!-- Estilos específicos de la página del dashboard directamente con ruta relativa -->
    <link href="./assets/css/dashboard.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../includes/header.php'; // Incluir la barra de navegación ?>

    <div class="manual-container">
        <div class="manual-header">
            <h1>📚 Manual de Usuario</h1>
            <p>Sistema de Nómina - Guía Completa para Usuarios</p>
        </div>

        <section id="descripcion-general" class="section-card mb-5">
            <div class="section-icon">🏢</div>
            <h2>1. Descripción General del Sistema</h2>
            <p>El Sistema de Nómina es una herramienta robusta desarrollada en PHP para la gestión eficiente de la nómina en empresas. Permite el cálculo de salarios quincenales, la administración de empleados, la definición de conceptos de pago y la generación de reportes analíticos. Soporta cálculos tanto en USD como en Bolívar Venezolano (Bs), utilizando la tasa de cambio oficial del Banco Central de Venezuela (BCV).</p>
            <p>Este sistema está diseñado para simplificar las tareas administrativas relacionadas con la nómina, asegurando precisión y cumplimiento con las regulaciones laborales.</p>

            <div class="feature-list">
                <div class="feature-item">
                    <h5>🎯 Precisión Garantizada</h5>
                    <p>Cálculos automáticos que eliminan errores humanos en las nóminas.</p>
                </div>
                <div class="feature-item">
                    <h5>⚡ Procesamiento Rápido</h5>
                    <p>Generación de nóminas quincenales en segundos para múltiples empleados.</p>
                </div>
                <div class="feature-item">
                    <h5>📊 Reportes Avanzados</h5>
                    <p>Análisis detallados y exportación a PDF para auditorías.</p>
                </div>
                <div class="feature-item">
                    <h5>🔒 Seguridad Total</h5>
                    <p>Control de acceso basado en roles con encriptación de datos.</p>
                </div>
            </div>
        </section>

        <section id="caracteristicas-principales" class="section-card mb-5">
            <div class="section-icon">✨</div>
            <h2>2. Características Principales</h2>

            <div class="feature-list">
                <div class="feature-item">
                    <h5>🔐 Autenticación de Usuarios</h5>
                    <p>Sistema de login seguro con roles definidos (administrador, asistente, solo lectura) para un control de acceso granular.</p>
                </div>
                <div class="feature-item">
                    <h5>👥 Gestión de Empleados</h5>
                    <p>Funcionalidades completas para agregar, editar y listar empleados, incluyendo datos personales, cargo y salario base.</p>
                </div>
                <div class="feature-item">
                    <h5>💰 Conceptos de Nómina</h5>
                    <p>Flexibilidad para definir y administrar diversos conceptos de pago, como ingresos, deducciones legales (SSO, SPF, FAOV), deducciones personales y beneficios (Cesta Ticket).</p>
                </div>
                <div class="feature-item">
                    <h5>🧮 Cálculo de Nómina</h5>
                    <p>Proceso automatizado para calcular períodos quincenales, considerando fechas, tasa BCV y días trabajados, aplicando automáticamente deducciones y beneficios.</p>
                </div>
                <div class="feature-item">
                    <h5>📈 Reportes y Análisis</h5>
                    <p>Generación de reportes detallados por empleado, reportes analíticos consolidados, reportes de descuentos y de pagos realizados.</p>
                </div>
                <div class="feature-item">
                    <h5>📱 Interfaz Web Responsiva</h5>
                    <p>Diseño moderno y adaptable a cualquier dispositivo, basado en Bootstrap 5.</p>
                </div>
            </div>
        </section>

        <section id="roles-de-usuario" class="section-card mb-5">
            <div class="section-icon">👤</div>
            <h2>3. Roles de Usuario y Permisos</h2>
            <p>El sistema cuenta con tres roles de usuario principales, cada uno con un nivel de acceso y permisos específicos:</p>

            <div class="feature-list">
                <div class="feature-item">
                    <h5>👑 Administrador (admin)</h5>
                    <p>Acceso completo al sistema. Puede gestionar usuarios, empleados, conceptos de nómina, cálculos y todos los reportes.</p>
                    <ul>
                        <li>✅ Gestión completa de usuarios</li>
                        <li>✅ Gestión de empleados</li>
                        <li>✅ Configuración de conceptos</li>
                        <li>✅ Cálculos de nómina</li>
                        <li>✅ Todos los reportes</li>
                    </ul>
                </div>
                <div class="feature-item">
                    <h5>👨‍💼 Asistente (assistant)</h5>
                    <p>Operaciones diarias de nómina sin acceso administrativo. Ideal para contadores y encargados de RRHH.</p>
                    <ul>
                        <li>✅ Gestión de empleados</li>
                        <li>✅ Cálculos de nómina</li>
                        <li>✅ Visualización de reportes</li>
                        <li>❌ Gestión de usuarios</li>
                    </ul>
                </div>
                <div class="feature-item">
                    <h5>👀 Solo Lectura (read_only)</h5>
                    <p>Acceso limitado a consulta únicamente. Perfecto para supervisores y auditores.</p>
                    <ul>
                        <li>✅ Ver reportes</li>
                        <li>✅ Ver listados de empleados</li>
                        <li>❌ Modificar cualquier dato</li>
                        <li>❌ Acceder a cálculos</li>
                    </ul>
                </div>
            </div>

            <div class="example-box">
                <p><strong>Recomendación:</strong> Asigna siempre el rol con menos privilegios necesario para cada usuario, siguiendo el principio de "menor privilegio" para mantener la seguridad del sistema.</p>
            </div>
        </section>

        <section id="operaciones-comunes" class="section-card mb-5">
            <div class="section-icon">🚀</div>
            <h2>4. Operaciones Comunes</h2>

            <h3>4.1. Inicio de Sesión</h3>
            <p>Para acceder al sistema, dirígete a la página de login (<code>http://localhost/payroll_system/public/index.php</code>) e introduce tus credenciales de usuario y contraseña.</p>

            <div class="step-list">
                <li>Abre tu navegador web y navega a la URL del sistema.</li>
                <li>En la página de login, verás dos campos: "Usuario" y "Contraseña".</li>
                <li>Ingresa tu nombre de usuario asignado por el administrador.</li>
                <li>Ingresa tu contraseña. Recuerda que las contraseñas son sensibles a mayúsculas y minúsculas.</li>
                <li>Haz clic en el botón "Iniciar Sesión" o presiona Enter.</li>
                <li>Si las credenciales son correctas, serás redirigido al Dashboard. Si no, verás un mensaje de error.</li>
            </div>

            <div class="example-box">
                <p>Las credenciales por defecto son: Usuario: <code>admin</code>, Contraseña: <code>admin</code>. Se recomienda cambiar la contraseña del administrador después del primer inicio de sesión.</p>
            </div>

            <div class="warning-box">
                <p><strong>Nota de Seguridad:</strong> Si olvidas tu contraseña, contacta al administrador del sistema para restablecerla. Nunca compartas tus credenciales con otros usuarios.</p>
            </div>

           <h3>4.2. Dashboard</h3>
           <p>Después de iniciar sesión, serás redirigido al Dashboard, la página principal que ofrece enlaces rápidos a las diferentes secciones del sistema, adaptados a tu rol.</p>

           <div class="feature-list">
               <div class="feature-item">
                   <h5>📊 Barra de Navegación</h5>
                   <p>Muestra tu nombre de usuario, rol actual y opciones para cerrar sesión.</p>
               </div>
               <div class="feature-item">
                   <h5>👋 Mensaje de Bienvenida</h5>
                   <p>Te saluda y confirma tu rol en el sistema.</p>
               </div>
               <div class="feature-item">
                   <h5>🎯 Tarjetas Interactivas</h5>
                   <p>Enlaces visuales animados a las funciones principales del sistema.</p>
               </div>
           </div>

           <h4>4.2.1. Para Administradores y Asistentes:</h4>
           <div class="feature-list">
               <div class="feature-item">
                   <h5>👥 Gestión de Empleados</h5>
                   <p>Accede a la lista completa de empleados, permite agregar nuevos, editar existentes y gestionar su información personal y laboral.</p>
               </div>
               <div class="feature-item">
                   <h5>🧮 Calcular Nómina</h5>
                   <p>Herramienta principal para procesar los pagos quincenales, configurar períodos y aplicar cálculos automáticos.</p>
               </div>
               <div class="feature-item">
                   <h5>💰 Conceptos de Nómina</h5>
                   <p>Administra los diferentes tipos de ingresos, deducciones y beneficios que se aplican en las nóminas.</p>
               </div>
           </div>

           <h4>4.2.2. Solo para Administradores:</h4>
           <div class="feature-list">
               <div class="feature-item">
                   <h5>👤 Gestión de Usuarios</h5>
                   <p>Crea, edita y elimina cuentas de usuario, asignando roles y permisos.</p>
               </div>
           </div>

           <h4>4.2.3. Para Todos los Roles:</h4>
           <div class="feature-list">
               <div class="feature-item">
                   <h5>📋 Reportes por Empleado</h5>
                   <p>Genera reportes detallados de la nómina de empleados individuales.</p>
               </div>
               <div class="feature-item">
                   <h5>📊 Reportes Estadísticos</h5>
                   <p>Visualiza análisis consolidados y estadísticas generales de la nómina.</p>
               </div>
           </div>

           <div class="example-box">
               <p><strong>Consejo:</strong> El Dashboard se adapta automáticamente a tu rol, mostrando solo las opciones que tienes permiso para acceder.</p>
           </div>

           <h3>4.3. Gestión de Empleados</h3>
           <p>La gestión de empleados es fundamental para el sistema de nómina. Desde el menú principal, selecciona "Gestión de Empleados" para acceder a esta sección.</p>

           <div class="feature-list">
               <div class="feature-item">
                   <h5>📋 Listado de Empleados</h5>
                   <p>Tabla que muestra todos los empleados registrados con su información básica.</p>
               </div>
               <div class="feature-item">
                   <h5>⚡ Botones de Acción</h5>
                   <p>Opciones para agregar, editar y eliminar empleados.</p>
               </div>
               <div class="feature-item">
                   <h5>🔍 Filtros y Búsqueda</h5>
                   <p>Herramientas para localizar empleados específicos.</p>
               </div>
           </div>

           <h4>4.3.1. Añadir Nuevo Empleado</h4>
           <p>Para registrar un nuevo empleado en el sistema:</p>
           <div class="step-list">
               <li>En el Dashboard, haz clic en "Gestión de Empleados".</li>
               <li>En la página de empleados, haz clic en "Añadir Nuevo Empleado".</li>
               <li>Completa el formulario con la siguiente información obligatoria:
                   <ul>
                       <li><strong>Cédula:</strong> Número de identificación único del empleado (ej: V-12345678).</li>
                       <li><strong>Nombre Completo:</strong> Nombre y apellido del empleado.</li>
                       <li><strong>Fecha de Ingreso:</strong> Fecha en que el empleado comenzó a trabajar (formato YYYY-MM-DD).</li>
                       <li><strong>Cargo:</strong> Posición laboral del empleado (ej: Analista, Gerente, etc.).</li>
                       <li><strong>Salario Base Mensual:</strong> Salario mensual en dólares estadounidenses (ej: 1500.00).</li>
                   </ul>
               </li>
               <li>Opcionalmente, puedes subir una foto del empleado.</li>
               <li>Marca la casilla "Empleado Activo" si el empleado está actualmente trabajando.</li>
               <li>Haz clic en "Guardar Empleado" para registrar la información.</li>
           </div>

           <div class="example-box">
               <p><strong>Ejemplo:</strong> Para registrar a Juan Pérez como analista con salario de $1200 mensuales, ingresa: Cédula: V-87654321, Nombre: Juan Pérez García, Fecha: 2024-01-15, Cargo: Analista de Sistemas, Salario: 1200.00.</p>
           </div>

           <h4>4.3.2. Editar Empleado</h4>
           <p>Para modificar la información de un empleado existente:</p>
           <div class="step-list">
               <li>En el listado de empleados, localiza al empleado que deseas editar.</li>
               <li>Haz clic en el botón "Editar" (ícono de lápiz) en la columna "Acciones".</li>
               <li>Modifica los campos necesarios en el formulario.</li>
               <li>Haz clic en "Actualizar Empleado" para guardar los cambios.</li>
           </div>
           <div class="warning-box">
               <p><strong>Nota:</strong> La cédula no se puede modificar una vez registrada, ya que es el identificador único.</p>
           </div>

           <h4>4.3.3. Eliminar Empleado</h4>
           <p>Para remover un empleado del sistema:</p>
           <div class="step-list">
               <li>En el listado, haz clic en el botón "Eliminar" (ícono de papelera) del empleado correspondiente.</li>
               <li>Confirma la eliminación en el diálogo que aparece.</li>
               <li>El empleado será marcado como inactivo, pero sus datos históricos se mantendrán para reportes.</li>
           </div>
           <div class="warning-box">
               <p><strong>Advertencia:</strong> La eliminación es permanente y puede afectar cálculos históricos. Usa con precaución.</p>
           </div>

           <h4>4.3.4. Ver Detalles del Empleado</h4>
           <p>La tabla muestra información clave de cada empleado:</p>
           <ul>
               <li><strong>Cédula:</strong> Identificación única.</li>
               <li><strong>Nombre Completo:</strong> Nombre del empleado.</li>
               <li><strong>Fecha de Ingreso:</strong> Cuando comenzó a trabajar.</li>
               <li><strong>Cargo:</strong> Posición laboral.</li>
               <li><strong>Salario Base:</strong> Salario mensual en USD.</li>
               <li><strong>Activo:</strong> Estado del empleado (Sí/No).</li>
           </ul>

           <h3>4.4. Conceptos de Nómina</h3>
           <p>Los conceptos de nómina son los diferentes elementos que componen el salario de un empleado: ingresos, deducciones legales, deducciones personales y beneficios.</p>
           <p>Para acceder: Desde el Dashboard, haz clic en "Conceptos de Nómina".</p>

           <h4>4.4.1. Tipos de Conceptos</h4>
           <div class="feature-list">
               <div class="feature-item">
                   <h5>💰 Ingreso</h5>
                   <p>Pagos que recibe el empleado (ej: Salario Base, Bonos).</p>
               </div>
               <div class="feature-item">
                   <h5>⚖️ Deducción Legal</h5>
                   <p>Obligatorias por ley (ej: SSO, SPF, FAOV).</p>
               </div>
               <div class="feature-item">
                   <h5>📝 Deducción Personal</h5>
                   <p>Voluntarias o acordadas (ej: Préstamos, Seguro médico).</p>
               </div>
               <div class="feature-item">
                   <h5>🎁 Beneficio</h5>
                   <p>Pagos adicionales (ej: Cesta Ticket, Vacaciones).</p>
               </div>
           </div>

           <h4>4.4.2. Tipos de Cálculo</h4>
           <div class="feature-list">
               <div class="feature-item">
                   <h5>💵 Valor Fijo</h5>
                   <p>Monto específico (ej: $50.00).</p>
               </div>
               <div class="feature-item">
                   <h5>📊 Porcentaje del Salario</h5>
                   <p>Calculado como porcentaje (ej: 4% del salario base).</p>
               </div>
               <div class="feature-item">
                   <h5>📅 Valor por Día</h5>
                   <p>Monto diario multiplicado por días trabajados.</p>
               </div>
               <div class="feature-item">
                   <h5>✏️ Entrada Manual</h5>
                   <p>Ingresado manualmente en cada cálculo.</p>
               </div>
           </div>

           <h4>4.4.3. Añadir Nuevo Concepto</h4>
           <p>Pasos para crear un concepto:</p>
           <div class="step-list">
               <li>Haz clic en "Añadir Nuevo Concepto" en la página de conceptos.</li>
               <li>Ingresa el nombre descriptivo (ej: "Seguro Social Obligatorio").</li>
               <li>Selecciona el tipo de concepto.</li>
               <li>Elige el tipo de cálculo.</li>
               <li>Si aplica, ingresa el valor por defecto (ej: 0.04 para 4%).</li>
               <li>Marca si aplica a todos los empleados por defecto.</li>
               <li>Confirma que el concepto esté activo.</li>
               <li>Guarda el concepto.</li>
           </div>

           <h4>4.4.4. Configuración de Conceptos Predefinidos</h4>
           <p>El sistema incluye conceptos legales venezolanos:</p>
           <div class="feature-list">
               <div class="feature-item">
                   <h5>🏥 SSO (Seguro Social Obligatorio)</h5>
                   <p>4% del salario, deducción legal.</p>
               </div>
               <div class="feature-item">
                   <h5>💼 SPF (Seguro de Paro Forzoso)</h5>
                   <p>0.5% del salario, deducción legal.</p>
               </div>
               <div class="feature-item">
                   <h5>🏠 FAOV (Fondo de Ahorro Obligatorio)</h5>
                   <p>1% del salario, deducción legal.</p>
               </div>
               <div class="feature-item">
                   <h5>🛒 Cesta Ticket</h5>
                   <p>Valor diario por días trabajados, beneficio.</p>
               </div>
           </div>

           <h3>4.5. Cálculo de Nómina</h3>
           <p>Esta es la función principal del sistema: calcular los pagos quincenales de los empleados.</p>
           <p>Acceso: Dashboard > "Calcular Nómina".</p>

           <h4>4.5.1. Configuración del Período</h4>
           <p>Antes de calcular:</p>
           <div class="feature-list">
               <div class="feature-item">
                   <h5>📅 Fecha de Inicio</h5>
                   <p>Primer día del período quincenal (ej: 2024-01-01).</p>
               </div>
               <div class="feature-item">
                   <h5>🏁 Fecha de Fin</h5>
                   <p>Último día del período (ej: 2024-01-15).</p>
               </div>
               <div class="feature-item">
                   <h5>💱 Tasa BCV</h5>
                   <p>Tipo de cambio oficial (ej: 35.50 Bs/USD).</p>
               </div>
               <div class="feature-item">
                   <h5>📊 Días en el Período</h5>
                   <p>Normalmente 15 para quincenal.</p>
               </div>
           </div>

           <h4>4.5.2. Selección de Empleados</h4>
           <p>Por defecto, todos los empleados activos están seleccionados. Desmarca aquellos que no deben incluirse en este período (ej: empleados en vacaciones).</p>

           <h4>4.5.3. Proceso de Cálculo Automático</h4>
           <p>El sistema calcula automáticamente:</p>
           <div class="feature-list">
               <div class="feature-item">
                   <h5>💰 Salario Base Quincenal</h5>
                   <p>Salario mensual / 2.</p>
               </div>
               <div class="feature-item">
                   <h5>⚖️ Deducciones Legales</h5>
                   <p>Aplicadas según porcentajes configurados.</p>
               </div>
               <div class="feature-item">
                   <h5>🎁 Beneficios</h5>
                   <p>Calculados por días trabajados.</p>
               </div>
               <div class="feature-item">
                   <h5>✅ Neto a Pagar</h5>
                   <p>Ingresos + Beneficios - Deducciones.</p>
               </div>
           </div>

           <div class="example-box">
               <p><strong>Ejemplo de Cálculo:</strong> Empleado con salario $1200 mensual:</p>
               <ul>
                   <li>Salario quincenal: $600</li>
                   <li>SSO (4%): $24</li>
                   <li>SPF (0.5%): $3</li>
                   <li>FAOV (1%): $6</li>
                   <li>Cesta Ticket (15 días x $1.33): $20</li>
                   <li><strong>Neto: $600 + $20 - $24 - $3 - $6 = $587</strong></li>
               </ul>
           </div>

           <h4>4.5.4. Estados del Período</h4>
           <div class="feature-list">
               <div class="feature-item">
                   <h5>⏳ Pendiente</h5>
                   <p>Período creado pero no calculado.</p>
               </div>
               <div class="feature-item">
                   <h5>🧮 Calculado</h5>
                   <p>Nómina procesada, lista para pago.</p>
               </div>
               <div class="feature-item">
                   <h5>💰 Pagado</h5>
                   <p>Nómina confirmada como pagada.</p>
               </div>
               <div class="feature-item">
                   <h5>🔒 Cerrado</h5>
                   <p>Período finalizado, no modificable.</p>
               </div>
           </div>

           <h3>4.6. Reportes</h3>
           <p>El sistema ofrece varios tipos de reportes para análisis y auditoría:</p>

           <h4>4.6.1. Reportes por Empleado</h4>
           <p>Genera historial completo de nómina para un empleado específico.</p>
           <div class="feature-list">
               <div class="feature-item">
                   <h5>👤 Selección de Empleado</h5>
                   <p>Elige del listado completo de empleados.</p>
               </div>
               <div class="feature-item">
                   <h5>📅 Filtros por Fechas</h5>
                   <p>Opcional: limita el período de análisis.</p>
               </div>
               <div class="feature-item">
                   <h5>📈 Historial Completo</h5>
                   <p>Visualiza todos los períodos calculados.</p>
               </div>
               <div class="feature-item">
                   <h5>📄 Exportación PDF</h5>
                   <p>Descarga detalles completos en PDF.</p>
               </div>
           </div>
           <div class="example-box">
               <p><strong>Uso:</strong> Para revisar pagos históricos o preparar comprobantes de pago.</p>
           </div>

           <h4>4.6.2. Reportes Analíticos</h4>
           <p>Estadísticas consolidadas de la nómina.</p>
           <div class="feature-list">
               <div class="feature-item">
                   <h5>💰 Totales Consolidados</h5>
                   <p>Ingresos, deducciones y beneficios totales.</p>
               </div>
               <div class="feature-item">
                   <h5>👥 Conteo de Empleados</h5>
                   <p>Número de empleados activos en período.</p>
               </div>
               <div class="feature-item">
                   <h5>📊 Períodos Calculados</h5>
                   <p>Cantidad de períodos procesados.</p>
               </div>
               <div class="feature-item">
                   <h5>📋 Resumen Ejecutivo</h5>
                   <p>Información para toma de decisiones.</p>
               </div>
           </div>
           <div class="example-box">
               <p><strong>Uso:</strong> Para análisis financiero y reportes gerenciales.</p>
           </div>

           <h4>4.6.3. Reportes de Descuentos</h4>
           <p>Detalle de todas las deducciones aplicadas.</p>
           <div class="feature-list">
               <div class="feature-item">
                   <h5>📋 Agrupación por Concepto</h5>
                   <p>Organizado por tipo de deducción.</p>
               </div>
               <div class="feature-item">
                   <h5>📅 Análisis por Período</h5>
                   <p>Desglose temporal de descuentos.</p>
               </div>
               <div class="feature-item">
                   <h5>⚖️ Cumplimiento Legal</h5>
                   <p>Útil para reportes regulatorios.</p>
               </div>
               <div class="feature-item">
                   <h5>🏛️ Entidades Gubernamentales</h5>
                   <p>Reportes para autoridades fiscales.</p>
               </div>
           </div>
           <div class="example-box">
               <p><strong>Uso:</strong> Para cumplimiento de obligaciones legales y auditorías.</p>
           </div>

           <h4>4.6.4. Reportes de Pagos Realizados</h4>
           <p>Historial de nóminas efectivamente pagadas.</p>
           <div class="feature-list">
               <div class="feature-item">
                   <h5>✅ Solo Pagos Confirmados</h5>
                   <p>Períodos marcados como "Pagado".</p>
               </div>
               <div class="feature-item">
                   <h5>💵 Totales de Pagos</h5>
                   <p>Sumatorias de pagos realizados.</p>
               </div>
               <div class="feature-item">
                   <h5>🔗 Detalles Individuales</h5>
                   <p>Enlace a detalles de cada período.</p>
               </div>
               <div class="feature-item">
                   <h5>📊 Control Financiero</h5>
                   <p>Herramientas de auditoría interna.</p>
               </div>
           </div>
           <div class="example-box">
               <p><strong>Uso:</strong> Para auditoría financiera y control de egresos.</p>
           </div>

           <div class="warning-box">
               <p><strong>Nota:</strong> Todos los reportes pueden filtrarse por fechas y exportarse a PDF para archivo o distribución.</p>
           </div>

            <h3>4.7. Gestión de Usuarios (Solo Administrador)</h3>
            <p>La gestión de usuarios permite controlar el acceso al sistema mediante roles y permisos.</p>

            <h4>4.7.1. Roles del Sistema</h4>
            <div class="feature-list">
                <div class="feature-item">
                    <h5>👑 Administrador</h5>
                    <p>Acceso completo a todas las funciones, incluyendo gestión de usuarios.</p>
                </div>
                <div class="feature-item">
                    <h5>👨‍💼 Asistente</h5>
                    <p>Acceso a operaciones diarias: empleados, cálculos, reportes.</p>
                </div>
                <div class="feature-item">
                    <h5>👀 Solo Lectura</h5>
                    <p>Acceso limitado a visualización de reportes e información básica.</p>
                </div>
            </div>

            <h4>4.7.2. Crear Nuevo Usuario</h4>
            <p>Sigue estos pasos para agregar un nuevo usuario:</p>
            <div class="step-list">
                <li>Dashboard > "Gestión de Usuarios" > "Añadir Nuevo Usuario".</li>
                <li>Ingresa nombre de usuario único.</li>
                <li>Asigna rol apropiado según responsabilidades.</li>
                <li>Establece contraseña inicial segura.</li>
                <li>Confirma contraseña para evitar errores.</li>
                <li>Guarda el usuario.</li>
            </div>

            <h4>4.7.3. Editar Usuario</h4>
            <p>Puedes cambiar rol y contraseña, pero no el nombre de usuario.</p>
            <div class="warning-box">
                <p><strong>Nota:</strong> Los cambios de rol afectan inmediatamente los permisos del usuario.</p>
            </div>

            <h4>4.7.4. Eliminar Usuario</h4>
            <p>Elimina usuarios que ya no necesitan acceso. Usa con precaución.</p>
            <div class="warning-box">
                <p><strong>Advertencia:</strong> La eliminación es permanente. Asegúrate de que el usuario ya no necesite acceso.</p>
            </div>

            <div class="example-box">
                <p><strong>Recomendación:</strong> Asigna el rol con menos privilegios necesario para cada usuario siguiendo el principio de "menor privilegio".</p>
            </div>
    </section>

        <section id="flujo-de-trabajo-recomendado" class="section-card mb-5">
            <div class="section-icon">🔄</div>
            <h2>5. Flujo de Trabajo Recomendado</h2>
            <p>Para usuarios nuevos, sigue este flujo lógico para familiarizarte con el sistema:</p>

            <h3>5.1. Configuración Inicial (Administrador)</h3>
            <div class="step-list">
                <li><strong>Cambiar Contraseña:</strong> Inicia sesión con admin/admin y cambia la contraseña inmediatamente.</li>
                <li><strong>Configurar Conceptos de Nómina:</strong> Verifica que los conceptos legales estén configurados correctamente.</li>
                <li><strong>Registrar Empleados:</strong> Agrega todos los empleados activos con su información completa.</li>
                <li><strong>Crear Usuarios Adicionales:</strong> Configura cuentas para asistentes y usuarios de solo lectura según sea necesario.</li>
            </div>

            <h3>5.2. Operaciones Diarias (Asistente/Administrador)</h3>
            <div class="step-list">
                <li><strong>Verificar Empleados Activos:</strong> Confirma que todos los empleados estén correctamente registrados.</li>
                <li><strong>Calcular Nómina:</strong> Al inicio de cada período quincenal, configura el período y calcula la nómina.</li>
                <li><strong>Revisar Detalles:</strong> Verifica los cálculos automáticos en la sección de detalles de nómina.</li>
                <li><strong>Marcar como Pagada:</strong> Una vez realizado el pago, marca el período como pagado.</li>
            </div>

            <h3>5.3. Consultas y Reportes (Todos los Roles)</h3>
            <div class="step-list">
                <li><strong>Reportes por Empleado:</strong> Para consultas individuales o preparación de comprobantes.</li>
                <li><strong>Reportes Analíticos:</strong> Para análisis mensual o anual de costos laborales.</li>
                <li><strong>Reportes de Descuentos:</strong> Para cumplimiento de obligaciones legales.</li>
                <li><strong>Reportes de Pagos:</strong> Para auditoría y control financiero.</li>
            </div>

            <h3>5.4. Mantenimiento (Administrador)</h3>
            <div class="step-list">
                <li><strong>Actualizar Información:</strong> Mantén actualizada la información de empleados (cambios de cargo, salario).</li>
                <li><strong>Configurar Nuevos Conceptos:</strong> Agrega bonos, deducciones especiales según sea necesario.</li>
                <li><strong>Gestionar Usuarios:</strong> Actualiza roles y permisos según cambios organizacionales.</li>
                <li><strong>Respaldos:</strong> Realiza respaldos regulares de la base de datos.</li>
            </div>
        </section>

    <section id="estructura-del-proyecto" class="mb-5">
        <h2>6. Estructura del Proyecto (Para Desarrolladores)</h2>
        <p>Esta sección es útil para entender la organización interna del sistema:</p>
        <pre><code>payroll_system/
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
</code></pre>
    </section>

        <section id="casos-de-uso-practicos" class="section-card mb-5">
            <div class="section-icon">💡</div>
            <h2>7. Casos de Uso Prácticos</h2>

            <h3>7.1. Nuevo Empleado en la Empresa</h3>
            <div class="step-list">
                <li>Registra al empleado en "Gestión de Empleados" con todos sus datos.</li>
                <li>Verifica que aparezca en el listado de empleados activos.</li>
                <li>En el próximo cálculo de nómina, el empleado será incluido automáticamente.</li>
                <li>Si el empleado comienza a mitad de período, ajústalo manualmente en la selección.</li>
            </div>

            <h3>7.2. Cambio de Salario</h3>
            <div class="step-list">
                <li>Ve a "Gestión de Empleados" y edita el empleado correspondiente.</li>
                <li>Actualiza el "Salario Base Mensual" con el nuevo monto.</li>
                <li>Guarda los cambios.</li>
                <li>El nuevo salario se aplicará en el siguiente cálculo de nómina.</li>
            </div>

            <h3>7.3. Empleado en Vacaciones</h3>
            <div class="step-list">
                <li>En "Calcular Nómina", desmarca al empleado en la lista de selección.</li>
                <li>Calcula la nómina normalmente para los demás empleados.</li>
                <li>Si es necesario, calcula un período especial solo para ese empleado con días proporcionales.</li>
            </div>

            <h3>7.4. Preparar Reporte para Contabilidad</h3>
            <div class="step-list">
                <li>Ve a "Reportes Analíticos".</li>
                <li>Selecciona el rango de fechas del mes o período fiscal.</li>
                <li>Genera el reporte y descárgalo en PDF.</li>
                <li>Utiliza los totales para conciliación financiera.</li>
            </div>

            <h3>7.5. Verificar Deducciones Legales</h3>
            <div class="step-list">
                <li>Accede a "Reportes de Descuentos".</li>
                <li>Filtra por el período de interés.</li>
                <li>Verifica que las deducciones SSO, SPF y FAOV se apliquen correctamente.</li>
                <li>Exporta el reporte para presentarlo a las autoridades correspondientes.</li>
            </div>

            <h3>7.6. Auditoría de Pagos</h3>
            <div class="step-list">
                <li>En "Reportes de Pagos Realizados", selecciona el período a auditar.</li>
                <li>Revisa que todos los pagos marcados como realizados estén correctos.</li>
                <li>Utiliza los enlaces a detalles para verificar cálculos individuales.</li>
                <li>Genera el PDF como respaldo documental.</li>
            </div>
        </section>

        <section id="solucion-de-problemas" class="section-card mb-5">
            <div class="section-icon">🔧</div>
            <h2>8. Solución de Problemas Comunes</h2>

            <div class="feature-list">
                <div class="feature-item">
                    <h5>🗄️ Error de conexión a la Base de Datos</h5>
                    <p>Verifica que el servidor MySQL esté ejecutándose en XAMPP y que las credenciales de conexión en <code>config/settings.php</code> (<code>DB_HOST</code>, <code>DB_NAME</code>, <code>DB_USER</code>, <code>DB_PASS</code>) sean correctas.</p>
                </div>
                <div class="feature-item">
                    <h5>📄 Página en blanco o errores PHP</h5>
                    <p>Asegúrate de que PHP esté correctamente configurado en tu servidor web (Apache) y que la ruta del proyecto en el navegador sea la correcta (<code>http://localhost/payroll_system/public/index.php</code>).</p>
                </div>
                <div class="feature-item">
                    <h5>🔒 Problemas de permisos</h5>
                    <p>Verifica que los archivos y directorios del proyecto tengan los permisos de lectura y escritura adecuados para el servidor web.</p>
                </div>
                <div class="feature-item">
                    <h5>📅 Problemas con formatos de fecha</h5>
                    <p>Asegúrate de que todas las fechas se ingresen y procesen en el formato <code>YYYY-MM-DD</code> para evitar inconsistencias.</p>
                </div>
                <div class="feature-item">
                    <h5>🧮 Cálculos incorrectos en nómina</h5>
                    <p>Verifica la configuración de conceptos de nómina, especialmente los porcentajes de deducciones legales. Confirma que la tasa BCV sea la correcta para el período.</p>
                </div>
                <div class="feature-item">
                    <h5>👤 Empleado no aparece en cálculos</h5>
                    <p>Asegúrate de que el empleado esté marcado como "Activo" y que esté seleccionado en el formulario de cálculo de nómina.</p>
                </div>
                <div class="feature-item">
                    <h5>🚫 No puedo acceder a ciertas funciones</h5>
                    <p>Verifica tu rol de usuario con el administrador. Cada rol tiene permisos específicos definidos.</p>
                </div>
            </div>
        </section>

        <section id="seguridad" class="section-card mb-5">
            <div class="section-icon">🛡️</div>
            <h2>9. Consideraciones de Seguridad</h2>

            <div class="feature-list">
                <div class="feature-item">
                    <h5>🔐 Encriptación de Contraseñas</h5>
                    <p>Las contraseñas de los usuarios se almacenan de forma segura utilizando la función <code>password_hash()</code> de PHP.</p>
                </div>
                <div class="feature-item">
                    <h5>✅ Validación de Entrada</h5>
                    <p>Se implementa validación de entrada en los formularios para prevenir ataques comunes como la inyección SQL y Cross-Site Scripting (XSS).</p>
                </div>
                <div class="feature-item">
                    <h5>👥 Control de Acceso por Roles</h5>
                    <p>El control de acceso basado en roles protege las funcionalidades sensibles del sistema.</p>
                </div>
                <div class="feature-item">
                    <h5>🔑 Gestión de Credenciales</h5>
                    <p>Siempre cierra sesión al terminar de usar el sistema, especialmente en equipos compartidos.</p>
                </div>
                <div class="feature-item">
                    <h5>🚫 No Compartir Credenciales</h5>
                    <p>No compartas tu contraseña con otros usuarios bajo ninguna circunstancia.</p>
                </div>
                <div class="feature-item">
                    <h5>🚨 Reporte de Actividad Sospechosa</h5>
                    <p>Reporta inmediatamente cualquier actividad sospechosa al administrador del sistema.</p>
                </div>
            </div>

            <div class="warning-box">
                <p><strong>Recomendación Importante:</strong> En un entorno de producción, cambia las credenciales por defecto (<code>admin/admin</code>) y configura un usuario de base de datos con los mínimos privilegios necesarios.</p>
            </div>
        </section>

        <section id="licencia-y-soporte" class="section-card mb-5">
            <div class="section-icon">📞</div>
            <h2>10. Licencia y Soporte</h2>
            <p>Este proyecto está bajo la Licencia MIT. Para más detalles, consulta el archivo <code>LICENSE</code> en la raíz del proyecto.</p>
            <p>Para cualquier pregunta, soporte técnico o para reportar un problema, por favor contacta al desarrollador o abre un "issue" en el repositorio del proyecto.</p>

            <div class="example-box">
                <p><strong>Recomendaciones para soporte:</strong></p>
                <ul>
                    <li>Antes de reportar un problema, verifica la sección de solución de problemas comunes.</li>
                    <li>Proporciona detalles específicos del error, incluyendo mensajes de error y pasos para reproducirlo.</li>
                    <li>Menciona la versión de PHP, MySQL y navegador que estás utilizando.</li>
                    <li>Para problemas de cálculo, incluye ejemplos específicos con datos de prueba.</li>
                </ul>
            </div>
        </section>

</div>

<?php include __DIR__ . '/../includes/footer.php'; // Incluir el pie de página ?>
<!-- Incluir Bootstrap JS (Bundle con Popper) directamente con ruta relativa -->
<script src="./assets/js/bootstrap.bundle.min.js"></script>
<!-- Scripts personalizados (si los hay) directamente con ruta relativa -->
<script src="./assets/js/script.js"></script>
</body>
</html>
