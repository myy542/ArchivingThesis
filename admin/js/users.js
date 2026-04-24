// DOM Elements
const hamburgerBtn = document.getElementById('hamburgerBtn');
const sidebar = document.getElementById('sidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');
const profileWrapper = document.getElementById('profileWrapper');
const profileDropdown = document.getElementById('profileDropdown');
const darkModeToggle = document.getElementById('darkmode');
const searchInput = document.getElementById('searchInput');
const topSearchInput = document.getElementById('topSearchInput');
const statusFilter = document.getElementById('statusFilter');
const applyFilters = document.getElementById('applyFilters');
const clearFilters = document.getElementById('clearFilters');
const addUserBtn = document.getElementById('addUserBtn');
const userModal = document.getElementById('userModal');
const modalTitle = document.getElementById('modalTitle');
const userForm = document.getElementById('userForm');
const formAction = document.getElementById('form_action');
const roleSelect = document.getElementById('role_id');
const departmentSelect = document.getElementById('department');
const deptRequired = document.getElementById('deptRequired');
const deptNote = document.getElementById('deptNote');
const passwordField = document.getElementById('password');
const passwordRequired = document.getElementById('passwordRequired');
const passwordNote = document.getElementById('passwordNote');

let currentRoleTab = null;

// Update department requirement based on role
function updateDepartmentRequirement() {
    if (!roleSelect) return;
    const selectedRole = parseInt(roleSelect.value);
    const isRequired = (selectedRole === 2 || selectedRole === 3); // Student or Research Adviser
    
    if (departmentSelect) {
        departmentSelect.required = isRequired;
        if (deptRequired) {
            deptRequired.textContent = isRequired ? '*' : '';
        }
        if (deptNote) {
            deptNote.innerHTML = isRequired ? 
                '<span style="color:#d32f2f;">* Required</span> for Students and Research Advisers.' : 
                'Optional for Admins, Deans, Librarians, Coordinators.';
        }
    }
}

// Update password requirement based on form action
function updatePasswordRequirement() {
    const formActionValue = formAction ? formAction.value : 'add';
    
    if (formActionValue === 'add') {
        if (passwordField) {
            passwordField.required = true;
            passwordField.placeholder = "Enter password (min. 6 characters)";
        }
        if (passwordRequired) passwordRequired.style.display = 'inline';
        if (passwordNote) passwordNote.style.display = 'none';
    } else {
        if (passwordField) {
            passwordField.required = false;
            passwordField.placeholder = "Leave blank to keep current password";
        }
        if (passwordRequired) passwordRequired.style.display = 'none';
        if (passwordNote) passwordNote.style.display = 'block';
    }
}

// Initialize Role Tabs
function initRoleTabs() {
    const tabs = document.querySelectorAll('.role-tab');
    const contents = document.querySelectorAll('.role-content');
    
    if (tabs.length > 0) {
        if (!currentRoleTab) {
            tabs[0].classList.add('active');
            const firstRoleId = tabs[0].getAttribute('data-role');
            const firstContent = document.querySelector(`.role-content[data-role-content="${firstRoleId}"]`);
            if (firstContent) firstContent.classList.add('active');
            currentRoleTab = firstRoleId;
        }
        
        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                const roleId = this.getAttribute('data-role');
                tabs.forEach(t => t.classList.remove('active'));
                contents.forEach(c => c.classList.remove('active'));
                this.classList.add('active');
                const selectedContent = document.querySelector(`.role-content[data-role-content="${roleId}"]`);
                if (selectedContent) selectedContent.classList.add('active');
                currentRoleTab = roleId;
                applyFilter();
            });
        });
    }
}

// Filter Function
function applyFilter() {
    const searchTerm = searchInput ? searchInput.value.trim().toLowerCase() : '';
    const status = statusFilter ? statusFilter.value : '';
    const activeTab = document.querySelector('.role-tab.active');
    if (!activeTab) return;
    
    const roleId = activeTab.getAttribute('data-role');
    const roleContent = document.querySelector(`.role-content[data-role-content="${roleId}"]`);
    if (!roleContent) return;
    
    const rows = roleContent.querySelectorAll('tbody tr');
    
    let visibleCount = 0;
    rows.forEach(row => {
        const userName = row.getAttribute('data-name') || '';
        const rowStatus = row.getAttribute('data-status') || '';
        
        let matchesSearch = true;
        let matchesStatus = true;
        
        if (searchTerm) matchesSearch = userName.includes(searchTerm);
        if (status) matchesStatus = rowStatus === status;
        
        if (matchesSearch && matchesStatus) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    const countBadge = activeTab.querySelector('.role-count-badge');
    if (countBadge) {
        const totalRows = rows.length;
        countBadge.textContent = (searchTerm || status) ? visibleCount : totalRows;
    }
}

function clearAllFilters() {
    if (searchInput) searchInput.value = '';
    if (topSearchInput) topSearchInput.value = '';
    if (statusFilter) statusFilter.value = '';
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

if (statusFilter) statusFilter.addEventListener('change', applyFilter);
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
        if (userModal && userModal.classList.contains('show')) closeModal();
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
    if (userModal) {
        userModal.classList.add('show');
        updatePasswordRequirement();
        updateDepartmentRequirement();
    }
}

function closeModal() {
    if (userModal) {
        userModal.classList.remove('show');
        if (userForm) userForm.reset();
        const userIdField = document.getElementById('user_id');
        if (userIdField) userIdField.value = '';
        if (formAction) formAction.value = 'add';
        if (modalTitle) modalTitle.innerHTML = '<i class="fas fa-user-plus"></i> Add New User';
        if (departmentSelect) departmentSelect.required = false;
        updateDepartmentRequirement();
        updatePasswordRequirement();
    }
}

// Edit User
function editUser(userId) {
    fetch('users.php?get_user=' + userId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('user_id').value = data.user.user_id;
                document.getElementById('first_name').value = data.user.first_name;
                document.getElementById('last_name').value = data.user.last_name;
                document.getElementById('email').value = data.user.email;
                document.getElementById('username').value = data.user.username;
                document.getElementById('role_id').value = data.user.role_id;
                document.getElementById('status').value = data.user.status;
                const deptField = document.getElementById('department');
                if (deptField && data.user.department) {
                    deptField.value = data.user.department;
                } else if (deptField) {
                    deptField.value = '';
                }
                if (formAction) formAction.value = 'edit';
                if (modalTitle) modalTitle.innerHTML = '<i class="fas fa-user-edit"></i> Edit User';
                updatePasswordRequirement();
                updateDepartmentRequirement();
                openModal();
            }
        })
        .catch(error => console.error('Error:', error));
}

// Toggle User Status
function toggleStatus(userId, currentStatus) {
    const newStatus = currentStatus === 'Active' ? 'Inactive' : 'Active';
    if (confirm('Are you sure you want to ' + (newStatus === 'Active' ? 'activate' : 'deactivate') + ' this user?')) {
        window.location.href = 'users.php?toggle=' + userId + '&status=' + newStatus;
    }
}

// Delete User
function deleteUser(userId) {
    if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
        window.location.href = 'users.php?delete=' + userId;
    }
}

// Event Listeners
if (roleSelect) {
    roleSelect.addEventListener('change', updateDepartmentRequirement);
}

if (addUserBtn) {
    addUserBtn.addEventListener('click', function() {
        if (userForm) userForm.reset();
        const userIdField = document.getElementById('user_id');
        if (userIdField) userIdField.value = '';
        if (formAction) formAction.value = 'add';
        if (modalTitle) modalTitle.innerHTML = '<i class="fas fa-user-plus"></i> Add New User';
        const deptField = document.getElementById('department');
        if (deptField) deptField.value = '';
        if (departmentSelect) departmentSelect.required = false;
        updatePasswordRequirement();
        updateDepartmentRequirement();
        openModal();
    });
}

// Close modal when clicking outside
window.addEventListener('click', function(e) {
    if (e.target === userModal) closeModal();
});

// Initialize
initDarkMode();
initRoleTabs();
console.log('User Management Page Initialized - Good luck sa Defense!');