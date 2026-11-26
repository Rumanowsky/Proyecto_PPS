<?php
// CONTROLADOR DE PRODUCTOS: sirve tanto para formularios HTML normales como para la API JSON

function comprobarSesion($esApi) {
    if (!isset($_SESSION['user_id'])) {
        if ($esApi) {
            http_response_code(401);
            echo json_encode(['error' => 'Tienes que iniciar sesion']);
            exit;
        }
        header('Location: /?error=' . urlencode('Tienes que iniciar sesion'));
        exit;
    }
    return $_SESSION['user_id'];
}

function listarProductos($pdo) {
    $userId = comprobarSesion(true);
    $lista = obtenerProductosUsuario($pdo, $userId);
    http_response_code(200);
    echo json_encode($lista);
    exit;
}

function guardarNuevoProducto($pdo, $datos, $esApi) {
    $userId = comprobarSesion($esApi);
    $titulo = trim($datos['title'] ?? '');
    $texto = trim($datos['text'] ?? '');

    if ($titulo == '' || $texto == '' || strlen($titulo) > 100 || strlen($texto) > 500) {
        if ($esApi) {
            http_response_code(400);
            echo json_encode(['error' => 'Rellena todos los campos correctamente']);
            exit;
        }
        header('Location: /?error=' . urlencode('Rellena todos los campos correctamente'));
        exit;
    }

    $nuevoId = crearProducto($pdo, $userId, $titulo, $texto);

    if ($esApi) {
        http_response_code(201);
        echo json_encode(['id' => $nuevoId, 'title' => $titulo, 'text' => $texto]);
        exit;
    }
    header('Location: /');
    exit;
}

function actualizarProducto($pdo, $id, $datos, $esApi) {
    $userId = comprobarSesion($esApi);
    $producto = buscarProductoPorId($pdo, $id);

    if (!$producto) {
        if ($esApi) {
            http_response_code(404);
            echo json_encode(['error' => 'El producto no existe']);
            exit;
        }
        header('Location: /?error=' . urlencode('El producto no existe'));
        exit;
    }

    if ($producto['user_id'] != $userId) {
        if ($esApi) {
            http_response_code(403);
            echo json_encode(['error' => 'No puedes editar productos de otro usuario']);
            exit;
        }
        header('Location: /?error=' . urlencode('No puedes editar productos de otro usuario'));
        exit;
    }

    $titulo = trim($datos['title'] ?? '');
    $texto = trim($datos['text'] ?? '');

    if ($titulo == '' || $texto == '' || strlen($titulo) > 100 || strlen($texto) > 500) {
        if ($esApi) {
            http_response_code(400);
            echo json_encode(['error' => 'Datos no validos']);
            exit;
        }
        header('Location: /?error=' . urlencode('Datos no validos'));
        exit;
    }

    editarProducto($pdo, $id, $titulo, $texto);

    if ($esApi) {
        http_response_code(200);
        echo json_encode(['id' => $id, 'title' => $titulo, 'text' => $texto]);
        exit;
    }
    header('Location: /');
    exit;
}

function eliminarProducto($pdo, $id, $esApi) {
    $userId = comprobarSesion($esApi);
    $producto = buscarProductoPorId($pdo, $id);

    if (!$producto) {
        if ($esApi) {
            http_response_code(404);
            echo json_encode(['error' => 'El producto no existe']);
            exit;
        }
        header('Location: /?error=' . urlencode('El producto no existe'));
        exit;
    }

    if ($producto['user_id'] != $userId) {
        if ($esApi) {
            http_response_code(403);
            echo json_encode(['error' => 'No puedes borrar productos de otro usuario']);
            exit;
        }
        header('Location: /?error=' . urlencode('No puedes borrar productos de otro usuario'));
        exit;
    }

    borrarProducto($pdo, $id);

    if ($esApi) {
        http_response_code(204);
        exit;
    }
    header('Location: /');
    exit;
}
