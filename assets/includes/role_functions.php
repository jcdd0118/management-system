<?php
/**
 * Multi-Role Management Functions for Captrack Vault
 */

/**
 * Check if user has a specific role
 */
function hasRole($user, $role) {
    if (empty($user)) {
        return false;
    }
    
    $targetRole = strtolower(trim($role));
    
    // Check primary role
    if (isset($user['role']) && strtolower(trim($user['role'])) === $targetRole) {
        return true;
    }
    
    // Check roles field
    if (isset($user['roles']) && !empty($user['roles'])) {
        $userRoles = array_map('trim', array_map('strtolower', explode(',', $user['roles'])));
        return in_array($targetRole, $userRoles);
    }
    
    return false;
}

/**
 * Check if user has any of the specified roles
 */
function hasAnyRole($user, $roles) {
    if (!is_array($roles)) {
        $roles = [$roles];
    }
    
    foreach ($roles as $role) {
        if (hasRole($user, $role)) {
            return true;
        }
    }
    return false;
}

/**
 * Get all roles for a user as an array
 */
function getUserRoles($user) {
    if (empty($user)) {
        return [];
    }
    
    $roles = [];
    
    // Add primary role
    if (isset($user['role']) && !empty($user['role'])) {
        $roles[] = strtolower(trim($user['role']));
    }
    
    // Add additional roles from roles field
    if (isset($user['roles']) && !empty($user['roles'])) {
        $additionalRoles = array_map('trim', array_map('strtolower', explode(',', $user['roles'])));
        foreach ($additionalRoles as $role) {
            if (!empty($role) && !in_array($role, $roles)) {
                $roles[] = $role;
            }
        }
    }
    
    return array_unique($roles);
}

/**
 * Get primary role (first role or role field)
 */
function getPrimaryRole($user) {
    if (empty($user)) {
        return 'faculty';
    }
    
    if (isset($user['role']) && !empty($user['role'])) {
        return strtolower(trim($user['role']));
    }
    
    $roles = getUserRoles($user);
    return !empty($roles) ? $roles[0] : 'faculty';
}

/**
 * Get role display name
 */
function getRoleDisplayName($role) {
    $roleNames = [
        'admin' => 'Administrator',
        'dean' => 'Dean',
        'adviser' => 'Capstone Professor', 
        'faculty' => 'Capstone Adviser',
        'panelist' => 'Panelist',
        'grammarian' => 'Grammarian',
        'student' => 'Student'
    ];
    
    return isset($roleNames[strtolower($role)]) ? $roleNames[strtolower($role)] : ucfirst($role);
}
?>
