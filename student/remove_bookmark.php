<?php
// remove_bookmark.php
session_start();

// Check if the user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../users/login.php");
    exit();
}

// Database connection
require_once '../config/database.php';

$bookmark_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = $_SESSION['user_id'];

if ($bookmark_id <= 0) {
    header("Location: bookmark.php?error=Invalid bookmark ID");
    exit();
}

// Verify the bookmark belongs to the user
$sql = "SELECT id FROM bookmarks WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $bookmark_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    header("Location: bookmark.php?error=Bookmark not found or unauthorized");
    $stmt->close();
    $conn->close();
    exit();
}
$stmt->close();

// Delete the bookmark
$sql = "DELETE FROM bookmarks WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $bookmark_id, $user_id);
if ($stmt->execute()) {
    header("Location: bookmark.php?success=Bookmark removed successfully");
} else {
    header("Location: bookmark.php?error=Failed to remove bookmark");
}
$stmt->close();
$conn->close();
?>