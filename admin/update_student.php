<?php
include '../config/database.php';
require_once '../assets/includes/year_section_functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $first = $_POST['first_name'];
    $middle = $_POST['middle_name'];
    $last = $_POST['last_name'];
    $gender = $_POST['gender'];
    $year_section = $_POST['year_section'];
    $group = $_POST['group_code'];

    // Validate year_section exists in database
    if (!yearSectionExists($conn, $year_section)) {
        $_SESSION['error_message'] = "Invalid year section. Please select a valid year section.";
        header("Location: student_list.php?update=error");
        exit();
    }

    $stmt = $conn->prepare("UPDATE students SET first_name=?, middle_name=?, last_name=?, gender=?, year_section=?, group_code=? WHERE id=?");
    $stmt->bind_param("ssssssi", $first, $middle, $last, $gender, $year_section, $group, $id);

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
                'You have been assigned to group ' . htmlspecialchars($group) . ' by the administrator.',
                'info',
                null,
                'group_assignment'
            );
        }
        
        $_SESSION['success_message'] = "Student updated successfully.";
        header("Location: student_list.php?update=success");
        header("Cache-Control: no-cache, must-revalidate");
        exit();
    } else {
        $_SESSION['error_message'] = "Failed to update student.";
        header("Location: student_list.php?update=error");
        exit();
    }
}
?>
