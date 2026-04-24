// Handle notification actions via AJAX
function markNotificationAsRead(notifId, element) {
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=mark_read&notification_id=' + notifId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (element) element.classList.remove('unread');
            const badge = document.querySelector('.notification-badge');
            if (badge) {
                let c = parseInt(badge.textContent);
                if (c > 0) {
                    c--;
                    if (c === 0) {
                        badge.style.display = 'none';
                    } else {
                        badge.textContent = c;
                    }
                }
            }
        }
    })
    .catch(error => console.error('Error:', error));
}

function markAllNotificationsAsRead() {
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=mark_all_read'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.querySelectorAll('.notification-item.unread').forEach(item => {
                item.classList.remove('unread');
            });
            const badge = document.querySelector('.notification-badge');
            if (badge) badge.style.display = 'none';
        }
    })
    .catch(error => console.error('Error:', error));
}

console.log('Notification Handler Loaded');