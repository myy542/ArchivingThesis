// DOM Elements
const toggle = document.getElementById('darkmode');
const avatarBtn = document.getElementById('avatarBtn');
const dropdownMenu = document.getElementById('dropdownMenu');
const notificationBell = document.getElementById('notificationBell');
const notificationDropdown = document.getElementById('notificationDropdown');
const mobileBtn = document.getElementById('mobileMenuBtn');
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('overlay');
const hamburgerBtn = document.getElementById('hamburgerBtn');

// Dark Mode
if (toggle) {
    toggle.addEventListener('change', () => {
        document.body.classList.toggle('dark-mode');
        localStorage.setItem('darkMode', toggle.checked);
    });
    if (localStorage.getItem('darkMode') === 'true') {
        toggle.checked = true;
        document.body.classList.add('dark-mode');
    }
}

// Avatar Dropdown
if (avatarBtn && dropdownMenu) {
    avatarBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        dropdownMenu.classList.toggle('show');
        if (notificationDropdown) notificationDropdown.classList.remove('show');
    });
}

// Notification Dropdown
if (notificationBell && notificationDropdown) {
    notificationBell.addEventListener('click', function(e) {
        e.stopPropagation();
        notificationDropdown.classList.toggle('show');
        if (dropdownMenu) dropdownMenu.classList.remove('show');
    });
}

// Close dropdowns on outside click
window.addEventListener('click', function() {
    if (notificationDropdown) notificationDropdown.classList.remove('show');
    if (dropdownMenu) dropdownMenu.classList.remove('show');
});

if (notificationDropdown) {
    notificationDropdown.addEventListener('click', function(e) {
        e.stopPropagation();
    });
}

if (dropdownMenu) {
    dropdownMenu.addEventListener('click', function(e) {
        e.stopPropagation();
    });
}

// Mark all as read
const markAllRead = document.getElementById('markAllRead');
if (markAllRead) {
    markAllRead.addEventListener('click', function(e) {
        e.preventDefault();
        fetch('mark_all_read.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.querySelectorAll('.notification-item').forEach(item => {
                    item.classList.remove('unread');
                });
                const badge = document.querySelector('.notification-badge');
                if (badge) badge.remove();
            }
        })
        .catch(error => console.error('Error:', error));
    });
}

// Mobile Menu
if (mobileBtn && sidebar && overlay) {
    mobileBtn.addEventListener('click', function() {
        sidebar.classList.toggle('show');
        overlay.classList.toggle('show');
        const icon = mobileBtn.querySelector('i');
        if (sidebar.classList.contains('show')) {
            icon.classList.remove('fa-bars');
            icon.classList.add('fa-times');
        } else {
            icon.classList.remove('fa-times');
            icon.classList.add('fa-bars');
        }
    });
}

if (hamburgerBtn && sidebar && overlay) {
    hamburgerBtn.addEventListener('click', function() {
        sidebar.classList.toggle('show');
        overlay.classList.toggle('show');
        const icon = hamburgerBtn.querySelector('i');
        if (sidebar.classList.contains('show')) {
            icon.classList.remove('fa-bars');
            icon.classList.add('fa-times');
        } else {
            icon.classList.remove('fa-times');
            icon.classList.add('fa-bars');
        }
    });
}

if (overlay) {
    overlay.addEventListener('click', function() {
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
        const mobileIcon = mobileBtn?.querySelector('i');
        if (mobileIcon) {
            mobileIcon.classList.remove('fa-times');
            mobileIcon.classList.add('fa-bars');
        }
        const hamburgerIcon = hamburgerBtn?.querySelector('i');
        if (hamburgerIcon) {
            hamburgerIcon.classList.remove('fa-times');
            hamburgerIcon.classList.add('fa-bars');
        }
    });
}

// Nav links - close sidebar on mobile
const navLinks = document.querySelectorAll('.nav-link');
navLinks.forEach(link => {
    link.addEventListener('click', function() {
        if (window.innerWidth <= 768 && sidebar && overlay) {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
            const mobileIcon = mobileBtn?.querySelector('i');
            if (mobileIcon) {
                mobileIcon.classList.remove('fa-times');
                mobileIcon.classList.add('fa-bars');
            }
            const hamburgerIcon = hamburgerBtn?.querySelector('i');
            if (hamburgerIcon) {
                hamburgerIcon.classList.remove('fa-times');
                hamburgerIcon.classList.add('fa-bars');
            }
        }
    });
});

console.log('Faculty Profile Page Initialized');