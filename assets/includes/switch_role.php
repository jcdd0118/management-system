<?php
session_start();
require_once __DIR__ . '/role_functions.php';

// Add debug logging
error_log("=== ROLE SWITCH DEBUG ===");

// Check if the 'role' parameter is set in the GET request
$error_role = isset($_GET['role']) ? $_GET['role'] : 'not set';
error_log("Requested role: " . $error_role);

// Check if 'user_data' exists in the session
$error_user_data = isset($_SESSION['user_data']) ? print_r($_SESSION['user_data'], true) : 'not set';
error_log("Current user data: " . $error_user_data);

// Check if 'active_role' exists in the session
$error_active_role = isset($_SESSION['active_role']) ? $_SESSION['active_role'] : 'not set';
error_log("Current active role: " . $error_active_role);


if (!isset($_SESSION['user_data'])) {
    error_log("No user data in session - redirecting to login");
    header("Location: ../../users/login.php");
    exit();
}

$requestedRole = isset($_GET['role']) ? trim($_GET['role']) : '';
// Optional next redirect after switching role
$requestedNext = isset($_GET['next']) ? trim($_GET['next']) : '';
$redirect = '';

error_log("Requested role (cleaned): " . $requestedRole);
error_log("Redirect URL: " . $redirect);

$currentUser = $_SESSION['user_data'];

// Validate that user has the requested role
if (!hasRole($currentUser, $requestedRole)) {
    error_log("User does not have requested role: " . $requestedRole);
    error_log("User roles: " . print_r(getUserRoles($currentUser), true));
    $_SESSION['error_message'] = '<i class="bi bi-exclamation-triangle-fill"></i> You do not have permission to access that role.';
    if (!empty($redirect)) {
        header("Location: " . $redirect);
    } else {
        header("Location: ../../admin/dashboard.php");
    }
    exit();
}

// Set the active role
$_SESSION['active_role'] = strtolower($requestedRole);
$_SESSION['role'] = strtolower($requestedRole); // Update current role for compatibility

error_log("Role switched successfully to: " . $requestedRole);
error_log("New session active_role: " . $_SESSION['active_role']);
error_log("New session role: " . $_SESSION['role']);

// Success message
$roleName = getRoleDisplayName($requestedRole);
$_SESSION['success_message'] = '<i class="bi bi-check-circle-fill"></i> Switched to <strong>' . htmlspecialchars($roleName) . '</strong> role successfully!';

// If a next URL is provided and matches the role's area, prefer it
function isSafeNextForRole($role, $next) {
    if (empty($next)) return false;
    // Only allow internal redirects and constrain by role base path
    $parsed = parse_url($next);
    if ($parsed === false) return false;
    if (isset($parsed['scheme']) || isset($parsed['host'])) return false; // disallow absolute URLs
    $path = isset($parsed['path']) ? $parsed['path'] : '';
    $roleBase = '/management_system/' . strtolower($role) . '/';
    return strpos($path, $roleBase) === 0;
}

// Determine redirect URL strictly by role, but allow safe next
switch (strtolower($requestedRole)) {
    case 'admin':
        $redirect = '../../admin/dashboard.php';
        break;
    case 'dean':
        $redirect = '../../dean/home.php';
        break;
    case 'adviser':
        $redirect = '../../adviser/home.php';
        break;
    case 'faculty':
        $redirect = '../../faculty/home.php';
        break;
    case 'grammarian':
        $redirect = '../../grammarian/home.php';
        break;
    case 'panelist':
        $redirect = '../../panel/home.php';
        break;
    default:
        $redirect = '../../admin/dashboard.php';
        break;
}

// Override with next if safe
if (!empty($requestedNext)) {
    // Normalize next to be relative to this file (../../)
    if (isSafeNextForRole($requestedRole, $requestedNext)) {
        // Convert absolute path /management_system/... to ../../...
        if (strpos($requestedNext, '/management_system/') === 0) {
            $redirect = '../..' . substr($requestedNext, strlen('/management_system'));
        } else if (strpos($requestedNext, '../..') === 0) {
            $redirect = $requestedNext;
        } else {
            // Keep as is if already relative within the role folder
            $redirect = $requestedNext;
        }
    }
}

error_log("Final redirect URL: " . $redirect);
header("Location: " . $redirect);
exit();
?>