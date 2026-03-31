// Admin Dashboard JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // DOM Elements
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const profileWrapper = document.getElementById('profileWrapper');
    const profileDropdown = document.getElementById('profileDropdown');
    const darkModeToggle = document.getElementById('darkmode');
    const searchInput = document.getElementById('searchInput');

    // ==================== SIDEBAR FUNCTIONS ====================
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

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (sidebar.classList.contains('open')) closeSidebar();
            if (profileDropdown && profileDropdown.classList.contains('show')) profileDropdown.classList.remove('show');
        }
    });

    window.addEventListener('resize', function() {
        if (window.innerWidth > 768 && sidebar.classList.contains('open')) closeSidebar();
    });

    // ==================== PROFILE DROPDOWN ====================
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

    // ==================== DARK MODE ====================
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

    // ==================== SEARCH FUNCTIONALITY ====================
    function initSearch() {
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const term = this.value.toLowerCase();
                // Add search functionality if needed
            });
        }
    }

    // ==================== CHARTS ====================
    function initCharts() {
        // User Distribution Chart - Updated labels: Faculty to Research Adviser
        const distCtx = document.getElementById('userDistributionChart');
        if (distCtx && window.userData) {
            new Chart(distCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Students', 'Research Advisers', 'Deans', 'Librarians', 'Coordinators', 'Admins'],
                    datasets: [{
                        data: [
                            window.userData.stats.students || 0,
                            window.userData.stats.research_advisers || 0,
                            window.userData.stats.deans || 0,
                            window.userData.stats.librarians || 0,
                            window.userData.stats.coordinators || 0,
                            window.userData.stats.admins || 0
                        ],
                        backgroundColor: ['#1976d2', '#388e3c', '#f57c00', '#7b1fa2', '#e67e22', '#d32f2f'],
                        borderWidth: 0,
                        cutout: '65%'
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

        // Registration Trend Chart
        const regCtx = document.getElementById('registrationChart');
        if (regCtx && window.userData) {
            new Chart(regCtx, {
                type: 'line',
                data: {
                    labels: window.userData.months || ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                    datasets: [{
                        label: 'New Users',
                        data: window.userData.monthlyData || [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
                        borderColor: '#dc2626',
                        backgroundColor: 'rgba(220, 38, 38, 0.1)',
                        borderWidth: 2,
                        pointBackgroundColor: '#dc2626',
                        pointBorderColor: 'white',
                        pointRadius: 4,
                        fill: true,
                        tension: 0.3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { stepSize: 1, precision: 0 },
                            title: { display: true, text: 'Number of Users', font: { size: 10 } }
                        }
                    }
                }
            });
        }
    }

    // ==================== INITIALIZE ====================
    initDarkMode();
    initSearch();
    initCharts();
    
    console.log('Admin Dashboard Initialized');
});