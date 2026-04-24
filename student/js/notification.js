// DOM Elements
const darkToggle = document.getElementById('darkmode');
const markAllReadBtn = document.getElementById('markAllReadBtn');
const notificationList = document.getElementById('notificationList');
const notificationBadge = document.querySelector('.notification-badge');

// Dark Mode
function initDarkMode() {
    const isDark = localStorage.getItem('darkMode') === 'true';
    if (isDark) {
        document.body.classList.add('dark-mode');
    }
}

// Mark single notification as read
function markAsRead(notifId, element) {
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

// Mark all notifications as read
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
            if (markAllReadBtn) {
                markAllReadBtn.style.display = 'none';
            }
        }
    })
    .catch(error => console.error('Error:', error));
}

// Handle notification item click
if (notificationList) {
    notificationList.addEventListener('click', function(e) {
        const notificationItem = e.target.closest('.notification-item');
        if (notificationItem && !notificationItem.classList.contains('empty')) {
            const notifId = notificationItem.dataset.id;
            const thesisId = notificationItem.dataset.thesisId;
            
            if (notifId && notificationItem.classList.contains('unread')) {
                markAsRead(notifId, notificationItem);
            }
            
            if (thesisId && parseInt(thesisId) > 0) {
                setTimeout(() => {
                    window.location.href = 'view_project.php?id=' + thesisId;
                }, 300);
            }
        }
    });
}

// Mark all read button
if (markAllReadBtn) {
    markAllReadBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        markAllAsRead();
    });
}

// Initialize
initDarkMode();
console.log('Notification Page Initialized');