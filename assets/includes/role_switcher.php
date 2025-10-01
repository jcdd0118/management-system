<?php
require_once __DIR__ . '/role_functions.php';

if (!isset($_SESSION['user_data'])) {
    return;
}

$currentUser = $_SESSION['user_data'];
$availableRoles = getUserRoles($currentUser);
$currentRole = isset($_SESSION['active_role']) ? $_SESSION['active_role'] : getPrimaryRole($currentUser);

// Debug output (remove after fixing)
echo "<!-- DEBUG: Available roles: " . implode(', ', $availableRoles) . " -->";
echo "<!-- DEBUG: Current role: " . $currentRole . " -->";
echo "<!-- DEBUG: User data: " . print_r($currentUser, true) . " -->";

if (count($availableRoles) > 1): ?>
<div class="role-switcher mb-3">
    <div class="card">
        <div class="card-body py-2">
            <div class="mb-2 d-flex align-items-center">
                <i class="bi bi-person-badge me-2 text-primary"></i>
                <small class="text-muted current-role-label m-0">Current Role:</small>
            </div>
            <div class="dropdown">
                <button class="btn btn-outline-primary btn-sm dropdown-toggle" type="button" id="roleSwitcher" data-bs-toggle="dropdown" aria-expanded="false">
                    <?php echo getRoleDisplayName($currentRole); ?>
                </button>
                <ul class="dropdown-menu" aria-labelledby="roleSwitcher">
                    <?php foreach ($availableRoles as $role): ?>
                        <li>
                            <a class="dropdown-item <?php echo $role === $currentRole ? 'active' : ''; ?>" 
                               href="../assets/includes/switch_role.php?role=<?php echo urlencode($role); ?>&redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>"
                               onclick="console.log('Switching to role: <?php echo $role; ?>');">
                                <?php echo getRoleDisplayName($role); ?>
                                <!-- Debug info -->
                                <small class="text-muted">(<?php echo $role; ?>)</small>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
<!-- DEBUG: Only one role available -->
<div class="alert alert-warning">Only one role: <?php echo implode(', ', $availableRoles); ?></div>
<?php endif; ?>