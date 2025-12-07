<?php
// Very small session-based auth helper for the helpdesk pages.
// NOT PRODUCTION READY — replace with proper auth for real deployments.

session_start();

// Simple credentials check - in production use hashed passwords stored securely.
// For demo: check against users.json file.
function check_login($username, $password) {
    $users = json_decode(file_get_contents(__DIR__ . '/../data/users.json'), true);
    if(empty($users[$username])) return false;
    $stored = $users[$username];
    // For demo: allow password "password" if no real hash present.
    if(!empty($stored['password']) && strpos($stored['password'],'$2y$')===0) {
        // assume bcrypt - verify
        return password_verify($password, $stored['password']);
    } else {
        return $password === 'password';
    }
}

// Login action
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $u = isset($_POST['username']) ? trim($_POST['username']) : '';
    $p = isset($_POST['password']) ? $_POST['password'] : '';
    if(check_login($u,$p)) {
        $_SESSION['helpdesk_user'] = $u;
        // Simple CSRF token
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
        header('Location: /helpdesk/dashboard.php');
        exit;
    } else {
        $login_error = 'Invalid credentials';
    }
}

// Require login for pages that include this file by calling require_once and then check:
// if (!isset($_SESSION['helpdesk_user'])) { show login form and exit; }