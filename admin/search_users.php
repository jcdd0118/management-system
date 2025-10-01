<?php
// search_users.php
header('Content-Type: application/json');

// Database connection
require_once '../config/database.php';

// Check if connection was successful
if ($conn->connect_error) {
    echo json_encode(['error' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

// Get the search term, pagination, sorting, and role filter parameters from the request
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name';
$order = isset($_GET['order']) ? $_GET['order'] : (isset($_GET['dir']) ? $_GET['dir'] : 'asc');
$roleFilter = isset($_GET['role']) ? trim($_GET['role']) : '';
$offset = ($page - 1) * $limit;

// Validate sort column
$allowed_sorts = ['name', 'email', 'gender'];
if (!in_array($sort, $allowed_sorts)) {
    $sort = 'name';
}

// Validate order
if (!in_array(strtolower($order), ['asc', 'desc'])) {
    $order = 'asc';
}

// Detect if users.roles column exists
$hasRolesColumn = false;
$res = $conn->query("SELECT COUNT(*) AS c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'roles'");
if ($res) {
    $row = $res->fetch_assoc();
    $hasRolesColumn = ((int)$row['c']) > 0;
    $res->close();
} else {
    // If we can't check for roles column, assume it doesn't exist
    $hasRolesColumn = false;
}

// Build users query (exclude students entirely)
$whereUsers = [];
$paramsUsers = [];
$typesUsers = '';

if (!empty($roleFilter)) {
    if ($hasRolesColumn) {
        $whereUsers[] = "(role = ? OR (roles IS NOT NULL AND roles <> '' AND FIND_IN_SET(?, REPLACE(roles, ' ', ''))))";
        $paramsUsers[] = $roleFilter;
        $paramsUsers[] = $roleFilter;
        $typesUsers .= 'ss';
    } else {
        $whereUsers[] = "role = ?";
        $paramsUsers[] = $roleFilter;
        $typesUsers .= 's';
    }
}

if (!empty($search)) {
    $whereUsers[] = "(CONCAT(last_name, ', ', first_name, ' ', COALESCE(middle_name,'')) LIKE ? OR email LIKE ?)";
    $like = "%$search%";
    $paramsUsers[] = $like; 
    $paramsUsers[] = $like; 
    $typesUsers .= 'ss';
}

$whereUsers[] = $hasRolesColumn
    ? "(LOWER(COALESCE(role,'')) <> 'student' AND (roles IS NULL OR roles = '' OR FIND_IN_SET('student', REPLACE(LOWER(roles), ' ', '')) = 0))"
    : "LOWER(COALESCE(role,'')) <> 'student'";

$whereUsersSql = count($whereUsers) ? ('WHERE ' . implode(' AND ', $whereUsers)) : '';

$usersSql = $hasRolesColumn
    ? "SELECT id,
            CONCAT(last_name, ', ', first_name, ' ', COALESCE(middle_name,'')) AS full_name,
            first_name, middle_name, last_name, email, gender,
            CASE WHEN (roles IS NULL OR roles = '') THEN role ELSE roles END AS roles,
            'users' AS source
       FROM users $whereUsersSql"
    : "SELECT id,
            CONCAT(last_name, ', ', first_name, ' ', COALESCE(middle_name,'')) AS full_name,
            first_name, middle_name, last_name, email, gender,
            role AS roles,
            'users' AS source
       FROM users $whereUsersSql";

// Prepare and execute the query
$stmt = mysqli_prepare($conn, $usersSql);
if (!$stmt) {
    echo json_encode(['error' => 'Database query preparation failed: ' . mysqli_error($conn)]);
    exit;
}

if (!empty($typesUsers)) {
    mysqli_stmt_bind_param($stmt, $typesUsers, ...$paramsUsers);
}

if (!mysqli_stmt_execute($stmt)) {
    echo json_encode(['error' => 'Database query execution failed: ' . mysqli_stmt_error($stmt)]);
    mysqli_stmt_close($stmt);
    mysqli_close($conn);
    exit;
}

$result = mysqli_stmt_get_result($stmt);
if (!$result) {
    echo json_encode(['error' => 'Failed to get result set: ' . mysqli_stmt_error($stmt)]);
    mysqli_stmt_close($stmt);
    mysqli_close($conn);
    exit;
}

// Build the response
$users = [];
while ($row = mysqli_fetch_assoc($result)) {
    $rolesString = isset($row['roles']) ? $row['roles'] : '';
    $roles = array_filter(array_map('trim', explode(',', $rolesString)));
    
    $users[] = [
        'id' => (int)$row['id'],
        'full_name' => isset($row['full_name']) ? $row['full_name'] : '',
        'first_name' => isset($row['first_name']) ? $row['first_name'] : '',
        'middle_name' => isset($row['middle_name']) ? $row['middle_name'] : '',
        'last_name' => isset($row['last_name']) ? $row['last_name'] : '',
        'email' => isset($row['email']) ? $row['email'] : '',
        'gender' => isset($row['gender']) ? $row['gender'] : '',
        'roles' => $rolesString,
        'roles_array' => $roles,
        'source' => isset($row['source']) ? $row['source'] : 'users',
    ];
}

// Sort the results according to sort/order
usort($users, function($a, $b) use ($sort, $order) {
    $mult = (strtolower($order) === 'desc') ? -1 : 1;
    
    if ($sort === 'email') {
        return $mult * strcasecmp((string)$a['email'], (string)$b['email']);
    }
    if ($sort === 'gender') {
        return $mult * strcasecmp((string)$a['gender'], (string)$b['gender']);
    }
    // default: name (full_name)
    return $mult * strcasecmp((string)$a['full_name'], (string)$b['full_name']);
});

// Get total count before pagination
$totalUsers = count($users);

// Paginate in PHP
$pagedUsers = array_slice($users, $offset, $limit);
$totalPages = max(1, (int)ceil($totalUsers / $limit));

// Close statement and connection
mysqli_stmt_close($stmt);
mysqli_close($conn);

// Output JSON response
echo json_encode([
    'users' => $pagedUsers,
    'total_pages' => $totalPages,
    'current_page' => $page,
    'limit' => $limit,
    'total_users' => $totalUsers
]);
?>