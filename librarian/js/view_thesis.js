// DOM Elements
const hamburgerBtn = document.getElementById('hamburgerBtn');
const sidebar = document.getElementById('sidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');
const profileWrapper = document.getElementById('profileWrapper');
const profileDropdown = document.getElementById('profileDropdown');
const darkModeToggle = document.getElementById('darkmode');
const notificationIcon = document.getElementById('notificationIcon');
const notificationDropdown = document.getElementById('notificationDropdown');
const markAllReadBtn = document.getElementById('markAllReadBtn');
const notificationBadge = document.getElementById('notificationBadge');
const notificationList = document.getElementById('notificationList');

// Sidebar Functions
function openSidebar() {
    sidebar.classList.add('open');
    sidebarOverlay.classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeSidebar() {
    sidebar.classList.remove('open');
    sidebarOverlay.classList.remove('show');
    document.body.style.overflow = '';
}

function toggleSidebar(e) {
    e.stopPropagation();
    if (sidebar.classList.contains('open')) closeSidebar();
    else openSidebar();
}

if (hamburgerBtn) hamburgerBtn.addEventListener('click', toggleSidebar);
if (sidebarOverlay) sidebarOverlay.addEventListener('click', closeSidebar);

// Escape key handler
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        if (sidebar.classList.contains('open')) closeSidebar();
        if (profileDropdown && profileDropdown.classList.contains('show')) profileDropdown.classList.remove('show');
        if (notificationDropdown && notificationDropdown.classList.contains('show')) notificationDropdown.classList.remove('show');
    }
});

// Close sidebar on window resize
window.addEventListener('resize', function() {
    if (window.innerWidth > 768 && sidebar.classList.contains('open')) closeSidebar();
});

// Profile Dropdown
function toggleProfileDropdown(e) {
    e.stopPropagation();
    profileDropdown.classList.toggle('show');
    if (notificationDropdown && notificationDropdown.classList.contains('show')) notificationDropdown.classList.remove('show');
}

function closeProfileDropdown(e) {
    if (!profileWrapper.contains(e.target)) profileDropdown.classList.remove('show');
}

if (profileWrapper) {
    profileWrapper.addEventListener('click', toggleProfileDropdown);
    document.addEventListener('click', closeProfileDropdown);
}

// Notification Dropdown
if (notificationIcon) {
    notificationIcon.addEventListener('click', function(e) {
        e.stopPropagation();
        notificationDropdown.classList.toggle('show');
        if (profileDropdown && profileDropdown.classList.contains('show')) profileDropdown.classList.remove('show');
    });
}

document.addEventListener('click', function(e) {
    if (notificationIcon && !notificationIcon.contains(e.target) && notificationDropdown) {
        notificationDropdown.classList.remove('show');
    }
});

// Mark Notification as Read
function markNotificationAsRead(notifId, element) {
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'mark_read=1&notif_id=' + notifId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            element.classList.remove('unread');
            if (notificationBadge) {
                let c = parseInt(notificationBadge.textContent);
                if (c > 0) {
                    c--;
                    if (c === 0) {
                        notificationBadge.style.display = 'none';
                    } else {
                        notificationBadge.textContent = c;
                    }
                }
            }
        }
    })
    .catch(error => console.error('Error:', error));
}

function markAllAsRead() {
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'mark_all_read=1'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.querySelectorAll('.notification-item.unread').forEach(item => {
                item.classList.remove('unread');
            });
            if (notificationBadge) {
                notificationBadge.style.display = 'none';
            }
            if (markAllReadBtn) markAllReadBtn.style.display = 'none';
        }
    })
    .catch(error => console.error('Error:', error));
}

function initNotifications() {
    document.querySelectorAll('.notification-item').forEach(item => {
        if (!item.classList.contains('empty')) {
            item.addEventListener('click', function(e) {
                if (e.target.closest('.notification-footer')) return;
                const id = this.dataset.id;
                if (id && this.classList.contains('unread')) markNotificationAsRead(id, this);
            });
        }
    });
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            markAllAsRead();
        });
    }
}

// Dark Mode
function initDarkMode() {
    const isDark = localStorage.getItem('darkMode') === 'true';
    if (isDark) {
        document.body.classList.add('dark-mode');
        if (darkModeToggle) darkModeToggle.checked = true;
    }
    if (darkModeToggle) {
        darkModeToggle.addEventListener('change', function() {
            if (this.checked) {
                document.body.classList.add('dark-mode');
                localStorage.setItem('darkMode', 'true');
            } else {
                document.body.classList.remove('dark-mode');
                localStorage.setItem('darkMode', 'false');
            }
        });
    }
}

// Initialize
function init() {
    initDarkMode();
    initNotifications();
    console.log('View Thesis Page Initialized');
}

init();