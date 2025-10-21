<?php
// register.php - Updated to redirect clients to client profile
require_once __DIR__ . '/config.php';

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    $input = $_POST; // fallback for form-encoded submissions
}

$name = trim($input['name'] ?? '');
$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';
$phone = trim($input['phone'] ?? '');
$role = trim($input['role'] ?? $input['account-type'] ?? 'contractor');

$errors = [];

// Basic validation only
if ($name === '') $errors[] = 'Name is required';
if ($email === '') $errors[] = 'Email is required';
if ($password === '') $errors[] = 'Password required';

if (!empty($errors)) {
    json_response(['ok' => false, 'errors' => $errors], 400);
}

try {
    $pdo = getPDO();

    // Check email uniqueness
    $stmt = $pdo->prepare('SELECT user_id FROM `User` WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    if ($stmt->fetch()) {
        json_response(['ok' => false, 'error' => 'Email already registered'], 409);
    }

    // Insert user
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $ins = $pdo->prepare('INSERT INTO `User` (name, email, password, phone, role, created_at) VALUES (:name, :email, :password, :phone, :role, NOW())');
    $ins->execute([
        ':name' => $name,
        ':email' => $email,
        ':password' => $hashed,
        ':phone' => $phone,
        ':role' => $role
    ]);

    $newId = (int)$pdo->lastInsertId();

    // Create profile based on role
// ... existing code ...

    // Create profile based on role
    if ($role === 'contractor') {
        // Create ContractorProfile
        $contractorStmt = $pdo->prepare('INSERT INTO ContractorProfile (contractor_id) VALUES (?)');
        $contractorStmt->execute([$newId]);
        $redirectUrl = (defined('BASE_URL') ? BASE_URL : '') . '/new_backend/contractors_frontend/private_profile.php?user_id=' . $newId;
    } else if ($role === 'client') {
        // Create ClientProfile
        $clientStmt = $pdo->prepare('INSERT INTO ClientProfile (client_id, member_since) VALUES (?, CURDATE())');
        $clientStmt->execute([$newId]);
        $redirectUrl = (defined('BASE_URL') ? BASE_URL : '') . '/new_backend/admin_page/client_profile.php?user_id=' . $newId;
    } else if ($role === 'admin') {
        // For admin - redirect to admin dashboard
        $redirectUrl = (defined('BASE_URL') ? BASE_URL : '') . '/new_backend/admin_page/ad.php?user_id=' . $newId;
    } else {
        // For other roles (fallback)
        $redirectUrl = (defined('BASE_URL') ? BASE_URL : '') . '/cei.html';
    }

    json_response([ 
        'ok' => true, 
        'message' => 'Registered successfully', 
        'user_id' => $newId,
        'user_role' => $role,
        'redirect' => $redirectUrl 
    ]);

} catch (Exception $ex) {
    error_log('register_error: ' . $ex->getMessage());
    json_response(['ok' => false, 'error' => 'Server error: ' . $ex->getMessage()], 500);
}