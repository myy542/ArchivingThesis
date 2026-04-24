
// DOM Elements
const hamburgerBtn = document.getElementById('hamburgerBtn');
const sidebar = document.getElementById('sidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');
const profileWrapper = document.getElementById('profileWrapper');
const profileDropdown = document.getElementById('profileDropdown');
const forwardModal = document.getElementById('forwardModal');
const returnModal = document.getElementById('returnModal');

// Toggle Sidebar
function toggleSidebar() {
    sidebar.classList.toggle('open');
    if (sidebarOverlay) {
        sidebarOverlay.classList.toggle('show');
    }
}

// Close Sidebar
function closeSidebar() {
    sidebar.classList.remove('open');
    if (sidebarOverlay) {
        sidebarOverlay.classList.remove('show');
    }
}

// Toggle Profile Dropdown
function toggleProfileDropdown(e) {
    e.stopPropagation();
    if (profileDropdown) {
        profileDropdown.classList.toggle('show');
    }
}

// Close Profile Dropdown
function closeProfileDropdown(e) {
    if (profileWrapper && !profileWrapper.contains(e.target)) {
        if (profileDropdown) {
            profileDropdown.classList.remove('show');
        }
    }
}

// Modal Functions
function openForwardModal() {
    if (forwardModal) {
        forwardModal.classList.add('show');
    }
}

function closeForwardModal() {
    if (forwardModal) {
        forwardModal.classList.remove('show');
    }
}

function openReturnModal() {
    if (returnModal) {
        returnModal.classList.add('show');
    }
}

function closeReturnModal() {
    if (returnModal) {
        returnModal.classList.remove('show');
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (forwardModal && event.target === forwardModal) {
        closeForwardModal();
    }
    if (returnModal && event.target === returnModal) {
        closeReturnModal();
    }
}

// Initialize Dark Mode
function initDarkMode() {
    const isDark = localStorage.getItem('darkMode') === 'true';
    if (isDark) {
        document.body.classList.add('dark-mode');
    }
}

// Escape key handler
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        if (sidebar && sidebar.classList.contains('open')) {
            closeSidebar();
        }
        if (forwardModal && forwardModal.classList.contains('show')) {
            closeForwardModal();
        }
        if (returnModal && returnModal.classList.contains('show')) {
            closeReturnModal();
        }
        if (profileDropdown && profileDropdown.classList.contains('show')) {
            profileDropdown.classList.remove('show');
        }
    }
});

// Event Listeners
if (hamburgerBtn) {
    hamburgerBtn.addEventListener('click', toggleSidebar);
}

if (sidebarOverlay) {
    sidebarOverlay.addEventListener('click', closeSidebar);
}

if (profileWrapper) {
    profileWrapper.addEventListener('click', toggleProfileDropdown);
    document.addEventListener('click', closeProfileDropdown);
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    initDarkMode();
});