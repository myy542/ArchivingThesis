// DOM Elements
const hamburger = document.getElementById('hamburgerBtn');
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('sidebarOverlay');
const profileWrapper = document.getElementById('profileWrapper');
const profileDropdown = document.getElementById('profileDropdown');
const darkModeToggle = document.getElementById('darkmode');
const notificationIcon = document.getElementById('notificationIcon');
const notificationDropdown = document.getElementById('notificationDropdown');
const markAllReadBtn = document.getElementById('markAllReadBtn');

// Chart instances
let statusChartInstance = null;
let workloadChartInstance = null;

// Toggle Sidebar
function toggleSidebar() {
    sidebar.classList.toggle('open');
    overlay.classList.toggle('show');
    document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
}

// Close Sidebar
function closeSidebar() {
    sidebar.classList.remove('open');
    overlay.classList.remove('show');
    document.body.style.overflow = '';
}

// Toggle Profile Dropdown
function toggleProfileDropdown(e) {
    e.stopPropagation();
    profileDropdown.classList.toggle('show');
    if (notificationDropdown && notificationDropdown.classList.contains('show')) {
        notificationDropdown.classList.remove('show');
    }
}

// Close Profile Dropdown
function closeProfileDropdown(e) {
    if (profileWrapper && !profileWrapper.contains(e.target)) {
        if (profileDropdown) profileDropdown.classList.remove('show');
    }
}

// Toggle Notification Dropdown
function toggleNotificationDropdown(e) {
    e.stopPropagation();
    if (notificationDropdown) {
        notificationDropdown.classList.toggle('show');
    }
    if (profileDropdown && profileDropdown.classList.contains('show')) {
        profileDropdown.classList.remove('show');
    }
}

// Close Notification Dropdown
function closeNotificationDropdown(e) {
    if (notificationIcon && !notificationIcon.contains(e.target) && 
        notificationDropdown && !notificationDropdown.contains(e.target)) {
        notificationDropdown.classList.remove('show');
    }
}

// Mark single notification as read
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
            const badge = document.querySelector('.notification-badge');
            if (badge) {
                let c = parseInt(badge.textContent);
                if (c > 0) {
                    c--;
                    if (c === 0) {
                        badge.style.display = 'none';
                    } else {
                        badge.textContent = c;
                    }
                }
            }
        }
    })
    .catch(error => console.error('Error:', error));
}

// Mark all notifications as read
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
            const badge = document.querySelector('.notification-badge');
            if (badge) {
                badge.style.display = 'none';
            }
            if (markAllReadBtn) {
                markAllReadBtn.style.display = 'none';
            }
        }
    })
    .catch(error => console.error('Error:', error));
}

// Initialize notifications
function initNotifications() {
    document.querySelectorAll('.notification-item').forEach(item => {
        if (!item.classList.contains('empty')) {
            item.addEventListener('click', function(e) {
                if (e.target.closest('.notification-footer')) return;
                const id = this.dataset.id;
                if (id && this.classList.contains('unread')) {
                    markNotificationAsRead(id, this);
                }
            });
        }
    });
    
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            markAllAsRead();
        });
    }
}

// Initialize Dark Mode
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

// Initialize Charts
function initCharts() {
    const statusCtx = document.getElementById('projectStatusChart');
    if (statusCtx && window.chartData) {
        if (statusChartInstance) statusChartInstance.destroy();
        
        statusChartInstance = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Ongoing', 'Archived'],
                datasets: [{
                    data: [window.chartData.status.ongoing, window.chartData.status.archived],
                    backgroundColor: ['#f59e0b', '#6b7280'],
                    borderWidth: 0,
                    cutout: '60%',
                    hoverOffset: 10,
                    borderRadius: 8,
                    spacing: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                const val = ctx.raw || 0;
                                const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                                const pct = total > 0 ? Math.round((val / total) * 100) : 0;
                                return `${ctx.label}: ${val} (${pct}%)`;
                            }
                        },
                        backgroundColor: '#1f2937',
                        titleColor: '#fef2f2',
                        bodyColor: '#fef2f2',
                        padding: 10,
                        cornerRadius: 8
                    }
                },
                layout: {
                    padding: { top: 10, bottom: 10, left: 10, right: 10 }
                }
            }
        });
    }
    
    const workloadCtx = document.getElementById('workloadChart');
    if (workloadCtx && window.chartData && window.chartData.workload_labels && window.chartData.workload_labels.length > 0) {
        if (workloadChartInstance) workloadChartInstance.destroy();
        
        const maxValue = Math.max(...window.chartData.workload_data, 1);
        const yAxisMax = Math.ceil(maxValue * 1.2);
        
        workloadChartInstance = new Chart(workloadCtx, {
            type: 'bar',
            data: {
                labels: window.chartData.workload_labels,
                datasets: [{
                    label: 'Projects Supervised',
                    data: window.chartData.workload_data,
                    backgroundColor: '#dc2626',
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: yAxisMax,
                        ticks: {
                            stepSize: 1,
                            precision: 0
                        }
                    }
                }
            }
        });
    } else if (workloadCtx) {
        new Chart(workloadCtx, {
            type: 'bar',
            data: {
                labels: ['No Data'],
                datasets: [{
                    label: 'Projects Supervised',
                    data: [0],
                    backgroundColor: '#dc2626'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true
            }
        });
    }
    
    // Report Status Chart
    const reportStatusCtx = document.getElementById('reportStatusChart');
    if (reportStatusCtx && window.chartData) {
        new Chart(reportStatusCtx, {
            type: 'pie',
            data: {
                labels: ['Ongoing', 'Archived'],
                datasets: [{
                    data: [window.chartData.status.ongoing, window.chartData.status.archived],
                    backgroundColor: ['#f59e0b', '#6b7280']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true
            }
        });
    }
    
    // Report Workload Chart
    const reportWorkloadCtx = document.getElementById('reportWorkloadChart');
    if (reportWorkloadCtx && window.chartData && window.chartData.workload_labels && window.chartData.workload_labels.length > 0) {
        new Chart(reportWorkloadCtx, {
            type: 'bar',
            data: {
                labels: window.chartData.workload_labels,
                datasets: [{
                    label: 'Projects Supervised',
                    data: window.chartData.workload_data,
                    backgroundColor: '#dc2626'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true
            }
        });
    }
}

// Event Listeners
if (hamburger) hamburger.addEventListener('click', toggleSidebar);
if (overlay) overlay.addEventListener('click', closeSidebar);
if (profileWrapper) {
    profileWrapper.addEventListener('click', toggleProfileDropdown);
    document.addEventListener('click', closeProfileDropdown);
}
if (notificationIcon) {
    notificationIcon.addEventListener('click', toggleNotificationDropdown);
    document.addEventListener('click', closeNotificationDropdown);
}

// Search functionality
const searchInput = document.getElementById('searchInput');
if (searchInput) {
    searchInput.addEventListener('keyup', function(e) {
        const searchTerm = this.value.toLowerCase();
        // Add your search logic here
        console.log('Searching for:', searchTerm);
    });
}

// Escape key to close modals
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        if (sidebar && sidebar.classList.contains('open')) closeSidebar();
        if (notificationDropdown && notificationDropdown.classList.contains('show')) {
            notificationDropdown.classList.remove('show');
        }
        if (profileDropdown && profileDropdown.classList.contains('show')) {
            profileDropdown.classList.remove('show');
        }
    }
});

// Initialize everything when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    initDarkMode();
    initCharts();
    initNotifications();
});