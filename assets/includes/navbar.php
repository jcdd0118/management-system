<?php
// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Unknown User';
$email = isset($_SESSION['email']) ? $_SESSION['email'] : 'No Email';
$avatar = isset($_SESSION['avatar']) && $_SESSION['avatar'] !== 'avatar.png'
    ? '../assets/img/avatar/' . $_SESSION['avatar']
    : '../assets/img/avatar.png';
?>
<nav class="navbar navbar-expand-lg navbar-custom mb-4 p-2">
    <div class="container-fluid">
        <!-- MOBILE: Top row (Burger, Date/Time, Bookmarks, Bell, Avatar) -->
        <div class="d-flex d-md-none justify-content-between align-items-center w-100">
            <!-- Left: Burger and Date/Time -->
            <div class="d-flex align-items-center mobile-left">
                <!-- Burger -->
                <button class="btn me-2 burger-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" data-bs-backdrop="false">
                    <i class="bi bi-list fs-3"></i>
                </button>
                <!-- Date/Time -->
                <div class="text-white d-flex align-items-center date-time">
                    <span class="small fw-bold me-2" id="currentDate-mobile"></span>
                    <span class="small" id="currentTime-mobile"></span>
                </div>
            </div>
            <!-- Right: Bookmarks + Bell + Avatar -->
            <div class="d-flex align-items-center">
                <?php if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'): ?>
                    <div class="dropdown position-relative me-2">
                        <a class="bookmark-icon" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false" title="Bookmarks" data-bs-toggle="tooltip">
                            <i class="bi bi-bookmark-fill fs-5 text-white"></i>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li class="dropdown-header">My Bookmarks</li>
                            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'student'): ?>
                                <li><a class="dropdown-item" href="../student/bookmark.php">View bookmarked research</a></li>
                            <?php else: ?>
                                <li><a class="dropdown-item" href="../users/bookmark.php">View bookmarked research</a></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <!-- SHARED NOTIFICATION SECTION -->
                <div class="notification-wrapper me-2">
                    <div class="notification-icon" onclick="toggleNotifications(event)">
                        <i class="bi bi-bell-fill fs-5 text-white"></i>
                        <span class="notification-badge" style="display: none;">0</span>
                    </div>
                </div>
                
                <div class="dropdown dropdown-avatar">
                    <div class="avatar" data-bs-toggle="dropdown" aria-expanded="false" title="Profile" data-bs-toggle="tooltip" style="cursor: pointer;">
                        <img src="<?php echo $avatar; ?>" alt="User Avatar" class="img-fluid rounded-circle">
                    </div>
                    <ul class="dropdown-menu text-center p-3" style="min-width: 220px;">
                        <li><span class="dropdown-item-text small text-muted"><?php echo htmlspecialchars($email); ?></span></li>
                        <li>
                            <a href="/management_system/users/profile.php" class="d-block my-2">
                                <img src="<?php echo $avatar; ?>" class="rounded-circle mb-3" width="100" height="100" alt="Avatar Preview">
                            </a>
                        </li>
                        <li class="dropdown-header">Hi, <?php echo htmlspecialchars($name); ?>!</li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="../users/logout.php">Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <!-- DESKTOP: Full row layout -->
        <div class="d-none d-md-flex align-items-center w-100">
            <!-- Left: Date and Time -->
            <div class="text-white d-flex align-items-center me-3">
                <span class="fw-bold me-2" id="currentDate-desktop"></span>
                <span class="small" id="currentTime-desktop"></span>
            </div>
            <!-- Burger -->
            <div class="me-3 burger-wrapper">
                <button class="btn d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" data-bs-backdrop="false">
                    <i class="bi bi-list fs-3"></i>
                </button>
            </div>
            <!-- Spacer to push items to the right -->
            <div class="flex-grow-1"></div>
            <!-- Bookmarks, Bell & Avatar -->
            <div class="d-flex align-items-center">
                <?php if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin'): ?>
                    <div class="dropdown position-relative me-2">
                        <a class="bookmark-icon" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false" title="Bookmarks" data-bs-toggle="tooltip">
                            <i class="bi bi-bookmark-fill fs-5 text-white"></i>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li class="dropdown-header">My Bookmarks</li>
                            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'student'): ?>
                                <li><a class="dropdown-item" href="../student/bookmark.php">View bookmarked research</a></li>
                            <?php else: ?>
                                <li><a class="dropdown-item" href="../users/bookmark.php">View bookmarked research</a></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <!-- SHARED NOTIFICATION SECTION (SAME AS MOBILE) -->
                <div class="notification-wrapper me-2">
                    <div class="notification-icon" onclick="toggleNotifications(event)">
                        <i class="bi bi-bell-fill fs-5 text-white"></i>
                        <span class="notification-badge" style="display: none;">0</span>
                    </div>
                </div>
                
                <div class="dropdown dropdown-avatar">
                    <div class="avatar" data-bs-toggle="dropdown" aria-expanded="false" title="Profile" data-bs-toggle="tooltip" style="cursor: pointer;">
                        <img src="<?php echo $avatar; ?>" alt="User Avatar" class="img-fluid rounded-circle">
                    </div>
                    <ul class="dropdown-menu text-center p-3" style="min-width: 220px;">
                        <li><span class="dropdown-item-text small text-muted"><?php echo htmlspecialchars($email); ?></span></li>
                        <li>
                            <a href="/management_system/users/profile.php" class="d-block my-2">
                                <img src="<?php echo $avatar; ?>" class="rounded-circle mb-3" width="100" height="100" alt="Avatar Preview">
                            </a>
                        </li>
                        <li class="dropdown-header">Hi, <?php echo htmlspecialchars($name); ?>!</li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="../users/logout.php">Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>

<!-- SINGLE NOTIFICATION DROPDOWN (SHARED BY BOTH MOBILE AND DESKTOP) -->
<div class="notification-dropdown" id="notificationDropdown" style="display: none;">
    <div class="notification-dropdown-header">
        <span>Notifications</span>
        <div class="d-flex gap-2">
            <button class="btn btn-sm btn-link p-0" onclick="markAllNotificationsAsRead()" style="font-size: 0.8em;">Mark all as read</button>
            <button class="btn btn-sm btn-link p-0 text-danger" onclick="clearAllNotifications()" style="font-size: 0.8em;">Clear all</button>
        </div>
    </div>
    <div class="notification-dropdown-content">
        <div class="loading-notifications">
            <i class="bi bi-arrow-clockwise spin"></i> Loading...
        </div>
    </div>
</div>

<!-- Modal to Edit Avatar -->
<div class="modal fade" id="editAvatarModal" tabindex="-1" aria-labelledby="editAvatarModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content text-center">
            <div class="modal-header">
                <h5 class="modal-title" id="editAvatarModalLabel">Edit Avatar</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="../users/upload_avatar.php" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <img id="avatarPreview" src="<?php echo $avatar; ?>" class="rounded-circle mb-3" width="100" height="100" alt="Avatar Preview">
                    <input type="file" name="avatar" id="avatarInput" class="form-control mb-2" accept="image/*">
                    <?php if ($_SESSION['avatar'] !== 'avatar.png'): ?>
                        <div class="mb-2">
                            <button type="button" class="btn btn-danger w-100" id="removeAvatarBtn">
                                Remove current avatar
                            </button>
                        </div>
                    <?php endif; ?>
                    <input type="hidden" name="remove_avatar" id="removeAvatarInput" value="0">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Avatar preview functionality
document.addEventListener('DOMContentLoaded', function() {
    const avatarInput = document.getElementById('avatarInput');
    const removeAvatarBtn = document.getElementById('removeAvatarBtn');
    
    if (avatarInput) {
        avatarInput.addEventListener('change', function (event) {
            const file = event.target.files[0];
            const preview = document.getElementById('avatarPreview');
            if (file && preview) {
                preview.src = URL.createObjectURL(file);
            }
        });
    }

    // Remove avatar functionality
    if (removeAvatarBtn) {
        removeAvatarBtn.addEventListener('click', function() {
            if (confirm('Are you sure you want to delete your current avatar?')) {
                const removeInput = document.getElementById('removeAvatarInput');
                if (removeInput) {
                    removeInput.value = '1';
                }
                const form = this.closest('form');
                if (form) {
                    form.submit();
                }
            }
        });
    }
});

// Date and Time functionality
function updateDateTime() {
    const now = new Date();
    const isMobile = window.innerWidth < 768;
    const dateOptions = isMobile
        ? { weekday: 'short', month: 'short', day: 'numeric' }
        : { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    const timeOptions = isMobile
        ? { hour: 'numeric', minute: '2-digit', hour12: true }
        : { hour: '2-digit', minute: '2-digit', hour12: true };

    const dateString = now.toLocaleDateString('en-US', dateOptions);
    const timeString = now.toLocaleTimeString('en-US', timeOptions);

    const desktopDate = document.getElementById('currentDate-desktop');
    const desktopTime = document.getElementById('currentTime-desktop');
    if (desktopDate) desktopDate.textContent = dateString;
    if (desktopTime) desktopTime.textContent = timeString;

    const mobileDate = document.getElementById('currentDate-mobile');
    const mobileTime = document.getElementById('currentTime-mobile');
    if (mobileDate) mobileDate.textContent = dateString;
    if (mobileTime) mobileTime.textContent = timeString;
}

// Notification functionality
let lastNotificationAnchor = null;

function positionNotificationDropdown(anchorEl) {
    const dropdown = document.getElementById('notificationDropdown');
    if (!dropdown || !anchorEl) return;

    const rect = anchorEl.getBoundingClientRect();
    dropdown.style.position = 'fixed';
    // Ensure width is known for positioning calculations
    const dropdownWidth = Math.min(window.innerWidth * 0.92, 380);
    const dropdownHeight = Math.min(window.innerHeight * 0.7, 480);
    dropdown.style.width = dropdownWidth + 'px';
    dropdown.style.maxHeight = dropdownHeight + 'px';

    // Default place below the icon
    let top = rect.bottom + 6;
    // If not enough space below, place above
    if (top + dropdownHeight > window.innerHeight - 8) {
        top = Math.max(8, rect.top - dropdownHeight - 6);
    }

    // Compute left so the right edge aligns to the icon's right, then clamp
    let left = rect.right - dropdownWidth;
    left = Math.max(8, Math.min(left, window.innerWidth - dropdownWidth - 8));

    dropdown.style.top = top + 'px';
    dropdown.style.left = left + 'px';
}

function toggleNotifications(event) {
    const dropdown = document.getElementById('notificationDropdown');
    const clickedIcon = event ? event.target.closest('.notification-icon') : document.querySelector('.notification-icon');
    
    if (!dropdown || !clickedIcon) {
        console.error('Notification elements not found');
        return;
    }
    
    if (dropdown.style.display === 'none' || dropdown.style.display === '') {
        lastNotificationAnchor = clickedIcon;
        dropdown.style.display = 'block';
        positionNotificationDropdown(clickedIcon);
        loadNotifications();
    } else {
        dropdown.style.display = 'none';
    }
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const notificationWrapper = event.target.closest('.notification-wrapper');
    const dropdownEl = event.target.closest('#notificationDropdown');
    if (!notificationWrapper && !dropdownEl) {
        const dropdown = document.getElementById('notificationDropdown');
        if (dropdown) {
            dropdown.style.display = 'none';
        }
    }
});

function loadNotifications() {
    console.log('Starting to load notifications...');
    
    // Determine API endpoint based on user role
    const userRole = '<?php echo isset($_SESSION['role']) ? $_SESSION['role'] : 'student'; ?>';
    let apiEndpoint = '/management_system/student/api/notifications.php?action=get_notifications';
    
    switch(userRole) {
        case 'admin':
            apiEndpoint = '/management_system/admin/api/notifications.php?action=get_notifications';
            break;
        case 'adviser':
            apiEndpoint = '/management_system/adviser/api/notifications.php?action=get_notifications';
            break;
        case 'dean':
            apiEndpoint = '/management_system/dean/api/notifications.php?action=get_notifications';
            break;
        case 'panelist':
            apiEndpoint = '/management_system/panel/api/notifications.php?action=get_notifications';
            break;
        case 'faculty':
            apiEndpoint = '/management_system/faculty/api/notifications.php?action=get_notifications';
            break;
        case 'grammarian':
            apiEndpoint = '/management_system/grammarian/api/notifications.php?action=get_notifications';
            break;
        default:
            apiEndpoint = '/management_system/student/api/notifications.php?action=get_notifications';
    }
    
    fetch(apiEndpoint)
        .then(response => {
            console.log('Response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('Full API response:', data);
            
            if (data.success) {
                updateNotificationBadge(data.unread_count);
                displayNotifications(data.notifications);
            } else {
                console.error('API returned error:', data);
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
        });
}

function updateNotificationBadge(count) {
    console.log('updateNotificationBadge called with:', count);
    
    // Update ALL badge elements (both mobile and desktop)
    const badges = document.querySelectorAll('.notification-badge');
    console.log('Found badge elements:', badges.length);
    
    badges.forEach(badge => {
        if (count > 0) {
            badge.textContent = count;
            badge.style.display = 'inline';
            console.log('Badge updated and shown');
        } else {
            badge.style.display = 'none';
            console.log('Badge hidden');
        }
    });
}

function displayNotifications(notifications) {
    console.log('displayNotifications called with:', notifications);
    const container = document.querySelector('.notification-dropdown-content');
    console.log('Notification container found:', container);
    
    if (!container) {
        console.error('Notification container not found!');
        return;
    }
    
    // Clear existing notifications
    container.innerHTML = '';
    
    if (notifications.length === 0) {
        container.innerHTML = '<div class="no-notifications">No notifications</div>';
        console.log('No notifications to display');
        return;
    }
    
    notifications.forEach((notification, index) => {
        console.log('Processing notification', index, ':', notification);
        
        const notificationElement = document.createElement('div');
        const isUnread = notification.is_read === '0' || notification.is_read === 0 || notification.is_read === false;
        notificationElement.className = `notification-item ${isUnread ? 'unread' : ''}`;
        
        // Escape quotes in the URL
        const safeUrl = (notification.url || '/management_system/student/home.php').replace(/'/g, "\\'");
        
        notificationElement.innerHTML = `
            <div class="notification-content" onclick="handleNotificationClick(${notification.id}, '${safeUrl}', ${notification.is_read})">
                <div class="notification-title">${notification.title}</div>
                <div class="notification-message">${notification.message}</div>
                <div class="notification-time">${formatTimeAgo(notification.created_at)}</div>
            </div>
            <div class="notification-menu">
                <div class="notification-dots" onclick="toggleNotificationMenu(event, ${notification.id})">⋮</div>
                <div class="notification-submenu" id="submenu-${notification.id}">
                    <div class="submenu-item" onclick="markAsUnread(${notification.id})">Mark as unread</div>
                    <div class="submenu-item delete" onclick="deleteNotification(${notification.id})">Delete</div>
                </div>
            </div>
        `;
        
        container.appendChild(notificationElement);
        console.log('Notification element added to container');
    });
}

// Handle notification click (navigate to related content)
function handleNotificationClick(notificationId, url, isRead) {
    console.log('Notification clicked:', notificationId, url, isRead);
    
    // Mark as read if unread
    const isUnread = isRead === '0' || isRead === 0 || isRead === false;
    if (isUnread) {
        markAsRead(notificationId, false);
    }
    
    // Close notification dropdown first
    document.getElementById('notificationDropdown').style.display = 'none';
    
    // Navigate to the notification URL
    if (url) {
        console.log('Navigating to:', url);
        // Use a small delay to ensure dropdown closes before navigation
        setTimeout(() => {
            window.location.href = url;
        }, 100);
    } else {
        console.log('No URL provided for notification');
    }
}

// Toggle 3-dot menu
function toggleNotificationMenu(event, notificationId) {
    event.stopPropagation();
    
    // Close all other submenus
    document.querySelectorAll('.notification-submenu').forEach(submenu => {
        if (submenu.id !== `submenu-${notificationId}`) {
            submenu.classList.remove('show');
        }
    });
    
    // Toggle current submenu
    const submenu = document.getElementById(`submenu-${notificationId}`);
    submenu.classList.toggle('show');
}

// Mark notification as unread
function markAsUnread(notificationId) {
    // Determine API endpoint based on user role
    const userRole = '<?php echo isset($_SESSION['role']) ? $_SESSION['role'] : 'student'; ?>';
    let apiEndpoint = '/management_system/student/api/notifications.php';
    
    switch(userRole) {
        case 'admin':
            apiEndpoint = '/management_system/admin/api/notifications.php';
            break;
        case 'adviser':
            apiEndpoint = '/management_system/adviser/api/notifications.php';
            break;
        case 'dean':
            apiEndpoint = '/management_system/dean/api/notifications.php';
            break;
        case 'panelist':
            apiEndpoint = '/management_system/panel/api/notifications.php';
            break;
        case 'faculty':
            apiEndpoint = '/management_system/faculty/api/notifications.php';
            break;
        case 'grammarian':
            apiEndpoint = '/management_system/grammarian/api/notifications.php';
            break;
        default:
            apiEndpoint = '/management_system/student/api/notifications.php';
    }
    
    fetch(apiEndpoint, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=mark_unread&notification_id=${notificationId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadNotifications();
            closeAllSubmenus();
        }
    })
    .catch(error => console.error('Error marking notification as unread:', error));
}

// Delete notification
function deleteNotification(notificationId) {
    if (confirm('Are you sure you want to delete this notification?')) {
        // Determine API endpoint based on user role
        const userRole = '<?php echo isset($_SESSION['role']) ? $_SESSION['role'] : 'student'; ?>';
        let apiEndpoint = '/management_system/student/api/notifications.php';
        
        switch(userRole) {
            case 'admin':
                apiEndpoint = '/management_system/admin/api/notifications.php';
                break;
            case 'adviser':
                apiEndpoint = '/management_system/adviser/api/notifications.php';
                break;
            case 'dean':
                apiEndpoint = '/management_system/dean/api/notifications.php';
                break;
            case 'panelist':
                apiEndpoint = '/management_system/panel/api/notifications.php';
                break;
            case 'faculty':
                apiEndpoint = '/management_system/faculty/api/notifications.php';
                break;
            case 'grammarian':
                apiEndpoint = '/management_system/grammarian/api/notifications.php';
                break;
            default:
                apiEndpoint = '/management_system/student/api/notifications.php';
        }
        
        fetch(apiEndpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=delete&notification_id=${notificationId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadNotifications();
                closeAllSubmenus();
            }
        })
        .catch(error => console.error('Error deleting notification:', error));
    }
}

// Close all submenus
function closeAllSubmenus() {
    document.querySelectorAll('.notification-submenu').forEach(submenu => {
        submenu.classList.remove('show');
    });
}

// Update the existing click handler to also close submenus
document.addEventListener('click', function(event) {
    const notificationWrapper = event.target.closest('.notification-wrapper');
    const notificationMenu = event.target.closest('.notification-menu');
    
    if (!notificationWrapper) {
        const dropdown = document.getElementById('notificationDropdown');
        if (dropdown) {
            dropdown.style.display = 'none';
        }
    }
    
    if (!notificationMenu) {
        closeAllSubmenus();
    }
});

function formatTimeAgo(dateString) {
    const now = new Date();
    const date = new Date(dateString);
    const diff = Math.floor((now - date) / 1000);
    if (diff < 60) return 'Just now';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    if (diff < 2592000) return Math.floor(diff / 86400) + 'd ago';
    return date.toLocaleDateString();
}

// Update markAsRead to not reload by default for navigation
function markAsRead(notificationId, reload = true) {
    // Determine API endpoint based on user role
    const userRole = '<?php echo isset($_SESSION['role']) ? $_SESSION['role'] : 'student'; ?>';
    let apiEndpoint = '/management_system/student/api/notifications.php';
    
    switch(userRole) {
        case 'admin':
            apiEndpoint = '/management_system/admin/api/notifications.php';
            break;
        case 'adviser':
            apiEndpoint = '/management_system/adviser/api/notifications.php';
            break;
        case 'dean':
            apiEndpoint = '/management_system/dean/api/notifications.php';
            break;
        case 'panelist':
            apiEndpoint = '/management_system/panel/api/notifications.php';
            break;
        case 'faculty':
            apiEndpoint = '/management_system/faculty/api/notifications.php';
            break;
        case 'grammarian':
            apiEndpoint = '/management_system/grammarian/api/notifications.php';
            break;
        default:
            apiEndpoint = '/management_system/student/api/notifications.php';
    }
    
    fetch(apiEndpoint, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=mark_read&notification_id=${notificationId}`
    })
        .then(response => response.json())
        .then(data => {
            if (data.success && reload) {
                loadNotifications();
            }
        })
        .catch(error => {
            console.error('Error marking notification as read:', error);
        });
}

function markAllNotificationsAsRead() {
    // Determine API endpoint based on user role
    const userRole = '<?php echo isset($_SESSION['role']) ? $_SESSION['role'] : 'student'; ?>';
    let apiEndpoint = '/management_system/student/api/notifications.php';
    
    switch(userRole) {
        case 'admin':
            apiEndpoint = '/management_system/admin/api/notifications.php';
            break;
        case 'adviser':
            apiEndpoint = '/management_system/adviser/api/notifications.php';
            break;
        case 'dean':
            apiEndpoint = '/management_system/dean/api/notifications.php';
            break;
        case 'panelist':
            apiEndpoint = '/management_system/panel/api/notifications.php';
            break;
        case 'faculty':
            apiEndpoint = '/management_system/faculty/api/notifications.php';
            break;
        case 'grammarian':
            apiEndpoint = '/management_system/grammarian/api/notifications.php';
            break;
        default:
            apiEndpoint = '/management_system/student/api/notifications.php';
    }
    
    fetch(apiEndpoint, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=mark_all_read'
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadNotifications();
            }
        })
        .catch(error => {
            console.error('Error marking all notifications as read:', error);
        });
}

function clearAllNotifications() {
    if (!confirm('Are you sure you want to clear all notifications? This action cannot be undone.')) {
        return;
    }
    
    // Determine API endpoint based on user role
    const userRole = '<?php echo isset($_SESSION['role']) ? $_SESSION['role'] : 'student'; ?>';
    let apiEndpoint = '/management_system/student/api/notifications.php';
    
    switch(userRole) {
        case 'admin':
            apiEndpoint = '/management_system/admin/api/notifications.php';
            break;
        case 'adviser':
            apiEndpoint = '/management_system/adviser/api/notifications.php';
            break;
        case 'dean':
            apiEndpoint = '/management_system/dean/api/notifications.php';
            break;
        case 'panelist':
            apiEndpoint = '/management_system/panel/api/notifications.php';
            break;
        case 'faculty':
            apiEndpoint = '/management_system/faculty/api/notifications.php';
            break;
        case 'grammarian':
            apiEndpoint = '/management_system/grammarian/api/notifications.php';
            break;
        default:
            apiEndpoint = '/management_system/student/api/notifications.php';
    }
    
    fetch(apiEndpoint, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=clear_all'
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadNotifications();
            } else {
                console.error('Error clearing notifications:', data.message);
            }
        })
        .catch(error => {
            console.error('Error clearing all notifications:', error);
        });
}

document.addEventListener('DOMContentLoaded', function() {
    // Initialize date/time
    updateDateTime();
    // Update every minute
    setInterval(updateDateTime, 60000);
    // Update on window resize to handle format changes
    window.addEventListener('resize', updateDateTime);
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(function (tooltipTriggerEl) {
        new bootstrap.Tooltip(tooltipTriggerEl);
    });
    // Load notifications initially
    loadNotifications();
    // Load notifications every 30 seconds
    setInterval(loadNotifications, 30000);
    // Reposition dropdown on resize/orientation change
    window.addEventListener('resize', function() {
        const dropdown = document.getElementById('notificationDropdown');
        if (dropdown && dropdown.style.display === 'block' && lastNotificationAnchor) {
            positionNotificationDropdown(lastNotificationAnchor);
        }
    });
});
</script>

<style>
/* Mobile layout (below 768px - matches Bootstrap's md breakpoint) */
@media (max-width: 767.98px) {
    .navbar-custom .mobile-left {
        flex-direction: row !important;
        align-items: center;
        gap: 0.5rem;
    }
    .navbar-custom .burger-btn {
        order: -1 !important;
        flex-shrink: 0;
    }
    .navbar-custom .date-time {
        order: 0;
        flex-shrink: 1;
    }
    .navbar-custom .text-white {
        font-size: clamp(0.7rem, 2vw, 0.8rem);
    }
    .navbar-custom .small {
        font-size: clamp(0.65rem, 1.8vw, 0.75rem);
    }
}

/* Tablet layout (768px to 991px) - ensure burger stays left */
@media (min-width: 768px) and (max-width: 991.98px) {
    .navbar-custom .burger-wrapper {
        order: -1 !important;
        margin-right: 0.75rem;
    }
    .navbar-custom .flex-grow-1 {
        flex: 1 1 auto !important;
    }
}

/* Extra small screens */
@media (max-width: 576px) {
    .navbar-custom .text-white {
        font-size: clamp(0.65rem, 1.8vw, 0.75rem);
    }
    .navbar-custom .small {
        font-size: clamp(0.6rem, 1.5vw, 0.7rem);
    }
}

.spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* NOTIFICATION STYLES */
.notification-wrapper {
    position: relative;
    display: inline-block;
}

.notification-icon {
    cursor: pointer;
    position: relative;
}

.notification-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #dc3545 !important;
    color: white !important;
    border-radius: 50% !important;
    padding: 2px 6px !important;
    font-size: 10px !important;
    min-width: 16px !important;
    text-align: center !important;
    font-weight: bold !important;
    line-height: 1.2 !important;
}

.notification-dropdown {
    position: fixed;
    background: white !important;
    border: 1px solid #ddd !important;
    border-radius: 10px !important;
    box-shadow: 0 10px 30px rgba(0,0,0,0.18) !important;
    width: 360px !important;
    max-height: 70vh !important;
    overflow: hidden !important;
    display: none;
    z-index: 1050 !important;
}

.notification-dropdown-header {
    padding: 12px 16px !important;
    border-bottom: 1px solid #eee !important;
    background: #f8f9fa !important;
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    font-weight: bold !important;
    color: #333 !important;
}

.notification-dropdown-content {
    padding: 0 !important;
    overflow-y: auto !important;
    max-height: calc(70vh - 48px) !important; /* minus header */
}

.notification-item {
    padding: 12px 16px !important;
    border-bottom: 1px solid #eee !important;
    cursor: pointer !important;
    background: white !important;
    color: #333 !important;
    transition: background-color 0.2s !important;
}

.notification-item:hover {
    background: #f5f5f5 !important;
}

.notification-item:last-child {
    border-bottom: none !important;
}

.notification-item.unread {
    background: #e3f2fd !important;
    border-left: 4px solid #2196f3 !important;
    position: relative !important;
}

.notification-item.unread::before {
    content: '';
    position: absolute !important;
    top: 50% !important;
    left: -8px !important;
    transform: translateY(-50%) !important;
    width: 8px !important;
    height: 8px !important;
    background: #2196f3 !important;
    border-radius: 50% !important;
}

.notification-item.unread .notification-title {
    font-weight: bold !important;
    color: #1976d2 !important;
}

.notification-title {
    font-weight: bold !important;
    margin-bottom: 4px !important;
    color: #333 !important;
}

.notification-message {
    color: #666 !important;
    font-size: 14px !important;
    margin-bottom: 4px !important;
    line-height: 1.4 !important;
    word-break: break-word !important;
    overflow-wrap: anywhere !important;
}

.notification-time {
    color: #999 !important;
    font-size: 12px !important;
}

.no-notifications {
    padding: 20px !important;
    text-align: center !important;
    color: #666 !important;
    font-style: italic !important;
}

.loading-notifications {
    padding: 20px !important;
    text-align: center !important;
    color: #666 !important;
}

/* Mobile responsive */
@media (max-width: 576px) {
    .notification-dropdown {
        width: min(92vw, 360px) !important;
        border-radius: 12px !important;
    }
}

/* Add these to your existing notification CSS */
.notification-item {
    display: flex !important;
    justify-content: space-between !important;
    align-items: flex-start !important;
}

.notification-content {
    flex: 1 !important;
    margin-right: 10px !important;
}

.notification-menu {
    position: relative !important;
    flex-shrink: 0 !important;
}

.notification-dots {
    padding: 4px 8px !important;
    cursor: pointer !important;
    border-radius: 4px !important;
    transition: background-color 0.2s !important;
    font-weight: bold !important;
    user-select: none !important;
}

.notification-dots:hover {
    background-color: #e9ecef !important;
}

.notification-submenu {
    position: absolute !important;
    top: 100% !important;
    right: 0 !important;
    background: white !important;
    border: 1px solid #ddd !important;
    border-radius: 4px !important;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1) !important;
    z-index: 1100 !important;
    min-width: 150px !important;
    display: none !important;
}

.notification-submenu.show {
    display: block !important;
}

.submenu-item {
    padding: 8px 12px !important;
    cursor: pointer !important;
    transition: background-color 0.2s !important;
    border-bottom: 1px solid #eee !important;
    color: #333 !important;
    font-size: 14px !important;
}

.submenu-item:last-child {
    border-bottom: none !important;
}

.submenu-item:hover {
    background-color: #f8f9fa !important;
}

.submenu-item.delete {
    color: #dc3545 !important;
}

.submenu-item.delete:hover {
    background-color: #f8d7da !important;
}

/* Bookmark and Bell hover highlight + animation */
.navbar-custom .bookmark-icon,
.navbar-custom .notification-icon {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    padding: 6px !important;
    border-radius: 8px !important;
    transition: transform 0.18s ease, background-color 0.18s ease, box-shadow 0.18s ease, color 0.18s ease !important;
}

.navbar-custom .notification-icon:hover {
    background-color: rgba(255, 255, 255, 0.12) !important;
    transform: translateY(-1px) scale(1.06) !important;
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.18) !important;
}

/* Bookmark: keep motion, remove grey background */
.navbar-custom .bookmark-icon:hover {
    transform: translateY(-1px) scale(1.06) !important;
}

.navbar-custom .notification-icon:hover i {
    color: #ffc107 !important; /* highlight color for bell only */
}

.navbar-custom .bookmark-icon:active,
.navbar-custom .notification-icon:active {
    transform: translateY(0) scale(0.98) !important;
}

.navbar-custom .bookmark-icon:focus-visible,
.navbar-custom .notification-icon:focus-visible {
    outline: 2px solid rgba(255, 193, 7, 0.6) !important; /* match highlight */
    outline-offset: 2px !important;
}
</style>