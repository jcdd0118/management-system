<?php
// student/api/bookmark.php
session_start();
header('Content-Type: application/json');

// Debug: Log request details
error_log("Bookmark request: " . file_get_contents('php://input'));

// Check if the user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please log in as a student.']);
    exit();
}

// Database connection
require_once '../../config/database.php'; // Adjust path if config is elsewhere
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . mysqli_connect_error()]);
    error_log("Database connection failed: " . mysqli_connect_error());
    exit();
}

// Verify POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit();
}

// Get JSON input
$data = json_decode(file_get_contents('php://input'), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input: ' . json_last_error_msg()]);
    exit();
}

$research_id = isset($data['research_id']) ? (int)$data['research_id'] : 0;
$action = isset($data['action']) ? $data['action'] : 'add';
$user_id = (int)$_SESSION['user_id'];

if ($research_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid research ID.']);
    exit();
}

// Check if the research paper exists and is verified
$sql = "SELECT id FROM capstone WHERE id = ? AND status = 'verified'";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database query preparation failed']);
    error_log("Prepare failed: " . $conn->error);
    exit();
}
$stmt->bind_param('i', $research_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Research not found or not verified.']);
    $stmt->close();
    $conn->close();
    exit();
}
$stmt->close();

// Check if already bookmarked
$sql = "SELECT id FROM bookmarks WHERE user_id = ? AND research_id = ?";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database query preparation failed']);
    error_log("Prepare failed: " . $conn->error);
    exit();
}
$stmt->bind_param('ii', $user_id, $research_id);
$stmt->execute();
$result = $stmt->get_result();
$is_bookmarked = $result->num_rows > 0;
$stmt->close();

if ($action === 'add') {
    if ($is_bookmarked) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Research already bookmarked.']);
        $conn->close();
        exit();
    }
    // Insert bookmark
    $sql = "INSERT INTO bookmarks (user_id, research_id) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database query preparation failed']);
        error_log("Prepare failed: " . $conn->error);
        exit();
    }
    $stmt->bind_param('ii', $user_id, $research_id);
    if ($stmt->execute()) {
        // Add notification for bookmark
        require_once '../../assets/includes/notification_functions.php';
        
        // Get research title for notification
        $titleQuery = $conn->prepare("SELECT title FROM capstone WHERE id = ?");
        $titleQuery->bind_param('i', $research_id);
        $titleQuery->execute();
        $titleResult = $titleQuery->get_result();
        $researchTitle = $titleResult->fetch_assoc()['title'];
        $titleQuery->close();
        
        createNotification(
            $conn, 
            $user_id, 
            'Research Bookmarked', 
            'You have bookmarked the research: "' . htmlspecialchars($researchTitle) . '"',
            'info',
            $research_id,
            'bookmark'
        );
        
        echo json_encode(['success' => true, 'message' => 'Research bookmarked successfully.', 'action' => 'added']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to bookmark research: ' . $conn->error]);
        error_log("Insert failed: " . $conn->error);
    }
} elseif ($action === 'remove') {
    if (!$is_bookmarked) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Research not bookmarked.']);
        $conn->close();
        exit();
    }
    // Delete bookmark
    $sql = "DELETE FROM bookmarks WHERE user_id = ? AND research_id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database query preparation failed']);
        error_log("Prepare failed: " . $conn->error);
        exit();
    }
    $stmt->bind_param('ii', $user_id, $research_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Bookmark removed successfully.', 'action' => 'removed']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to remove bookmark: ' . $conn->error]);
        error_log("Delete failed: " . $conn->error);
    }
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
}

$stmt->close();
$conn->close();
?>