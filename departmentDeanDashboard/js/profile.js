// DOM Elements
const hamburgerBtn = document.getElementById('hamburgerBtn');
const sidebar = document.getElementById('sidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');
const profileWrapper = document.getElementById('profileWrapper');
const profileDropdown = document.getElementById('profileDropdown');
const darkModeToggle = document.getElementById('darkmode');

// Modal Elements
const editProfileModal = document.getElementById('editProfileModal');
const editProfileBtn = document.getElementById('editProfileBtn');
const editPersonalBtn = document.getElementById('editPersonalBtn');
const editBioBtn = document.getElementById('editBioBtn');
const modalCloseBtns = document.querySelectorAll('.modal-close');
const cancelBtns = document.querySelectorAll('.btn-cancel');

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
        if (editProfileModal && editProfileModal.classList.contains('show')) closeModal();
    }
});

// Close sidebar on window resize (mobile to desktop)
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
    // Populate modal fields with current data
    document.getElementById('editName').value = document.getElementById('displayName').textContent;
    document.getElementById('editEmail').value = document.getElementById('displayEmail').textContent;
    document.getElementById('editPhone').value = document.getElementById('displayPhone').textContent;
    document.getElementById('editAddress').value = document.getElementById('displayAddress').textContent;
    
    const birthDateText = document.getElementById('displayBirthDate').textContent;
    if (birthDateText !== 'Not provided') {
        const dateObj = new Date(birthDateText);
        if (!isNaN(dateObj.getTime())) {
            const year = dateObj.getFullYear();
            const month = String(dateObj.getMonth() + 1).padStart(2, '0');
            const day = String(dateObj.getDate()).padStart(2, '0');
            document.getElementById('editBirthDate').value = `${year}-${month}-${day}`;
        }
    } else {
        document.getElementById('editBirthDate').value = '';
    }
    
    document.getElementById('editBio').value = document.getElementById('displayBio').textContent;
    editProfileModal.classList.add('show');
}

function closeModal() {
    editProfileModal.classList.remove('show');
}

function saveProfile() {
    const form = document.getElementById('editProfileForm');
    const formData = new FormData(form);
    
    fetch('update_profile.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update display values
            document.getElementById('displayName').textContent = document.getElementById('editName').value;
            document.getElementById('displayEmail').textContent = document.getElementById('editEmail').value;
            document.getElementById('displayPhone').textContent = document.getElementById('editPhone').value;
            document.getElementById('displayAddress').textContent = document.getElementById('editAddress').value;
            
            const birthDate = document.getElementById('editBirthDate').value;
            if (birthDate) {
                const dateObj = new Date(birthDate);
                if (!isNaN(dateObj.getTime())) {
                    document.getElementById('displayBirthDate').textContent = dateObj.toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                    });
                }
            } else {
                document.getElementById('displayBirthDate').textContent = 'Not provided';
            }
            
            document.getElementById('displayBio').textContent = document.getElementById('editBio').value;
            
            // Update profile card name
            const profileCardName = document.querySelector('.profile-card h2');
            if (profileCardName) {
                profileCardName.textContent = document.getElementById('editName').value;
            }
            
            // Update top bar name
            const profileNameSpan = document.querySelector('.profile-name');
            if (profileNameSpan) {
                profileNameSpan.textContent = document.getElementById('editName').value;
            }
            
            // Update initials if name changed
            const fullName = document.getElementById('editName').value;
            const nameParts = fullName.split(' ');
            const firstName = nameParts[0] || '';
            const lastName = nameParts[nameParts.length - 1] || '';
            const newInitials = (firstName.charAt(0) + lastName.charAt(0)).toUpperCase();
            
            const profileAvatar = document.querySelector('.profile-avatar');
            const profileAvatarLarge = document.querySelector('.profile-avatar-large');
            if (profileAvatar) profileAvatar.textContent = newInitials;
            if (profileAvatarLarge) profileAvatarLarge.textContent = newInitials;
            
            alert('Profile updated successfully!');
            closeModal();
        } else {
            alert('Error: ' + (data.message || 'Failed to update profile'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to update profile. Please try again.');
    });
}

// Event listeners for modals
if (editProfileBtn) editProfileBtn.addEventListener('click', openModal);
if (editPersonalBtn) editPersonalBtn.addEventListener('click', openModal);
if (editBioBtn) editBioBtn.addEventListener('click', openModal);

modalCloseBtns.forEach(btn => btn.addEventListener('click', closeModal));
cancelBtns.forEach(btn => btn.addEventListener('click', closeModal));

// Close modal when clicking outside
window.addEventListener('click', function(e) {
    if (e.target === editProfileModal) {
        closeModal();
    }
});

// Initialize
initDarkMode();
console.log('Dean Profile Page Initialized');