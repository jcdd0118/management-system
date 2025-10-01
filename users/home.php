<?php
session_start();

// Example: Assuming $_SESSION['role'] contains the user role after login
if (!isset($_SESSION['role'])) {
    // Not logged in or role not set — redirect to login or error page
    header("Location: login.php");
    exit();
}

switch ($_SESSION['role']) {
    case 'student':
        header("Location: ../student/home.php");
        break;
    case 'panel':
        header("Location: ../panel/home.php");
        break;
    case 'dean':
        header("Location: ../dean/home.php");
        break;
    case 'adviser':
        header("Location: ../adviser/home.php");
        break;
    case 'faculty':
        header("Location: ../faculty/home.php");
        break;
    default:
        // Unknown role — redirect to a generic error or login page
        header("Location: login.php?error=unknown_role");
        break;
}

exit(); // Ensure no further code is executed
?>
