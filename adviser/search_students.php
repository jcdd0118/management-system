<?php
// search_students.php
header('Content-Type: application/json');

// Database connection
require_once '../config/database.php';

// Get the search term and limit from the request
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Build the SQL query
$query = "SELECT * FROM students WHERE 1=1";
$params = [];
if (!empty($search)) {
    $query .= " AND (CONCAT(first_name, ' ', middle_name, ' ', last_name) LIKE ? 
                OR email LIKE ? 
                OR year_section LIKE ? 
                OR group_code LIKE ?)";
    $search_param = "%" . $search . "%";
    $params = [$search_param, $search_param, $search_param, $search_param];
}
$query .= " ORDER BY id DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

// Prepare and execute the query
$stmt = mysqli_prepare($conn, $query);
if (!empty($search)) {
    mysqli_stmt_bind_param($stmt, "ssssii", ...$params);
} else {
    mysqli_stmt_bind_param($stmt, "ii", $limit, $offset);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Build the response
$students = [];
while ($row = mysqli_fetch_assoc($result)) {
    $students[] = [
        'id' => $row['id'],
        'full_name' => "{$row['last_name']}, {$row['first_name']} {$row['middle_name']}",
        'email' => $row['email'],
        'year_section' => $row['year_section'],
        'group_code' => $row['group_code'],
        'status' => $row['status'],
        'first_name' => $row['first_name'],
        'middle_name' => $row['middle_name'],
        'last_name' => $row['last_name'],
        'gender' => $row['gender']
    ];
}

// Get total number of students for pagination
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

// Close statements and connection
mysqli_stmt_close($stmt);
mysqli_stmt_close($total_stmt);
mysqli_close($conn);

// Output JSON response
echo json_encode([
    'students' => $students,
    'total_pages' => $total_pages,
    'current_page' => $page,
    'limit' => $limit
]);
?>