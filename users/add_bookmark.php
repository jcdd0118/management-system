<?php
session_start();

// Check if the user is logged in and has appropriate role
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if user has one of the allowed roles (not admin or student)
$allowed_roles = ['adviser', 'dean', 'faculty', 'grammarian', 'panelist'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    header("Location: login.php?error=unauthorized_access");
    exit();
}

// Database connection
require_once '../config/database.php';

$research_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = $_SESSION['user_id'];

if ($research_id <= 0) {
    header("Location: research_repository.php?error=Invalid research ID");
    exit();
}

// Check if research exists and is verified
$checkQuery = "SELECT id FROM capstone WHERE id = ? AND status = 'verified'";
$checkStmt = $conn->prepare($checkQuery);
$checkStmt->bind_param('i', $research_id);
$checkStmt->execute();
$result = $checkStmt->get_result();

if ($result->num_rows === 0) {
    header("Location: research_repository.php?error=Research not found or not verified");
    $checkStmt->close();
    $conn->close();
    exit();
}
$checkStmt->close();

// Check if already bookmarked
$existingQuery = "SELECT id FROM bookmarks WHERE user_id = ? AND research_id = ?";
$existingStmt = $conn->prepare($existingQuery);
$existingStmt->bind_param('ii', $user_id, $research_id);
$existingStmt->execute();
$existingResult = $existingStmt->get_result();

if ($existingResult->num_rows > 0) {
    header("Location: view_research.php?id=" . $research_id . "&error=Already bookmarked");
    $existingStmt->close();
    $conn->close();
    exit();
}
$existingStmt->close();

// Add bookmark
$insertQuery = "INSERT INTO bookmarks (user_id, research_id, created_at) VALUES (?, ?, NOW())";
$insertStmt = $conn->prepare($insertQuery);
$insertStmt->bind_param('ii', $user_id, $research_id);

if ($insertStmt->execute()) {
    header("Location: view_research.php?id=" . $research_id . "&success=Bookmark added successfully");
} else {
    header("Location: view_research.php?id=" . $research_id . "&error=Failed to add bookmark");
}

$insertStmt->close();
$conn->close();
?>
