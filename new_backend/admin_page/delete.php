<?php
// delete.php - Handle user deletion
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

    // Prevent admin from deleting themselves
    if ($admin_user_id == $target_user_id) {
        die(json_encode(['success' => false, 'message' => 'Cannot delete your own account']));
    }

    // Get user data for confirmation
    $stmt = $pdo->prepare("SELECT * FROM `User` WHERE user_id = ?");
    $stmt->execute([$target_user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        die(json_encode(['success' => false, 'message' => 'User not found']));
    }

    // Handle deletion
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Start transaction for safe deletion
        $pdo->beginTransaction();
        
        try {
            // Delete user and related data (cascade delete will handle related tables)
            $stmt = $pdo->prepare("DELETE FROM `User` WHERE user_id = ?");
            $stmt->execute([$target_user_id]);
            
            $pdo->commit();
            
            echo json_encode([
                'success' => true, 
                'message' => 'User deleted successfully',
                'deleted_user_id' => $target_user_id
            ]);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        exit;
    }

} catch (Exception $e) {
    die(json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]));
}
?>