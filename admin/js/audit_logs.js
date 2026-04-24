// DOM Elements
const hamburgerBtn = document.getElementById('hamburgerBtn');
const sidebar = document.getElementById('sidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');
const profileWrapper = document.getElementById('profileWrapper');
const profileDropdown = document.getElementById('profileDropdown');
const darkModeToggle = document.getElementById('darkmode');
const searchInputFilter = document.getElementById('searchInputFilter');
const actionFilter = document.getElementById('actionFilter');
const dateFrom = document.getElementById('dateFrom');
const dateTo = document.getElementById('dateTo');
const applyFilters = document.getElementById('applyFilters');
const clearFilters = document.getElementById('clearFilters');

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

// Filter Functions
function applyFilter() {
    const search = searchInputFilter ? searchInputFilter.value.trim() : '';
    const action = actionFilter ? actionFilter.value : '';
    const from = dateFrom ? dateFrom.value : '';
    const to = dateTo ? dateTo.value : '';
    
    let url = window.location.pathname + '?';
    if (search) url += 'search=' + encodeURIComponent(search) + '&';
    if (action) url += 'action=' + encodeURIComponent(action) + '&';
    if (from) url += 'date_from=' + encodeURIComponent(from) + '&';
    if (to) url += 'date_to=' + encodeURIComponent(to);
    
    window.location.href = url;
}

function clearAllFilters() {
    window.location.href = window.location.pathname;
}

if (applyFilters) applyFilters.addEventListener('click', applyFilter);
if (clearFilters) clearFilters.addEventListener('click', clearAllFilters);

// Enter key on search input
if (searchInputFilter) {
    searchInputFilter.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') applyFilter();
    });
}

// Initialize
initDarkMode();
console.log('Audit Logs Page Loaded');