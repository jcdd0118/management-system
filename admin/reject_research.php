<?php
session_start();

// Ensure admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
	header("Location: ../users/login.php");
	exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	$_SESSION['error_message'] = 'Invalid request method.';
	header('Location: research_list.php');
	exit();
}

if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
	$_SESSION['error_message'] = 'Invalid research ID.';
	header('Location: research_list.php');
	exit();
}

$research_id = (int) $_POST['id'];
$remarks = isset($_POST['remarks']) ? trim($_POST['remarks']) : '';

if ($remarks === '') {
	$_SESSION['error_message'] = 'Remarks are required to reject a research.';
	header('Location: research_list.php');
	exit();
}

include '../config/database.php';

// Get user_id and title for notification
$get_query = $conn->prepare("SELECT user_id, title FROM capstone WHERE id = ?");
$get_query->bind_param('i', $research_id);
$get_query->execute();
$result = $get_query->get_result();
if (!$result || !$result->num_rows) {
	$_SESSION['error_message'] = 'Research not found.';
	$get_query->close();
	mysqli_close($conn);
	header('Location: research_list.php');
	exit();
}
$row = $result->fetch_assoc();
$user_id = (int) $row['user_id'];
$research_title = $row['title'];
$get_query->close();

// Update capstone status to rejected
$update = $conn->prepare("UPDATE capstone SET status = 'rejected' WHERE id = ?");
$update->bind_param('i', $research_id);
$update->execute();
$update->close();

// Send notification to student
if ($user_id) {
	require_once '../assets/includes/notification_functions.php';
	$title = 'Research Rejected';
	$message = 'Your research "' . htmlspecialchars($research_title) . '" was rejected. Remarks: ' . htmlspecialchars($remarks);
	createNotification($conn, $user_id, $title, $message, 'danger', $research_id, 'capstone');
}

mysqli_close($conn);

$_SESSION['success_message'] = 'Research rejected and student notified.';
header('Location: research_list.php');
exit();
