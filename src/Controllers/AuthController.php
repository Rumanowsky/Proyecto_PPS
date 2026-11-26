<?php
// CONTROLADOR DE USUARIOS: sirve tanto para formularios HTML normales como para la API JSON

function registrarUsuario($pdo, $datos, $esApi) {
    $usuario = trim($datos['username'] ?? '');
    $clave = $datos['password'] ?? '';

    if ($usuario == '' || $clave == '' || strlen($usuario) > 50 || strlen($clave) < 4) {
        if ($esApi) {
            http_response_code(400);
            echo json_encode(['error' => 'Datos no validos']);
            exit;
        }
        header('Location: /?error=' . urlencode('Datos no validos (mínimo 4 caracteres)'));
        exit;
    }

    if (buscarUsuario($pdo, $usuario)) {
        if ($esApi) {
            http_response_code(409);
            echo json_encode(['error' => 'El usuario ya existe']);
            exit;
        }
        header('Location: /?error=' . urlencode('El usuario ya existe'));
        exit;
    }

    crearUsuario($pdo, $usuario, $clave);

    if ($esApi) {
        http_response_code(201);
        echo json_encode(['username' => $usuario]);
        exit;
    }
    header('Location: /?ok=' . urlencode('Cuenta creada. Ya puedes iniciar sesión.'));
    exit;
}

function iniciarSesion($pdo, $datos, $esApi) {
    $usuario = trim($datos['username'] ?? '');
    $clave = $datos['password'] ?? '';

    $encontrado = buscarUsuario($pdo, $usuario);

    if (!$encontrado || !password_verify($clave, $encontrado['password'])) {
        if ($esApi) {
            http_response_code(401);
            echo json_encode(['error' => 'Usuario o contraseña incorrectos']);
            exit;
        }
        header('Location: /?error=' . urlencode('Usuario o contraseña incorrectos'));
        exit;
    }

    $_SESSION['user_id'] = $encontrado['id'];
    $_SESSION['username'] = $encontrado['username'];

    if ($esApi) {
        http_response_code(200);
        echo json_encode(['username' => $encontrado['username']]);
        exit;
    }
    header('Location: /');
    exit;
}

function cerrarSesion($esApi) {
    session_destroy();
    if ($esApi) {
        http_response_code(200);
        echo json_encode(['mensaje' => 'Sesion cerrada']);
        exit;
    }
    header('Location: /');
    exit;
}
