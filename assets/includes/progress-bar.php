<?php
$current_page = basename($_SERVER['PHP_SELF']);
$project_id = isset($_GET['project_id']) ? $_GET['project_id'] : '';
$project_param = $project_id ? "?project_id=$project_id" : '';

// Initialize step status with main and sub steps
$step_status = [
    // Project Title
    'project_title' => 'pending',
    'capstone_adviser' => 'pending',
    'project_approval' => 'pending',
    
    // Title Defense
    'title_defense' => 'pending',
    'title_schedule' => 'pending',
    'title_approval' => 'pending',
    
    // Final Defense
    'final_defense' => 'pending',
    'final_schedule' => 'pending',
    'final_approval' => 'pending',
    
    // Grammarian
    'grammarian' => 'pending',
    'grammarian_assign' => 'pending',
    'grammarian_approval' => 'pending',
    
    // Submit Manuscript
    'submit_manuscript' => 'pending'
];

$current_step = 1;
$can_access_title_defense = false;
$can_access_final_defense = false;
$can_access_grammarian = false;
$can_access_submit_manuscript = false;

// Determine if current student is 4th year (restrict final defense for 3rd year)
$is_fourth_year = true; // default allow for non-student contexts
if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['role']) && $_SESSION['role'] === 'student' && isset($_SESSION['user_id']) && isset($conn)) {
    $yr_stmt = $conn->prepare("SELECT year_section FROM students WHERE user_id = ? LIMIT 1");
    if ($yr_stmt) {
        $yr_stmt->bind_param("i", $_SESSION['user_id']);
        $yr_stmt->execute();
        $yr_res = $yr_stmt->get_result();
        if ($yr_row = $yr_res->fetch_assoc()) {
            $year_section = isset($yr_row['year_section']) ? $yr_row['year_section'] : '';
            $is_fourth_year = (strlen($year_section) > 0 && substr($year_section, 0, 1) === '4');
        }
        $yr_stmt->close();
    }
}

// Check database status if project_id exists and database connection is available
if (!empty($project_id) && isset($conn)) {
    try {
        // Check if project submission exists
        $project_query = $conn->prepare("SELECT id FROM project_working_titles WHERE id = ?");
        if ($project_query) {
            $project_query->bind_param("i", $project_id);
            $project_query->execute();
            $project_result = $project_query->get_result();
            if ($project_result->num_rows > 0) {
                $step_status['project_title'] = 'completed';
                
                // Check if capstone adviser is assigned (from project_working_titles table)
                $adviser_query = $conn->prepare("SELECT noted_by FROM project_working_titles WHERE id = ?");
                if ($adviser_query) {
                    $adviser_query->bind_param("i", $project_id);
                    $adviser_query->execute();
                    $adviser_result = $adviser_query->get_result();
                    if ($adviser_result->num_rows > 0) {
                        $adviser_data = $adviser_result->fetch_assoc();
                        
                        // Check if adviser is assigned (noted_by is not NULL)
                        if (!empty($adviser_data['noted_by'])) {
                            $step_status['capstone_adviser'] = 'completed';
                        }
                    }
                    $adviser_query->close();
                }
                
                // Check project approvals from project_approvals table
                $approval_query = $conn->prepare("SELECT faculty_approval, adviser_approval, dean_approval FROM project_approvals WHERE project_id = ?");
                $approval_query->bind_param("i", $project_id);
                $approval_query->execute();
                $approval_result = $approval_query->get_result();
                if ($approval_result->num_rows > 0) {
                    $approval_data = $approval_result->fetch_assoc();
                    
                    // Check if all 3 approvals are approved
                    if ($approval_data['faculty_approval'] === 'approved' && 
                        $approval_data['adviser_approval'] === 'approved' && 
                        $approval_data['dean_approval'] === 'approved') {
                        $step_status['project_approval'] = 'completed';
                        $can_access_title_defense = true;
                        $current_step = 2;
                    }
                }
                $approval_query->close();
            }
            $project_query->close();
        }
        
        // Check title defense status
        $title_query = $conn->prepare("SELECT status, scheduled_date FROM title_defense WHERE project_id = ?");
        if ($title_query) {
            $title_query->bind_param("i", $project_id);
            $title_query->execute();
            $title_result = $title_query->get_result();
            $title_defense = $title_result->fetch_assoc();
            $title_query->close();
            
            if ($title_defense) {
                $step_status['title_defense'] = 'completed';
                
                // Check if scheduled
                if (!empty($title_defense['scheduled_date'])) {
                    $step_status['title_schedule'] = 'completed';
                }
                
                // Check title defense approval
                if ($title_defense['status'] === 'approved') {
                    $step_status['title_approval'] = 'completed';
                    // Final defense only available to 4th year students
                    $can_access_final_defense = $is_fourth_year ? true : false;
                    $current_step = $can_access_final_defense ? 3 : $current_step;
                }
            }
        }
        
        // Check final defense status
        $final_query = $conn->prepare("SELECT status, scheduled_date FROM final_defense WHERE project_id = ?");
        if ($final_query) {
            $final_query->bind_param("i", $project_id);
            $final_query->execute();
            $final_result = $final_query->get_result();
            $final_defense = $final_result->fetch_assoc();
            $final_query->close();
            
            if ($final_defense) {
                $step_status['final_defense'] = 'completed';
                
                // Check if scheduled
                if (!empty($final_defense['scheduled_date'])) {
                    $step_status['final_schedule'] = 'completed';
                }
                
                // Check final defense approval
                if ($final_defense['status'] === 'approved') {
                    $step_status['final_approval'] = 'completed';
                    $can_access_grammarian = true;
                    $current_step = 4;
                }
            }
        }
        
        // Check grammarian assignment and approval
        // Grammarian is assigned when manuscript is submitted (manuscript_reviews table has reviewed_by)
        $grammarian_query = $conn->prepare("SELECT reviewed_by FROM manuscript_reviews WHERE project_id = ? AND reviewed_by IS NOT NULL");
        if ($grammarian_query) {
            $grammarian_query->bind_param("i", $project_id);
            $grammarian_query->execute();
            $grammarian_result = $grammarian_query->get_result();
            if ($grammarian_result->num_rows > 0) {
                $step_status['grammarian'] = 'completed';
                $step_status['grammarian_assign'] = 'completed';
            }
            $grammarian_query->close();
        }
        
        // Check manuscript review status
        $manuscript_query = $conn->prepare("SELECT status FROM manuscript_reviews WHERE project_id = ?");
        if ($manuscript_query) {
            $manuscript_query->bind_param("i", $project_id);
            $manuscript_query->execute();
            $manuscript_result = $manuscript_query->get_result();
            $manuscript = $manuscript_result->fetch_assoc();
            $manuscript_query->close();
            
            if ($manuscript) {
                if ($manuscript['status'] === 'approved') {
                    $step_status['grammarian_approval'] = 'completed';
                    $can_access_submit_manuscript = true;
                    $current_step = 5;
                }
            }
        }

		// If admin has verified the final manuscript (capstone), mark Submit Manuscript as completed
		// Group-aware: consider any verified capstone submitted by a member of the same group
		if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['role']) && $_SESSION['role'] === 'student') {
			// Prefer using user_id to derive group_code
			if (isset($_SESSION['user_id'])) {
				$uid = (int)$_SESSION['user_id'];
				$capSql = "
					SELECT COUNT(*) AS submission_count
					FROM capstone c
					WHERE c.status = 'verified'
					AND c.user_id IN (
						SELECT u.id
						FROM users u
						INNER JOIN students s ON u.email = s.email
						WHERE s.group_code = (
							SELECT s2.group_code FROM students s2 WHERE s2.user_id = ? LIMIT 1
						)
					)
				";
				$capStmt = $conn->prepare($capSql);
				if ($capStmt) {
					$capStmt->bind_param("i", $uid);
					$capStmt->execute();
					$capRes = $capStmt->get_result();
					if ($capRow = $capRes->fetch_assoc()) {
						if ((int)$capRow['submission_count'] > 0) {
							$step_status['submit_manuscript'] = 'completed';
						}
					}
					$capStmt->close();
				}
			}
		}
    } catch (Exception $e) {
        // If database queries fail, fall back to page-based detection
        error_log("Progress bar database query failed: " . $e->getMessage());
    }
}

// Override current step based on current page (for visual feedback)
$page_step = 1;
if (strpos($current_page, 'title-defense') !== false) {
    $page_step = 2;
} elseif (strpos($current_page, 'final-defense') !== false) {
    $page_step = 3;
} elseif (strpos($current_page, 'manuscript_upload') !== false) {
    $page_step = 4;
} elseif (strpos($current_page, 'submit_manuscript') !== false) {
    $page_step = 5;
}

// Use the higher of database step or page step for highlighting
$display_step = max($current_step, $page_step);

// Debug: Show current status (remove this after testing)
if (isset($_GET['debug']) && $_GET['debug'] == '1') {
    echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px; border: 1px solid #ccc;'>";
    echo "<h4>Progress Bar Debug Info:</h4>";
    echo "<p><strong>Project ID:</strong> " . $project_id . "</p>";
    echo "<p><strong>Current Step:</strong> " . $current_step . "</p>";
    echo "<p><strong>Display Step:</strong> " . $display_step . "</p>";
    
    // Debug capstone adviser assignment
    if (!empty($project_id) && isset($conn)) {
        $debug_query = $conn->prepare("SELECT noted_by FROM project_working_titles WHERE id = ?");
        $debug_query->bind_param("i", $project_id);
        $debug_query->execute();
        $debug_result = $debug_query->get_result();
        if ($debug_result->num_rows > 0) {
            $debug_data = $debug_result->fetch_assoc();
            echo "<p><strong>Capstone Adviser Assignment (noted_by):</strong> " . ($debug_data['noted_by'] ? $debug_data['noted_by'] : 'NULL') . "</p>";
        }
        $debug_query->close();
    }
    
    echo "<p><strong>Access Controls:</strong></p>";
    echo "<ul>";
    echo "<li>Title Defense: " . ($can_access_title_defense ? 'YES' : 'NO') . "</li>";
    echo "<li>Final Defense: " . ($can_access_final_defense ? 'YES' : 'NO') . "</li>";
    echo "<li>Grammarian: " . ($can_access_grammarian ? 'YES' : 'NO') . "</li>";
    echo "<li>Submit Manuscript: " . ($can_access_submit_manuscript ? 'YES' : 'NO') . "</li>";
    echo "</ul>";
    echo "<p><strong>Step Status:</strong></p>";
    echo "<pre>" . print_r($step_status, true) . "</pre>";
    echo "</div>";
}
?>

<div class="progress-bar-container">
    <div class="progress-steps animate-load">
        
        <!-- PROJECT TITLE SECTION -->
        <div class="main-step-section">
            <!-- Main Step: Project Title -->
            <a href="submit_research.php<?php echo $project_param; ?>" class="main-step-link">
                <div class="main-step <?php echo $step_status['project_title'] === 'completed' ? 'completed' : ($display_step == 1 ? 'active' : ''); ?>">
                    <div class="main-circle">
                        <?php if ($step_status['project_title'] === 'completed'): ?>
                            <i class="bi bi-check-lg"></i>
                        <?php else: ?>
                            1
                        <?php endif; ?>
                    </div>
					<span class="main-label" title="Project Title">Project Title</span>
                </div>
            </a>
            
            <!-- Sub-steps -->
            <div class="sub-steps">
                <!-- Capstone Adviser Assignment -->
                <div title="Capstone Adviser" class="sub-step <?php echo $step_status['capstone_adviser'] === 'completed' ? 'completed' : ''; ?>">
                    <div class="sub-circle">
                        <?php if ($step_status['capstone_adviser'] === 'completed'): ?>
                            <i class="bi bi-check-lg"></i>
                        <?php else: ?>
                            <i class="bi bi-person-plus"></i>
                        <?php endif; ?>
                    </div>
					<span class="sub-label" title="Capstone Adviser">Capstone Adviser</span>
                </div>
                
                <div class="mini-line <?php echo $step_status['project_approval'] !== 'pending' ? 'filled' : ''; ?>"></div>
                
                <!-- Project Approval -->
                <div title="Approval" class="sub-step <?php echo $step_status['project_approval'] === 'completed' ? 'completed' : ''; ?>">
                    <div class="sub-circle">
                        <?php if ($step_status['project_approval'] === 'completed'): ?>
                            <i class="bi bi-check-lg"></i>
                        <?php else: ?>
                            <i class="bi bi-check-circle"></i>
                        <?php endif; ?>
                    </div>
					<span class="sub-label" title="Approval">Approval</span>
                </div>
            </div>
        </div>

        <div class="main-line <?php echo $step_status['title_defense'] !== 'pending' ? 'filled' : ''; ?>"></div>

        <!-- TITLE DEFENSE SECTION -->
        <div class="main-step-section">
            <!-- Main Step: Title Defense -->
            <?php if ($can_access_title_defense): ?>
                <a href="title-defense.php<?php echo $project_param; ?>" class="main-step-link">
            <?php else: ?>
                <div class="main-step-link disabled" title="Complete project approval first">
            <?php endif; ?>
                <div class="main-step <?php echo $step_status['title_defense'] === 'completed' ? 'completed' : ($display_step == 2 ? 'active' : ($can_access_title_defense ? '' : 'disabled')); ?>">
                    <div class="main-circle">
                        <?php if ($step_status['title_defense'] === 'completed'): ?>
                            <i class="bi bi-check-lg"></i>
                        <?php else: ?>
                            2
                        <?php endif; ?>
                    </div>
					<span class="main-label" title="Title Defense">Title Defense</span>
                </div>
            <?php if ($can_access_title_defense): ?>
                </a>
            <?php else: ?>
                </div>
            <?php endif; ?>
            
            <!-- Sub-steps -->
            <div class="sub-steps">
                <!-- Schedule -->
                <div title="Schedule" class="sub-step <?php echo $step_status['title_schedule'] === 'completed' ? 'completed' : ''; ?>">
                    <div class="sub-circle">
                        <?php if ($step_status['title_schedule'] === 'completed'): ?>
                            <i class="bi bi-check-lg"></i>
                        <?php else: ?>
                            <i class="bi bi-calendar"></i>
                        <?php endif; ?>
                    </div>
					<span class="sub-label" title="Schedule">Schedule</span>
                </div>
                
                <div class="mini-line <?php echo $step_status['title_approval'] !== 'pending' ? 'filled' : ''; ?>"></div>
                
                <!-- Title Approval -->
                <div title="Approval"class="sub-step <?php echo $step_status['title_approval'] === 'completed' ? 'completed' : ''; ?>">
                    <div class="sub-circle">
                        <?php if ($step_status['title_approval'] === 'completed'): ?>
                            <i class="bi bi-check-lg"></i>
                        <?php else: ?>
                            <i class="bi bi-check-circle"></i>
                        <?php endif; ?>
                    </div>
					<span class="sub-label" title="Approval">Approval</span>
                </div>
            </div>
        </div>

        <div class="main-line <?php echo $step_status['final_defense'] !== 'pending' ? 'filled' : ''; ?>"></div>

        <!-- FINAL DEFENSE SECTION -->
        <div class="main-step-section">
            <!-- Main Step: Final Defense -->
            <?php if ($can_access_final_defense): ?>
                <a href="final-defense.php<?php echo $project_param; ?>" class="main-step-link">
            <?php else: ?>
                <div class="main-step-link disabled" title="<?php echo $is_fourth_year ? 'Complete title defense first' : 'Available in 4th year'; ?>">
            <?php endif; ?>
                <div class="main-step <?php echo $step_status['final_defense'] === 'completed' ? 'completed' : ($display_step == 3 ? 'active' : ($can_access_final_defense ? '' : 'disabled')); ?>">
                    <div class="main-circle">
                        <?php if ($step_status['final_defense'] === 'completed'): ?>
                            <i class="bi bi-check-lg"></i>
                        <?php else: ?>
                            3
                        <?php endif; ?>
                    </div>
					<span class="main-label" title="Final Defense">Final Defense</span>
                </div>
            <?php if ($can_access_final_defense): ?>
                </a>
            <?php else: ?>
                </div>
            <?php endif; ?>
            
            <!-- Sub-steps -->
            <div class="sub-steps">
                <!-- Schedule -->
                <div title="Schedule" class="sub-step <?php echo $step_status['final_schedule'] === 'completed' ? 'completed' : ''; ?>">
                    <div class="sub-circle">
                        <?php if ($step_status['final_schedule'] === 'completed'): ?>
                            <i class="bi bi-check-lg"></i>
                        <?php else: ?>
                            <i class="bi bi-calendar"></i>
                        <?php endif; ?>
                    </div>
					<span class="sub-label" title="Schedule">Schedule</span>
                </div>
                
                <div class="mini-line <?php echo $step_status['final_approval'] !== 'pending' ? 'filled' : ''; ?>"></div>
                
                <!-- Final Approval -->
                <div title="Approval" class="sub-step <?php echo $step_status['final_approval'] === 'completed' ? 'completed' : ''; ?>">
                    <div class="sub-circle">
                        <?php if ($step_status['final_approval'] === 'completed'): ?>
                            <i class="bi bi-check-lg"></i>
                        <?php else: ?>
                            <i class="bi bi-check-circle"></i>
                        <?php endif; ?>
                    </div>
					<span class="sub-label" title="Approval">Approval</span>
                </div>
            </div>
        </div>

        <div class="main-line <?php echo $step_status['grammarian'] !== 'pending' ? 'filled' : ''; ?>"></div>

        <!-- GRAMMARIAN SECTION -->
        <div class="main-step-section">
            <!-- Main Step: Grammarian -->
            <?php if ($can_access_grammarian): ?>
                <a href="manuscript_upload.php<?php echo $project_param; ?>" class="main-step-link">
            <?php else: ?>
                <div class="main-step-link disabled" title="Complete final defense first">
            <?php endif; ?>
                <div class="main-step <?php echo $step_status['grammarian'] === 'completed' ? 'completed' : ($display_step == 4 ? 'active' : ($can_access_grammarian ? '' : 'disabled')); ?>">
                    <div class="main-circle">
                        <?php if ($step_status['grammarian'] === 'completed'): ?>
                            <i class="bi bi-check-lg"></i>
                        <?php else: ?>
                            4
                        <?php endif; ?>
                    </div>
					<span class="main-label" title="Grammarian">Grammarian</span>
                </div>
            <?php if ($can_access_grammarian): ?>
                </a>
            <?php else: ?>
                </div>
            <?php endif; ?>
            
            <!-- Sub-steps -->
            <div class="sub-steps">
                <!-- Grammarian Assignment -->
                <div title="Grammarian Assign" class="sub-step <?php echo $step_status['grammarian_assign'] === 'completed' ? 'completed' : ''; ?>">
                    <div class="sub-circle">
                        <?php if ($step_status['grammarian_assign'] === 'completed'): ?>
                            <i class="bi bi-check-lg"></i>
                        <?php else: ?>
                            <i class="bi bi-person-plus"></i>
                        <?php endif; ?>
                    </div>
					<span class="sub-label" title="Grammarian Assign">Grammarian Assign</span>
                </div>
                
                <div class="mini-line <?php echo $step_status['grammarian_approval'] !== 'pending' ? 'filled' : ''; ?>"></div>
                
                <!-- Grammarian Approval -->
                <div title="Approval" class="sub-step <?php echo $step_status['grammarian_approval'] === 'completed' ? 'completed' : ''; ?>">
                    <div class="sub-circle">
                        <?php if ($step_status['grammarian_approval'] === 'completed'): ?>
                            <i class="bi bi-check-lg"></i>
                        <?php else: ?>
                            <i class="bi bi-check-circle"></i>
                        <?php endif; ?>
                    </div>
					<span class="sub-label" title="Approval">Approval</span>
                </div>
            </div>
        </div>

        <div class="main-line <?php echo $step_status['submit_manuscript'] !== 'pending' ? 'filled' : ''; ?>"></div>

        <!-- SUBMIT MANUSCRIPT SECTION -->
        <div class="main-step-section">
            <!-- Main Step: Submit Manuscript -->
            <?php if ($can_access_submit_manuscript): ?>
                <a href="submit_manuscript.php<?php echo $project_param; ?>" class="main-step-link">
            <?php else: ?>
                <div class="main-step-link disabled" title="Complete grammarian approval first">
            <?php endif; ?>
                <div class="main-step <?php echo $step_status['submit_manuscript'] === 'completed' ? 'completed' : ($display_step == 5 ? 'active' : ($can_access_submit_manuscript ? '' : 'disabled')); ?>">
                    <div class="main-circle">
                        <?php if ($step_status['submit_manuscript'] === 'completed'): ?>
                            <i class="bi bi-check-lg"></i>
                        <?php else: ?>
                            5
                        <?php endif; ?>
                    </div>
					<span class="main-label" title="Submit Manuscript">Submit Manuscript</span>
                </div>
            <?php if ($can_access_submit_manuscript): ?>
                </a>
            <?php else: ?>
                </div>
            <?php endif; ?>
        </div>
        
    </div>
</div>

<style>
/* Container */
.progress-bar-container {
    padding: 15px 0;
    background: #fff;
    border-bottom: 1px solid #eee;
    margin-bottom: 20px;
    overflow-x: auto;
}

/* Step link wrapper */
.step-link {
    text-decoration: none;
    color: inherit;
    display: flex;
    flex-direction: column;
    align-items: center;
    transition: transform 0.2s ease;
}

.step-link:hover {
    transform: translateY(-2px);
}

/* Disabled step link */
.step-link.disabled {
    cursor: not-allowed;
    opacity: 1;
}

.step-link.disabled:hover {
    transform: none;
}

/* Flex container for steps */
.progress-steps {
    display: flex;
    justify-content: center;
    align-items: flex-start;
    max-width: 1200px;
    margin: 0 auto;
    gap: 20px;
    opacity: 0;
    transform: translateY(-10px);
    animation: fadeInUp 0.6s ease-out forwards;
}

/* Main step section */
.main-step-section {
    display: flex;
    flex-direction: column;
    align-items: center;
    min-width: 150px;
}

/* Main step link */
.main-step-link {
    text-decoration: none;
    color: inherit;
    display: flex;
    flex-direction: column;
    align-items: center;
    transition: transform 0.2s ease;
}

.main-step-link:hover {
    transform: translateY(-2px);
}

.main-step-link.disabled {
    cursor: not-allowed;
    opacity: 0.6;
}

.main-step-link.disabled:hover {
    transform: none;
}

/* Main step */
.main-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    color: #aaa;
    font-size: 12px;
    transition: color 0.3s ease;
    position: relative;
}

.main-circle {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    background-color: #e0e0e0;
    color: #555;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 14px;
    margin-bottom: 5px;
    transition: all 0.4s ease;
    border: 2px solid transparent;
}

.main-label {
    text-align: center;
    white-space: nowrap;
    transition: color 0.3s;
    font-weight: 600;
    font-size: 12px;
    max-width: 120px;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Sub-steps container */
.sub-steps {
    display: flex;
    align-items: center;
    gap: 5px;
    margin-top: 10px;
}

/* Sub-step */
.sub-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    color: #999;
    font-size: 10px;
    transition: all 0.3s ease;
    position: relative;
}

.sub-circle {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background-color: #f0f0f0;
    color: #999;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    margin-bottom: 2px;
    transition: all 0.3s ease;
    border: 1px solid transparent;
}

.sub-label {
    text-align: center;
    white-space: nowrap;
    font-weight: 500;
    font-size: 10px;
    max-width: 60px;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Mini line between sub-steps */
.mini-line {
    height: 2px;
    width: 15px;
    background-color: #ddd;
    position: relative;
    overflow: hidden;
    border-radius: 1px;
}

.mini-line.filled::after {
    content: '';
    position: absolute;
    height: 100%;
    width: 100%;
    background: linear-gradient(90deg, #4CAF50, #66BB6A);
    animation: fillLine 0.6s ease forwards;
}

/* Main line between main steps */
.main-line {
    height: 3px;
    flex: 1;
    background-color: #eee;
    position: relative;
    overflow: hidden;
    border-radius: 2px;
    margin-top: 18px;
}

.main-line.filled::after {
    content: '';
    position: absolute;
    height: 100%;
    width: 100%;
    background: linear-gradient(90deg, #4CAF50, #66BB6A);
    animation: fillLine 0.8s ease forwards;
}


/* Main step states */
.main-step.active .main-circle {
    background-color: #667eea;
    color: #fff;
    transform: scale(1.1);
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.3);
    animation: pulse 2s infinite;
}

.main-step.active {
    color: #667eea;
}

.main-step.completed .main-circle {
    background-color: #4CAF50;
    color: #fff;
    border-color: #4CAF50;
}

.main-step.completed {
    color: #4CAF50;
}

.main-step.disabled .main-circle {
    background-color: #f5f5f5;
    color: #ccc;
    border-color: #e0e0e0;
}

.main-step.disabled {
    color: #ccc;
}

/* Sub-step states */
.sub-step.completed .sub-circle {
    background-color: #4CAF50;
    color: #fff;
    border-color: #4CAF50;
}

.sub-step.completed {
    color: #4CAF50;
}

.sub-step.in-progress .sub-circle {
    background-color: #ff9800;
    color: #fff;
    border-color: #ff9800;
}

.sub-step.in-progress {
    color: #ff9800;
}


/* Status text for in-progress items */
.status-text {
    font-size: 10px;
    margin-top: 2px;
    font-style: italic;
    opacity: 0.8;
}

/* Animations */
@keyframes fadeInUp {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes fillLine {
    from {
        width: 0%;
    }
    to {
        width: 100%;
    }
}

@keyframes pulse {
    0% {
        box-shadow: 0 0 0 0 rgba(102, 126, 234, 0.4);
    }
    70% {
        box-shadow: 0 0 0 8px rgba(102, 126, 234, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(102, 126, 234, 0);
    }
}


/* Responsive design */
@media (max-width: 1024px) {
    .progress-steps {
        gap: 8px;
    }
    
    .main-step-section {
        min-width: 100px;
    }
    
    .main-circle {
        width: 32px;
        height: 32px;
        font-size: 12px;
    }
    
    .main-label {
        font-size: 10px;
        max-width: 100px;
    }
    
    .sub-circle {
        width: 18px;
        height: 18px;
        font-size: 9px;
    }
    
    .sub-label {
        font-size: 9px;
        max-width: 50px;
    }
}

@media (max-width: 768px) {
    .progress-steps {
        gap: 3px;
    }
    
    .main-step-section {
        min-width: 60px;
    }
    
    .sub-steps {
        gap: 2px;
    }
    
    .sub-circle {
        width: 14px;
        height: 14px;
        font-size: 7px;
    }
    
    .sub-label {
        font-size: 7px;
        max-width: 30px;
    }
    
    .main-circle {
        width: 24px;
        height: 24px;
        font-size: 8px;
    }
    
    .main-label {
        font-size: 8px;
        max-width: 60px;
    }
    
    .main-line {
        height: 2px;
        margin-top: 12px;
    }
    
    .mini-line {
        width: 8px;
    }
}

@media (max-width: 480px) {
    .progress-bar-container {
        padding: 4px 0;
    }
    
    .progress-steps {
        gap: 2px;
    }
    
    .sub-steps {
        gap: 1px;
    }
    
    .sub-circle {
        width: 12px;
        height: 12px;
        font-size: 6px;
    }
    
    .sub-label {
        font-size: 6px;
        max-width: 25px;
    }
    
    .main-circle {
        width: 20px;
        height: 20px;
        font-size: 6px;
    }
    
    .main-label {
        font-size: 7px;
        max-width: 50px;
    }
    
    .main-line {
        height: 1px;
        margin-top: 10px;
    }
    
    .mini-line {
        width: 6px;
    }
}
</style>

<script>
// Lightweight mobile tooltip for truncated labels
(function() {
	function showTooltipFor(labelEl) {
		var container = labelEl.closest('.sub-step, .main-step') || labelEl.parentElement;
		if (!container) return;
		// Remove existing tooltip if any
		var existing = container.querySelector('.tooltip-bubble');
		if (existing) existing.remove();
		var text = labelEl.getAttribute('title') || labelEl.textContent;
		var bubble = document.createElement('div');
		bubble.className = 'tooltip-bubble';
		bubble.textContent = text;
		container.appendChild(bubble);
		// Trigger fade-in
		requestAnimationFrame(function(){ bubble.classList.add('visible'); });
		// Auto-hide
		setTimeout(function(){
			bubble.classList.remove('visible');
			setTimeout(function(){ bubble.remove(); }, 200);
		}, 1600);
	}

	function attachTooltipHandlers() {
		var labels = document.querySelectorAll('.progress-bar-container .sub-label, .progress-bar-container .main-label');
		labels.forEach(function(lbl) {
			// Tap/click
			lbl.addEventListener('click', function(e){ showTooltipFor(lbl); });
			lbl.addEventListener('touchstart', function(e){ showTooltipFor(lbl); }, { passive: true });
			// Keyboard focus
			lbl.addEventListener('focus', function(){ showTooltipFor(lbl); });
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', attachTooltipHandlers);
	} else {
		attachTooltipHandlers();
	}
})();
</script>

<style>
/* Tooltip bubble for touch devices */
.tooltip-bubble {
	position: absolute;
	bottom: 100%;
	left: 50%;
	transform: translateX(-50%) translateY(-6px);
	background: rgba(33, 33, 33, 0.95);
	color: #fff;
	font-size: 11px;
	line-height: 1.2;
	padding: 4px 6px;
	border-radius: 4px;
	white-space: nowrap;
	pointer-events: none;
	opacity: 0;
	transition: opacity 0.2s ease;
	z-index: 1000;
}

.tooltip-bubble.visible {
	opacity: 1;
}
</style>