<?php
// search_research.php
header('Content-Type: application/json');

// Database connection
require_once '../config/database.php';

// Get the search term, pagination, and sorting parameters from the request
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'id';
$order = isset($_GET['order']) ? $_GET['order'] : 'DESC';
$offset = ($page - 1) * $limit;

// Validate sort column
$allowed_sorts = ['id', 'title', 'author', 'year', 'status'];
if (!in_array($sort, $allowed_sorts)) {
    $sort = 'id';
}

// Validate order
if (!in_array(strtoupper($order), ['ASC', 'DESC'])) {
    $order = 'DESC';
}

// Build the SQL query
$query = "SELECT * FROM capstone WHERE 1=1";
$params = [];
if (!empty($search)) {
    $query .= " AND (title LIKE ? OR author LIKE ? OR keywords LIKE ?)";
    $search_param = "%" . $search . "%";
    $params = [$search_param, $search_param, $search_param];
}
$query .= " ORDER BY $sort $order LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

// Prepare and execute the query
$stmt = mysqli_prepare($conn, $query);
if (!empty($search)) {
    mysqli_stmt_bind_param($stmt, "sssii", ...$params);
} else {
    mysqli_stmt_bind_param($stmt, "ii", $limit, $offset);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Build the response
$research = [];
while ($row = mysqli_fetch_assoc($result)) {
    $statusLower = strtolower(trim($row['status'] ?: 'nonverified'));
    if ($statusLower === 'verified') {
        $statusBadge = "Verified";
        $statusClass = "success";
    } elseif ($statusLower === 'rejected') {
        $statusBadge = "Rejected";
        $statusClass = "danger";
    } else {
        $statusBadge = "Not Verified";
        $statusClass = "warning";
    }
    
    // Extract display author from special format if present
    $displayAuthor = $row['author'];
    if (strpos($row['author'], 'STUDENT_DATA:') === 0) {
        $parts = explode('|DISPLAY:', $row['author']);
        if (count($parts) === 2) {
            $displayAuthor = $parts[1];
        }
    }
    
    $research[] = [
        'id' => $row['id'],
        'title' => $row['title'],
        'author' => $displayAuthor, // Use display author for table
        'author_raw' => $row['author'], // Keep raw author for modal
        'year' => $row['year'],
        'abstract' => $row['abstract'],
        'keywords' => $row['keywords'],
        'document_path' => $row['document_path'],
        'user_id' => $row['user_id'],
        'status' => $row['status'] ?: 'nonverified',
        'status_badge' => $statusBadge,
        'status_class' => $statusClass
    ];
}

// Get total number of research entries for pagination
$total_query = "SELECT COUNT(id) as total_research FROM capstone WHERE 1=1";
if (!empty($search)) {
    $total_query .= " AND (title LIKE ? OR author LIKE ? OR keywords LIKE ?)";
}
$total_stmt = mysqli_prepare($conn, $total_query);
if (!empty($search)) {
    mysqli_stmt_bind_param($total_stmt, "sss", $search_param, $search_param, $search_param);
}
mysqli_stmt_execute($total_stmt);
$total_result = mysqli_stmt_get_result($total_stmt);
$total_row = mysqli_fetch_assoc($total_result);
$total_research = $total_row['total_research'];
$total_pages = ceil($total_research / $limit);

// Close statements and connection
mysqli_stmt_close($stmt);
mysqli_stmt_close($total_stmt);
mysqli_close($conn);

// Output JSON response
echo json_encode([
    'research' => $research,
    'total_pages' => $total_pages,
    'current_page' => $page,
    'limit' => $limit
]);
?>