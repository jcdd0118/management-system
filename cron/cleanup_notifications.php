<?php
require_once '../config/database.php';

// Function to clean up old notifications
function cleanupOldNotifications($conn, $daysOld = 30) {
    $stmt = $conn->prepare("DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
    $stmt->bind_param("i", $daysOld);
    $success = $stmt->execute();
    $deletedRows = $stmt->affected_rows;
    $stmt->close();
    
    return [
        'success' => $success,
        'deleted_count' => $deletedRows
    ];
}

// Function to clean up read notifications older than X days
function cleanupReadNotifications($conn, $daysOld = 7) {
    $stmt = $conn->prepare("DELETE FROM notifications WHERE is_read = TRUE AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
    $stmt->bind_param("i", $daysOld);
    $success = $stmt->execute();
    $deletedRows = $stmt->affected_rows;
    $stmt->close();
    
    return [
        'success' => $success,
        'deleted_count' => $deletedRows
    ];
}

// Function to keep only the latest N notifications per user
function keepLatestNotifications($conn, $maxNotificationsPerUser = 50) {
    // Get all users with notifications
    $usersQuery = $conn->query("SELECT DISTINCT user_id FROM notifications");
    $users = $usersQuery->fetch_all(MYSQLI_ASSOC);
    
    $totalDeleted = 0;
    
    foreach ($users as $user) {
        $userId = $user['user_id'];
        
        // Delete old notifications, keeping only the latest N
        $deleteQuery = $conn->prepare("
            DELETE FROM notifications 
            WHERE user_id = ? 
            AND id NOT IN (
                SELECT id FROM (
                    SELECT id FROM notifications 
                    WHERE user_id = ? 
                    ORDER BY created_at DESC 
                    LIMIT ?
                ) AS latest
            )
        ");
        $deleteQuery->bind_param("iii", $userId, $userId, $maxNotificationsPerUser);
        $deleteQuery->execute();
        $totalDeleted += $deleteQuery->affected_rows;
        $deleteQuery->close();
    }
    
    return [
        'success' => true,
        'deleted_count' => $totalDeleted
    ];
}

// Run cleanup based on command line arguments or default behavior
$cleanupType = isset($argv[1]) ? $argv[1] : 'all';
$days = isset($argv[2]) ? (int)$argv[2] : 30;

echo "Starting notification cleanup...\n";

switch ($cleanupType) {
    case 'old':
        $result = cleanupOldNotifications($conn, $days);
        echo "Cleaned up notifications older than {$days} days: {$result['deleted_count']} deleted\n";
        break;
        
    case 'read':
        $result = cleanupReadNotifications($conn, $days);
        echo "Cleaned up read notifications older than {$days} days: {$result['deleted_count']} deleted\n";
        break;
        
    case 'keep_latest':
        $maxNotifications = isset($argv[2]) ? (int)$argv[2] : 50;
        $result = keepLatestNotifications($conn, $maxNotifications);
        echo "Kept only latest {$maxNotifications} notifications per user: {$result['deleted_count']} deleted\n";
        break;
        
    case 'all':
    default:
        // Clean up read notifications older than 7 days
        $readResult = cleanupReadNotifications($conn, 7);
        echo "Cleaned up read notifications older than 7 days: {$readResult['deleted_count']} deleted\n";
        
        // Clean up all notifications older than 30 days
        $oldResult = cleanupOldNotifications($conn, 30);
        echo "Cleaned up all notifications older than 30 days: {$oldResult['deleted_count']} deleted\n";
        
        // Keep only latest 100 notifications per user
        $latestResult = keepLatestNotifications($conn, 100);
        echo "Kept only latest 100 notifications per user: {$latestResult['deleted_count']} deleted\n";
        break;
}

echo "Notification cleanup completed.\n";
$conn->close();
?>