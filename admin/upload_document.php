<?php
// upload_document.php
session_start();
require_once 'db_connection.php';   // your database connection

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['document_id'])) {
    $doc_id = intval($_POST['document_id']);
    $uploadDir = 'uploads/documents/';   // Create this folder and set 777 permission (or proper security)

    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $file = $_FILES['document_file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['pdf','jpg','jpeg','png','doc','docx'];

    if (!in_array($ext, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type']);
        exit;
    }

    if ($file['size'] > 10 * 1024 * 1024) { // 10MB limit
        echo json_encode(['success' => false, 'message' => 'File too large']);
        exit;
    }

    $newFileName = 'doc_' . $doc_id . '_' . time() . '.' . $ext;
    $targetPath = $uploadDir . $newFileName;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        // Update database
        $stmt = $pdo->prepare("UPDATE student_documents SET 
            submitted = 1, 
            file_path = ?, 
            uploaded_by = ?, 
            uploaded_at = NOW() 
            WHERE id = ?");
        
        $uploaded_by = $_SESSION['user_id'] ?? 'registrar';   // change according to your session

        $stmt->execute([$targetPath, $uploaded_by, $doc_id]);

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save file']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);