// Users Management JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // DOM Elements
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const profileWrapper = document.getElementById('profileWrapper');
    const profileDropdown = document.getElementById('profileDropdown');
    const darkModeToggle = document.getElementById('darkmode');
    const searchInputFilter = document.getElementById('searchInputFilter');
    const roleFilter = document.getElementById('roleFilter');
    const statusFilter = document.getElementById('statusFilter');
    const applyFilters = document.getElementById('applyFilters');
    const clearFilters = document.getElementById('clearFilters');
    const addUserBtn = document.getElementById('addUserBtn');
    const addUserModal = document.getElementById('addUserModal');
    const editUserModal = document.getElementById('editUserModal');
    const submitAddUser = document.getElementById('submitAddUser');
    const submitEditUser = document.getElementById('submitEditUser');
    const closeModalBtns = document.querySelectorAll('.close-modal, .btn-cancel');
    
    // Sidebar Functions
    function openSidebar() { sidebar.classList.add('open'); sidebarOverlay.classList.add('show'); document.body.style.overflow = 'hidden'; }
    function closeSidebar() { sidebar.classList.remove('open'); sidebarOverlay.classList.remove('show'); document.body.style.overflow = ''; }
    
    if (hamburgerBtn) hamburgerBtn.addEventListener('click', function(e) { e.stopPropagation(); sidebar.classList.contains('open') ? closeSidebar() : openSidebar(); });
    if (sidebarOverlay) sidebarOverlay.addEventListener('click', closeSidebar);
    
    document.addEventListener('keydown', function(e) { if (e.key === 'Escape') { if (sidebar.classList.contains('open')) closeSidebar(); if (profileDropdown.classList.contains('show')) profileDropdown.classList.remove('show'); closeAllModals(); } });
    window.addEventListener('resize', function() { if (window.innerWidth > 768 && sidebar.classList.contains('open')) closeSidebar(); });
    
    // Profile Dropdown
    if (profileWrapper && profileDropdown) {
        profileWrapper.addEventListener('click', function(e) { e.stopPropagation(); profileDropdown.classList.toggle('show'); });
        document.addEventListener('click', function(e) { if (profileDropdown.classList.contains('show') && !profileWrapper.contains(e.target)) profileDropdown.classList.remove('show'); });
    }
    
    // Dark Mode
    function initDarkMode() {
        const isDark = localStorage.getItem('darkMode') === 'true';
        if (isDark) { document.body.classList.add('dark-mode'); if (darkModeToggle) darkModeToggle.checked = true; }
        if (darkModeToggle) darkModeToggle.addEventListener('change', function() { if (this.checked) { document.body.classList.add('dark-mode'); localStorage.setItem('darkMode', 'true'); } else { document.body.classList.remove('dark-mode'); localStorage.setItem('darkMode', 'false'); } });
    }
    
    // Filter Functions
    function applyFilter() {
        const search = searchInputFilter ? searchInputFilter.value.trim() : '';
        const role = roleFilter ? roleFilter.value : '';
        const status = statusFilter ? statusFilter.value : '';
        let url = window.location.pathname + '?';
        if (search) url += 'search=' + encodeURIComponent(search) + '&';
        if (role) url += 'role=' + encodeURIComponent(role) + '&';
        if (status) url += 'status=' + encodeURIComponent(status);
        window.location.href = url;
    }
    
    function clearAllFilters() { window.location.href = window.location.pathname; }
    
    if (applyFilters) applyFilters.addEventListener('click', applyFilter);
    if (clearFilters) clearFilters.addEventListener('click', clearAllFilters);
    
    // Modal Functions
    function openModal(modal) { if (modal) modal.classList.add('show'); }
    function closeModal(modal) { if (modal) modal.classList.remove('show'); }
    function closeAllModals() { closeModal(addUserModal); closeModal(editUserModal); }
    
    closeModalBtns.forEach(btn => { btn.addEventListener('click', closeAllModals); });
    window.addEventListener('click', function(e) { if (e.target.classList && e.target.classList.contains('modal')) closeAllModals(); });
    if (addUserBtn) addUserBtn.addEventListener('click', () => openModal(addUserModal));
    
    // Add User
    if (submitAddUser) {
        submitAddUser.addEventListener('click', function() {
            const form = document.getElementById('addUserForm');
            const formData = new FormData(form);
            formData.append('add_user', '1');
            fetch(window.location.href, { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => { if (data.success) { alert('User added successfully!'); location.reload(); } else { alert('Error: ' + (data.message || 'Failed to add user')); } })
                .catch(error => { console.error('Error:', error); alert('An error occurred'); });
        });
    }
    
    // Edit User
    window.editUser = function(id, name, email, username, roleId) {
        const nameParts = name.split(' ');
        const firstName = nameParts[0] || '';
        const lastName = nameParts.slice(1).join(' ') || '';
        document.getElementById('edit_user_id').value = id;
        document.getElementById('edit_first_name').value = firstName;
        document.getElementById('edit_last_name').value = lastName;
        document.getElementById('edit_email').value = email;
        document.getElementById('edit_username').value = username;
        document.getElementById('edit_role_id').value = roleId;
        openModal(editUserModal);
    };
    
    if (submitEditUser) {
        submitEditUser.addEventListener('click', function() {
            const form = document.getElementById('editUserForm');
            const formData = new FormData(form);
            formData.append('edit_user', '1');
            fetch(window.location.href, { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => { if (data.success) { alert('User updated successfully!'); location.reload(); } else { alert('Error: ' + (data.message || 'Failed to update user')); } })
                .catch(error => { console.error('Error:', error); alert('An error occurred'); });
        });
    }
    
    // Toggle User Status
    document.querySelectorAll('.toggle-status').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const userId = this.dataset.id;
            const currentStatus = this.dataset.status;
            const newStatus = currentStatus === 'Active' ? 'Inactive' : 'Active';
            if (confirm(`Are you sure you want to ${newStatus === 'Active' ? 'activate' : 'deactivate'} this user?`)) {
                const formData = new FormData();
                formData.append('update_status', '1');
                formData.append('user_id', userId);
                formData.append('status', newStatus);
                fetch(window.location.href, { method: 'POST', body: formData })
                    .then(response => response.json())
                    .then(data => { if (data.success) location.reload(); else alert('Error: Failed to update status'); });
            }
        });
    });
    
    // Delete User
    document.querySelectorAll('.delete').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const userId = this.dataset.id;
            const userName = this.dataset.name;
            if (confirm(`Are you sure you want to delete user "${userName}"? This action cannot be undone.`)) {
                const formData = new FormData();
                formData.append('delete_user', '1');
                formData.append('user_id', userId);
                fetch(window.location.href, { method: 'POST', body: formData })
                    .then(response => response.json())
                    .then(data => { if (data.success) location.reload(); else alert('Error: ' + (data.message || 'Failed to delete user')); });
            }
        });
    });
    
    // Initialize
    initDarkMode();
});