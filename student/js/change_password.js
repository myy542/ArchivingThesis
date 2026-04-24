// DOM Elements
const hamburgerBtn = document.getElementById('hamburgerBtn');
const sidebar = document.getElementById('sidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');
const profileWrapper = document.getElementById('profileWrapper');
const profileDropdown = document.getElementById('profileDropdown');
const darkModeToggle = document.getElementById('darkmode');
const notificationIcon = document.getElementById('notificationIcon');
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
    if (sidebar.classList.contains('open')) {
        closeSidebar();
    } else {
        openSidebar();
    }
}

if (hamburgerBtn) hamburgerBtn.addEventListener('click', toggleSidebar);
if (sidebarOverlay) sidebarOverlay.addEventListener('click', closeSidebar);

// Escape key handler
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        if (sidebar.classList.contains('open')) closeSidebar();
        if (profileDropdown && profileDropdown.classList.contains('show')) profileDropdown.classList.remove('show');
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
}

function closeProfileDropdown(e) {
    if (!profileWrapper.contains(e.target)) {
        profileDropdown.classList.remove('show');
    }
}

if (profileWrapper) {
    profileWrapper.addEventListener('click', toggleProfileDropdown);
    document.addEventListener('click', closeProfileDropdown);
}

// Notification Click
if (notificationIcon) {
    notificationIcon.addEventListener('click', function() {
        window.location.href = 'notification.php';
    });
}

// Search Function
if (searchInput) {
    searchInput.addEventListener('input', function() {
        const term = this.value.toLowerCase();
        console.log('Searching for:', term);
    });
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

// Form validation
const passwordForm = document.querySelector('form');
if (passwordForm) {
    passwordForm.addEventListener('submit', function(e) {
        const newPass = document.querySelector('input[name="new_password"]');
        const confirmPass = document.querySelector('input[name="confirm_password"]');
        
        if (newPass && confirmPass && newPass.value !== confirmPass.value) {
            e.preventDefault();
            alert('New password and confirm password do not match!');
            confirmPass.focus();
            return false;
        }
        
        if (newPass && newPass.value.length < 6 && newPass.value.length > 0) {
            e.preventDefault();
            alert('Password must be at least 6 characters long!');
            newPass.focus();
            return false;
        }
    });
}

// Initialize
function init() {
    initDarkMode();
    console.log('Change Password Page Initialized');
}

init();