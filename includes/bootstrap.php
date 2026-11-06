<?php
declare(strict_types=1);

$isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_name('ivenews_session');
session_set_cookie_params([
    'httponly' => true,
    'secure' => $isHttps,
    'samesite' => 'Lax',
    'path' => '/',
]);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function app_config(): array
{
    return [
        'dsn' => getenv('IVENews_DSN') ?: 'mysql:host=127.0.0.1;dbname=ivenews;charset=utf8mb4',
        'db_user' => getenv('IVENews_DB_USER') ?: 'root',
        'db_password' => getenv('IVENews_DB_PASSWORD') ?: '',
        'site_name' => "I've News",
    ];
}

function db(): PDO
{
    static $connection;
    if ($connection instanceof PDO) {
        return $connection;
    }

    $config = app_config();
    try {
        $connection = new PDO($config['dsn'], $config['db_user'], $config['db_password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $exception) {
        error_log('Ive News database connection failed: ' . $exception->getMessage());
        http_response_code(503);
        exit('The publication database is not available. Check the database settings and try again.');
    }

    return $connection;
}

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path, array $query = []): string
{
    return $path . ($query === [] ? '' : '?' . http_build_query($query));
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    if (!is_string($submitted) || !hash_equals(csrf_token(), $submitted)) {
        http_response_code(419);
        exit('Your session expired. Go back, refresh the page, and try again.');
    }
}

function current_user(): ?array
{
    static $loaded = false;
    static $user = null;
    if ($loaded) {
        return $user;
    }
    $loaded = true;
    $userId = $_SESSION['user_id'] ?? null;
    if (!is_int($userId)) {
        return null;
    }

    $statement = db()->prepare(
        'SELECT u.id, u.name, u.email, r.name AS role_name, r.label AS role_label
         FROM users u JOIN roles r ON r.id = u.role_id
         WHERE u.id = :id AND u.is_active = 1'
    );
    $statement->execute(['id' => $userId]);
    $user = $statement->fetch() ?: null;
    if ($user === null) {
        unset($_SESSION['user_id']);
    }
    return $user;
}

function has_permission(string $permission): bool
{
    $user = current_user();
    if ($user === null) {
        return false;
    }
    static $permissions = [];
    if (!isset($permissions[$user['id']])) {
        $statement = db()->prepare(
            'SELECT p.name FROM permissions p
             JOIN role_permissions rp ON rp.permission_id = p.id
             JOIN users u ON u.role_id = rp.role_id
             WHERE u.id = :id'
        );
        $statement->execute(['id' => $user['id']]);
        $permissions[$user['id']] = $statement->fetchAll(PDO::FETCH_COLUMN);
    }
    return in_array($permission, $permissions[$user['id']], true);
}

function require_permission(string $permission): void
{
    if (current_user() === null) {
        header('Location: login.php');
        exit;
    }
    if (!has_permission($permission)) {
        http_response_code(403);
        exit('You do not have permission to access this page.');
    }
}

function record_audit(int $userId, string $action, string $entityType, ?int $entityId, array $details = []): void
{
    $statement = db()->prepare(
        'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address)
         VALUES (:user_id, :action, :entity_type, :entity_id, :details, :ip_address)'
    );
    $statement->execute([
        'user_id' => $userId,
        'action' => $action,
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'details' => json_encode($details, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}

function redirect(string $location): never
{
    header('Location: ' . $location);
    exit;
}

function reading_minutes(string $body): int
{
    return max(1, (int) ceil(str_word_count(strip_tags($body)) / 220));
}
