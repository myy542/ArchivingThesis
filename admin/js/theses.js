// DOM Elements
const hamburgerBtn = document.getElementById('hamburgerBtn');
const sidebar = document.getElementById('sidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');
const profileWrapper = document.getElementById('profileWrapper');
const profileDropdown = document.getElementById('profileDropdown');
const darkModeToggle = document.getElementById('darkmode');
const searchInput = document.getElementById('searchInput');
const topSearchInput = document.getElementById('topSearchInput');
const archiveFilter = document.getElementById('archiveFilter');
const applyFilters = document.getElementById('applyFilters');
const clearFilters = document.getElementById('clearFilters');
const addThesisBtn = document.getElementById('addThesisBtn');
const thesisModal = document.getElementById('thesisModal');
const statusModal = document.getElementById('statusModal');
const modalTitle = document.getElementById('modalTitle');
const thesisForm = document.getElementById('thesisForm');
const formAction = document.getElementById('form_action');

let currentDeptTab = null;

// Department Tab Functionality
function initDeptTabs() {
    const tabs = document.querySelectorAll('.dept-tab');
    const contents = document.querySelectorAll('.dept-content');
    
    if (tabs.length > 0) {
        if (!currentDeptTab) {
            tabs[0].classList.add('active');
            const firstDeptCode = tabs[0].getAttribute('data-dept');
            const firstContent = document.querySelector(`.dept-content[data-dept-content="${firstDeptCode}"]`);
            if (firstContent) firstContent.classList.add('active');
            currentDeptTab = firstDeptCode;
        }
        
        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                const deptCode = this.getAttribute('data-dept');
                tabs.forEach(t => t.classList.remove('active'));
                contents.forEach(c => c.classList.remove('active'));
                this.classList.add('active');
                const selectedContent = document.querySelector(`.dept-content[data-dept-content="${deptCode}"]`);
                if (selectedContent) selectedContent.classList.add('active');
                currentDeptTab = deptCode;
                applyFilter();
            });
        });
    }
}

// Filter Function
function applyFilter() {
    const searchTerm = searchInput ? searchInput.value.trim().toLowerCase() : '';
    const archive = archiveFilter ? archiveFilter.value : '';
    const activeTab = document.querySelector('.dept-tab.active');
    if (!activeTab) return;
    
    const deptCode = activeTab.getAttribute('data-dept');
    const deptContent = document.querySelector(`.dept-content[data-dept-content="${deptCode}"]`);
    if (!deptContent) return;
    
    const rows = deptContent.querySelectorAll('tbody tr');
    
    let visibleCount = 0;
    rows.forEach(row => {
        const title = row.getAttribute('data-title') || '';
        const author = row.getAttribute('data-author') || '';
        const year = row.getAttribute('data-year') || '';
        const rowStatus = row.getAttribute('data-status') || '';
        
        let matchesSearch = true;
        let matchesArchive = true;
        
        if (searchTerm) {
            matchesSearch = title.includes(searchTerm) || author.includes(searchTerm) || year.includes(searchTerm);
        }
        if (archive !== '') {
            const isArchived = (rowStatus === 'archived') ? '1' : '0';
            matchesArchive = isArchived === archive;
        }
        
        if (matchesSearch && matchesArchive) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    const countBadge = activeTab.querySelector('.dept-count-badge');
    if (countBadge) {
        const totalRows = rows.length;
        if (searchTerm || archive) {
            countBadge.textContent = visibleCount;
        } else {
            countBadge.textContent = totalRows;
        }
    }
}

function clearAllFilters() {
    if (searchInput) searchInput.value = '';
    if (topSearchInput) topSearchInput.value = '';
    if (archiveFilter) archiveFilter.value = '';
    applyFilter();
}

// Sync top search with main search
if (topSearchInput && searchInput) {
    topSearchInput.value = searchInput.value;
    topSearchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            searchInput.value = this.value;
            applyFilter();
        }
    });
    topSearchInput.addEventListener('input', function() {
        searchInput.value = this.value;
        applyFilter();
    });
}

if (searchInput) {
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') applyFilter();
    });
    searchInput.addEventListener('input', function() {
        applyFilter();
    });
}

if (archiveFilter) {
    archiveFilter.addEventListener('change', applyFilter);
}

if (applyFilters) applyFilters.addEventListener('click', applyFilter);
if (clearFilters) clearFilters.addEventListener('click', clearAllFilters);

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
    if (sidebar.classList.contains('open')) closeSidebar();
    else openSidebar();
}

if (hamburgerBtn) hamburgerBtn.addEventListener('click', toggleSidebar);
if (sidebarOverlay) sidebarOverlay.addEventListener('click', closeSidebar);

// Escape key handler
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        if (sidebar.classList.contains('open')) closeSidebar();
        if (profileDropdown && profileDropdown.classList.contains('show')) profileDropdown.classList.remove('show');
        if (thesisModal && thesisModal.classList.contains('show')) closeModal();
        if (statusModal && statusModal.classList.contains('show')) closeStatusModal();
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
    if (thesisModal) thesisModal.classList.add('show');
}

function closeModal() {
    if (thesisModal) thesisModal.classList.remove('show');
    if (thesisForm) thesisForm.reset();
    const thesisIdField = document.getElementById('thesis_id');
    if (thesisIdField) thesisIdField.value = '';
    if (formAction) formAction.value = 'add';
    if (modalTitle) modalTitle.textContent = 'Add New Thesis';
}

function openStatusModal(thesisId, currentStatus) {
    const statusThesisId = document.getElementById('status_thesis_id');
    const newStatus = document.getElementById('new_status');
    if (statusThesisId) statusThesisId.value = thesisId;
    if (newStatus) newStatus.value = currentStatus;
    if (statusModal) statusModal.classList.add('show');
}

function closeStatusModal() {
    if (statusModal) statusModal.classList.remove('show');
}

function saveStatusUpdate() {
    const thesisId = document.getElementById('status_thesis_id')?.value;
    const newStatus = document.getElementById('new_status')?.value;
    if (thesisId && newStatus) {
        window.location.href = 'theses.php?update_archive=' + thesisId + '&is_archived=' + newStatus;
    }
}

// Edit Thesis
function editThesis(thesisId) {
    fetch('theses.php?get_thesis=' + thesisId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('thesis_id').value = data.thesis.thesis_id;
                document.getElementById('title').value = data.thesis.title || '';
                document.getElementById('author').value = data.thesis.author || '';
                document.getElementById('department').value = data.thesis.department || '';
                document.getElementById('year').value = data.thesis.year || '';
                document.getElementById('abstract').value = data.thesis.abstract || '';
                document.getElementById('is_archived').value = data.thesis.is_archived || 0;
                if (formAction) formAction.value = 'edit';
                if (modalTitle) modalTitle.textContent = 'Edit Thesis';
                openModal();
            }
        })
        .catch(error => console.error('Error:', error));
}

// Update Archive Status
function updateArchiveStatus(thesisId, currentStatus) {
    const newStatus = currentStatus == 0 ? 1 : 0;
    if (confirm('Change thesis status to ' + (newStatus == 1 ? 'Archived' : 'Active') + '?')) {
        window.location.href = 'theses.php?update_archive=' + thesisId + '&is_archived=' + newStatus;
    }
}

// Delete Thesis
function deleteThesis(thesisId) {
    if (confirm('Are you sure you want to delete this thesis? This action cannot be undone.')) {
        window.location.href = 'theses.php?delete=' + thesisId;
    }
}

// Event Listeners
if (addThesisBtn) {
    addThesisBtn.addEventListener('click', function() {
        if (thesisForm) thesisForm.reset();
        const thesisIdField = document.getElementById('thesis_id');
        if (thesisIdField) thesisIdField.value = '';
        if (formAction) formAction.value = 'add';
        if (modalTitle) modalTitle.textContent = 'Add New Thesis';
        openModal();
    });
}

// Close modal when clicking outside
window.addEventListener('click', function(e) {
    if (e.target === thesisModal) closeModal();
    if (e.target === statusModal) closeStatusModal();
});

// Initialize
initDarkMode();
initDeptTabs();
console.log('Theses Management Page - Using is_archived column');