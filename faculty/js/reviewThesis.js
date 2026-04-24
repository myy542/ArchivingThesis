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
function showApproveModal() {
    const modal = document.getElementById('approveModal');
    if (modal) modal.style.display = 'flex';
}

function closeApproveModal() {
    const modal = document.getElementById('approveModal');
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

function showRejectModal() {
    const modal = document.getElementById('rejectModal');
    if (modal) modal.style.display = 'flex';
}

function closeRejectModal() {
    const modal = document.getElementById('rejectModal');
    if (modal) modal.style.display = 'none';
}

function openArchiveModal(id) {
    const archiveThesisId = document.getElementById('archive_thesis_id');
    const archiveModal = document.getElementById('archiveModal');
    if (archiveThesisId) archiveThesisId.value = id;
    if (archiveModal) archiveModal.style.display = 'flex';
}

function closeArchiveModal() {
    const modal = document.getElementById('archiveModal');
    if (modal) modal.style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(e) {
    if (e.target.classList && e.target.classList.contains('modal')) {
        e.target.style.display = 'none';
    }
};

// Escape key handler
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const approveModal = document.getElementById('approveModal');
        const reviseModal = document.getElementById('reviseModal');
        const rejectModal = document.getElementById('rejectModal');
        const archiveModal = document.getElementById('archiveModal');
        
        if (approveModal) approveModal.style.display = 'none';
        if (reviseModal) reviseModal.style.display = 'none';
        if (rejectModal) rejectModal.style.display = 'none';
        if (archiveModal) archiveModal.style.display = 'none';
        
        if (sidebar && sidebar.classList.contains('show')) toggleSidebar();
    }
});

console.log('Review Thesis Page Initialized');