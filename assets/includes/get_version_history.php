<?php
session_start();
include dirname(dirname(__DIR__)) . '/config/database.php';

// Handle AJAX request to get version history
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_version_history') {
    header('Content-Type: application/json');
    
    // Debug: Log the request
    error_log("Version history request received. POST data: " . print_r($_POST, true));
    
    if (!isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        error_log("CSRF token mismatch");
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit();
    }
    
    $projectId = intval($_POST['project_id']);
    
    try {
        error_log("Looking for project ID: $projectId");
        
        // Get all versions of this project (including archived ones)
        // First, get the project title of the current project
        $titleQuery = $conn->prepare("SELECT project_title, submitted_by FROM project_working_titles WHERE id = ?");
        if (!$titleQuery) {
            throw new Exception("Database error: " . $conn->error);
        }
        
        $titleQuery->bind_param("i", $projectId);
        $titleQuery->execute();
        $titleResult = $titleQuery->get_result();
        
        $versions = [];
        
        if ($titleRow = $titleResult->fetch_assoc()) {
            $projectTitle = $titleRow['project_title'];
            $submittedBy = $titleRow['submitted_by'];
            error_log("Found project title: $projectTitle, submitted by: $submittedBy");
            
            // Now get all versions with the same title, including submissions from any member of the same group
            // This mirrors the student view logic so faculty can see a complete history
            $historyQuery = $conn->prepare("
                SELECT pw.*, pa.faculty_approval, pa.faculty_comments, pa.adviser_approval, pa.adviser_comments, pa.dean_approval, pa.dean_comments 
                FROM project_working_titles pw
                LEFT JOIN project_approvals pa ON pw.id = pa.project_id
                WHERE pw.project_title = ?
                  AND pw.submitted_by IN (
                    SELECT email FROM students WHERE group_code = (
                        SELECT group_code FROM students WHERE email = ? LIMIT 1
                    )
                  )
                ORDER BY pw.version ASC
            ");
            
            if (!$historyQuery) {
                throw new Exception("Database error: " . $conn->error);
            }
            
            // Bind in the order of placeholders: project title first, then the reference submitter email
            $historyQuery->bind_param("ss", $projectTitle, $submittedBy);
            $historyQuery->execute();
            $historyResult = $historyQuery->get_result();
            
            $count = 0;
            while ($row = $historyResult->fetch_assoc()) {
                // Format the proponents for display
                $proponents = array_filter([
                    $row['proponent_1'],
                    $row['proponent_2'],
                    $row['proponent_3'],
                    $row['proponent_4']
                ], function($value) { return !empty($value); });
                
                $row['proponents'] = implode(', ', $proponents);
                $versions[] = $row;
                $count++;
            }
            error_log("Found $count versions for project: $projectTitle");
            $historyQuery->close();
        } else {
            error_log("No project found with ID: $projectId");
        }
        $titleQuery->close();
        
        echo json_encode(['success' => true, 'versions' => $versions, 'debug' => ['projectId' => $projectId, 'submittedBy' => $submittedBy, 'count' => count($versions)]]);
        
    } catch (Exception $e) {
        error_log("Exception in version history: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// If not a POST request or wrong action, return error
echo json_encode(['success' => false, 'message' => 'Invalid request']);
?>
