<?php

declare(strict_types=1);

load_env_file(dirname(__DIR__) . '/.env');

function load_env_file(string $path): void
{
    static $loaded = [];

    if (isset($loaded[$path]) || !is_file($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }

        $parts = explode('=', $trimmed, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $key = trim($parts[0]);
        $value = trim($parts[1]);
        $value = trim($value, "\"'");

        $_ENV[$key] = $value;
        putenv($key . '=' . $value);
    }

    $loaded[$path] = true;
}

function env_value(string $key, string $default): string
{
    $value = $_ENV[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return (string) $value;
}

function json_response(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function no_content_response(): void
{
    http_response_code(204);
    exit;
}

function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        json_response(400, ['error' => 'JSON invalido.']);
    }

    return $data;
}

function normalize_tags(mixed $value): array
{
    if (is_array($value)) {
        $tags = $value;
    } elseif (is_string($value)) {
        $tags = explode(',', $value);
    } else {
        return [];
    }

    $tags = array_map(static fn ($tag) => trim((string) $tag), $tags);
    return array_values(array_filter($tags, static fn ($tag) => $tag !== ''));
}

function validate_note_payload(array $payload): array
{
    $title = trim((string) ($payload['title'] ?? ''));
    $content = trim((string) ($payload['content'] ?? ''));

    if ($title === '') {
        json_response(422, ['error' => 'El titulo es obligatorio.']);
    }

    if ($content === '') {
        json_response(422, ['error' => 'El contenido es obligatorio.']);
    }

    return [
        'title' => $title,
        'content' => $content,
        'tags' => normalize_tags($payload['tags'] ?? []),
        'isPinned' => (bool) ($payload['isPinned'] ?? false),
    ];
}

function validate_checkout_payload(array $payload, array $availableMethods): array
{
    $methodId = trim((string) ($payload['methodId'] ?? ''));
    $plan = trim((string) ($payload['plan'] ?? ''));
    $customerName = trim((string) ($payload['customerName'] ?? ''));
    $customerEmail = trim((string) ($payload['customerEmail'] ?? ''));

    if ($methodId === '' || $plan === '' || $customerName === '' || $customerEmail === '') {
        json_response(422, ['error' => 'Todos los campos del checkout son obligatorios.']);
    }

    $methodIds = array_map(static fn ($method) => $method['id'], $availableMethods);
    if (!in_array($methodId, $methodIds, true)) {
        json_response(422, ['error' => 'Metodo de pago no valido.']);
    }

    return [
        'methodId' => $methodId,
        'plan' => $plan,
        'customerName' => $customerName,
        'customerEmail' => $customerEmail,
    ];
}

function map_note(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'title' => $row['title'],
        'content' => $row['content'],
        'tags' => json_decode($row['tags'], true, 512, JSON_THROW_ON_ERROR),
        'isPinned' => (bool) $row['is_pinned'],
        'createdAt' => $row['created_at'],
        'updatedAt' => $row['updated_at'],
    ];
}

function map_method(array $row): array
{
    return [
        'id' => $row['id'],
        'name' => $row['name'],
        'description' => $row['description'],
        'providerUrl' => $row['provider_url'],
        'enabled' => (bool) $row['enabled'],
    ];
}

function map_checkout(array $row): array
{
    return [
        'id' => (int) $row['id'],
        'methodId' => $row['method_id'],
        'plan' => $row['plan'],
        'customerName' => $row['customer_name'],
        'customerEmail' => $row['customer_email'],
        'status' => $row['status'],
        'createdAt' => $row['created_at'],
    ];
}

function database(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (!class_exists('PDO')) {
        throw new RuntimeException('PHP no tiene habilitada la extension PDO.');
    }

    if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        throw new RuntimeException('PDO SQLite no esta habilitado. Activa pdo_sqlite y sqlite3 en php.ini.');
    }

    $databaseDir = dirname(__DIR__) . '/data';
    if (!is_dir($databaseDir)) {
        mkdir($databaseDir, 0777, true);
    }

    $databasePath = $databaseDir . '/notes.sqlite';
    $pdo = new PDO('sqlite:' . $databasePath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA busy_timeout = 5000;');
    $pdo->exec('PRAGMA foreign_keys = ON;');

    initialize_database($pdo);
    return $pdo;
}

function initialize_database(PDO $pdo): void
{
    $pdo->exec('PRAGMA journal_mode = WAL;');

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS notes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            content TEXT NOT NULL,
            tags TEXT NOT NULL DEFAULT "[]",
            is_pinned INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS payment_methods (
            id TEXT PRIMARY KEY,
            name TEXT NOT NULL,
            description TEXT NOT NULL,
            provider_url TEXT NOT NULL,
            enabled INTEGER NOT NULL DEFAULT 1
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS checkouts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            method_id TEXT NOT NULL,
            plan TEXT NOT NULL,
            customer_name TEXT NOT NULL,
            customer_email TEXT NOT NULL,
            status TEXT NOT NULL,
            created_at TEXT NOT NULL
        )'
    );

    seed_payment_methods($pdo);
    seed_notes($pdo);
}

function seed_payment_methods(PDO $pdo): void
{
    $total = (int) $pdo->query('SELECT COUNT(*) FROM payment_methods')->fetchColumn();
    if ($total > 0) {
        return;
    }

    $methods = [
        ['stripe', 'Stripe', 'Cobros con tarjeta y flujo moderno para suscripciones.', 'https://stripe.com/'],
        ['paypal', 'PayPal', 'Pago con cuenta PayPal y opcion de tarjeta para compradores.', 'https://www.paypal.com/'],
        ['mercadopago', 'Mercado Pago', 'Metodo popular en LATAM con checkout flexible.', 'https://www.mercadopago.com/'],
    ];

    $stmt = $pdo->prepare(
        'INSERT INTO payment_methods (id, name, description, provider_url, enabled)
         VALUES (:id, :name, :description, :provider_url, 1)'
    );

    foreach ($methods as [$id, $name, $description, $providerUrl]) {
        $stmt->execute([
            ':id' => $id,
            ':name' => $name,
            ':description' => $description,
            ':provider_url' => $providerUrl,
        ]);
    }
}

function seed_notes(PDO $pdo): void
{
    $total = (int) $pdo->query('SELECT COUNT(*) FROM notes')->fetchColumn();
    if ($total > 0) {
        return;
    }

    $notes = [
        [
            'title' => 'Resumen de clases',
            'content' => 'Organiza las ideas clave por tema, agrega tareas pendientes y marca lo mas urgente.',
            'tags' => ['escuela', 'resumen'],
            'isPinned' => 1,
        ],
        [
            'title' => 'Pendientes del proyecto',
            'content' => 'Separar frontend y backend, revisar pagos y preparar el manual de usuario.',
            'tags' => ['proyecto', 'entrega'],
            'isPinned' => 0,
        ],
    ];

    $stmt = $pdo->prepare(
        'INSERT INTO notes (title, content, tags, is_pinned, created_at, updated_at)
         VALUES (:title, :content, :tags, :is_pinned, :created_at, :updated_at)'
    );

    $now = gmdate('c');
    foreach ($notes as $note) {
        $stmt->execute([
            ':title' => $note['title'],
            ':content' => $note['content'],
            ':tags' => json_encode($note['tags'], JSON_UNESCAPED_UNICODE),
            ':is_pinned' => $note['isPinned'],
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
    }
}

function list_notes(PDO $pdo): array
{
    $rows = $pdo->query(
        'SELECT id, title, content, tags, is_pinned, created_at, updated_at
         FROM notes
         ORDER BY is_pinned DESC, updated_at DESC'
    )->fetchAll();

    return array_map('map_note', $rows);
}

function get_note(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, title, content, tags, is_pinned, created_at, updated_at
         FROM notes
         WHERE id = :id'
    );
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();

    return $row ? map_note($row) : null;
}

function create_note(PDO $pdo, array $note): array
{
    $now = gmdate('c');
    $stmt = $pdo->prepare(
        'INSERT INTO notes (title, content, tags, is_pinned, created_at, updated_at)
         VALUES (:title, :content, :tags, :is_pinned, :created_at, :updated_at)'
    );
    $stmt->execute([
        ':title' => $note['title'],
        ':content' => $note['content'],
        ':tags' => json_encode($note['tags'], JSON_UNESCAPED_UNICODE),
        ':is_pinned' => $note['isPinned'] ? 1 : 0,
        ':created_at' => $now,
        ':updated_at' => $now,
    ]);

    return get_note($pdo, (int) $pdo->lastInsertId());
}

function update_note(PDO $pdo, int $id, array $note): ?array
{
    $stmt = $pdo->prepare(
        'UPDATE notes
         SET title = :title, content = :content, tags = :tags, is_pinned = :is_pinned, updated_at = :updated_at
         WHERE id = :id'
    );
    $stmt->execute([
        ':title' => $note['title'],
        ':content' => $note['content'],
        ':tags' => json_encode($note['tags'], JSON_UNESCAPED_UNICODE),
        ':is_pinned' => $note['isPinned'] ? 1 : 0,
        ':updated_at' => gmdate('c'),
        ':id' => $id,
    ]);

    if ($stmt->rowCount() === 0) {
        return get_note($pdo, $id);
    }

    return get_note($pdo, $id);
}

function delete_note(PDO $pdo, int $id): bool
{
    $stmt = $pdo->prepare('DELETE FROM notes WHERE id = :id');
    $stmt->execute([':id' => $id]);
    return $stmt->rowCount() > 0;
}

function list_payment_methods(PDO $pdo): array
{
    $rows = $pdo->query(
        'SELECT id, name, description, provider_url, enabled
         FROM payment_methods
         WHERE enabled = 1
         ORDER BY name ASC'
    )->fetchAll();

    return array_map('map_method', $rows);
}

function create_checkout(PDO $pdo, array $checkout): array
{
    $now = gmdate('c');
    $methodId = $checkout['methodId'];
    $providerCheckout = match ($methodId) {
        'stripe' => create_stripe_session($checkout),
        'paypal' => create_paypal_order($checkout),
        'mercadopago' => create_mercadopago_preference($checkout),
        default => [
            'mode' => 'demo',
            'redirectUrl' => null,
            'message' => 'Metodo registrado en modo demo.',
        ],
    };

    $status = !empty($providerCheckout['redirectUrl']) ? 'pending_' . $methodId : 'demo_' . $methodId;

    $stmt = $pdo->prepare(
        'INSERT INTO checkouts (method_id, plan, customer_name, customer_email, status, created_at)
         VALUES (:method_id, :plan, :customer_name, :customer_email, :status, :created_at)'
    );
    $stmt->execute([
        ':method_id' => $methodId,
        ':plan' => $checkout['plan'],
        ':customer_name' => $checkout['customerName'],
        ':customer_email' => $checkout['customerEmail'],
        ':status' => $status,
        ':created_at' => $now,
    ]);

    $id = (int) $pdo->lastInsertId();

    return [
        'id' => $id,
        'methodId' => $methodId,
        'plan' => $checkout['plan'],
        'customerName' => $checkout['customerName'],
        'customerEmail' => $checkout['customerEmail'],
        'status' => $status,
        'createdAt' => $now,
        ...$providerCheckout,
    ];
}

function create_stripe_session(array $checkout): array
{
    $stripeKey = env_value('STRIPE_SECRET_KEY', '');
    if (empty($stripeKey)) {
        return [
            'mode' => 'demo',
            'sessionId' => null,
            'redirectUrl' => null,
            'message' => 'Stripe en modo demo (falta STRIPE_SECRET_KEY en .env)',
        ];
    }

    $prices = [
        'basic' => 1000,
        'pro' => 2500,
        'team' => 5000,
    ];

    $amount = $prices[$checkout['plan']] ?? 1000;
    $description = "Suscripción {$checkout['plan']} - NotasFlow";

    try {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL no esta habilitado.');
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.stripe.com/v1/checkout/sessions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_USERPWD, $stripeKey . ':');
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'payment_method_types[]' => 'card',
            'line_items[0][price_data][currency]' => 'usd',
            'line_items[0][price_data][unit_amount]' => $amount,
            'line_items[0][price_data][product_data][name]' => $description,
            'line_items[0][quantity]' => 1,
            'mode' => 'payment',
            'success_url' => env_value('FRONTEND_ORIGIN', 'http://localhost:5173') . '?payment=success',
            'cancel_url' => env_value('FRONTEND_ORIGIN', 'http://localhost:5173') . '?payment=cancel',
            'customer_email' => $checkout['customerEmail'],
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException('Error de red al conectar con Stripe: ' . $curlError);
        }

        if ($httpCode !== 200) {
            throw new RuntimeException('Error al crear sesión en Stripe: ' . $response);
        }

        $data = json_decode($response, true);

        if (!isset($data['id'])) {
            throw new RuntimeException('No se generó session ID de Stripe');
        }

        return [
            'mode' => 'live',
            'sessionId' => $data['id'],
            'redirectUrl' => $data['url'] ?? null,
            'message' => 'Redirigiendo a Stripe Checkout.',
        ];
    } catch (Throwable $error) {
        error_log('Stripe error: ' . $error->getMessage());
        return [
            'mode' => 'demo',
            'sessionId' => null,
            'redirectUrl' => null,
            'message' => 'Stripe no pudo iniciar el checkout real. Se registro el intento en modo demo.',
            'error' => $error->getMessage(),
        ];
    }
}

function create_paypal_order(array $checkout): array
{
    $clientId = env_value('PAYPAL_CLIENT_ID', '');
    $secret = env_value('PAYPAL_SECRET', '');
    if ($clientId === '' || $secret === '' || str_contains($clientId, 'YOUR_') || str_contains($secret, 'YOUR_')) {
        return [
            'mode' => 'demo',
            'orderId' => null,
            'redirectUrl' => null,
            'message' => 'PayPal en modo demo (faltan PAYPAL_CLIENT_ID y PAYPAL_SECRET en .env).',
        ];
    }

    try {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL no esta habilitado.');
        }

        $baseUrl = env_value('PAYPAL_BASE_URL', 'https://api-m.sandbox.paypal.com');
        $currency = env_value('PAYPAL_CURRENCY', 'USD');
        $pricing = plan_pricing($checkout['plan']);
        $frontUrl = env_value('FRONTEND_ORIGIN', 'http://localhost:5173');

        $tokenResponse = http_form_request(
            $baseUrl . '/v1/oauth2/token',
            ['grant_type' => 'client_credentials'],
            [
                CURLOPT_USERPWD => $clientId . ':' . $secret,
                CURLOPT_HTTPHEADER => ['Accept: application/json', 'Accept-Language: es_MX'],
            ]
        );

        if (($tokenResponse['status'] ?? 0) !== 200 || empty($tokenResponse['data']['access_token'])) {
            throw new RuntimeException('No se pudo obtener access token de PayPal.');
        }

        $orderResponse = http_json_request(
            $baseUrl . '/v2/checkout/orders',
            [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'description' => $pricing['description'],
                    'amount' => [
                        'currency_code' => $currency,
                        'value' => $pricing['major'],
                    ],
                ]],
                'application_context' => [
                    'return_url' => $frontUrl . '?payment=success&provider=paypal',
                    'cancel_url' => $frontUrl . '?payment=cancel&provider=paypal',
                    'user_action' => 'PAY_NOW',
                    'shipping_preference' => 'NO_SHIPPING',
                ],
            ],
            [
                'Authorization: Bearer ' . $tokenResponse['data']['access_token'],
                'Content-Type: application/json',
            ]
        );

        if (($orderResponse['status'] ?? 0) !== 201 || empty($orderResponse['data']['id'])) {
            throw new RuntimeException('PayPal no devolvio una orden valida.');
        }

        $approvalUrl = null;
        foreach ($orderResponse['data']['links'] ?? [] as $link) {
            if (($link['rel'] ?? '') === 'payer-action' || ($link['rel'] ?? '') === 'approve') {
                $approvalUrl = $link['href'] ?? null;
                break;
            }
        }

        if (empty($approvalUrl)) {
            throw new RuntimeException('PayPal no devolvio approval URL.');
        }

        return [
            'mode' => 'live',
            'orderId' => $orderResponse['data']['id'],
            'redirectUrl' => $approvalUrl,
            'message' => 'Redirigiendo a PayPal.',
        ];
    } catch (Throwable $error) {
        error_log('PayPal error: ' . $error->getMessage());
        return [
            'mode' => 'demo',
            'orderId' => null,
            'redirectUrl' => null,
            'message' => 'PayPal no pudo iniciar el checkout real. Se registro el intento en modo demo.',
            'error' => $error->getMessage(),
        ];
    }
}

function create_mercadopago_preference(array $checkout): array
{
    $accessToken = env_value('MERCADOPAGO_ACCESS_TOKEN', '');
    if ($accessToken === '' || str_contains($accessToken, 'XXXXXXXX') || str_contains($accessToken, 'YOUR_')) {
        return [
            'mode' => 'demo',
            'preferenceId' => null,
            'redirectUrl' => null,
            'message' => 'Mercado Pago en modo demo (falta MERCADOPAGO_ACCESS_TOKEN en .env).',
        ];
    }

    try {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL no esta habilitado.');
        }

        $baseUrl = env_value('MERCADOPAGO_BASE_URL', 'https://api.mercadopago.com');
        $currency = env_value('MERCADOPAGO_CURRENCY', 'MXN');
        $pricing = plan_pricing($checkout['plan']);
        $frontUrl = env_value('FRONTEND_ORIGIN', 'http://localhost:5173');
        $isLocalFrontend =
            str_contains($frontUrl, 'localhost') ||
            str_contains($frontUrl, '127.0.0.1');

        $payload = [
            'items' => [[
                'title' => $pricing['description'],
                'quantity' => 1,
                'currency_id' => $currency,
                'unit_price' => (float) $pricing['major'],
            ]],
            'payer' => [
                'name' => $checkout['customerName'],
                'email' => $checkout['customerEmail'],
            ],
            'back_urls' => [
                'success' => $frontUrl . '?payment=success&provider=mercadopago',
                'failure' => $frontUrl . '?payment=cancel&provider=mercadopago',
                'pending' => $frontUrl . '?payment=pending&provider=mercadopago',
            ],
            'external_reference' => 'notasflow-' . uniqid(),
        ];

        if (!$isLocalFrontend) {
            $payload['auto_return'] = 'approved';
        }

        $response = http_json_request(
            $baseUrl . '/checkout/preferences',
            $payload,
            [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ]
        );

        if (!in_array($response['status'] ?? 0, [200, 201], true) || empty($response['data']['id'])) {
            throw new RuntimeException(
                'Mercado Pago no devolvio una preferencia valida. Respuesta: ' . ($response['raw'] ?? 'sin detalle')
            );
        }

        $redirectUrl = $response['data']['init_point'] ?? $response['data']['sandbox_init_point'] ?? null;
        if (empty($redirectUrl)) {
            throw new RuntimeException('Mercado Pago no devolvio init_point.');
        }

        return [
            'mode' => 'live',
            'preferenceId' => $response['data']['id'],
            'redirectUrl' => $redirectUrl,
            'message' => 'Redirigiendo a Mercado Pago.',
        ];
    } catch (Throwable $error) {
        error_log('Mercado Pago error: ' . $error->getMessage());
        return [
            'mode' => 'demo',
            'preferenceId' => null,
            'redirectUrl' => null,
            'message' => 'Mercado Pago no pudo iniciar el checkout real. Se registro el intento en modo demo.',
            'error' => $error->getMessage(),
        ];
    }
}

function plan_pricing(string $plan): array
{
    $plans = [
        'basic' => ['minor' => 1000, 'major' => '10.00', 'description' => 'Suscripción Basic - NotasFlow'],
        'pro' => ['minor' => 2500, 'major' => '25.00', 'description' => 'Suscripción Pro - NotasFlow'],
        'team' => ['minor' => 5000, 'major' => '50.00', 'description' => 'Suscripción Team - NotasFlow'],
    ];

    return $plans[$plan] ?? $plans['basic'];
}

function http_form_request(string $url, array $fields, array $curlOptions = []): array
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));

    foreach ($curlOptions as $option => $value) {
        curl_setopt($ch, $option, $value);
    }

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException('Error de red: ' . $error);
    }

    return [
        'status' => $status,
        'data' => json_decode($response, true) ?? [],
        'raw' => $response,
    ];
}

function http_json_request(string $url, array $payload, array $headers = []): array
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException('Error de red: ' . $error);
    }

    return [
        'status' => $status,
        'data' => json_decode($response, true) ?? [],
        'raw' => $response,
    ];
}

function list_checkouts(PDO $pdo): array
{
    $rows = $pdo->query(
        'SELECT id, method_id, plan, customer_name, customer_email, status, created_at
         FROM checkouts
         ORDER BY created_at DESC'
    )->fetchAll();

    return array_map('map_checkout', $rows);
}

function dashboard_stats(PDO $pdo): array
{
    return [
        'notesTotal' => (int) $pdo->query('SELECT COUNT(*) FROM notes')->fetchColumn(),
        'pinnedTotal' => (int) $pdo->query('SELECT COUNT(*) FROM notes WHERE is_pinned = 1')->fetchColumn(),
        'checkoutTotal' => (int) $pdo->query('SELECT COUNT(*) FROM checkouts')->fetchColumn(),
    ];
}
