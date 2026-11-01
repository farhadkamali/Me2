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
// Bundled Android WebView pages loaded from file:// send Origin: null.
if ($origin === 'null' || ($origin !== '' && $allowedOrigin !== '' && hash_equals($allowedOrigin, $origin))) {
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
function bearerToken(): string {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/^Bearer\\s+([a-f0-9]{64})$/i', $header, $m)) return strtolower($m[1]);
    return '';
}
function authenticatedUser(PDO $pdo): array {
    $token = bearerToken();
    if ($token === '') respond(401, ['ok'=>false, 'error'=>'authentication_required']);
    $stmt = $pdo->prepare('SELECT m.id, m.username, m.role FROM auth_tokens t JOIN members m ON m.id=t.user_id WHERE t.token_hash=? AND t.expires_at>UTC_TIMESTAMP() LIMIT 1');
    $stmt->execute([hash('sha256', $token)]);
    $user = $stmt->fetch();
    if (!$user) respond(401, ['ok'=>false, 'error'=>'invalid_or_expired_token']);
    return $user;
}
function requireAdmin(PDO $pdo): array {
    $user = authenticatedUser($pdo);
    if (($user['role'] ?? 'member') !== 'admin') respond(403, ['ok'=>false, 'error'=>'admin_access_required']);
    return $user;
}


function ensureFeatureTables(PDO $pdo): void {
 $pdo->exec("CREATE TABLE IF NOT EXISTS member_inquiries (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, phone VARCHAR(11) NOT NULL DEFAULT '', subject VARCHAR(180) NOT NULL, body TEXT NOT NULL, status ENUM('new','answered','closed') NOT NULL DEFAULT 'new', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX(user_id), INDEX(status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $cols=$pdo->query("SHOW COLUMNS FROM member_inquiries LIKE 'phone'"); if(!$cols->fetch()) $pdo->exec("ALTER TABLE member_inquiries ADD phone VARCHAR(11) NOT NULL DEFAULT '' AFTER user_id");
 $pdo->exec("CREATE TABLE IF NOT EXISTS member_messages (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, member_id BIGINT UNSIGNED NOT NULL, sender_id BIGINT UNSIGNED NOT NULL, body TEXT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX(member_id,id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $pdo->exec("CREATE TABLE IF NOT EXISTS purchase_requests (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, ad_id BIGINT UNSIGNED NOT NULL, full_name VARCHAR(120) NOT NULL, phone VARCHAR(11) NOT NULL, contact_id VARCHAR(160) NOT NULL DEFAULT '', city VARCHAR(80) NOT NULL DEFAULT '', notes TEXT NOT NULL, status ENUM('new','contacted','closed') NOT NULL DEFAULT 'new', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX(user_id), INDEX(ad_id), INDEX(status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
 $cols=$pdo->query("SHOW COLUMNS FROM purchase_requests LIKE 'contact_id'"); if(!$cols->fetch()) $pdo->exec("ALTER TABLE purchase_requests ADD contact_id VARCHAR(160) NOT NULL DEFAULT '' AFTER phone");
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
        $stmt = $pdo->prepare('INSERT INTO members (username, password_hash, role) VALUES (?, ?, \'member\')');
        $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
        $id = (int)$pdo->lastInsertId();
        respond(201, ['ok'=>true, 'user'=>['id'=>$id, 'username'=>$username, 'role'=>'member'], 'token'=>issueToken($pdo, $id)]);
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
        $stmt = $pdo->prepare('SELECT id, username, password_hash, role FROM members WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) {
            respond(401, ['ok'=>false, 'error'=>'invalid_credentials', 'message'=>'نام کاربری یا رمز عبور اشتباه است.']);
        }
        $token = issueToken($pdo, (int)$user['id']);
        respond(200, ['ok'=>true, 'user'=>['id'=>(int)$user['id'], 'username'=>$user['username'], 'role'=>$user['role']], 'token'=>$token]);
    } catch (Throwable $e) {
        error_log('Vam Kamali API login error: '.$e->getMessage());
        respond(503, ['ok'=>false, 'error'=>'service_unavailable']);
    }
}


if ($action === 'me' && $method === 'GET') {
    try {
        $pdo = database();
        $user = authenticatedUser($pdo);
        respond(200, ['ok'=>true, 'user'=>['id'=>(int)$user['id'], 'username'=>$user['username'], 'role'=>$user['role']]]);
    } catch (Throwable $e) {
        error_log('Vam Kamali API me error: '.$e->getMessage());
        respond(503, ['ok'=>false, 'error'=>'service_unavailable']);
    }
}

if ($action === 'admin-check' && $method === 'GET') {
    try {
        $pdo = database();
        $user = requireAdmin($pdo);
        respond(200, ['ok'=>true, 'admin'=>true, 'user'=>['id'=>(int)$user['id'], 'username'=>$user['username'], 'role'=>'admin']]);
    } catch (Throwable $e) {
        error_log('Vam Kamali API admin check error: '.$e->getMessage());
        respond(503, ['ok'=>false, 'error'=>'service_unavailable']);
    }
}

if ($action === 'password-change' && $method === 'POST') {
 try {
  $pdo=database(); $u=authenticatedUser($pdo); $d=requestData();
  $current=(string)($d['current_password']??''); $next=(string)($d['new_password']??'');
  if(strlen($next)<8||strlen($next)>200) respond(422,['ok'=>false,'error'=>'invalid_new_password']);
  $q=$pdo->prepare('SELECT password_hash FROM members WHERE id=? LIMIT 1'); $q->execute([$u['id']]); $row=$q->fetch();
  if(!$row||!password_verify($current,$row['password_hash'])) respond(401,['ok'=>false,'error'=>'current_password_incorrect']);
  $q=$pdo->prepare('UPDATE members SET password_hash=? WHERE id=?'); $q->execute([password_hash($next,PASSWORD_DEFAULT),$u['id']]);
  respond(200,['ok'=>true]);
 } catch(Throwable $e) { error_log('Vam Kamali password change error: '.$e->getMessage()); respond(503,['ok'=>false,'error'=>'service_unavailable']); }
}
if ($action === 'logout' && $method === 'POST') {
    try {
        $token = bearerToken();
        if ($token === '') respond(401, ['ok'=>false, 'error'=>'authentication_required']);
        $pdo = database();
        $stmt = $pdo->prepare('DELETE FROM auth_tokens WHERE token_hash=?');
        $stmt->execute([hash('sha256', $token)]);
        respond(200, ['ok'=>true, 'logged_out'=>true]);
    } catch (Throwable $e) {
        error_log('Vam Kamali API logout error: '.$e->getMessage());
        respond(503, ['ok'=>false, 'error'=>'service_unavailable']);
    }
}

if ($action === 'ads' && $method === 'GET') {
    try {
        $pdo = database();
        $stmt = $pdo->query('SELECT a.id, a.title, a.bank, a.loan_amount, a.price, a.term_months, a.interest_rate, a.description FROM ads a WHERE a.is_active=1 ORDER BY a.id DESC');
        respond(200, ['ok'=>true, 'ads'=>$stmt->fetchAll()]);
    } catch (Throwable $e) {
        error_log('Vam Kamali API ads error: '.$e->getMessage());
        respond(503, ['ok'=>false, 'error'=>'database_unavailable']);
    }
}

/* Admin-only advertisement management. All writes are server-authorized. */
if ($action === 'admin-ads' && $method === 'GET') {
    try {
        $pdo = database();
        requireAdmin($pdo);
        $stmt = $pdo->query('SELECT id, title, bank, loan_amount, price, term_months, interest_rate, description, is_active FROM ads ORDER BY id DESC');
        respond(200, ['ok'=>true, 'ads'=>$stmt->fetchAll()]);
    } catch (Throwable $e) {
        error_log('Vam Kamali API admin ads error: '.$e->getMessage());
        respond(503, ['ok'=>false, 'error'=>'service_unavailable']);
    }
}
if ($action === 'admin-ad-save' && $method === 'POST') {
    try {
        $pdo = database();
        requireAdmin($pdo);
        $d = requestData();
        $title = trim((string)($d['title'] ?? ''));
        $bank = trim((string)($d['bank'] ?? ''));
        $loan = filter_var($d['loan_amount'] ?? null, FILTER_VALIDATE_INT);
        $price = filter_var($d['price'] ?? null, FILTER_VALIDATE_INT);
        $term = filter_var($d['term_months'] ?? null, FILTER_VALIDATE_INT);
        $interest = trim((string)($d['interest_rate'] ?? ''));
        $description = trim((string)($d['description'] ?? ''));
        $id = filter_var($d['id'] ?? 0, FILTER_VALIDATE_INT);
        if ($title === '' || mb_strlen($title) > 180 || $bank === '' || mb_strlen($bank) > 120 ||
            $loan === false || $loan < 1 || $price === false || $price < 0 ||
            $term === false || $term < 1 || $term > 120 || mb_strlen($interest) > 40 || mb_strlen($description) > 5000) {
            respond(422, ['ok'=>false, 'error'=>'invalid_ad_fields', 'message'=>'اطلاعات آگهی معتبر نیست.']);
        }
        if ($id && $id > 0) {
            $stmt = $pdo->prepare('UPDATE ads SET title=?, bank=?, loan_amount=?, price=?, term_months=?, interest_rate=?, description=? WHERE id=?');
            $stmt->execute([$title,$bank,$loan,$price,$term,$interest,$description,$id]);
            respond(200, ['ok'=>true, 'updated'=>true]);
        }
        $stmt = $pdo->prepare('INSERT INTO ads (title, bank, loan_amount, price, term_months, interest_rate, description, is_active) VALUES (?,?,?,?,?,?,?,1)');
        $stmt->execute([$title,$bank,$loan,$price,$term,$interest,$description]);
        respond(201, ['ok'=>true, 'id'=>(int)$pdo->lastInsertId()]);
    } catch (Throwable $e) {
        error_log('Vam Kamali API admin save ad error: '.$e->getMessage());
        respond(503, ['ok'=>false, 'error'=>'service_unavailable']);
    }
}
if ($action === 'admin-ad-delete' && $method === 'POST') {
    try {
        $pdo = database();
        requireAdmin($pdo);
        $d = requestData();
        $id = filter_var($d['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || $id < 1) respond(422, ['ok'=>false, 'error'=>'invalid_ad_id']);
        $stmt = $pdo->prepare('UPDATE ads SET is_active=0 WHERE id=?');
        $stmt->execute([$id]);
        respond(200, ['ok'=>true, 'deleted'=>true]);
    } catch (Throwable $e) {
        error_log('Vam Kamali API admin delete ad error: '.$e->getMessage());
        respond(503, ['ok'=>false, 'error'=>'service_unavailable']);
    }
}


/* Purchase requests submitted by signed-in members; visible to admins. */
if ($action === 'purchase-request-create' && $method === 'POST') {
 try {
  $pdo=database(); $u=authenticatedUser($pdo); $d=requestData();
  $adId=filter_var($d['ad_id']??null,FILTER_VALIDATE_INT);
  $name=trim((string)($d['full_name']??'')); $phone=trim((string)($d['phone']??''));
  $contactId=trim((string)($d['contact_id']??''));
  $city=''; $notes='';
  if(!$adId||$adId<1||$name===''||mb_strlen($name)>120||!preg_match('/^09[0-9]{9}$/',$phone)||$contactId===''||mb_strlen($contactId)>160) respond(422,['ok'=>false,'error'=>'invalid_purchase_request']);
  $q=$pdo->prepare('SELECT id FROM ads WHERE id=? AND is_active=1 LIMIT 1'); $q->execute([$adId]);
  if(!$q->fetch()) respond(404,['ok'=>false,'error'=>'ad_not_available']);
  $q=$pdo->prepare('INSERT INTO purchase_requests(user_id,ad_id,full_name,phone,contact_id,city,notes) VALUES(?,?,?,?,?,?,?)');
  $q->execute([$u['id'],$adId,$name,$phone,$contactId,$city,$notes]);
  respond(201,['ok'=>true,'id'=>(int)$pdo->lastInsertId()]);
 } catch(Throwable $e) { error_log('Vam Kamali purchase request error: '.$e->getMessage().' | '.$e->getFile().':'.$e->getLine()); respond(503,['ok'=>false,'error'=>'service_unavailable']); }
}
if ($action === 'admin-purchase-requests' && $method === 'GET') {
 try {
  $pdo=database(); requireAdmin($pdo);
  $q=$pdo->query('SELECT r.id,r.user_id,r.ad_id,r.full_name,r.phone,r.contact_id,r.city,r.notes,r.status,r.created_at,m.username,a.title AS ad_title,a.bank FROM purchase_requests r JOIN members m ON m.id=r.user_id JOIN ads a ON a.id=r.ad_id ORDER BY r.id DESC');
  respond(200,['ok'=>true,'requests'=>$q->fetchAll()]);
 } catch(Throwable $e) { error_log('Vam Kamali admin purchase requests error: '.$e->getMessage().' | '.$e->getFile().':'.$e->getLine()); respond(503,['ok'=>false,'error'=>'service_unavailable']); }
}

/* Informational requests and member/support messaging only. */
if ($action === 'my-inquiries' && $method === 'GET') { try {$pdo=database();ensureFeatureTables($pdo);$u=authenticatedUser($pdo);$s=$pdo->prepare("SELECT id,subject,body,status,created_at FROM member_inquiries WHERE user_id=? ORDER BY id DESC");$s->execute([$u['id']]);respond(200,['ok'=>true,'inquiries'=>$s->fetchAll()]);}catch(Throwable $e){respond(503,['ok'=>false,'error'=>'service_unavailable']);} }
if ($action === 'inquiry-create' && $method === 'POST') { try {$pdo=database();ensureFeatureTables($pdo);$u=authenticatedUser($pdo);$d=requestData();$phone=trim((string)($d['phone']??''));$subject=trim((string)($d['subject']??''));$body=trim((string)($d['body']??''));if(!preg_match('/^09[0-9]{9}$/',$phone)||$subject===''||mb_strlen($subject)>180||$body===''||mb_strlen($body)>3000)respond(422,['ok'=>false,'error'=>'invalid_inquiry']);$s=$pdo->prepare("INSERT INTO member_inquiries(user_id,phone,subject,body) VALUES(?,?,?,?)");$s->execute([$u['id'],$phone,$subject,$body]);respond(201,['ok'=>true,'id'=>(int)$pdo->lastInsertId()]);}catch(Throwable $e){respond(503,['ok'=>false,'error'=>'service_unavailable']);} }
if ($action === 'admin-inquiries' && $method === 'GET') { try {$pdo=database();ensureFeatureTables($pdo);requireAdmin($pdo);$s=$pdo->query("SELECT i.id,i.user_id,i.subject,i.body,i.status,i.created_at,m.username FROM member_inquiries i JOIN members m ON m.id=i.user_id ORDER BY i.id DESC");respond(200,['ok'=>true,'inquiries'=>$s->fetchAll()]);}catch(Throwable $e){respond(503,['ok'=>false,'error'=>'service_unavailable']);} }
if ($action === 'admin-inquiry-update' && $method === 'POST') { try {$pdo=database();ensureFeatureTables($pdo);requireAdmin($pdo);$d=requestData();$id=(int)($d['id']??0);$status=(string)($d['status']??'');if($id<1||!in_array($status,['answered','closed'],true))respond(422,['ok'=>false,'error'=>'invalid_inquiry_update']);$s=$pdo->prepare("UPDATE member_inquiries SET status=? WHERE id=?");$s->execute([$status,$id]);respond(200,['ok'=>true]);}catch(Throwable $e){respond(503,['ok'=>false,'error'=>'service_unavailable']);} }
if ($action === 'admin-conversations' && $method === 'GET') { try {$pdo=database();ensureFeatureTables($pdo);requireAdmin($pdo);$s=$pdo->query("SELECT m.id AS member_id,m.username,MAX(x.id) AS last_message_id FROM members m JOIN member_messages x ON x.member_id=m.id GROUP BY m.id,m.username ORDER BY last_message_id DESC");respond(200,['ok'=>true,'conversations'=>$s->fetchAll()]);}catch(Throwable $e){respond(503,['ok'=>false,'error'=>'service_unavailable']);} }
if ($action === 'messages' && $method === 'GET') { try {$pdo=database();ensureFeatureTables($pdo);$u=authenticatedUser($pdo);$mid=$u['role']==='admin'?(int)($_GET['member_id']??0):(int)$u['id'];if($mid<1||($u['role']!=='admin'&&$mid!==(int)$u['id']))respond(403,['ok'=>false,'error'=>'message_access_denied']);$s=$pdo->prepare("SELECT id,member_id,sender_id,body,created_at FROM member_messages WHERE member_id=? ORDER BY id ASC LIMIT 300");$s->execute([$mid]);respond(200,['ok'=>true,'messages'=>$s->fetchAll()]);}catch(Throwable $e){respond(503,['ok'=>false,'error'=>'service_unavailable']);} }
if ($action === 'message-send' && $method === 'POST') { try {$pdo=database();ensureFeatureTables($pdo);$u=authenticatedUser($pdo);$d=requestData();$mid=$u['role']==='admin'?(int)($d['member_id']??0):(int)$u['id'];$body=trim((string)($d['body']??''));if($mid<1||$body===''||mb_strlen($body)>3000)respond(422,['ok'=>false,'error'=>'invalid_message']);$s=$pdo->prepare("SELECT id FROM members WHERE id=?");$s->execute([$mid]);if(!$s->fetch())respond(404,['ok'=>false,'error'=>'member_not_found']);$s=$pdo->prepare("INSERT INTO member_messages(member_id,sender_id,body) VALUES(?,?,?)");$s->execute([$mid,$u['id'],$body]);respond(201,['ok'=>true]);}catch(Throwable $e){respond(503,['ok'=>false,'error'=>'service_unavailable']);} }

respond(405, ['ok'=>false, 'error'=>'not_found_or_method_not_allowed']);
