<?php
// Start session
session_start();
session_unset(); // Clear all session variables
// Destroy all session data
session_destroy();

// Redirect to login page (or any other desired page)
header("Location: ../users/login.php"); // Adjust the path if needed
exit();
?>
