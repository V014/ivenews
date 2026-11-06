<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Use the sign-out form to end your session.');
}
verify_csrf();
if ($user = current_user()) {
    record_audit((int) $user['id'], 'auth.logout', 'user', (int) $user['id']);
}
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $cookie = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $cookie['path'], '', $cookie['secure'], $cookie['httponly']);
}
session_destroy();
redirect('login.php');
