<?php
include '../config/database.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $group = $_POST['group_code'];

    $stmt = $conn->prepare("UPDATE students SET group_code=? WHERE id=?");
    $stmt->bind_param("si", $group, $id);

    if ($stmt->execute()) {
        // Add notification for group code assignment
        require_once '../assets/includes/notification_functions.php';
        
        // Get student user_id from email
        $studentQuery = $conn->prepare("SELECT u.id as user_id, u.first_name, u.last_name FROM students s INNER JOIN users u ON s.email = u.email WHERE s.id = ?");
        $studentQuery->bind_param("i", $id);
        $studentQuery->execute();
        $studentResult = $studentQuery->get_result();
        
        if ($student = $studentResult->fetch_assoc()) {
            createNotification(
                $conn, 
                $student['user_id'], 
                'Group Code Assigned', 
                'You have been assigned to group ' . htmlspecialchars($group) . ' by your adviser.',
                'info',
                null,
                'group_assignment'
            );
        }
        
        $_SESSION['success_message'] = "Group code updated successfully.";
        header("Location: student_list.php?update=success");
        header("Cache-Control: no-cache, must-revalidate");
        exit();
    } else {
        $_SESSION['error_message'] = "Failed to update group code.";
        header("Location: student_list.php?update=error");
        exit();
    }
}
?>