<?php
session_start();

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Database connection
require_once '../config/database.php';

// Get parameters
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$offset = ($page - 1) * $limit;

// Build query
$query = "SELECT * FROM students WHERE 1=1";
$params = [];
$param_types = "";

if (!empty($search)) {
    $query .= " AND (CONCAT(first_name, ' ', middle_name, ' ', last_name) LIKE ? 
                OR email LIKE ? 
                OR year_section LIKE ? 
                OR group_code LIKE ?)";
    $search_param = "%" . $search . "%";
    $params = [$search_param, $search_param, $search_param, $search_param];
    $param_types = "ssss";
}

$query .= " ORDER BY id DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$param_types .= "ii";

$stmt = mysqli_prepare($conn, $query);
if (!empty($search)) {
    mysqli_stmt_bind_param($stmt, $param_types, ...$params);
} else {
    mysqli_stmt_bind_param($stmt, "ii", $limit, $offset);
}

mysqli_stmt_execute($stmt);
$students = mysqli_stmt_get_result($stmt);

// Get total students for pagination
$total_query = "SELECT COUNT(id) as total_students FROM students WHERE 1=1";
if (!empty($search)) {
    $total_query .= " AND (CONCAT(first_name, ' ', middle_name, ' ', last_name) LIKE ? 
                     OR email LIKE ? 
                     OR year_section LIKE ? 
                     OR group_code LIKE ?)";
}

$total_stmt = mysqli_prepare($conn, $total_query);
if (!empty($search)) {
    mysqli_stmt_bind_param($total_stmt, "ssss", $search_param, $search_param, $search_param, $search_param);
}
mysqli_stmt_execute($total_stmt);
$total_result = mysqli_stmt_get_result($total_stmt);
$total_row = mysqli_fetch_assoc($total_result);
$total_students = $total_row['total_students'];
$total_pages = ceil($total_students / $limit);

// Prepare response
$response = [
    'success' => true,
    'students' => [],
    'current_page' => $page,
    'total_pages' => $total_pages,
    'total_students' => $total_students,
    'limit' => $limit
];

// Fetch students
while ($row = mysqli_fetch_assoc($students)) {
    $response['students'][] = [
        'id' => $row['id'],
        'full_name' => "{$row['last_name']}, {$row['first_name']} {$row['middle_name']}",
        'first_name' => $row['first_name'],
        'middle_name' => $row['middle_name'],
        'last_name' => $row['last_name'],
        'email' => $row['email'],
        'gender' => $row['gender'],
        'year_section' => $row['year_section'],
        'group_code' => $row['group_code'],
        'status' => $row['status'],
        'ready_to_graduate' => (bool)$row['ready_to_graduate']
    ];
}

// Close connections
mysqli_stmt_close($stmt);
mysqli_stmt_close($total_stmt);
mysqli_close($conn);

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>