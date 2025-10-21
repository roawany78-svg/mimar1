<?php
// add_order_handler.php
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_order'])) {
    $user_id = $_POST['user_id'] ?? 1;
    
    try {
        $pdo = getPDO();
        
        // Handle project contract file upload
        $project_contract_filename = null;
        if (!empty($_FILES['project_contract']['name']) && $_FILES['project_contract']['error'] === UPLOAD_ERR_OK) {
            $contract_file = $_FILES['project_contract'];
            
            // Validate file type
            $allowed_types = ['application/pdf'];
            $file_type = mime_content_type($contract_file['tmp_name']);
            
            if (!in_array($file_type, $allowed_types)) {
                throw new Exception("Only PDF files are allowed for project contract");
            }
            
            // Validate file size (10MB max)
            if ($contract_file['size'] > 10 * 1024 * 1024) {
                throw new Exception("Project contract file must be less than 10MB");
            }
            
            // Generate unique filename
            $file_extension = pathinfo($contract_file['name'], PATHINFO_EXTENSION);
            $project_contract_filename = 'contract_' . uniqid() . '.' . $file_extension;
            
            // Upload directory for contracts
            $contract_upload_dir = __DIR__ . '/../uploads/contracts/';
            if (!is_dir($contract_upload_dir)) {
                mkdir($contract_upload_dir, 0777, true);
            }
            
            $target_contract_path = $contract_upload_dir . $project_contract_filename;
            
            if (!move_uploaded_file($contract_file['tmp_name'], $target_contract_path)) {
                throw new Exception("Failed to upload project contract");
            }
        } else {
            throw new Exception("Project contract file is required");
        }
        
        // Insert into Project table
        $project_stmt = $pdo->prepare("
            INSERT INTO Project (
                order_id, title, description, location, client_id, 
                accepted_contractor_id, estimated_cost, status, 
                start_date, end_date, project_contract
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $project_stmt->execute([
            $_POST['order_id'],
            $_POST['title'],
            $_POST['description'],
            $_POST['location'],
            $user_id,
            $_POST['accepted_contractor_id'],
            $_POST['estimated_cost'],
            $_POST['status'],
            $_POST['start_date'],
            $_POST['end_date'],
            $project_contract_filename
        ]);
        
        $project_id = $pdo->lastInsertId();
        
        // Store contract file info in Attachment table
        $contract_attachment_stmt = $pdo->prepare("
            INSERT INTO Attachment (file_name, file_type, file_path, project_id, file_category) 
            VALUES (?, ?, ?, ?, 'contract')
        ");
        $contract_original_name = $_FILES['project_contract']['name'];
        $contract_attachment_stmt->execute([
            $contract_original_name,
            $file_type,
            $project_contract_filename,
            $project_id
        ]);
        
        // Insert project specifications if provided
        if (!empty($_POST['specifications'])) {
            $spec_lines = explode("\n", $_POST['specifications']);
            $spec_stmt = $pdo->prepare("
                INSERT INTO ProjectSpecification (project_id, specification) 
                VALUES (?, ?)
            ");
            
            foreach ($spec_lines as $spec_line) {
                $spec_line = trim($spec_line);
                // Remove bullet points if present
                $spec_line = preg_replace('/^[•\-\*]\s*/', '', $spec_line);
                
                if (!empty($spec_line)) {
                    $spec_stmt->execute([$project_id, $spec_line]);
                }
            }
        }
        
        // Handle additional file uploads for attachments
        if (!empty($_FILES['attachments']['name'][0])) {
            $attachment_stmt = $pdo->prepare("
                INSERT INTO Attachment (file_name, file_type, file_path, project_id, file_category) 
                VALUES (?, ?, ?, ?, 'attachment')
            ");
            
            foreach ($_FILES['attachments']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
                    $original_name = $_FILES['attachments']['name'][$key];
                    $file_type = $_FILES['attachments']['type'][$key];
                    
                    // Generate unique filename
                    $file_extension = pathinfo($original_name, PATHINFO_EXTENSION);
                    $unique_filename = 'attachment_' . uniqid() . '_' . $key . '.' . $file_extension;
                    
                    // Move uploaded file to storage directory
                    $upload_dir = __DIR__ . '/../uploads/projects/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    $target_path = $upload_dir . $unique_filename;
                    
                    if (move_uploaded_file($tmp_name, $target_path)) {
                        $attachment_stmt->execute([$original_name, $file_type, $unique_filename, $project_id]);
                    }
                }
            }
        }
        
        // Redirect back to client profile with success message
        header("Location: client_profile.php?user_id=$user_id&order_added=1");
        exit;
        
    } catch (Exception $e) {
        // Handle error
        error_log("Order creation error: " . $e->getMessage());
        header("Location: client_profile.php?user_id=$user_id&order_error=1&message=" . urlencode($e->getMessage()));
        exit;
    }
} else {
    header("Location: client_profile.php");
    exit;
}
?>