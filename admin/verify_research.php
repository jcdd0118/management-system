<?php
// Start the session
session_start();

// Check if the user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../users/login.php");
    exit();
}

// Database connection
include '../config/database.php';
include '../assets/includes/email_functions.php';

// Get the research ID from the URL
$research_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Verify research and update status in both tables
if ($research_id) {
    // First, get research and student information before updating
    $get_research_query = "SELECT c.title, c.user_id, s.first_name, s.middle_name, s.last_name, s.email 
                           FROM capstone c 
                           LEFT JOIN students s ON c.user_id = s.user_id 
                           WHERE c.id = ?";
    $stmt_research_info = $conn->prepare($get_research_query);
    $stmt_research_info->bind_param("i", $research_id);
    $stmt_research_info->execute();
    $research_result = $stmt_research_info->get_result();
    $research_info = $research_result->fetch_assoc();
    
    if (!$research_info) {
        $_SESSION['error_message'] = "Research not found.";
        header("Location: research_list.php");
        exit();
    }
    
    // Update status in the capstone table
    $update_research_query = "UPDATE capstone SET status = 'verified' WHERE id = ?";
    $stmt = $conn->prepare($update_research_query);
    $stmt->bind_param("i", $research_id);
    $stmt->execute();

    // Get user_id from capstone table to update users table
    $get_user_query = "SELECT user_id FROM capstone WHERE id = ?";
    $stmt_user = $conn->prepare($get_user_query);
    $stmt_user->bind_param("i", $research_id);
    $stmt_user->execute();
    $result = $stmt_user->get_result();
    $research = $result->fetch_assoc();
    $user_id = $research['user_id'];

    // Update status in the users table if user_id exists
    if ($user_id) {
        $update_user_query = "UPDATE users SET status = 'verified' WHERE id = ?";
        $stmt_user_update = $conn->prepare($update_user_query);
        $stmt_user_update->bind_param("i", $user_id);
        $stmt_user_update->execute();
        $stmt_user_update->close();
        
        // Add notification for research verification
        require_once '../assets/includes/notification_functions.php';
        
        $researchTitle = $research_info['title'];
        
        createNotification(
            $conn, 
            $user_id, 
            'Research Verified', 
            'Your research "' . htmlspecialchars($researchTitle) . '" has been verified and is now available in the repository.',
            'success',
            $research_id,
            'capstone'
        );
        
        // Send email notification to the student
        $student_name = trim($research_info['first_name'] . ' ' . $research_info['middle_name'] . ' ' . $research_info['last_name']);
        $student_email = $research_info['email'];
        
        $email_sent = false;
        if (!empty($student_email) && filter_var($student_email, FILTER_VALIDATE_EMAIL)) {
            $email_subject = 'Research Verification Complete - CapTrack Vault SRC';
            $email_message = "
            <p>Great news! Your research project has been successfully verified by the administrator.</p>
            
            <div style='background-color: #e8f5e8; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                <p style='margin: 0;'><strong>Research Details:</strong></p>
                <p style='margin: 10px 0;'><strong>Title:</strong> " . htmlspecialchars($researchTitle) . "</p>
            </div>
            
            <p><strong>What this means:</strong></p>
            <ul>
                <li>Your research is now available in the repository</li>
                <li>Other students and faculty can access your work</li>
                <li>Your research contributes to the academic knowledge base</li>
            </ul>
            
            <p>You can view your verified research in the system:</p>
            
            <div style='text-align: center; margin: 30px 0;'>
                <a href='" . BASE_URL . "/student/my_projects.php' style='background-color: #007bff; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>View My Projects</a>
            </div>";
            
            $email_sent = sendNotificationEmail($student_email, $student_name, $email_subject, $email_message);
        }
    }

    // Close the database connections
    $stmt->close();
    $stmt_user->close();
    $stmt_research_info->close();
    mysqli_close($conn);

    // Redirect back to the research list page with a success message
    if (isset($email_sent) && $email_sent) {
        $_SESSION['success_message'] = "Research verified successfully and notification email sent!";
    } else {
        $_SESSION['success_message'] = "Research verified successfully, but email notification could not be sent.";
    }
    header("Location: research_list.php");
    exit();
} else {
    // Set an error message if no valid research ID is provided
    $_SESSION['error_message'] = "Invalid research ID.";
    header("Location: research_list.php");
    exit();
}
?>