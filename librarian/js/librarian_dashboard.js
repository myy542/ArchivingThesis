// DOM Elements
const hamburgerBtn = document.getElementById('hamburgerBtn');
const sidebar = document.getElementById('sidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');
const profileWrapper = document.getElementById('profileWrapper');
const profileDropdown = document.getElementById('profileDropdown');
const notificationIcon = document.getElementById('notificationIcon');
const notificationDropdown = document.getElementById('notificationDropdown');
const notificationBadge = document.getElementById('notificationBadge');
const notificationList = document.getElementById('notificationList');
const markAllReadBtn = document.getElementById('markAllReadBtn');
const archiveModal = document.getElementById('archiveModal');

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
        if (notificationDropdown && notificationDropdown.classList.contains('show')) notificationDropdown.classList.remove('show');
        if (archiveModal && archiveModal.classList.contains('show')) closeArchiveModal();
    }
});

// Close sidebar on window resize
window.addEventListener('resize', function() {
    if (window.innerWidth > 768 && sidebar.classList.contains('open')) closeSidebar();
});

// Profile Dropdown
function toggleProfileDropdown(e) {
    e.stopPropagation();
    profileDropdown.classList.toggle('show');
    if (notificationDropdown && notificationDropdown.classList.contains('show')) notificationDropdown.classList.remove('show');
}

function closeProfileDropdown(e) {
    if (!profileWrapper.contains(e.target)) profileDropdown.classList.remove('show');
}

if (profileWrapper) {
    profileWrapper.addEventListener('click', toggleProfileDropdown);
    document.addEventListener('click', closeProfileDropdown);
}

// Notification Dropdown
if (notificationIcon) {
    notificationIcon.addEventListener('click', function(e) {
        e.stopPropagation();
        notificationDropdown.classList.toggle('show');
        if (profileDropdown && profileDropdown.classList.contains('show')) profileDropdown.classList.remove('show');
    });
}

document.addEventListener('click', function(e) {
    if (notificationIcon && !notificationIcon.contains(e.target) && notificationDropdown) {
        notificationDropdown.classList.remove('show');
    }
});

// Mark Notification as Read
function markNotificationAsRead(notifId, element) {
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'mark_read=1&notif_id=' + notifId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            element.classList.remove('unread');
            if (notificationBadge) {
                let c = parseInt(notificationBadge.textContent);
                if (c > 0) {
                    c--;
                    if (c === 0) {
                        notificationBadge.style.display = 'none';
                    } else {
                        notificationBadge.textContent = c;
                    }
                }
            }
        }
    })
    .catch(error => console.error('Error:', error));
}

function markAllAsRead() {
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'mark_all_read=1'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.querySelectorAll('.notification-item.unread').forEach(item => {
                item.classList.remove('unread');
            });
            if (notificationBadge) {
                notificationBadge.style.display = 'none';
            }
            if (markAllReadBtn) markAllReadBtn.style.display = 'none';
        }
    })
    .catch(error => console.error('Error:', error));
}

// Handle notification clicks
if (notificationList) {
    notificationList.addEventListener('click', function(e) {
        const link = e.target.closest('a');
        if (link && link.href && link.href !== '#') {
            return;
        }
        
        const notificationItem = e.target.closest('.notification-item');
        if (notificationItem && !notificationItem.classList.contains('empty')) {
            const notifId = notificationItem.dataset.id;
            const linkUrl = notificationItem.dataset.link;
            
            if (notifId && notificationItem.classList.contains('unread')) {
                markNotificationAsRead(notifId, notificationItem);
            }
            
            if (linkUrl && linkUrl !== '#') {
                setTimeout(() => {
                    window.location.href = linkUrl;
                }, 300);
            }
        }
    });
}

if (markAllReadBtn) {
    markAllReadBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        markAllAsRead();
    });
}

if (notificationBadge && (notificationBadge.textContent === '' || parseInt(notificationBadge.textContent) === 0)) {
    notificationBadge.style.display = 'none';
}

// Archive Modal Functions
let currentArchiveId = null;

function openArchiveModal(id) {
    if (!id || id === '' || id === 'undefined') {
        alert('Error: Invalid thesis ID!');
        return;
    }
    currentArchiveId = id;
    const archiveThesisId = document.getElementById('archive_thesis_id');
    const retentionPeriod = document.getElementById('retention_period');
    const archiveNotes = document.getElementById('archive_notes');
    
    if (archiveThesisId) archiveThesisId.value = id;
    if (retentionPeriod) retentionPeriod.value = '5';
    if (archiveNotes) archiveNotes.value = '';
    
    if (archiveModal) {
        archiveModal.style.display = 'flex';
        archiveModal.classList.add('show');
    }
}

function closeArchiveModal() {
    if (archiveModal) {
        archiveModal.style.display = 'none';
        archiveModal.classList.remove('show');
        currentArchiveId = null;
    }
}

function confirmArchive() {
    const thesisId = currentArchiveId;
    const retentionPeriod = document.getElementById('retention_period')?.value || '5';
    const archiveNotes = document.getElementById('archive_notes')?.value || '';
    
    if (!thesisId) {
        alert('Error: Invalid thesis ID!');
        return;
    }
    
    const confirmBtn = event.target;
    const originalText = confirmBtn.innerHTML;
    confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Archiving...';
    confirmBtn.disabled = true;
    
    const formData = new FormData();
    formData.append('thesis_id', thesisId);
    formData.append('retention_period', retentionPeriod);
    formData.append('archive_notes', archiveNotes);
    
    fetch('librarian_archive.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ ' + data.message);
            location.reload();
        } else {
            alert('❌ Error: ' + data.message);
            confirmBtn.innerHTML = originalText;
            confirmBtn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Network error. Please try again.');
        confirmBtn.innerHTML = originalText;
        confirmBtn.disabled = false;
    });
    
    closeArchiveModal();
}

// Close modal on outside click
window.onclick = function(event) {
    if (event.target === archiveModal) {
        closeArchiveModal();
    }
}

// Dark Mode
const darkModeToggle = document.getElementById('darkmode');
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

// Initialize
function init() {
    initDarkMode();
    console.log('Librarian Dashboard Initialized');
}

init();