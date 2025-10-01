<?php
session_start();
require '../config/database.php';

// Check if user logged in
if (!isset($_SESSION['user_id'])) {
    die("No user logged in.");
}

$user_id = $_SESSION['user_id'];
$upload_dir = '../assets/img/avatar/';
$default_avatar = 'avatar.png';

$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'student';

// Get current avatar from DB
$stmt = $conn->prepare("SELECT avatar FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$current = $result->fetch_assoc();
$current_avatar = $current ? $current['avatar'] : $default_avatar;

// Debug: Log session data before changes
error_log("Before update - user_id: " . $_SESSION['user_id'] . ", role: " . $_SESSION['role'] . ", avatar: " . (isset($_SESSION['avatar']) ? $_SESSION['avatar'] : 'null'));

if (isset($_POST['remove_avatar']) && $_POST['remove_avatar'] == '1' && $current_avatar !== $default_avatar) {
    $current_path = $upload_dir . $current_avatar;
    if (file_exists($current_path)) {
        unlink($current_path);
    }

    $stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
    $stmt->bind_param("si", $default_avatar, $user_id);
    $stmt->execute();
    $stmt->close();

    $_SESSION['avatar'] = $default_avatar;

} elseif (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
    $file_tmp = $_FILES['avatar']['tmp_name'];
    $file_name = basename($_FILES['avatar']['name']);
    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];

    if (in_array($file_ext, $allowed_exts)) {
        $new_filename = uniqid('avatar_') . '.' . $file_ext;
        $target_path = $upload_dir . $new_filename;

        if (move_uploaded_file($file_tmp, $target_path)) {
            // Delete old avatar if not default
            if ($current_avatar !== $default_avatar && file_exists($upload_dir . $current_avatar)) {
                unlink($upload_dir . $current_avatar);
            }

            $stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
            $stmt->bind_param("si", $new_filename, $user_id);
            $stmt->execute();
            $stmt->close();

            $_SESSION['avatar'] = $new_filename;
        }
    }
}

// Debug: Log session data after changes
error_log("After update - user_id: " . $_SESSION['user_id'] . ", role: " . $_SESSION['role'] . ", avatar: " . (isset($_SESSION['avatar']) ? $_SESSION['avatar'] : 'null'));

// Preserve session and redirect based on role
$redirect_url = '';
switch ($user_role) {
    case 'student':
        $redirect_url = '../student/home.php';
        break;
    case 'adviser':
        $redirect_url = '../adviser/home.php';
        break;
    case 'faculty':
        $redirect_url = '../faculty/home.php';
        break;
    case 'dean':
        $redirect_url = '../dean/home.php';
        break;
    case 'panelist':
        $redirect_url = '../panelist/home.php';
        break;
    default:
        $redirect_url = '../admin/dashboard.php';
        break;
}

header("Location: " . $redirect_url);
exit();
?>