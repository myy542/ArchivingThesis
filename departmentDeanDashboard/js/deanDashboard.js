// DOM Elements
const hamburgerBtn = document.getElementById('hamburgerBtn');
const sidebar = document.getElementById('sidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');
const profileWrapper = document.getElementById('profileWrapper');
const profileDropdown = document.getElementById('profileDropdown');
const darkModeToggle = document.getElementById('darkmode');

// Toggle Sidebar
function toggleSidebar() {
    sidebar.classList.toggle('open');
    if (sidebar.classList.contains('open')) {
        sidebarOverlay.classList.add('show');
        document.body.style.overflow = 'hidden';
    } else {
        sidebarOverlay.classList.remove('show');
        document.body.style.overflow = '';
    }
}

function closeSidebar() {
    sidebar.classList.remove('open');
    sidebarOverlay.classList.remove('show');
    document.body.style.overflow = '';
}

// Toggle Profile Dropdown
function toggleProfileDropdown(e) {
    e.stopPropagation();
    profileDropdown.classList.toggle('show');
}

function closeProfileDropdown(e) {
    if (!profileWrapper.contains(e.target)) {
        profileDropdown.classList.remove('show');
    }
}

// Dark Mode
function initDarkMode() {
    const isDark = localStorage.getItem('darkMode') === 'true';
    if (isDark) {
        document.body.classList.add('dark-mode');
        if (darkModeToggle) darkModeToggle.checked = true;
        applyDarkMode();
    }
    if (darkModeToggle) {
        darkModeToggle.addEventListener('change', function() {
            if (this.checked) {
                document.body.classList.add('dark-mode');
                localStorage.setItem('darkMode', 'true');
                applyDarkMode();
            } else {
                document.body.classList.remove('dark-mode');
                localStorage.setItem('darkMode', 'false');
                removeDarkMode();
            }
        });
    }
}

function applyDarkMode() {
    const style = document.createElement('style');
    style.id = 'darkModeStyle';
    style.textContent = `
        body.dark-mode { background: #1a1a1a; }
        body.dark-mode .top-nav { background: #2d2d2d; border-bottom-color: #991b1b; }
        body.dark-mode .logo { color: #fecaca; }
        body.dark-mode .search-area { background: #3d3d3d; }
        body.dark-mode .search-area input { background: #3d3d3d; color: white; }
        body.dark-mode .profile-name { color: #fecaca; }
        body.dark-mode .stat-card,
        body.dark-mode .dept-stat-card,
        body.dark-mode .chart-card,
        body.dark-mode .faculty-section,
        body.dark-mode .projects-section,
        body.dark-mode .defenses-section,
        body.dark-mode .students-section,
        body.dark-mode .activities-section,
        body.dark-mode .workload-section { background: #2d2d2d; border-color: #991b1b; }
        body.dark-mode .stat-details h3,
        body.dark-mode .dept-stat-value,
        body.dark-mode .faculty-name { color: #fecaca; }
        body.dark-mode .faculty-card { background: #3d3d3d; }
        body.dark-mode .theses-table td,
        body.dark-mode .students-table td { color: #e5e7eb; border-bottom-color: #3d3d3d; }
        body.dark-mode .theses-table th,
        body.dark-mode .students-table th { color: #9ca3af; border-bottom-color: #991b1b; }
        body.dark-mode .activity-item { border-bottom-color: #3d3d3d; }
        body.dark-mode .activity-text { color: #e5e7eb; }
        body.dark-mode .profile-dropdown { background: #2d2d2d; border-color: #991b1b; }
        body.dark-mode .profile-dropdown a { color: #e5e7eb; }
        body.dark-mode .profile-dropdown a:hover { background: #3d3d3d; }
        body.dark-mode .btn-view { background: #3d3d3d; color: #fecaca; }
        body.dark-mode .btn-view:hover { background: #4a4a4a; }
        body.dark-mode .defense-item { background: #3d3d3d; }
        body.dark-mode .defense-date-box { background: #2d2d2d; }
        body.dark-mode .quick-action-btn { background: #3d3d3d; color: #fecaca; }
    `;
    if (!document.getElementById('darkModeStyle')) {
        document.head.appendChild(style);
    }
}

function removeDarkMode() {
    const style = document.getElementById('darkModeStyle');
    if (style) style.remove();
}

// Initialize Charts
function initCharts() {
    // Status Chart
    const statusCtx = document.getElementById('projectStatusChart');
    if (statusCtx && window.chartData) {
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'In Progress', 'Completed', 'Archived'],
                datasets: [{
                    data: [
                        window.chartData.status.pending,
                        window.chartData.status.in_progress,
                        window.chartData.status.completed,
                        window.chartData.status.archived
                    ],
                    backgroundColor: ['#f59e0b', '#3b82f6', '#10b981', '#6b7280'],
                    borderWidth: 0,
                    cutout: '65%',
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { font: { size: 11 } }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                return `${context.label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Workload Chart
    const workloadCtx = document.getElementById('workloadChart');
    if (workloadCtx && window.chartData) {
        new Chart(workloadCtx, {
            type: 'bar',
            data: {
                labels: window.chartData.workload_labels,
                datasets: [{
                    label: 'Projects Supervised',
                    data: window.chartData.workload_data,
                    backgroundColor: '#dc2626',
                    borderRadius: 6,
                    barPercentage: 0.7,
                    hoverBackgroundColor: '#991b1b'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `Projects: ${context.raw}`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '#fee2e2' },
                        ticks: { stepSize: 1, precision: 0 },
                        title: {
                            display: true,
                            text: 'Number of Projects',
                            color: '#6b7280',
                            font: { size: 10 }
                        }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: '#6b7280' }
                    }
                }
            }
        });
    }
    
    // Report Status Chart
    const reportStatusCtx = document.getElementById('reportStatusChart');
    if (reportStatusCtx && window.chartData) {
        new Chart(reportStatusCtx, {
            type: 'pie',
            data: {
                labels: ['Pending', 'In Progress', 'Completed', 'Archived'],
                datasets: [{
                    data: [
                        window.chartData.status.pending,
                        window.chartData.status.in_progress,
                        window.chartData.status.completed,
                        window.chartData.status.archived
                    ],
                    backgroundColor: ['#f59e0b', '#3b82f6', '#10b981', '#6b7280'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { font: { size: 11 } }
                    }
                }
            }
        });
    }
    
    // Report Workload Chart
    const reportWorkloadCtx = document.getElementById('reportWorkloadChart');
    if (reportWorkloadCtx && window.chartData) {
        new Chart(reportWorkloadCtx, {
            type: 'bar',
            data: {
                labels: window.chartData.workload_labels,
                datasets: [{
                    label: 'Projects Supervised',
                    data: window.chartData.workload_data,
                    backgroundColor: '#dc2626',
                    borderRadius: 6,
                    barPercentage: 0.7
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 } },
                    x: { grid: { display: false } }
                }
            }
        });
    }
}

// Search functionality
const searchInput = document.querySelector('.search-area input');
if (searchInput) {
    searchInput.addEventListener('input', function() {
        const term = this.value.toLowerCase();
        const cards = document.querySelectorAll('.faculty-card, .defense-item, .theses-table tbody tr');
        cards.forEach(card => {
            const text = card.textContent.toLowerCase();
            card.style.display = text.includes(term) ? '' : 'none';
        });
    });
}

// Event Listeners
if (hamburgerBtn) {
    hamburgerBtn.addEventListener('click', toggleSidebar);
}

if (sidebarOverlay) {
    sidebarOverlay.addEventListener('click', closeSidebar);
}

if (profileWrapper) {
    profileWrapper.addEventListener('click', toggleProfileDropdown);
    document.addEventListener('click', closeProfileDropdown);
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initDarkMode();
    initCharts();
    
    // Close sidebar on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar.classList.contains('open')) {
            closeSidebar();
        }
    });
    
    // Close sidebar when window is resized to desktop
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768 && sidebar.classList.contains('open')) {
            closeSidebar();
        }
    });
});