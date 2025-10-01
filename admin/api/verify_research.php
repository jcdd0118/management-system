<?php
session_start();

// Check if the user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Database connection
include '../../config/database.php';

// Get the research ID from POST data
$research_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if (!$research_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid research ID']);
    exit();
}

try {
    // Start transaction for data consistency
    $conn->begin_transaction();
    
    // Update status in the capstone table
    $update_research_query = "UPDATE capstone SET status = 'verified' WHERE id = ?";
    $stmt = $conn->prepare($update_research_query);
    $stmt->bind_param("i", $research_id);
    $stmt->execute();
    
    if ($stmt->affected_rows === 0) {
        throw new Exception("Research not found or already verified");
    }
    
    // Get user_id from capstone table to update users table
    $get_user_query = "SELECT user_id, title FROM capstone WHERE id = ?";
    $stmt_user = $conn->prepare($get_user_query);
    $stmt_user->bind_param("i", $research_id);
    $stmt_user->execute();
    $result = $stmt_user->get_result();
    $research = $result->fetch_assoc();
    
    if (!$research) {
        throw new Exception("Research data not found");
    }
    
    $user_id = $research['user_id'];
    $research_title = $research['title'];
    
    // Update status in the users table if user_id exists
    if ($user_id) {
        $update_user_query = "UPDATE users SET status = 'verified' WHERE id = ?";
        $stmt_user_update = $conn->prepare($update_user_query);
        $stmt_user_update->bind_param("i", $user_id);
        $stmt_user_update->execute();
        
        // Add notification for research verification
        require_once '../../assets/includes/notification_functions.php';
        
        createNotification(
            $conn, 
            $user_id, 
            'Research Verified', 
            'Your research "' . htmlspecialchars($research_title) . '" has been verified and is now available in the repository.',
            'success',
            $research_id,
            'capstone'
        );
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Research verified successfully!',
        'research_title' => $research_title
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    // Close statements
    if (isset($stmt)) $stmt->close();
    if (isset($stmt_user)) $stmt_user->close();
    if (isset($stmt_user_update)) $stmt_user_update->close();
    mysqli_close($conn);
}
?>
