// js/coordinatorDashboard.js
window.addEventListener('load', function() {
    'use strict';
    
    // HAMBURGER MENU
    var hamburgerBtn = document.getElementById('hamburgerBtn');
    var sidebar = document.getElementById('sidebar');
    var sidebarOverlay = document.getElementById('sidebarOverlay');
    
    if (hamburgerBtn && sidebar && sidebarOverlay) {
        hamburgerBtn.addEventListener('click', function() {
            sidebar.classList.toggle('open');
            sidebarOverlay.classList.toggle('show');
        });
        
        sidebarOverlay.addEventListener('click', function() {
            sidebar.classList.remove('open');
            sidebarOverlay.classList.remove('show');
        });
    }
    
    // PROFILE DROPDOWN
    var profileWrapper = document.getElementById('profileWrapper');
    var profileDropdown = document.getElementById('profileDropdown');
    
    if (profileWrapper && profileDropdown) {
        profileWrapper.addEventListener('click', function(e) {
            e.stopPropagation();
            profileDropdown.classList.toggle('show');
        });
        
        document.addEventListener('click', function() {
            profileDropdown.classList.remove('show');
        });
    }
    
    // NOTIFICATION DROPDOWN
    var notificationIcon = document.getElementById('notificationIcon');
    var notificationDropdown = document.getElementById('notificationDropdown');
    
    if (notificationIcon && notificationDropdown) {
        notificationIcon.addEventListener('click', function(e) {
            e.stopPropagation();
            notificationDropdown.classList.toggle('show');
        });
        
        document.addEventListener('click', function() {
            notificationDropdown.classList.remove('show');
        });
    }
    
    // MARK NOTIFICATION AS READ
    var notificationItems = document.querySelectorAll('.notification-item');
    notificationItems.forEach(function(item) {
        if (!item.classList.contains('empty')) {
            item.addEventListener('click', function() {
                var notifId = this.getAttribute('data-id');
                var notifLink = this.getAttribute('data-link');
                
                if (notifId && this.classList.contains('unread')) {
                    fetch(window.location.href, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'mark_read=1&notif_id=' + notifId
                    })
                    .then(function(response) { return response.json(); })
                    .then(function(data) {
                        if (data.success) {
                            this.classList.remove('unread');
                            var badge = document.querySelector('.notification-badge');
                            if (badge) {
                                var count = parseInt(badge.textContent);
                                if (count > 1) {
                                    badge.textContent = count - 1;
                                } else {
                                    badge.style.display = 'none';
                                }
                            }
                        }
                    }.bind(this))
                    .catch(function(error) { console.error('Error:', error); });
                }
                
                if (notifLink && notifLink !== '#') {
                    setTimeout(function() { window.location.href = notifLink; }, 300);
                }
            });
        }
    });
    
    // MARK ALL NOTIFICATIONS AS READ
    var markAllBtn = document.getElementById('markAllReadBtn');
    if (markAllBtn) {
        markAllBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'mark_all_read=1'
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    var unreadItems = document.querySelectorAll('.notification-item.unread');
                    unreadItems.forEach(function(item) {
                        item.classList.remove('unread');
                    });
                    var badge = document.querySelector('.notification-badge');
                    if (badge) badge.style.display = 'none';
                    markAllBtn.style.display = 'none';
                }
            })
            .catch(function(error) { console.error('Error:', error); });
        });
    }
    
    // HIDE BADGE IF ZERO
    var badge = document.querySelector('.notification-badge');
    if (badge) {
        var badgeText = badge.textContent;
        if (badgeText === '' || parseInt(badgeText) === 0) {
            badge.style.display = 'none';
        }
    }
    
    // DARK MODE
    var darkModeToggle = document.getElementById('darkmode');
    if (darkModeToggle) {
        darkModeToggle.addEventListener('change', function() {
            document.body.classList.toggle('dark-mode');
            localStorage.setItem('darkMode', this.checked);
        });
        
        if (localStorage.getItem('darkMode') === 'true') {
            darkModeToggle.checked = true;
            document.body.classList.add('dark-mode');
        }
    }
    
    // CLOSE MESSAGE MODAL
    var messageModal = document.getElementById('messageModal');
    if (messageModal) {
        var okButton = messageModal.querySelector('button');
        if (okButton) {
            okButton.addEventListener('click', function() {
                messageModal.style.display = 'none';
                window.history.replaceState({}, document.title, window.location.pathname);
            });
        }
    }
    
    console.log('Coordinator Dashboard loaded');
});