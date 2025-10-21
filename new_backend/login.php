<?php
// login.php - SIMPLE TEST VERSION
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method Not Allowed'], 405);
}

// Get data from either JSON or form data
$contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
if (strpos($contentType, 'application/json') !== false) {
    $data = json_decode(file_get_contents('php://input'), true) ?? [];
} else {
    $data = $_POST;
}

$email = isset($data['email']) ? trim((string)$data['email']) : '';
$password = $data['password'] ?? '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
    json_response(['error' => 'Missing or invalid credentials'], 400);
}

try {
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT user_id, name, password, role FROM `User` WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        json_response(['error' => 'Invalid email or password'], 401);
    }

    // Login successful - redirect based on role
    $redirectUrl = '';
    if ($user['role'] === 'contractor') {
        $redirectUrl = '/new_backend/contractors_frontend/private_profile.php?user_id=' . $user['user_id'];
    } else if ($user['role'] === 'client') {
        $redirectUrl = '/new_backend/admin_page/client_profile.php?user_id=' . $user['user_id'];
    } else if ($user['role'] === 'admin') {
        $redirectUrl = '/new_backend/admin_page/ad.php?user_id=' . $user['user_id'];
    } else {
        $redirectUrl = 'cei.html?user_id=' . $user['user_id'];
    }

    json_response([
        'ok' => true, 
        'message' => 'Login successful',
        'user' => [
            'id' => $user['user_id'], 
            'name' => $user['name'], 
            'role' => $user['role']
        ],
        'redirect' => $redirectUrl
    ]);

} catch (Exception $ex) {
    error_log('Login error: ' . $ex->getMessage());
    json_response(['error' => 'Server error'], 500);
}