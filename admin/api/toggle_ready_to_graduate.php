<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Database connection
require_once '../../config/database.php';

// Accept both JSON body and form-encoded POST (frontend sends x-www-form-urlencoded: id, ready)
$raw = file_get_contents('php://input');
$input = json_decode($raw, true);

// Default values
$student_id = null;
$ready_to_graduate = null;

if (is_array($input)) {
    if (isset($input['student_id'])) { $student_id = (int)$input['student_id']; }
    if (isset($input['ready_to_graduate'])) { $ready_to_graduate = (int)!!$input['ready_to_graduate']; }
}

// Fallback to form data keys used by UI
if ($student_id === null && isset($_POST['id'])) {
    $student_id = (int)$_POST['id'];
}
if ($ready_to_graduate === null && isset($_POST['ready'])) {
    $ready_to_graduate = (int)($_POST['ready'] ? 1 : 0);
}

if ($student_id === null || $ready_to_graduate === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit();
}

try {
    // Update the student's ready_to_graduate status
    $stmt = $conn->prepare("UPDATE students SET ready_to_graduate = ? WHERE id = ?");
    $stmt->bind_param("ii", $ready_to_graduate, $student_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Student ready status updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update student ready status']);
    }
    
    $stmt->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} finally {
    $conn->close();
}
?>