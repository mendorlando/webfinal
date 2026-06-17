<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';

$frontendOrigin = env_value('FRONTEND_ORIGIN', 'http://localhost:5173');
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = strlen($requestUri) > 1 ? rtrim($requestUri, '/') : $requestUri;

header('Access-Control-Allow-Origin: ' . $frontendOrigin);
header('Access-Control-Allow-Methods: GET,POST,PUT,DELETE,OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($requestMethod === 'OPTIONS') {
    no_content_response();
}

if ($requestMethod === 'GET' && $path === '/favicon.ico') {
    no_content_response();
}

try {
    if ($requestMethod === 'GET' && $path === '/') {
        json_response(200, [
            'message' => 'Notas API en PHP activa.',
            'routes' => ['/api/health', '/api/notes', '/api/payments/methods', '/api/payments'],
        ]);
    }

    if ($requestMethod === 'GET' && $path === '/api') {
        json_response(200, [
            'message' => 'API lista.',
            'routes' => ['/api/health', '/api/notes', '/api/payments/methods', '/api/payments'],
        ]);
    }

    if ($requestMethod === 'GET' && $path === '/api/health') {
        json_response(200, ['ok' => true, 'service' => 'notas-api-php']);
    }

    $pdo = database();

    if ($requestMethod === 'GET' && $path === '/api/notes') {
        json_response(200, ['notes' => list_notes($pdo), 'stats' => dashboard_stats($pdo)]);
    }

    if ($requestMethod === 'POST' && $path === '/api/notes') {
        $note = validate_note_payload(read_json_body());
        json_response(201, ['note' => create_note($pdo, $note)]);
    }

    if (preg_match('#^/api/notes/(\d+)$#', $path, $matches) === 1) {
        $noteId = (int) $matches[1];

        if ($requestMethod === 'GET') {
            $note = get_note($pdo, $noteId);
            if ($note === null) {
                json_response(404, ['error' => 'Nota no encontrada.']);
            }

            json_response(200, ['note' => $note]);
        }

        if ($requestMethod === 'PUT') {
            $note = update_note($pdo, $noteId, validate_note_payload(read_json_body()));
            if ($note === null) {
                json_response(404, ['error' => 'Nota no encontrada.']);
            }

            json_response(200, ['note' => $note]);
        }

        if ($requestMethod === 'DELETE') {
            if (!delete_note($pdo, $noteId)) {
                json_response(404, ['error' => 'Nota no encontrada.']);
            }

            no_content_response();
        }
    }

    if ($requestMethod === 'GET' && $path === '/api/payments/methods') {
        json_response(200, ['methods' => list_payment_methods($pdo)]);
    }

    if ($requestMethod === 'GET' && $path === '/api/payments') {
        json_response(200, ['checkouts' => list_checkouts($pdo)]);
    }

    if ($requestMethod === 'POST' && $path === '/api/payments/checkout') {
        $methods = list_payment_methods($pdo);
        $checkout = validate_checkout_payload(read_json_body(), $methods);
        $createdCheckout = create_checkout($pdo, $checkout);
        if (!empty($createdCheckout['error'])) {
            json_response(502, [
                'error' => 'El proveedor de pago no pudo iniciar el checkout.',
                'detail' => $createdCheckout['error'],
                'checkout' => $createdCheckout,
                'redirectUrl' => $createdCheckout['redirectUrl'] ?? null,
                'message' => $createdCheckout['message'] ?? 'Fallo al crear el checkout.',
            ]);
        }

        json_response(201, [
            'checkout' => $createdCheckout,
            'redirectUrl' => $createdCheckout['redirectUrl'] ?? null,
            'message' => $createdCheckout['message'] ?? 'Checkout registrado correctamente.',
        ]);
    }

    json_response(404, [
        'error' => 'Ruta no encontrada.',
        'method' => $requestMethod,
        'path' => $path,
    ]);
} catch (JsonException) {
    error_log('JSON decode error while reading stored data.');
    json_response(500, ['error' => 'Error al leer datos almacenados.']);
} catch (Throwable $error) {
    error_log($error->getMessage());
    json_response(500, [
        'error' => 'Error interno del servidor.',
        'detail' => $error->getMessage(),
    ]);
}
