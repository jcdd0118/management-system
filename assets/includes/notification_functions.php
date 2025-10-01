<?php
function createNotification($conn, $user_id, $title, $message, $type = 'info', $related_id = null, $related_type = null) {
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, related_id, related_type) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssss", $user_id, $title, $message, $type, $related_id, $related_type);
    return $stmt->execute();
}

function getUserNotifications($conn, $user_id, $unread_only = false, $limit = 20) {
    $where_clause = "WHERE user_id = ?";
    if ($unread_only) {
        $where_clause .= " AND is_read = FALSE";
    }
    
    $stmt = $conn->prepare("SELECT * FROM notifications $where_clause ORDER BY created_at DESC LIMIT ?");
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getUnreadNotificationCount($conn, $user_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = FALSE");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc()['count'];
}

function markNotificationAsRead($conn, $notification_id, $user_id) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notification_id, $user_id);
    return $stmt->execute();
}

function markAllNotificationsAsRead($conn, $user_id) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    return $stmt->execute();
}

// Clear all notifications for a user (delete all notifications)
function clearAllNotifications($conn, $user_id) {
    $stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

// Helper function to get project owner
function getProjectOwner($conn, $project_id) {
    $stmt = $conn->prepare("SELECT submitted_by FROM project_working_titles WHERE id = ?");
    $stmt->bind_param("i", $project_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        // Get user_id from email
        $email = $row['submitted_by'];
        $stmt2 = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt2->bind_param("s", $email);
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        if ($row2 = $result2->fetch_assoc()) {
            return $row2['id'];
        }
    }
    return null;
}

function markNotificationAsUnread($conn, $notification_id, $user_id) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = FALSE WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notification_id, $user_id);
    return $stmt->execute();
}

function deleteNotification($conn, $notification_id, $user_id) {
    $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notification_id, $user_id);
    return $stmt->execute();
}

function getNotificationUrl($notification) {
    // Generate URL based on notification type and related data
    // Check related_type first, then fall back to type
    $relatedType = isset($notification['related_type']) ? $notification['related_type'] : '';
    $type = isset($notification['type']) ? $notification['type'] : '';
    
    // Handle based on related_type (more specific)
    switch ($relatedType) {
        case 'project':
        case 'project_update':
            // Working title projects - go to submit_research.php history
            if (!empty($notification['related_id'])) {
                return "/management_system/student/submit_research.php?project_id=" . $notification['related_id'];
            }
            return "/management_system/student/submit_research.php";
        case 'title_defense':
            // If we have a project_id, go to specific title defense page
            if (!empty($notification['related_id'])) {
                return "/management_system/student/title-defense.php?project_id=" . $notification['related_id'];
            }
            return "/management_system/student/home.php";
        case 'final_defense':
            // If we have a project_id, go to specific final defense page
            if (!empty($notification['related_id'])) {
                return "/management_system/student/final-defense.php?project_id=" . $notification['related_id'];
            }
            return "/management_system/student/home.php";
        case 'panel_assignment':
            if (!empty($notification['related_id'])) {
                return "/management_system/student/submit_research.php?project_id=" . $notification['related_id'];
            }
            return "/management_system/student/submit_research.php";
        case 'capstone':
            // Capstone/research paper notifications - go to my_projects.php
            return "/management_system/student/my_projects.php";
        case 'bookmark':
            return "/management_system/student/bookmark.php";
        case 'group_assignment':
            return "/management_system/student/home.php";
    }
    
    // Fall back to type-based routing
    switch ($type) {
        case 'comment':
            if (!empty($notification['related_id'])) {
                return "/management_system/student/submit_research.php?project_id=" . $notification['related_id'];
            }
            return "/management_system/student/submit_research.php";
        case 'approval':
            if (!empty($notification['related_id'])) {
                return "/management_system/student/submit_research.php?project_id=" . $notification['related_id'];
            }
            return "/management_system/student/submit_research.php";
        case 'message':
            return "/management_system/student/home.php";
        case 'bookmark':
            return "/management_system/student/bookmark.php";
        case 'success':
            // For success notifications, check if it's about a project
            if ($relatedType === 'project') {
                if (!empty($notification['related_id'])) {
                    return "/management_system/student/submit_research.php?project_id=" . $notification['related_id'];
                }
                return "/management_system/student/submit_research.php";
            }
            return "/management_system/student/home.php";
        case 'warning':
            return "/management_system/student/home.php";
        case 'danger':
            return "/management_system/student/home.php";
        case 'info':
            return "/management_system/student/home.php";
        default:
            return "/management_system/student/home.php";
    }
}

// Admin-specific notification URL function
function getAdminNotificationUrl($notification) {
    $relatedType = isset($notification['related_type']) ? $notification['related_type'] : '';
    $type = isset($notification['type']) ? $notification['type'] : '';
    
    // Handle based on related_type (more specific)
    switch ($relatedType) {
        case 'new_research':
            return "/management_system/admin/research_list.php?status=nonverified";
        case 'new_student':
            return "/management_system/admin/student_list.php?status=nonverified";
        case 'capstone':
            if (!empty($notification['related_id'])) {
                return "/management_system/admin/research_list.php?search=" . urlencode($notification['related_id']);
            }
            return "/management_system/admin/research_list.php";
        case 'student_registration':
            return "/management_system/admin/student_list.php?status=nonverified";
        default:
            // Fallback to type-based URLs
            switch ($type) {
                case 'new_research':
                    return "/management_system/admin/research_list.php?status=nonverified";
                case 'new_student':
                    return "/management_system/admin/student_list.php?status=nonverified";
                case 'info':
                case 'success':
                case 'warning':
                case 'danger':
                default:
                    return "/management_system/admin/dashboard.php";
            }
    }
}

// Adviser-specific notification URL function
function getAdviserNotificationUrl($notification) {
    $relatedType = isset($notification['related_type']) ? $notification['related_type'] : '';
    $type = isset($notification['type']) ? $notification['type'] : '';
    
    switch ($relatedType) {
        case 'project_assignment':
            return "/management_system/adviser/student_list.php";
        case 'title_defense':
            if (!empty($notification['related_id'])) {
                return "/management_system/adviser/title_defense_file.php?project_id=" . $notification['related_id'];
            }
            return "/management_system/adviser/title_defense_list.php";
        case 'final_defense':
            if (!empty($notification['related_id'])) {
                return "/management_system/adviser/final_defense_file.php?project_id=" . $notification['related_id'];
            }
            return "/management_system/adviser/final_defense_list.php";
        case 'project_review':
            if (!empty($notification['related_id'])) {
                return "/management_system/adviser/review_project.php?project_id=" . $notification['related_id'];
            }
            return "/management_system/adviser/student_list.php";
        default:
            switch ($type) {
                case 'project_assignment':
                    return "/management_system/adviser/student_list.php";
                case 'title_defense':
                    return "/management_system/adviser/title_defense_list.php";
                case 'final_defense':
                    return "/management_system/adviser/final_defense_list.php";
                default:
                    return "/management_system/adviser/home.php";
            }
    }
}

// Dean-specific notification URL function
function getDeanNotificationUrl($notification) {
    $relatedType = isset($notification['related_type']) ? $notification['related_type'] : '';
    $type = isset($notification['type']) ? $notification['type'] : '';
    
    switch ($relatedType) {
        case 'panel_assignment':
            return "/management_system/dean/assign_panel.php";
        case 'adviser_assignment':
            return "/management_system/dean/assign_technical_adviser.php";
        case 'grammarian_assignment':
            return "/management_system/dean/assign_grammarian.php";
        case 'project_assignment':
            return "/management_system/dean/assign_technical_adviser.php";
        case 'project_review':
            if (!empty($notification['related_id'])) {
                return "/management_system/dean/review_project.php?project_id=" . $notification['related_id'];
            }
            return "/management_system/dean/home.php";
        default:
            switch ($type) {
                case 'panel_assignment':
                    return "/management_system/dean/assign_panel.php";
                case 'adviser_assignment':
                    return "/management_system/dean/assign_technical_adviser.php";
                case 'grammarian_assignment':
                    return "/management_system/dean/assign_grammarian.php";
                case 'project_assignment':
                    return "/management_system/dean/assign_technical_adviser.php";
                default:
                    return "/management_system/dean/home.php";
            }
    }
}

// Panelist-specific notification URL function
function getPanelistNotificationUrl($notification) {
    $relatedType = isset($notification['related_type']) ? $notification['related_type'] : '';
    $type = isset($notification['type']) ? $notification['type'] : '';
    
    switch ($relatedType) {
        case 'panel_assignment':
            return "/management_system/panel/home.php";
        case 'title_defense':
            if (!empty($notification['related_id'])) {
                return "/management_system/panel/title_defense_file.php?project_id=" . $notification['related_id'];
            }
            return "/management_system/panel/title_defense_list.php";
        case 'final_defense':
            if (!empty($notification['related_id'])) {
                return "/management_system/panel/final_defense_file.php?project_id=" . $notification['related_id'];
            }
            return "/management_system/panel/final_defense_list.php";
        default:
            switch ($type) {
                case 'panel_assignment':
                    return "/management_system/panel/home.php";
                case 'title_defense':
                    return "/management_system/panel/title_defense_list.php";
                case 'final_defense':
                    return "/management_system/panel/final_defense_list.php";
                default:
                    return "/management_system/panel/home.php";
            }
    }
}

// Faculty-specific notification URL function
function getFacultyNotificationUrl($notification) {
    $relatedType = isset($notification['related_type']) ? $notification['related_type'] : '';
    $type = isset($notification['type']) ? $notification['type'] : '';
    
    switch ($relatedType) {
        case 'advisory_assignment':
            return "/management_system/faculty/my_advisory.php";
        case 'project_review':
            if (!empty($notification['related_id'])) {
                return "/management_system/faculty/review_project.php?project_id=" . $notification['related_id'];
            }
            return "/management_system/faculty/my_advisory.php";
        default:
            switch ($type) {
                case 'advisory_assignment':
                    return "/management_system/faculty/my_advisory.php";
                default:
                    return "/management_system/faculty/home.php";
            }
    }
}

// Grammarian-specific notification URL function
function getGrammarianNotificationUrl($notification) {
    $relatedType = isset($notification['related_type']) ? $notification['related_type'] : '';
    $type = isset($notification['type']) ? $notification['type'] : '';
    
    switch ($relatedType) {
        case 'manuscript_review':
            if (!empty($notification['related_id'])) {
                return "/management_system/grammarian/review_manuscript.php?project_id=" . $notification['related_id'];
            }
            return "/management_system/grammarian/home.php";
        case 'grammarian_assignment':
            return "/management_system/grammarian/home.php";
        default:
            switch ($type) {
                case 'manuscript_review':
                    return "/management_system/grammarian/home.php";
                case 'grammarian_assignment':
                    return "/management_system/grammarian/home.php";
                default:
                    return "/management_system/grammarian/home.php";
            }
    }
}
?>