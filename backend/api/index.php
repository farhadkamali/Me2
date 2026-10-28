<?php
declare(strict_types=1);

/**
 * Vam Kamali API starter.
 * Copy config.example.php to config.php and fill in hosting database details.
 * Never upload real database passwords to a public repository.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigin = getenv('VK_ALLOWED_ORIGIN') ?: '';
if ($origin !== '' && $allowedOrigin !== '' && hash_equals($allowedOrigin, $origin)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function respond(int $status, array $data): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    respond(405, ['ok' => false, 'error' => 'method_not_allowed']);
}

$action = $_GET['action'] ?? 'health';
if ($action === 'health') {
    respond(200, [
        'ok' => true,
        'service' => 'vam-kamali-api',
        'configured' => is_file(__DIR__ . '/config.php'),
        'message' => 'API endpoint is running; database connection has not yet been verified.'
    ]);
}

if ($action === 'ads') {
    $configFile = __DIR__ . '/config.php';
    if (!is_file($configFile)) {
        respond(503, ['ok' => false, 'error' => 'server_not_configured']);
    }
    $config = require $configFile;
    try {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $config['db_host'], $config['db_name']);
        $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $stmt = $pdo->query("SELECT id, title, bank, loan_amount, price, term_months, interest_rate, description FROM ads WHERE is_active = 1 ORDER BY id DESC");
        respond(200, ['ok' => true, 'ads' => $stmt->fetchAll()]);
    } catch (Throwable $e) {
        error_log('Vam Kamali API database error: ' . $e->getMessage());
        respond(503, ['ok' => false, 'error' => 'database_unavailable']);
    }
}

respond(404, ['ok' => false, 'error' => 'not_found']);
