<?php
include '../config/database.php';

// Check if connection is successful
if ($conn === false) {
    die("Database connection failed: " . mysqli_connect_error());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $title = $_POST['title'];
    $author = isset($_POST['author']) ? $_POST['author'] : null; // Debug: Check if author is set
    $year = $_POST['year'];
    $abstract = $_POST['abstract'];
    $keywords = $_POST['keywords'];
    $status = $_POST['status'];

    // Debug: Log the received values
    error_log("Update Research: id=$id, title=$title, author=$author, year=$year, abstract=$abstract, keywords=$keywords, status=$status");

    // Prepare the statement
    $stmt = $conn->prepare("UPDATE capstone SET title=?, author=?, year=?, abstract=?, keywords=?, status=? WHERE id=?");

    // Check if prepare was successful
    if ($stmt === false) {
        die("Prepare failed: " . $conn->error);
    }

    // Bind parameters
    $stmt->bind_param("ssssssi", $title, $author, $year, $abstract, $keywords, $status, $id);

    // Execute the statement
    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Capstone updated successfully."; // Updated message
        header("Location: research_list.php?update=success");
        header("Cache-Control: no-cache, must-revalidate");
        exit();
    } else {
        $_SESSION['error_message'] = "Failed to update capstone: " . $stmt->error;
        header("Location: research_list.php?update=error");
        exit();
    }

    // Close the statement
    $stmt->close();
}

// Close the connection (optional, as it might be handled in database.php)
$conn->close();
?>