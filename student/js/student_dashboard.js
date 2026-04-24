// DOM Elements
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('overlay');
const hamburgerBtn = document.getElementById('hamburgerBtn');
const mobileMenuBtn = document.getElementById('mobileMenuBtn');
const notificationBell = document.getElementById('notificationBell');
const notificationDropdown = document.getElementById('notificationDropdown');
const avatarBtn = document.getElementById('avatarBtn');
const dropdownMenu = document.getElementById('dropdownMenu');
const markAllRead = document.getElementById('markAllRead');
const darkToggle = document.getElementById('darkmode');

let statusChart = null;
let timelineChart = null;

// Sidebar Functions
function openSidebar() {
    sidebar.classList.add('show');
    overlay.classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeSidebar() {
    sidebar.classList.remove('show');
    overlay.classList.remove('show');
    document.body.style.overflow = '';
}

function toggleSidebar(e) {
    e.stopPropagation();
    if (sidebar.classList.contains('show')) {
        closeSidebar();
    } else {
        openSidebar();
    }
}

if (hamburgerBtn) hamburgerBtn.addEventListener('click', toggleSidebar);
if (mobileMenuBtn) mobileMenuBtn.addEventListener('click', toggleSidebar);
if (overlay) overlay.addEventListener('click', closeSidebar);

// Escape key to close sidebar
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && sidebar.classList.contains('show')) {
        closeSidebar();
    }
});

// Close sidebar on window resize
window.addEventListener('resize', function() {
    if (window.innerWidth > 768 && sidebar.classList.contains('show')) {
        closeSidebar();
    }
});

// Avatar Dropdown
if (avatarBtn && dropdownMenu) {
    avatarBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        dropdownMenu.classList.toggle('show');
        if (notificationDropdown) notificationDropdown.classList.remove('show');
    });
}

// Notification Dropdown
if (notificationBell && notificationDropdown) {
    notificationBell.addEventListener('click', (e) => {
        e.stopPropagation();
        notificationDropdown.classList.toggle('show');
        if (dropdownMenu) dropdownMenu.classList.remove('show');
    });
}

// Close dropdowns on outside click
document.addEventListener('click', (e) => {
    if (dropdownMenu && !avatarBtn?.contains(e.target)) {
        dropdownMenu.classList.remove('show');
    }
    if (notificationDropdown && !notificationBell?.contains(e.target)) {
        notificationDropdown.classList.remove('show');
    }
});

// Dark Mode
if (darkToggle) {
    darkToggle.addEventListener('change', () => {
        document.body.classList.toggle('dark-mode');
        localStorage.setItem('darkMode', darkToggle.checked);
        updateChartColors();
    });
    if (localStorage.getItem('darkMode') === 'true') {
        darkToggle.checked = true;
        document.body.classList.add('dark-mode');
    }
}

// Notification Functions
function markAsRead(element) {
    const notifId = element.dataset.notificationId;
    if (!notifId) return;
    
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
            if (badge) badge.style.display = 'none';
        }
    })
    .catch(error => console.error('Error:', error));
}

// Notification click handler
document.querySelectorAll('.notification-item').forEach(item => {
    item.addEventListener('click', function(e) {
        const notifId = this.dataset.notificationId;
        const thesisId = this.dataset.thesisId;
        
        if (notifId && this.classList.contains('unread')) {
            markAsRead(this);
        }
        
        if (thesisId && parseInt(thesisId) > 0) {
            setTimeout(() => {
                window.location.href = 'view_project.php?id=' + thesisId;
            }, 300);
        }
    });
});

if (markAllRead) {
    markAllRead.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        markAllAsRead();
    });
}

// Chart Functions
function initStatusChart() {
    const ctx = document.getElementById('projectStatusChart');
    if (!ctx) return;
    
    if (statusChart) statusChart.destroy();
    
    const isDarkMode = document.body.classList.contains('dark-mode');
    const textColor = isDarkMode ? '#e0e0e0' : '#333';
    
    statusChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Pending', 'Approved', 'Rejected', 'Archived'],
            datasets: [{
                data: [
                    window.chartData.pending || 0,
                    window.chartData.approved || 0,
                    window.chartData.rejected || 0,
                    window.chartData.archived || 0
                ],
                backgroundColor: ['#f59e0b', '#10b981', '#ef4444', '#6b7280'],
                borderWidth: 0,
                cutout: '60%',
                hoverOffset: 10
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { color: textColor, font: { size: 11 } }
                },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            const val = ctx.raw || 0;
                            const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                            const pct = total > 0 ? Math.round((val / total) * 100) : 0;
                            return `${ctx.label}: ${val} (${pct}%)`;
                        }
                    }
                }
            }
        }
    });
}

function initTimelineChart() {
    const ctx = document.getElementById('timelineChart');
    if (!ctx) return;
    
    if (timelineChart) timelineChart.destroy();
    
    const isDarkMode = document.body.classList.contains('dark-mode');
    const gridColor = isDarkMode ? '#4a5568' : '#e2e8f0';
    const textColor = isDarkMode ? '#e0e0e0' : '#333';
    
    timelineChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            datasets: [{
                label: 'Submissions',
                data: [2, 3, 5, 4, 6, 8, 7, 9, 5, 4, 3, 2],
                borderColor: '#FE4853',
                backgroundColor: 'rgba(254, 72, 83, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointBackgroundColor: '#FE4853',
                pointBorderColor: '#fff',
                pointBorderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: { labels: { color: textColor } },
                tooltip: { callbacks: { label: function(ctx) { return `Submissions: ${ctx.raw}`; } } }
            },
            scales: {
                y: { beginAtZero: true, grid: { color: gridColor }, ticks: { stepSize: 1, color: textColor } },
                x: { grid: { display: false }, ticks: { color: textColor } }
            }
        }
    });
}

function updateChartColors() {
    if (statusChart) {
        const isDarkMode = document.body.classList.contains('dark-mode');
        statusChart.options.plugins.legend.labels.color = isDarkMode ? '#e0e0e0' : '#333';
        statusChart.update();
    }
    if (timelineChart) {
        const isDarkMode = document.body.classList.contains('dark-mode');
        const gridColor = isDarkMode ? '#4a5568' : '#e2e8f0';
        timelineChart.options.scales.y.grid.color = gridColor;
        timelineChart.options.scales.y.ticks.color = isDarkMode ? '#e0e0e0' : '#333';
        timelineChart.options.scales.x.ticks.color = isDarkMode ? '#e0e0e0' : '#333';
        timelineChart.options.plugins.legend.labels.color = isDarkMode ? '#e0e0e0' : '#333';
        timelineChart.update();
    }
}

// Chart period change handlers
const chartPeriod = document.getElementById('chartPeriod');
const timelinePeriod = document.getElementById('timelinePeriod');

if (chartPeriod) {
    chartPeriod.addEventListener('change', function() {
        // Update chart data based on period
        console.log('Chart period changed to:', this.value);
    });
}

if (timelinePeriod) {
    timelinePeriod.addEventListener('change', function() {
        // Update timeline chart based on period
        console.log('Timeline period changed to:', this.value);
    });
}

// Initialize
function init() {
    if (typeof Chart !== 'undefined') {
        initStatusChart();
        initTimelineChart();
    }
    console.log('Student Dashboard Initialized');
}

init();