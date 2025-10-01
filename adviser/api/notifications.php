<?php
error_log("Adviser Notifications.php accessed with action: " . (isset($_GET['action']) ? $_GET['action'] : 'none'));
session_start();
require_once '../../config/database.php';
require_once '../../assets/includes/notification_functions.php';

header('Content-Type: application/json');

// Add debugging
error_log("Adviser session data: " . print_r($_SESSION, true));

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'adviser') {
    error_log("No adviser user_id in session");
    echo json_encode(['success' => false, 'message' => 'Not authenticated as adviser', 'session' => $_SESSION]);
    exit();
}

$user_id = $_SESSION['user_id'];
$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

switch ($action) {
    case 'get_notifications':
        $notifications = getUserNotifications($conn, $user_id);
        $unread_count = getUnreadNotificationCount($conn, $user_id);
        
        // Add URLs to notifications
        foreach ($notifications as &$notification) {
            $notification['url'] = getAdviserNotificationUrl($notification);
        }
        
        echo json_encode([
            'success' => true,
            'notifications' => $notifications,
            'unread_count' => $unread_count
        ]);
        break;
        
    case 'mark_read':
        $notification_id = isset($_POST['notification_id']) ? $_POST['notification_id'] : 0;
        $success = markNotificationAsRead($conn, $notification_id, $user_id);
        echo json_encode(['success' => $success]);
        break;
        
    case 'mark_unread':
        $notification_id = isset($_POST['notification_id']) ? $_POST['notification_id'] : 0;
        $success = markNotificationAsUnread($conn, $notification_id, $user_id);
        echo json_encode(['success' => $success]);
        break;
        
    case 'delete':
        $notification_id = isset($_POST['notification_id']) ? $_POST['notification_id'] : 0;
        $success = deleteNotification($conn, $notification_id, $user_id);
        echo json_encode(['success' => $success]);
        break;
        
    case 'mark_all_read':
        $success = markAllNotificationsAsRead($conn, $user_id);
        echo json_encode(['success' => $success]);
        break;
        
    case 'clear_all':
        $success = clearAllNotifications($conn, $user_id);
        echo json_encode(['success' => $success]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}
?>
