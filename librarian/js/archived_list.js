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
const searchInput = document.getElementById('searchInput');

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
        if (profileDropdown.classList.contains('show')) profileDropdown.classList.remove('show');
        if (notificationDropdown.classList.contains('show')) notificationDropdown.classList.remove('show');
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
    if (notificationDropdown.classList.contains('show')) notificationDropdown.classList.remove('show');
}

function closeProfileDropdown(e) {
    if (!profileWrapper.contains(e.target)) profileDropdown.classList.remove('show');
}

if (profileWrapper) {
    profileWrapper.addEventListener('click', toggleProfileDropdown);
    document.addEventListener('click', closeProfileDropdown);
}

// Notification Dropdown
function toggleNotificationDropdown(e) {
    e.stopPropagation();
    notificationDropdown.classList.toggle('show');
    if (profileDropdown.classList.contains('show')) profileDropdown.classList.remove('show');
}

function closeNotificationDropdown(e) {
    if (!notificationIcon.contains(e.target) && !notificationDropdown.contains(e.target)) {
        notificationDropdown.classList.remove('show');
    }
}

if (notificationIcon) {
    notificationIcon.addEventListener('click', toggleNotificationDropdown);
    document.addEventListener('click', closeNotificationDropdown);
}

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
            const badge = document.getElementById('notificationBadge');
            if (badge) {
                let c = parseInt(badge.textContent);
                if (c > 0) {
                    c--;
                    if (c === 0) badge.style.display = 'none';
                    else badge.textContent = c;
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
            document.querySelectorAll('.notification-item.unread').forEach(item => item.classList.remove('unread'));
            const badge = document.getElementById('notificationBadge');
            if (badge) badge.style.display = 'none';
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

// Search functionality
if (searchInput) {
    searchInput.addEventListener('input', function() {
        const term = this.value.toLowerCase();
        document.querySelectorAll('.theses-table tbody tr').forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(term) ? '' : 'none';
        });
        
        document.querySelectorAll('.dept-archive-section').forEach(section => {
            const visibleRows = section.querySelectorAll('.theses-table tbody tr:not([style*="display: none"])').length;
            const header = section.querySelector('.dept-archive-header');
            if (visibleRows === 0) {
                section.style.display = 'none';
            } else {
                section.style.display = 'block';
                const badge = header.querySelector('.badge');
                if (badge) badge.textContent = visibleRows + ' theses';
            }
        });
    });
}

// Initialize
function init() {
    initDarkMode();
    initNotifications();
    console.log("Archived List Page Loaded - Grouped by Department");
}

init();