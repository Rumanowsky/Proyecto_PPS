<?php
// ARCHIVO PRINCIPAL (ENRUTADOR + VISTA SÍNCRONA)

$ruta = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Si piden el archivo CSS, PHP lo entrega directamente
if ($ruta == '/style.css') {
    return false;
}

session_start();

// Cargamos todos los archivos del Modelo y Controlador
require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Models/User.php';
require_once __DIR__ . '/../src/Models/Item.php';
require_once __DIR__ . '/../src/Controllers/AuthController.php';
require_once __DIR__ . '/../src/Controllers/ItemController.php';

$pdo = conectarBD();
$metodo = $_SERVER['REQUEST_METHOD'];

// Comprobamos si la petición viene por JSON (API/Pruebas) o por Formulario HTML normal
$esApi = (strpos($ruta, '/api/') === 0);

if ($esApi) {
    header('Content-Type: application/json; charset=utf-8');
    $jsonRecibido = file_get_contents('php://input');
    $datos = json_decode($jsonRecibido, true);
    if (!is_array($datos)) {
        $datos = [];
    }
} else {
    $datos = $_POST;
}

// =========================================================
// 1. RUTAS DE ACCIONES (TANTO PARA FORMULARIOS COMO PARA API)
// =========================================================

if ($metodo == 'POST' && ($ruta == '/api/register' || $ruta == '/register')) {
    registrarUsuario($pdo, $datos, $esApi);
}

if ($metodo == 'POST' && ($ruta == '/api/login' || $ruta == '/login')) {
    iniciarSesion($pdo, $datos, $esApi);
}

if ($metodo == 'POST' && ($ruta == '/api/logout' || $ruta == '/logout')) {
    cerrarSesion($esApi);
}

if ($metodo == 'GET' && $ruta == '/api/items') {
    listarProductos($pdo);
}

if ($metodo == 'POST' && ($ruta == '/api/items' || $ruta == '/items')) {
    guardarNuevoProducto($pdo, $datos, $esApi);
}

// Rutas de la API con ID (/api/items/5)
if (preg_match('#^/api/items/(\d+)$#', $ruta, $coincidencias)) {
    $id = $coincidencias[1];
    if ($metodo == 'PUT')    actualizarProducto($pdo, $id, $datos, true);
    if ($metodo == 'DELETE') eliminarProducto($pdo, $id, true);
}

// Rutas de formularios HTML normales (porque HTML solo soporta GET y POST)
if ($metodo == 'POST' && $ruta == '/items/editar') {
    $id = $_POST['id'] ?? 0;
    actualizarProducto($pdo, $id, $datos, false);
}

if ($metodo == 'POST' && $ruta == '/items/borrar') {
    $id = $_POST['id'] ?? 0;
    eliminarProducto($pdo, $id, false);
}

if ($esApi) {
    http_response_code(404);
    echo json_encode(['error' => 'Ruta no encontrada']);
    exit;
}

// =========================================================
// 2. VISTA HTML SÍNCRONA (SIN JAVASCRIPT / SIN AJAX)
// =========================================================

$productos = [];
$productoEditar = null;

if (isset($_SESSION['user_id'])) {
    $productos = obtenerProductosUsuario($pdo, $_SESSION['user_id']);

    // Si el usuario ha pulsado "Editar" en un producto (?editar=ID)
    if (isset($_GET['editar'])) {
        $buscado = buscarProductoPorId($pdo, $_GET['editar']);
        if ($buscado && $buscado['user_id'] == $_SESSION['user_id']) {
            $productoEditar = $buscado;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tienda ASIR - Mis Productos</title>
    <link rel="stylesheet" href="/style.css">
</head>
<body>

    <h1>Mi Lista de Productos</h1>

    <?php if (isset($_GET['error'])): ?>
        <p class="error"><?php echo htmlspecialchars($_GET['error']); ?></p>
    <?php endif; ?>

    <?php if (isset($_GET['ok'])): ?>
        <p class="ok"><?php echo htmlspecialchars($_GET['ok']); ?></p>
    <?php endif; ?>

    <?php if (!isset($_SESSION['user_id'])): ?>
        <!-- PANTALLA DE LOGIN Y REGISTRO -->
        <div class="caja">
            <h2>Iniciar Sesión</h2>
            <form action="/login" method="POST">
                <input type="text" name="username" placeholder="Nombre de usuario" required>
                <input type="password" name="password" placeholder="Contraseña" required>
                <button type="submit">Entrar</button>
            </form>
        </div>

        <div class="caja">
            <h2>Crear Cuenta Nueva</h2>
            <form action="/register" method="POST">
                <input type="text" name="username" placeholder="Elige un usuario" required>
                <input type="password" name="password" placeholder="Elige una contraseña (mín. 4)" required>
                <button type="submit" class="boton-gris">Registrarse</button>
            </form>
        </div>

    <?php else: ?>
        <!-- PANTALLA PRINCIPAL (USUARIO CON SESIÓN INICIADA) -->
        <div class="caja">
            <p>Hola, <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong></p>
            <form action="/logout" method="POST">
                <button type="submit" class="boton-gris">Cerrar Sesión</button>
            </form>
        </div>

        <div class="caja">
            <?php if ($productoEditar): ?>
                <h2>Editar Producto</h2>
                <form action="/items/editar" method="POST">
                    <input type="hidden" name="id" value="<?php echo $productoEditar['id']; ?>">
                    <input type="text" name="title" value="<?php echo htmlspecialchars($productoEditar['title']); ?>" required>
                    <textarea name="text" required><?php echo htmlspecialchars($productoEditar['text']); ?></textarea>
                    <button type="submit">Guardar Cambios</button>
                    <a href="/" class="enlace-boton boton-gris">Cancelar</a>
                </form>
            <?php else: ?>
                <h2>Añadir Producto</h2>
                <form action="/items" method="POST">
                    <input type="text" name="title" placeholder="Nombre del producto" required>
                    <textarea name="text" placeholder="Descripción o precio" required></textarea>
                    <button type="submit">Guardar Producto</button>
                </form>
            <?php endif; ?>
        </div>

        <div class="caja">
            <h2>Productos Guardados</h2>
            <?php if (count($productos) == 0): ?>
                <p>Todavía no tienes productos guardados.</p>
            <?php else: ?>
                <?php foreach ($productos as $prod): ?>
                    <div class="producto">
                        <strong><?php echo htmlspecialchars($prod['title']); ?></strong>
                        <p><?php echo htmlspecialchars($prod['text']); ?></p>

                        <a href="/?editar=<?php echo $prod['id']; ?>" class="enlace-boton">Editar</a>

                        <form action="/items/borrar" method="POST">
                            <input type="hidden" name="id" value="<?php echo $prod['id']; ?>">
                            <button type="submit" class="boton-rojo">Borrar</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</body>
</html>
