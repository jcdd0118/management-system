<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../users/login.php");
    exit();
}

// Check for a valid research ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = 'Invalid research ID.';
    header("Location: research_list.php");
    exit();
}

$research_id = intval($_GET['id']);

include '../config/database.php';

// First, get the research's title, user_id, and document_path
$select_query = "SELECT title, user_id, document_path FROM capstone WHERE id = ?";
$stmt = mysqli_prepare($conn, $select_query);
mysqli_stmt_bind_param($stmt, "i", $research_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($row = mysqli_fetch_assoc($result)) {
    $research_title = $row['title'];
    $user_id = $row['user_id'];
    $document_path = trim((string)$row['document_path']);

    // Delete from capstone table
    $delete_research_query = "DELETE FROM capstone WHERE id = ?";
    $delete_research_stmt = mysqli_prepare($conn, $delete_research_query);
    mysqli_stmt_bind_param($delete_research_stmt, "i", $research_id);
    mysqli_stmt_execute($delete_research_stmt);

    // NOTE: Do NOT delete the user when deleting a research entry.
    // The student's account must remain intact even if their research is removed.

    // Best-effort: delete attached PDF from disk if present and path is within assets/uploads/capstone
    if (!empty($document_path)) {
        $relative = str_replace(['\\', '//'], '/', $document_path);
        // Resolve to filesystem path from admin/ directory
        $filePath = realpath(__DIR__ . '/../' . $relative);
        $uploadsBase = realpath(__DIR__ . '/../assets/uploads/capstone');
        if ($filePath && $uploadsBase && strpos($filePath, $uploadsBase) === 0 && is_file($filePath)) {
            @unlink($filePath);
        }
    }

    // Set success session variables for modal
    $_SESSION['delete_success'] = true;
    $_SESSION['deleted_research_title'] = htmlspecialchars($research_title);
} else {
    $_SESSION['error_message'] = 'Research not found.';
}

// Close statements and connection
mysqli_stmt_close($stmt);
mysqli_stmt_close($delete_research_stmt);
mysqli_close($conn);

header("Location: research_list.php");
exit();
?>