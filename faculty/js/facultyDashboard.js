// DOM Elements
const hamburgerBtn = document.getElementById('hamburgerBtn');
const sidebar = document.getElementById('sidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');
const profileWrapper = document.getElementById('profileWrapper');
const profileDropdown = document.getElementById('profileDropdown');
const darkModeToggle = document.getElementById('darkmode');
const notificationIcon = document.getElementById('notificationIcon');
const notificationDropdown = document.getElementById('notificationDropdown');
const notificationBadge = document.getElementById('notificationBadge');
const notificationList = document.getElementById('notificationList');
const markAllReadBtn = document.getElementById('markAllReadBtn');
const searchInput = document.getElementById('searchInput');

let submissionChart = null;

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
        if (notificationDropdown && notificationDropdown.classList.contains('show')) notificationDropdown.classList.remove('show');
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

// Notification Dropdown
if (notificationIcon) {
    notificationIcon.addEventListener('click', function(e) {
        e.stopPropagation();
        notificationDropdown.classList.toggle('show');
        if (profileDropdown.classList.contains('show')) profileDropdown.classList.remove('show');
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
                let c = parseInt(notificationBadge.textContent) || 0;
                if (c > 0) {
                    c--;
                    if (c === 0) {
                        notificationBadge.textContent = '';
                        notificationBadge.style.display = 'none';
                        if (markAllReadBtn) markAllReadBtn.style.display = 'none';
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
                notificationBadge.textContent = '';
                notificationBadge.style.display = 'none';
            }
            if (markAllReadBtn) markAllReadBtn.style.display = 'none';
        }
    })
    .catch(error => console.error('Error:', error));
}

if (notificationList) {
    notificationList.addEventListener('click', function(e) {
        const notificationItem = e.target.closest('.notification-item');
        if (notificationItem && !notificationItem.classList.contains('empty')) {
            const notifId = notificationItem.dataset.id;
            const thesisId = notificationItem.dataset.thesisId;
            if (notifId && notificationItem.classList.contains('unread')) {
                markNotificationAsRead(notifId, notificationItem);
            }
            if (thesisId) {
                setTimeout(() => {
                    window.location.href = 'reviewThesis.php?id=' + thesisId;
                }, 300);
            }
        }
    });
}

if (markAllReadBtn) {
    markAllReadBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        markAllAsRead();
    });
}

if (notificationBadge && notificationBadge.textContent === '') {
    notificationBadge.style.display = 'none';
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
            updateChartColors();
        });
    }
}

// Chart Functions
function initChart() {
    const ctx = document.getElementById('submissionChart');
    if (!ctx) return;
    
    const isDarkMode = document.body.classList.contains('dark-mode');
    const gridColor = isDarkMode ? '#4a5568' : '#e2e8f0';
    const textColor = isDarkMode ? '#cbd5e1' : '#4a5568';
    
    submissionChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: window.chartData.labels,
            datasets: [
                { label: 'Pending', data: window.chartData.pendingData, borderColor: '#f59e0b', backgroundColor: 'rgba(245, 158, 11, 0.1)', borderWidth: 3, fill: true, tension: 0.4, pointRadius: 5, pointBackgroundColor: '#f59e0b', pointBorderColor: '#fff', pointBorderWidth: 2 },
                { label: 'Approved', data: window.chartData.approvedData, borderColor: '#10b981', backgroundColor: 'rgba(16, 185, 129, 0.1)', borderWidth: 3, fill: true, tension: 0.4, pointRadius: 5, pointBackgroundColor: '#10b981', pointBorderColor: '#fff', pointBorderWidth: 2 },
                { label: 'Rejected', data: window.chartData.rejectedData, borderColor: '#ef4444', backgroundColor: 'rgba(239, 68, 68, 0.1)', borderWidth: 3, fill: true, tension: 0.4, pointRadius: 5, pointBackgroundColor: '#ef4444', pointBorderColor: '#fff', pointBorderWidth: 2 }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top', labels: { color: textColor, font: { size: 12, weight: '600' }, usePointStyle: true, boxWidth: 10 } },
                tooltip: { mode: 'index', intersect: false, backgroundColor: isDarkMode ? '#1f2937' : '#ffffff', titleColor: isDarkMode ? '#f3f4f6' : '#1f2937', bodyColor: isDarkMode ? '#d1d5db' : '#4b5563', borderColor: '#dc2626', borderWidth: 1, callbacks: { label: function(ctx) { return ctx.dataset.label + ': ' + ctx.raw + ' thesis/es'; } } }
            },
            scales: {
                y: { beginAtZero: true, grid: { color: gridColor }, title: { display: true, text: 'Number of Theses', color: textColor, font: { weight: '600', size: 12 } }, ticks: { stepSize: 1, color: textColor, callback: function(value) { return value + ' thesis'; } } },
                x: { grid: { color: gridColor }, title: { display: true, text: 'Month', color: textColor, font: { weight: '600', size: 12 } }, ticks: { color: textColor } }
            },
            interaction: { mode: 'nearest', axis: 'x', intersect: false }
        }
    });
}

function updateChartColors() {
    if (!submissionChart) return;
    const isDarkMode = document.body.classList.contains('dark-mode');
    const gridColor = isDarkMode ? '#4a5568' : '#e2e8f0';
    const textColor = isDarkMode ? '#cbd5e1' : '#4a5568';
    
    submissionChart.options.scales.y.grid.color = gridColor;
    submissionChart.options.scales.x.grid.color = gridColor;
    submissionChart.options.scales.y.ticks.color = textColor;
    submissionChart.options.scales.x.ticks.color = textColor;
    submissionChart.options.scales.y.title.color = textColor;
    submissionChart.options.scales.x.title.color = textColor;
    submissionChart.options.plugins.legend.labels.color = textColor;
    submissionChart.update();
}

// Search Function
if (searchInput) {
    searchInput.addEventListener('input', function() {
        const term = this.value.toLowerCase();
        const rows = document.querySelectorAll('.theses-table tbody tr');
        rows.forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
        });
    });
}

// Initialize
function init() {
    initDarkMode();
    if (typeof Chart !== 'undefined' && window.chartData) {
        initChart();
    }
}

init();
console.log('Faculty Dashboard Initialized');