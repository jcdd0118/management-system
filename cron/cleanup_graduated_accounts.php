<?php
// cleanup_graduated_accounts.php
// Usage: Run periodically (e.g., daily). Deletes graduated accounts older than 1 year.

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/retention.php';
$traceFile = defined('CLEANUP_TRACE_FILE') ? CLEANUP_TRACE_FILE : (__DIR__ . '/../admin/api/cleanup_trace.log');
@file_put_contents($traceFile, date('c') . " cleanup: start\n", FILE_APPEND);

// Ensure columns exist (be defensive on shared hosting)
$columns = [];
$res = $conn->query("SHOW COLUMNS FROM students");
if ($res instanceof mysqli_result) {
    while ($row = $res->fetch_assoc()) { $columns[$row['Field']] = true; }
    $res->close();
    if (!isset($columns['is_graduated'])) {
        @$conn->query("ALTER TABLE students ADD COLUMN is_graduated TINYINT(1) NOT NULL DEFAULT 0");
    }
    if (!isset($columns['graduated_at'])) {
        @$conn->query("ALTER TABLE students ADD COLUMN graduated_at DATETIME NULL DEFAULT NULL");
    }
    if (!isset($columns['ready_to_graduate'])) {
        @$conn->query("ALTER TABLE students ADD COLUMN ready_to_graduate TINYINT(1) NOT NULL DEFAULT 0");
    }
} else {
    error_log('cleanup_graduated_accounts: SHOW COLUMNS FROM students failed: ' . $conn->error);
    @file_put_contents($traceFile, date('c') . " cleanup: SHOW COLUMNS failed: " . $conn->error . "\n", FILE_APPEND);
}


// Immediate cleanup mode: delete all graduated accounts regardless of age
// NOTE: This replaces the months-based retention logic below.
// If you want to restore retention-based cleanup, comment the next two lines
// and uncomment the block further down.
$result = $conn->query("SELECT id, email, user_id FROM students WHERE is_graduated = 1");

/*
// Retention-based cleanup (Previous behavior):
// Deletes graduated accounts older than configured retention period (in months)
// Uses GRADUATE_RETENTION_MONTHS from config/retention.php
$retentionMonths = defined('GRADUATE_RETENTION_MONTHS') ? (int)GRADUATE_RETENTION_MONTHS : 12;
if ($retentionMonths < 1) { $retentionMonths = 1; }
$sql = "SELECT id, email FROM students WHERE is_graduated = 1 AND graduated_at IS NOT NULL AND graduated_at < DATE_SUB(NOW(), INTERVAL $retentionMonths MONTH)";
$result = $conn->query($sql);
*/

if (!($result instanceof mysqli_result)) {
    error_log('cleanup_graduated_accounts: SELECT graduated students failed: ' . $conn->error);
    @file_put_contents($traceFile, date('c') . " cleanup: SELECT graduated failed: " . $conn->error . "\n", FILE_APPEND);
    echo "No records processed.\n";
    return;
}

while ($row = $result->fetch_assoc()) {
    $studentId = (int)$row['id'];
    $email = $row['email'];
    $linkedUserId = isset($row['user_id']) ? (int)$row['user_id'] : 0;

    // Resolve linked user id by email if missing
    if ($linkedUserId <= 0 && !empty($email)) {
        if ($stmtFind = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1")) {
            $stmtFind->bind_param('s', $email);
            if ($stmtFind->execute()) {
                $stmtFind->bind_result($resolvedId);
                if ($stmtFind->fetch()) { $linkedUserId = (int)$resolvedId; }
            }
            $stmtFind->close();
        }
    }

    // Transaction per student to respect FK constraints
    $conn->begin_transaction();
    try {
        // Clean up project-related tables by email first
        if (!empty($email)) {
            // Delete project approvals first (they reference project_working_titles)
            if ($stmtDelApprovals = $conn->prepare("DELETE pa FROM project_approvals pa INNER JOIN project_working_titles pw ON pa.project_id = pw.id WHERE pw.submitted_by = ?")) {
                $stmtDelApprovals->bind_param('s', $email);
                $stmtDelApprovals->execute();
                $stmtDelApprovals->close();
                @file_put_contents($traceFile, date('c') . " cleanup: deleted project approvals for email {$email}\n", FILE_APPEND);
            }
            
            // Delete project working titles
            if ($stmtDelProjects = $conn->prepare("DELETE FROM project_working_titles WHERE submitted_by = ?")) {
                $stmtDelProjects->bind_param('s', $email);
                $stmtDelProjects->execute();
                $stmtDelProjects->close();
                @file_put_contents($traceFile, date('c') . " cleanup: deleted project working titles for email {$email}\n", FILE_APPEND);
            }
        }

        // Detach capstone foreign key first to avoid FK block on users
        if ($linkedUserId > 0) {
            if ($stmtDetach = $conn->prepare("UPDATE capstone SET user_id = NULL WHERE user_id = ?")) {
                $stmtDetach->bind_param('i', $linkedUserId);
                $stmtDetach->execute();
                $stmtDetach->close();
                @file_put_contents($traceFile, date('c') . " cleanup: detached capstone for user_id {$linkedUserId}\n", FILE_APPEND);
            } else {
                @file_put_contents($traceFile, date('c') . " cleanup: failed to prepare detach capstone: " . $conn->error . "\n", FILE_APPEND);
            }
        }

        // 1) Delete student row first
        if ($stmtStudent = $conn->prepare("DELETE FROM students WHERE id = ?")) {
            $stmtStudent->bind_param('i', $studentId);
            $stmtStudent->execute();
            $stmtStudent->close();
            @file_put_contents($traceFile, date('c') . " cleanup: deleted student id {$studentId}\n", FILE_APPEND);
        } else {
            throw new Exception('Prepare delete student failed: ' . $conn->error);
        }

        // 2) Delete user account by id if known, else by email
        if ($linkedUserId > 0) {
            if ($stmtDelUser = $conn->prepare("DELETE FROM users WHERE id = ?")) {
                $stmtDelUser->bind_param('i', $linkedUserId);
                $stmtDelUser->execute();
                $stmtDelUser->close();
                @file_put_contents($traceFile, date('c') . " cleanup: deleted user id {$linkedUserId}\n", FILE_APPEND);
            } else {
                throw new Exception('Prepare delete user by id failed: ' . $conn->error);
            }
        } elseif (!empty($email)) {
            if ($stmtDelUserEmail = $conn->prepare("DELETE FROM users WHERE email = ?")) {
                $stmtDelUserEmail->bind_param('s', $email);
                $stmtDelUserEmail->execute();
                $stmtDelUserEmail->close();
                @file_put_contents($traceFile, date('c') . " cleanup: deleted user by email {$email}\n", FILE_APPEND);
            } else {
                throw new Exception('Prepare delete user by email failed: ' . $conn->error);
            }
        }

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        @file_put_contents($traceFile, date('c') . " cleanup: rollback for student {$studentId}: " . $e->getMessage() . "\n", FILE_APPEND);
        // Continue to next student
    }
}

echo "Graduated accounts cleanup completed.\n";
@file_put_contents($traceFile, date('c') . " cleanup: done\n", FILE_APPEND);
?>