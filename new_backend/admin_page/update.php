<?php
// update.php - Handle user updates
require_once __DIR__ . '/../config.php';

// Simple authentication
$admin_user_id = $_POST['admin_user_id'] ?? 0;
$target_user_id = $_POST['target_user_id'] ?? 0;

if ($admin_user_id <= 0 || $target_user_id <= 0) {
    die(json_encode(['success' => false, 'message' => 'Missing user IDs']));
}

try {
    $pdo = getPDO();
    
    // Verify admin role
    $stmt = $pdo->prepare("SELECT * FROM `User` WHERE user_id = ? AND role = 'admin'");
    $stmt->execute([$admin_user_id]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$admin) {
        die(json_encode(['success' => false, 'message' => 'Admin not found or insufficient permissions']));
    }

    // Get current user data
    $stmt = $pdo->prepare("SELECT * FROM `User` WHERE user_id = ?");
    $stmt->execute([$target_user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        die(json_encode(['success' => false, 'message' => 'User not found']));
    }

    // Handle update
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? '';
        $status = $_POST['status'] ?? '';
        
        // Validate inputs
        if (empty($name) || empty($email)) {
            die(json_encode(['success' => false, 'message' => 'Name and email are required']));
        }
        
        if (!in_array($role, ['client', 'contractor', 'admin'])) {
            die(json_encode(['success' => false, 'message' => 'Invalid role']));
        }
        
        if (!in_array($status, ['active', 'inactive', 'suspended', 'pending'])) {
            die(json_encode(['success' => false, 'message' => 'Invalid status']));
        }
        
        // Check if email already exists for other users
        $stmt = $pdo->prepare("SELECT user_id FROM `User` WHERE email = ? AND user_id != ?");
        $stmt->execute([$email, $target_user_id]);
        if ($stmt->fetch()) {
            die(json_encode(['success' => false, 'message' => 'Email already exists']));
        }
        
        // Update user
        $stmt = $pdo->prepare("UPDATE `User` SET name = ?, email = ?, role = ?, status = ? WHERE user_id = ?");
        $stmt->execute([$name, $email, $role, $status, $target_user_id]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'User updated successfully',
            'user' => [
                'user_id' => $target_user_id,
                'name' => $name,
                'email' => $email,
                'role' => $role,
                'status' => $status
            ]
        ]);
        exit;
    }

} catch (Exception $e) {
    die(json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]));
}
?>