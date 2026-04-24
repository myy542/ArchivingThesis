// DOM Elements
const hamburger = document.getElementById('hamburgerBtn');
const sideNav = document.getElementById('sideNav');
const overlay = document.getElementById('sidebarOverlay');
const profileWrapper = document.getElementById('profileWrapper');
const profileDropdown = document.getElementById('profileDropdown');

// Sidebar Functions
function openSidebar() {
    sideNav.classList.add('open');
    overlay.classList.add('active');
}

function closeSidebar() {
    sideNav.classList.remove('open');
    overlay.classList.remove('active');
}

if (hamburger) {
    hamburger.addEventListener('click', e => {
        e.stopPropagation();
        sideNav.classList.contains('open') ? closeSidebar() : openSidebar();
    });
}

if (overlay) overlay.addEventListener('click', closeSidebar);

// Profile Dropdown
if (profileWrapper && profileDropdown) {
    profileWrapper.addEventListener('click', e => {
        e.stopPropagation();
        profileDropdown.classList.toggle('show');
    });
    document.addEventListener('click', () => profileDropdown.classList.remove('show'));
}

// Escape key handler
document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && sideNav.classList.contains('open')) closeSidebar();
});

console.log('R_adviserFeedback Page Initialized');