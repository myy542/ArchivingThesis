// DOM Elements
const darkToggle = document.getElementById('darkmode');
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('overlay');
const hamburger = document.getElementById('hamburgerBtn');
const mobileBtn = document.getElementById('mobileMenuBtn');

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

// Sidebar Functions
function toggleSidebar() {
    sidebar.classList.toggle('show');
    overlay.classList.toggle('show');
}

if (hamburger) hamburger.addEventListener('click', toggleSidebar);
if (mobileBtn) mobileBtn.addEventListener('click', toggleSidebar);
if (overlay) overlay.addEventListener('click', toggleSidebar);

// Modal Functions
function showForwardModal() {
    const modal = document.getElementById('forwardModal');
    if (modal) modal.style.display = 'flex';
}

function closeForwardModal() {
    const modal = document.getElementById('forwardModal');
    if (modal) modal.style.display = 'none';
}

function showReviseModal() {
    const modal = document.getElementById('reviseModal');
    if (modal) modal.style.display = 'flex';
}

function closeReviseModal() {
    const modal = document.getElementById('reviseModal');
    if (modal) modal.style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(e) {
    if (e.target.classList && e.target.classList.contains('modal')) {
        e.target.style.display = 'none';
    }
}

// Escape key handler
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const forwardModal = document.getElementById('forwardModal');
        const reviseModal = document.getElementById('reviseModal');
        if (forwardModal) forwardModal.style.display = 'none';
        if (reviseModal) reviseModal.style.display = 'none';
        if (sidebar && sidebar.classList.contains('show')) toggleSidebar();
    }
});

console.log('Review Thesis Page Initialized');