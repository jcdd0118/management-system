<?php
// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection and role functions
require_once '../config/database.php';
require_once '../assets/includes/role_functions.php';
require_once '../assets/includes/year_section_functions.php';

$current_page = basename($_SERVER['PHP_SELF']);
$role = isset($_SESSION['role']) ? $_SESSION['role'] : 'guest';
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$year_section = isset($_SESSION['year_section']) ? $_SESSION['year_section'] : '';
$currentUser = isset($_SESSION['user_data']) ? $_SESSION['user_data'] : null;
$activeRole = isset($_SESSION['active_role']) ? $_SESSION['active_role'] : $role;

// Determine the logo link based on role
$logo_link = ($role === 'admin') ? 'dashboard.php' : 'home.php';

// Get latest project ID for the student's group (so sidebar passes ?project_id=<id>)
$group_latest_project_id = null;
if ($role === 'student' && isset($_SESSION['email'])) {
    $email = $_SESSION['email'];
    $projectQuery = "
        SELECT pw.id
        FROM project_working_titles pw
        WHERE (
            pw.submitted_by IN (
                SELECT s2.email FROM students s2
                WHERE s2.group_code = (
                    SELECT s.group_code FROM students s WHERE s.email = ? LIMIT 1
                )
            ) OR pw.submitted_by = ?
        )
        ORDER BY pw.id DESC
        LIMIT 1
    ";
    $stmt = $conn->prepare($projectQuery);
    if ($stmt) {
        $stmt->bind_param("ss", $email, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $group_latest_project_id = $row['id'];
        }
        $stmt->close();
    }
}

// Determine Submit Project link
$submit_project_link = '/management_system/student/submit_research.php';
if ($group_latest_project_id) {
    $submit_project_link .= '?project_id=' . $group_latest_project_id;
}

?>

<!-- Sidebar (Offcanvas for mobile, fixed on desktop) -->
<div class="offcanvas-lg offcanvas-start sidebar" tabindex="-1" id="sidebarMenu" data-bs-backdrop="false">
    <div class="sidebar-header">
        <button class="btn d-block d-lg-none ms-3" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" data-bs-backdrop="false">
            <i class="bi bi-x fs-3"></i>
        </button>
        <a href="<?php echo $logo_link; ?>" style="text-decoration: none;">
            <img src="../assets/img/captrack.png" alt="Captrack Vault" class="img-fluid">
            <div class="logo-label">CapTrack Vault</div>
        </a>
    </div>
    
    <!-- Role Switcher - Show if user has multiple roles -->
    <?php if ($currentUser && count(getUserRoles($currentUser)) > 1): ?>
        <div class="px-3 pt-3">
            <?php include '../assets/includes/role_switcher.php'; ?>
        </div>
    <?php endif; ?>
    
    <div class="sidebar-nav">
    <?php if ($role === 'student'): ?>
        <?php 
        // Check if student is in 4th year (any 4th year section)
        $is_fourth_year = false;
        if (!empty($year_section)) {
            $year_section_data = getYearSectionByCode($conn, $year_section);
            $is_fourth_year = ($year_section_data && $year_section_data['year_level'] == 4);
        }
        ?>
        <?php if ($is_fourth_year): ?>
            <!-- Visible only to 4A and 4B -->
            <a href="/management_system/student/home.php" class="text-decoration-none <?= ($current_page == 'home.php') ? 'active' : '' ?>">
                <div class="nav-item"><i class="bi bi-house-door"></i><span>Home</span></div>
            </a>
            <a href="<?php echo $submit_project_link; ?>" class="text-decoration-none <?= ($current_page == 'submit_research.php') ? 'active' : '' ?>">
                <div class="nav-item"><i class="bi bi-upload"></i><span>Submit Project</span></div>
            </a>
            <a href="/management_system/student/research_repository.php" class="text-decoration-none <?= ($current_page == 'research_repository.php') ? 'active' : '' ?>">
                <div class="nav-item"><i class="bi bi-journal-bookmark"></i><span>Research Repository</span></div>
            </a>
            <a href="/management_system/student/submit_manuscript.php" class="text-decoration-none <?= ($current_page == 'submit_manuscript.php') ? 'active' : '' ?>">
                <div class="nav-item"><i class="bi bi-file-earmark-arrow-up"></i><span>Submit Manuscript</span></div>
            </a>
            <a href="/management_system/student/my_projects.php" class="text-decoration-none <?= ($current_page == 'my_projects.php') ? 'active' : '' ?>">
                <div class="nav-item"><i class="bi bi-folder-check"></i><span>My Submissions</span></div>
            </a>
        <?php else: ?>
            <!-- For other sections -->
            <a href="/management_system/student/home.php" class="text-decoration-none <?= ($current_page == 'home.php') ? 'active' : '' ?>">
                <div class="nav-item"><i class="bi bi-house-door"></i><span>Home</span></div>
            </a>
            <a href="<?php echo $submit_project_link; ?>" class="text-decoration-none <?= ($current_page == 'submit_research.php') ? 'active' : '' ?>">
                <div class="nav-item"><i class="bi bi-upload"></i><span>Submit Project</span></div>
            </a>
            <a href="/management_system/student/research_repository.php" class="text-decoration-none <?= ($current_page == 'research_repository.php') ? 'active' : '' ?>">
                <div class="nav-item"><i class="bi bi-journal-bookmark"></i><span>Research Repository</span></div>
            </a>
        <?php endif; ?>

        <div class="nav-item expandable" onclick="toggleSubmenu(this)">
            <i class="bi bi-gear"></i><span>Settings</span>
            <i class="bi bi-chevron-right toggle-icon"></i>
        </div>
        <div class="submenu">
            <a href="/management_system/users/profile.php" class="text-decoration-none <?= ($current_page == 'profile.php') ? 'active' : '' ?>" onclick="stayOpen(event)">
                <div class="nav-item"><i class="bi bi-person"></i><span>Profile</span></div>
            </a>
        </div>

    <?php elseif ($currentUser): ?>
        <!-- Multi-Role Navigation -->
        
        <!-- Admin Functions -->
        <?php if (hasRole($currentUser, 'admin')): ?>
            <div class="nav-section <?php echo $activeRole === 'admin' ? 'active-section' : ''; ?>">
                <div class="nav-section-title">Administration</div>
                <a href="<?php echo ($activeRole !== 'admin'
                    ? '/management_system/assets/includes/switch_role.php?role=admin&next=' . urlencode('/management_system/admin/dashboard.php')
                    : '/management_system/admin/dashboard.php'); ?>" class="text-decoration-none <?= ($current_page == 'dashboard.php' && $activeRole === 'admin') ? 'active' : '' ?>">
                    <div class="nav-item"><i class="bi bi-speedometer2"></i><span>Dashboard</span></div>
                </a>
                <a href="<?php echo ($activeRole !== 'admin'
                    ? '/management_system/assets/includes/switch_role.php?role=admin&next=' . urlencode('/management_system/admin/research_list.php')
                    : '/management_system/admin/research_list.php'); ?>" class="text-decoration-none <?= ($current_page == 'research_list.php') ? 'active' : '' ?>">
                    <div class="nav-item"><i class="bi bi-journal-text"></i><span>Research List</span></div>
                </a>
                <a href="<?php echo ($activeRole !== 'admin'
                    ? '/management_system/assets/includes/switch_role.php?role=admin&next=' . urlencode('/management_system/admin/year_sections.php')
                    : '/management_system/admin/year_sections.php'); ?>" class="text-decoration-none <?= ($current_page == 'year_sections.php') ? 'active' : '' ?>">
                    <div class="nav-item"><i class="bi bi-calendar3"></i><span>Year Sections</span></div>
                </a>
                <div class="nav-item expandable" onclick="toggleSubmenu(this)">
                    <i class="bi bi-gear"></i><span>User List</span>
                    <i class="bi bi-chevron-right toggle-icon"></i>
                </div>
                <div class="submenu">
                    <a href="<?php echo ($activeRole !== 'admin'
                        ? '/management_system/assets/includes/switch_role.php?role=admin&next=' . urlencode('/management_system/admin/users.php')
                        : '/management_system/admin/users.php'); ?>" class="text-decoration-none <?= ($current_page == 'users.php') ? 'active' : '' ?>">
                        <div class="nav-item"><i class="bi bi-people"></i><span>Manage Users</span></div>
                    </a>
                    <a href="<?php echo ($activeRole !== 'admin'
                        ? '/management_system/assets/includes/switch_role.php?role=admin&next=' . urlencode('/management_system/admin/add_user.php')
                        : '/management_system/admin/add_user.php'); ?>" class="text-decoration-none <?= ($current_page == 'add_user.php') ? 'active' : '' ?>">
                        <div class="nav-item"><i class="bi bi-person-add"></i><span>Add User</span></div>
                    </a>
                    <a href="<?php echo ($activeRole !== 'admin'
                        ? '/management_system/assets/includes/switch_role.php?role=admin&next=' . urlencode('/management_system/admin/student_list.php')
                        : '/management_system/admin/student_list.php'); ?>" class="text-decoration-none <?= ($current_page == 'student_list.php') ? 'active' : '' ?>">
                        <div class="nav-item"><i class="bi bi-people-fill"></i><span>Student List</span></div>
                    </a>
                </div>

            </div>
        <?php endif; ?>

        <!-- Dean Functions -->
        <?php if (hasRole($currentUser, 'dean')): ?>
            <div class="nav-section <?php echo $activeRole === 'dean' ? 'active-section' : ''; ?>">
                <div class="nav-section-title">Dean Functions</div>
                <a href="/management_system/dean/home.php" class="text-decoration-none <?= ($current_page == 'home.php' && $activeRole === 'dean') ? 'active' : '' ?>">
                    <div class="nav-item"><i class="bi bi-house-door"></i><span>Dashboard</span></div>
                </a>
                <a href="/management_system/dean/review_project.php" class="text-decoration-none <?= ($current_page == 'review_project.php' && $activeRole === 'dean') ? 'active' : '' ?>">
                    <div class="nav-item"><i class="bi bi-check2-square"></i><span>Review Project</span></div>
                </a>
                <a href="/management_system/dean/assign_technical_adviser.php" class="text-decoration-none <?= ($current_page == 'assign_technical_adviser.php') ? 'active' : '' ?>">
                    <div class="nav-item"><i class="bi bi-person-plus"></i><span>Assign Capstone Adviser</span></div>
                </a>
                <a href="/management_system/dean/assign_panel.php" class="text-decoration-none <?= ($current_page == 'assign_panel.php') ? 'active' : '' ?>">
                    <div class="nav-item"><i class="bi bi-people-fill"></i><span>Assign Panel</span></div>
                </a>
                <a href="/management_system/dean/assign_grammarian.php" class="text-decoration-none <?= ($current_page == 'assign_grammarian.php') ? 'active' : '' ?>">
                    <div class="nav-item"><i class="bi bi-pencil-square"></i><span>Assign Grammarian</span></div>
                </a>
                <a href="/management_system/users/research_repository.php" class="text-decoration-none <?= ($current_page == 'research_repository.php' && $activeRole === 'dean') ? 'active' : '' ?>">
                    <div class="nav-item"><i class="bi bi-journal-bookmark"></i><span>Research Repository</span></div>
                </a>
            </div>
        <?php endif; ?>

        <!-- Adviser Functions -->
        <?php if (hasRole($currentUser, 'adviser')): ?>
            <div class="nav-section <?php echo $activeRole === 'adviser' ? 'active-section' : ''; ?>">
                <div class="nav-section-title">Adviser Functions</div>
                <a href="/management_system/adviser/home.php" class="text-decoration-none <?= ($current_page == 'home.php' && $activeRole === 'adviser') ? 'active' : '' ?>">
                    <div class="nav-item"><i class="bi bi-house-door"></i><span>Dashboard</span></div>
                </a>
                <a href="/management_system/adviser/review_project.php" class="text-decoration-none <?= ($current_page == 'review_project.php' && $activeRole === 'adviser') ? 'active' : '' ?>">
                    <div class="nav-item"><i class="bi bi-check2-circle"></i><span>Review Project</span></div>
                </a>
                <a href="/management_system/adviser/title_defense_list.php" class="text-decoration-none <?= ($current_page == 'title_defense_list.php') ? 'active' : '' ?>">
                    <div class="nav-item"><i class="bi bi-easel2"></i><span>Title Defense</span></div>
                </a>
                <a href="/management_system/adviser/final_defense_list.php" class="text-decoration-none <?= ($current_page == 'final_defense_list.php') ? 'active' : '' ?>">
                    <div class="nav-item"><i class="bi bi-patch-check"></i><span>Final Defense</span></div>
                </a>
                <a href="/management_system/adviser/student_list.php" class="text-decoration-none <?= ($current_page == 'student_list.php') ? 'active' : '' ?>">
                    <div class="nav-item"><i class="bi bi-people"></i><span>Student List</span></div>
                </a>
                <a href="/management_system/users/research_repository.php" class="text-decoration-none <?= ($current_page == 'research_repository.php' && $activeRole === 'adviser') ? 'active' : '' ?>">
                    <div class="nav-item"><i class="bi bi-journal-bookmark"></i><span>Research Repository</span></div>
                </a>
            </div>
        <?php endif; ?>

        <!-- Faculty Functions -->
        <?php if (hasRole($currentUser, 'faculty')): ?>
            <div class="nav-section <?php echo $activeRole === 'faculty' ? 'active-section' : ''; ?>">
                <div class="nav-section-title">Faculty Functions</div>
                <a href="/management_system/faculty/home.php" class="text-decoration-none <?= ($current_page == 'home.php' && $activeRole === 'faculty') ? 'active' : '' ?>">
                    <div class="nav-item"><i class="bi bi-house-door"></i><span>Dashboard</span></div>
                </a>
                <a href="/management_system/faculty/review_project.php" class="text-decoration-none <?= ($current_page == 'review_project.php' && $activeRole === 'faculty') ? 'active' : '' ?>">
                    <div class="nav-item"><i class="bi bi-check2-square"></i><span>Review Project</span></div>
                </a>
                <a href="/management_system/faculty/my_advisory.php" class="text-decoration-none <?= ($current_page == 'my_advisory.php') ? 'active' : '' ?>">
                    <div class="nav-item"><i class="bi bi-person-badge"></i><span>My Advisory</span></div>
                </a>
                <a href="/management_system/users/research_repository.php" class="text-decoration-none <?= ($current_page == 'research_repository.php' && $activeRole === 'faculty') ? 'active' : '' ?>">
                    <div class="nav-item"><i class="bi bi-journal-bookmark"></i><span>Research Repository</span></div>
                </a>
            </div>
        <?php endif; ?>

        <!-- Panelist Functions -->
        <?php if (hasRole($currentUser, 'panelist')): ?>
            <div class="nav-section <?php echo $activeRole === 'panelist' ? 'active-section' : ''; ?>">
                <div class="nav-section-title">Panel Functions</div>
                <a href="/management_system/panel/home.php" class="text-decoration-none <?= ($current_page == 'home.php' && $activeRole === 'panelist') ? 'active' : '' ?>">
                    <div class="nav-item"><i class="bi bi-house-door"></i><span>Dashboard</span></div>
                </a>
                <a href="/management_system/panel/title_defense_list.php" class="text-decoration-none <?= ($current_page == 'title_defense_list.php' && $activeRole === 'panelist') ? 'active' : '' ?>">
                    <div class="nav-item"><i class="bi bi-easel2"></i><span>Title Defense</span></div>
                </a>
                <a href="/management_system/panel/final_defense_list.php" class="text-decoration-none <?= ($current_page == 'final_defense_list.php' && $activeRole === 'panelist') ? 'active' : '' ?>">
                    <div class="nav-item"><i class="bi bi-patch-check"></i><span>Final Defense</span></div>
                </a>
                <a href="/management_system/users/research_repository.php" class="text-decoration-none <?= ($current_page == 'research_repository.php' && $activeRole === 'panelist') ? 'active' : '' ?>">
                    <div class="nav-item"><i class="bi bi-journal-bookmark"></i><span>Research Repository</span></div>
                </a>
            </div>
        <?php endif; ?>

        <!-- Grammarian Functions -->
        <?php if (hasRole($currentUser, 'grammarian')): ?>
            <div class="nav-section <?php echo $activeRole === 'grammarian' ? 'active-section' : ''; ?>">
                <div class="nav-section-title">Grammarian Functions</div>
                <a href="/management_system/grammarian/home.php" class="text-decoration-none <?= ($current_page == 'home.php' && $activeRole === 'grammarian') ? 'active' : '' ?>">
                    <div class="nav-item"><i class="bi bi-house-door"></i><span>Dashboard</span></div>
                </a>
                <a href="/management_system/grammarian/home.php?status=pending" class="text-decoration-none <?= ($current_page == 'home.php' && $activeRole === 'grammarian' && isset($_GET['status']) && $_GET['status'] === 'pending') ? 'active' : '' ?>">
                    <div class="nav-item"><i class="bi bi-pencil-square"></i><span>Pending Reviews</span></div>
                </a>
                <a href="/management_system/users/research_repository.php" class="text-decoration-none <?= ($current_page == 'research_repository.php' && $activeRole === 'grammarian') ? 'active' : '' ?>">
                    <div class="nav-item"><i class="bi bi-journal-bookmark"></i><span>Research Repository</span></div>
                </a>
            </div>
        <?php endif; ?>

        <!-- Settings Section -->
        <div class="nav-item expandable" onclick="toggleSubmenu(this)">
            <i class="bi bi-gear"></i><span>Settings</span>
            <i class="bi bi-chevron-right toggle-icon"></i>
        </div>
        <div class="submenu">
            <a href="/management_system/users/profile.php" class="text-decoration-none <?= ($current_page == 'profile.php') ? 'active' : '' ?>" onclick="stayOpen(event)">
                <div class="nav-item"><i class="bi bi-person"></i><span>Profile</span></div>
            </a>
        </div>

    <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Restore sidebar scroll position
    const sidebarNav = document.querySelector('.sidebar-nav');
    const SCROLL_KEY = 'sidebar:scrollTop';
    try {
        const savedScroll = localStorage.getItem(SCROLL_KEY);
        if (sidebarNav && savedScroll !== null) {
            sidebarNav.scrollTop = parseInt(savedScroll, 10) || 0;
        }
    } catch (e) {
        // Ignore storage errors
    }

    // Ensure submenus stay open and chevrons are updated if the current page matches one of the submenu links
    const currentPage = window.location.pathname;
    const submenuLinks = document.querySelectorAll('.submenu a');

    submenuLinks.forEach(function(link) {
        if (link.href.includes(currentPage)) {
            // Open the corresponding submenu and rotate chevron if this link is the current page
            const submenu = link.closest('.submenu');
            submenu.classList.add('show');
            const expandable = submenu.previousElementSibling;
            const toggleIcon = expandable.querySelector('.toggle-icon');
            toggleIcon.classList.remove('bi-chevron-right');
            toggleIcon.classList.add('bi-chevron-down');
        }
    });

    // Function to toggle submenu visibility and chevron icon
    window.toggleSubmenu = function(element) {
        console.log('toggleSubmenu called with:', element);
        
        if (!element) {
            console.error('No element provided to toggleSubmenu');
            return;
        }
        
        const submenu = element.nextElementSibling;
        const toggleIcon = element.querySelector('.toggle-icon');
        
        if (!submenu || !toggleIcon) {
            console.error('Submenu or toggle icon not found');
            return;
        }
        
        const isActive = submenu.classList.contains('show');

        // Close all other submenus and reset their chevrons
        const allSubmenus = document.querySelectorAll('.submenu');
        const allExpandables = document.querySelectorAll('.expandable');
        allSubmenus.forEach(function(sub) {
            sub.classList.remove('show');
        });
        allExpandables.forEach(function(exp) {
            const icon = exp.querySelector('.toggle-icon');
            if (icon) {
                icon.classList.remove('bi-chevron-down');
                icon.classList.add('bi-chevron-right');
            }
        });

        // Toggle the clicked submenu and its chevron
        if (!isActive) {
            submenu.classList.add('show');
            toggleIcon.classList.remove('bi-chevron-right');
            toggleIcon.classList.add('bi-chevron-down');
        }
        
        console.log('Submenu toggled successfully');
    }

    // Prevent closing submenu when clicking on a submenu link
    window.stayOpen = function(event) {
        event.stopPropagation();
        const submenu = event.target.closest('.submenu');
        submenu.classList.add('show');
        const expandable = submenu.previousElementSibling;
        const toggleIcon = expandable.querySelector('.toggle-icon');
        toggleIcon.classList.remove('bi-chevron-right');
        toggleIcon.classList.add('bi-chevron-down');
    }

    // Bind stayOpen to each submenu link
    submenuLinks.forEach(function(link) {
        link.addEventListener('click', stayOpen);
    });

    // Save sidebar scroll position before navigating away
    window.addEventListener('beforeunload', function() {
        try {
            if (sidebarNav) {
                localStorage.setItem(SCROLL_KEY, String(sidebarNav.scrollTop));
            }
        } catch (e) {
            // Ignore storage errors
        }
    });
});
</script>