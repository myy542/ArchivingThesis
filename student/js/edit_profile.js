// DOM Elements
const darkToggle = document.getElementById('darkmode');
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('overlay');
const hamburgerBtn = document.getElementById('hamburgerBtn');
const mobileMenuBtn = document.getElementById('mobileMenuBtn');
const avatarBtn = document.getElementById('avatarBtn');
const dropdownMenu = document.getElementById('dropdownMenu');
const notificationBell = document.getElementById('notificationBell');
const notificationDropdown = document.getElementById('notificationDropdown');
const fileInput = document.getElementById('profile_picture');
const fileNameSpan = document.getElementById('fileName');

// Dark Mode
if (darkToggle) {
    darkToggle.addEventListener('change', () => {
        document.body.classList.toggle('dark-mode');
        localStorage.setItem('darkMode', darkToggle.checked);
    });
    if (localStorage.getItem('darkMode') === 'true') {
        darkToggle.checked = true;
        document.body.classList.add('dark-mode');
    }
}

// Sidebar toggle
function openSidebar() {
    sidebar.classList.add('show');
    overlay.classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeSidebar() {
    sidebar.classList.remove('show');
    overlay.classList.remove('show');
    document.body.style.overflow = '';
}

function toggleSidebar(e) {
    e.stopPropagation();
    if (sidebar.classList.contains('show')) {
        closeSidebar();
    } else {
        openSidebar();
    }
}

if (hamburgerBtn) hamburgerBtn.addEventListener('click', toggleSidebar);
if (mobileMenuBtn) mobileMenuBtn.addEventListener('click', toggleSidebar);
if (overlay) overlay.addEventListener('click', closeSidebar);

// Escape key handler
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && sidebar.classList.contains('show')) {
        closeSidebar();
    }
});

// Close sidebar on window resize
window.addEventListener('resize', function() {
    if (window.innerWidth > 768 && sidebar.classList.contains('show')) {
        closeSidebar();
    }
});

// Avatar Dropdown
if (avatarBtn && dropdownMenu) {
    avatarBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        dropdownMenu.classList.toggle('show');
        if (notificationDropdown) notificationDropdown.classList.remove('show');
    });
}

// Notification Dropdown
if (notificationBell && notificationDropdown) {
    notificationBell.addEventListener('click', (e) => {
        e.stopPropagation();
        notificationDropdown.classList.toggle('show');
        if (dropdownMenu) dropdownMenu.classList.remove('show');
    });
}

// Close dropdowns on outside click
document.addEventListener('click', (e) => {
    if (dropdownMenu && !avatarBtn?.contains(e.target)) {
        dropdownMenu.classList.remove('show');
    }
    if (notificationDropdown && !notificationBell?.contains(e.target)) {
        notificationDropdown.classList.remove('show');
    }
});

// File input display
if (fileInput && fileNameSpan) {
    fileInput.addEventListener('change', function() {
        if (this.files.length > 0) {
            fileNameSpan.innerHTML = '<i class="fas fa-check-circle"></i> ' + this.files[0].name;
            fileNameSpan.style.color = '#10b981';
        } else {
            fileNameSpan.innerHTML = 'No file chosen';
            fileNameSpan.style.color = '#6E6E6E';
        }
    });
}

// Form validation
const editForm = document.querySelector('.edit-form');
if (editForm) {
    editForm.addEventListener('submit', function(e) {
        const email = document.querySelector('input[name="email"]');
        if (email && email.value) {
            const emailRegex = /^[^\s@]+@([^\s@]+\.)+[^\s@]+$/;
            if (!emailRegex.test(email.value)) {
                e.preventDefault();
                alert('Please enter a valid email address.');
                email.focus();
                return false;
            }
        }
    });
}

console.log('Edit Profile Page Initialized');