// DOM Elements
const hamburgerBtn = document.getElementById('hamburgerBtn');
const sidebar = document.getElementById('sidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');
const profileWrapper = document.getElementById('profileWrapper');
const profileDropdown = document.getElementById('profileDropdown');
const darkModeToggle = document.getElementById('darkmode');

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
        const addModal = document.getElementById('feedbackModal');
        const editModal = document.getElementById('editModal');
        if (addModal && addModal.classList.contains('show')) closeModal();
        if (editModal && editModal.classList.contains('show')) closeEditModal();
    }
});

// Close sidebar on window resize
window.addEventListener('resize', function() {
    if (window.innerWidth > 768 && sidebar.classList.contains('open')) closeSidebar();
});

// Profile Dropdown
if (profileWrapper && profileDropdown) {
    profileWrapper.addEventListener('click', function(e) {
        e.stopPropagation();
        profileDropdown.classList.toggle('show');
    });
    document.addEventListener('click', function(e) {
        if (profileDropdown.classList.contains('show') && !profileWrapper.contains(e.target)) {
            profileDropdown.classList.remove('show');
        }
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

// Modal Functions
function openModal() {
    const modal = document.getElementById('feedbackModal');
    if (modal) modal.classList.add('show');
}

function closeModal() {
    const modal = document.getElementById('feedbackModal');
    if (modal) modal.classList.remove('show');
}

function closeEditModal() {
    const modal = document.getElementById('editModal');
    if (modal) modal.classList.remove('show');
}

function deleteFeedback(id) {
    if (confirm('Are you sure you want to delete this feedback?')) {
        window.location.href = '?delete=' + id;
    }
}

function editFeedback(id) {
    fetch('?get_feedback=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('edit_feedback_id').value = data.feedback.feedback_id;
                document.getElementById('edit_comments').value = data.feedback.comments;
                const editModal = document.getElementById('editModal');
                if (editModal) editModal.classList.add('show');
            } else {
                alert('Error loading feedback');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading feedback');
        });
}

// Close modals on outside click
window.onclick = function(event) {
    const addModal = document.getElementById('feedbackModal');
    const editModal = document.getElementById('editModal');
    if (event.target == addModal) closeModal();
    if (event.target == editModal) closeEditModal();
};

// Initialize
initDarkMode();
console.log('Faculty Feedback Page Initialized');