<?php
/**
 * auth.php — Authentication & staff account endpoints for Prakut CRM/CMS.
 * Modeled after Zoloverse architecture.
 */

declare(strict_types=1);
require_once __DIR__ . '/includes/init.php';

$pdo = db();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($action)) {
    $action = 'verify';
}

switch ($action) {
    case 'login':
        handleLogin($pdo);
        break;

    case 'verify':
    case 'profile':
        handleProfile();
        break;

    case 'logout':
        handleLogout();
        break;

    case 'change_password':
        handleChangePassword($pdo);
        break;

    default:
        json_error('Invalid auth action specified.', 400);
}

function handleLogin(PDO $pdo): void
{
    $data = read_json_body();
    $email = strtolower(trim((string)($data->email ?? '')));
    $password = (string)($data->password ?? '');

    if (empty($email) || empty($password)) {
        json_error('Email address and password are required.');
    }

    $stmt = $pdo->prepare('SELECT id, name, email, password, role, phone FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        json_error('Invalid email or password.', 401);
    }

    $tokenPayload = [
        'id'    => (int)$user['id'],
        'email' => $user['email'],
        'name'  => $user['name'],
        'role'  => $user['role'],
        'exp'   => time() + SESSION_TTL,
    ];

    $token = generateSessionToken($tokenPayload);

    // Set cookie as well for browser persistence
    setcookie('prakut_token', $token, [
        'expires'  => time() + SESSION_TTL,
        'path'     => '/',
        'httponly' => false,
        'samesite' => 'Lax'
    ]);

    logAudit('LOGIN', "User {$user['email']} signed in successfully.", (int)$user['id']);

    json_success('Login successful.', [
        'token' => $token,
        'user'  => [
            'id'    => (int)$user['id'],
            'name'  => $user['name'],
            'email' => $user['email'],
            'role'  => $user['role'],
            'phone' => $user['phone'],
        ]
    ]);
}

function handleProfile(): void
{
    $user = requireAuth(false);
    json_success('Session valid.', ['user' => $user]);
}

function handleLogout(): void
{
    setcookie('prakut_token', '', time() - 3600, '/');
    json_success('Logged out successfully.');
}

function handleChangePassword(PDO $pdo): void
{
    $currentUser = requireAuth(false);
    $data = read_json_body();

    $currentPass = (string)($data->current_password ?? '');
    $newPass     = (string)($data->new_password ?? '');

    if (empty($currentPass) || empty($newPass)) {
        json_error('Both current password and new password are required.');
    }

    if (!isValidPassword($newPass)) {
        json_error('New password must be between 6 and 72 characters.');
    }

    $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
    $stmt->execute([$currentUser->id]);
    $hash = $stmt->fetchColumn();

    if (!$hash || !password_verify($currentPass, $hash)) {
        json_error('Current password does not match.', 400);
    }

    $newHash = password_hash($newPass, PASSWORD_BCRYPT);
    $updateStmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
    $updateStmt->execute([$newHash, $currentUser->id]);

    logAudit('PASSWORD_CHANGE', 'Password changed successfully.', (int)$currentUser->id);
    json_success('Password updated successfully.');
}
