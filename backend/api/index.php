<?php
declare(strict_types=1);

/**
 * Vam Kamali API.
 * Keep config.php private on the server; never commit database credentials.
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
function requestData(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '', true);
    return is_array($data) ? $data : [];
}
function database(): PDO {
    $file = __DIR__ . '/config.php';
    if (!is_file($file)) respond(503, ['ok'=>false, 'error'=>'server_not_configured']);
    $c = require $file;
    return new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $c['db_host'], $c['db_name']),
        $c['db_user'], $c['db_pass'],
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]
    );
}
function issueToken(PDO $pdo, int $userId): string {
    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $stmt = $pdo->prepare('INSERT INTO auth_tokens (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 DAY))');
    $stmt->execute([$userId, $hash]);
    return $token;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? 'health';
if ($action === 'health' && $method === 'GET') {
    respond(200, ['ok'=>true, 'service'=>'vam-kamali-api', 'configured'=>is_file(__DIR__.'/config.php')]);
}

if ($action === 'dbcheck' && $method === 'GET') {
    try {
        $pdo = database();
        $pdo->query('SELECT 1');
        respond(200, ['ok'=>true, 'database'=>'connected']);
    } catch (PDOException $e) {
        error_log('Vam Kamali API database check error: '.$e->getMessage());
        respond(503, ['ok'=>false, 'error'=>'database_connection_failed', 'sqlstate'=>(string)$e->getCode()]);
    } catch (Throwable $e) {
        error_log('Vam Kamali API database check error: '.$e->getMessage());
        respond(503, ['ok'=>false, 'error'=>'database_check_failed']);
    }
}

if ($action === 'register' && $method === 'POST') {
    $d = requestData();
    $username = trim((string)($d['username'] ?? ''));
    $password = (string)($d['password'] ?? '');
    if (!preg_match('/^[a-zA-Z0-9_]{4,24}$/', $username)) {
        respond(422, ['ok'=>false, 'error'=>'invalid_username', 'message'=>'نام کاربری باید ۴ تا ۲۴ حرف انگلیسی، عدد یا زیرخط باشد.']);
    }
    if (strlen($password) < 8 || strlen($password) > 200) {
        respond(422, ['ok'=>false, 'error'=>'invalid_password', 'message'=>'رمز عبور باید حداقل ۸ نویسه باشد.']);
    }
    try {
        $pdo = database();
        $stmt = $pdo->prepare('INSERT INTO members (username, password_hash) VALUES (?, ?)');
        $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
        $id = (int)$pdo->lastInsertId();
        respond(201, ['ok'=>true, 'user'=>['id'=>$id, 'username'=>$username], 'token'=>issueToken($pdo, $id)]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') respond(409, ['ok'=>false, 'error'=>'username_taken', 'message'=>'این نام کاربری قبلاً ثبت شده است.']);
        error_log('Vam Kamali API register error: '.$e->getMessage());
        respond(503, ['ok'=>false, 'error'=>'database_unavailable']);
    } catch (Throwable $e) {
        error_log('Vam Kamali API register error: '.$e->getMessage());
        respond(503, ['ok'=>false, 'error'=>'service_unavailable']);
    }
}

if ($action === 'login' && $method === 'POST') {
    $d = requestData();
    $username = trim((string)($d['username'] ?? ''));
    $password = (string)($d['password'] ?? '');
    try {
        $pdo = database();
        $stmt = $pdo->prepare('SELECT id, username, password_hash FROM members WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) {
            respond(401, ['ok'=>false, 'error'=>'invalid_credentials', 'message'=>'نام کاربری یا رمز عبور اشتباه است.']);
        }
        $token = issueToken($pdo, (int)$user['id']);
        respond(200, ['ok'=>true, 'user'=>['id'=>(int)$user['id'], 'username'=>$user['username']], 'token'=>$token]);
    } catch (Throwable $e) {
        error_log('Vam Kamali API login error: '.$e->getMessage());
        respond(503, ['ok'=>false, 'error'=>'service_unavailable']);
    }
}

if ($action === 'ads' && $method === 'GET') {
    try {
        $pdo = database();
        $stmt = $pdo->query('SELECT id, title, bank, loan_amount, price, term_months, interest_rate, description FROM ads WHERE is_active = 1 ORDER BY id DESC');
        respond(200, ['ok'=>true, 'ads'=>$stmt->fetchAll()]);
    } catch (Throwable $e) {
        error_log('Vam Kamali API ads error: '.$e->getMessage());
        respond(503, ['ok'=>false, 'error'=>'database_unavailable']);
    }
}

respond(405, ['ok'=>false, 'error'=>'not_found_or_method_not_allowed']);
