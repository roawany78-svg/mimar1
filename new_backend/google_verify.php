<?php
// google_verify.php
// Accepts POST with { "id_token": "..." } (JSON or form)
// Verifies token using Google's tokeninfo endpoint, then creates/logs-in user.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    if (is_ajax()) json_response(['error' => 'Method Not Allowed'], 405);
    http_response_code(405);
    exit('405 Method Not Allowed');
}

$data = get_input_data();
$id_token = $data['id_token'] ?? '';

if (!is_string($id_token) || $id_token === '') {
    if (is_ajax()) json_response(['error' => 'Missing id_token'], 400);
    http_response_code(400);
    exit('Missing id_token');
}

/**
 * Verify ID token with Google's tokeninfo endpoint.
 * Docs: https://developers.google.com/identity/sign-in/web/backend-auth
 */
$verify_url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($id_token);

// use curl to GET
$ch = curl_init($verify_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_err = curl_error($ch);
curl_close($ch);

if ($response === false) {
    error_log('Google token verification curl error: ' . $curl_err);
    if (is_ajax()) json_response(['error' => 'Failed to verify token'], 502);
    http_response_code(502);
    exit('Failed to verify token');
}

$payload = json_decode($response, true);
if (!is_array($payload) || empty($payload['email'])) {
    if (is_ajax()) json_response(['error' => 'Invalid token'], 401);
    http_response_code(401);
    exit('Invalid token');
}

// Optional: check audience matches your client id
if (defined('GOOGLE_CLIENT_ID') && GOOGLE_CLIENT_ID !== '') {
    if (!isset($payload['aud']) || $payload['aud'] !== GOOGLE_CLIENT_ID) {
        error_log('Google token aud mismatch. expected=' . GOOGLE_CLIENT_ID . ' got=' . ($payload['aud'] ?? 'null'));
        if (is_ajax()) json_response(['error' => 'Token audience mismatch'], 401);
        http_response_code(401);
        exit('Token audience mismatch');
    }
}

// Token appears OK. Now upsert user in DB (create if not exists), start session.
$email = (string)$payload['email'];
$name = isset($payload['name']) ? (string)$payload['name'] : explode('@', $email)[0];
$picture = $payload['picture'] ?? null; // optional

try {
    $pdo = getPDO();

    // Check if user exists
    $stmt = $pdo->prepare('SELECT user_id, name, role FROM `User` WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if ($user) {
        // Existing user: login
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['user_id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $email;
        $_SESSION['user_role'] = $user['role'];

        $out = ['ok' => true, 'existing' => true, 'user' => ['id' => $user['user_id'], 'name' => $user['name'], 'role' => $user['role']]];
        if (is_ajax()) json_response($out);
        role_redirect((string)$user['role']);
    } else {
        // Create a new user with default role 'client'
        $defaultRole = 'client';
        $randomPass = bin2hex(random_bytes(12)); // unused but stored hashed
        $hash = password_hash($randomPass, PASSWORD_DEFAULT);

        $insert = $pdo->prepare('INSERT INTO `User` (`name`, `email`, `password`, `role`, `created_at`) VALUES (:name, :email, :password, :role, NOW())');
        $insert->execute([
            ':name' => $name,
            ':email' => $email,
            ':password' => $hash,
            ':role' => $defaultRole
        ]);
        $userId = (int)$pdo->lastInsertId();

        // Optional: store profile picture link into another table or add column if desired.

        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_role'] = $defaultRole;

        $out = ['ok' => true, 'existing' => false, 'user' => ['id' => $userId, 'name' => $name, 'role' => $defaultRole]];
        if (is_ajax()) json_response($out);
        role_redirect($defaultRole);
    }

} catch (Exception $ex) {
    error_log('google_verify error: ' . $ex->getMessage());
    if (is_ajax()) json_response(['error' => 'Server error'], 500);
    http_response_code(500);
    exit('Server error');
}
