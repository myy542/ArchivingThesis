// Mobile menu, profile dropdown, search, and charts functionality
document.addEventListener('DOMContentLoaded', function() {
    // Sidebar elements
    const hamburger = document.getElementById('hamburgerBtn');
    const sideNav = document.getElementById('sideNav');
    const overlay = document.getElementById('sidebarOverlay');
    
    // Profile elements
    const profileWrapper = document.getElementById('profileWrapper');
    const profileDropdown = document.getElementById('profileDropdown');
    
    // Search functionality
    const searchInput = document.querySelector('.search-bar input');
    const searchButton = document.querySelector('.search-bar button');
    
    // Open sidebar function
    function openSidebar() {
        if (sideNav) sideNav.classList.add('open');
        if (overlay) overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
    // Close sidebar function
    function closeSidebar() {
        if (sideNav) sideNav.classList.remove('open');
        if (overlay) overlay.classList.remove('active');
        document.body.style.overflow = '';
    }
    
    // Toggle sidebar function
    function toggleSidebar(e) {
        if (e) e.stopPropagation();
        if (sideNav && sideNav.classList.contains('open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    }
    
    // Sidebar event listeners
    if (hamburger) {
        hamburger.addEventListener('click', toggleSidebar);
    }
    
    if (overlay) {
        overlay.addEventListener('click', closeSidebar);
    }
    
    // Profile dropdown toggle
    if (profileWrapper && profileDropdown) {
        profileWrapper.addEventListener('click', function(e) {
            e.stopPropagation();
            profileDropdown.classList.toggle('show');
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (profileDropdown.classList.contains('show') && 
                !profileWrapper.contains(e.target)) {
                profileDropdown.classList.remove('show');
            }
        });
    }
    
    // Close sidebar with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (sideNav && sideNav.classList.contains('open')) {
                closeSidebar();
            }
            if (profileDropdown && profileDropdown.classList.contains('show')) {
                profileDropdown.classList.remove('show');
            }
        }
    });
    
    // Search functionality
    function searchTheses() {
        if (!searchInput) return;
        
        const searchTerm = searchInput.value.toLowerCase();
        const tableRows = document.querySelectorAll('.theses-table tbody tr');
        
        if (tableRows.length > 0) {
            tableRows.forEach(row => {
                const title = row.cells[0]?.textContent.toLowerCase() || '';
                const author = row.cells[1]?.textContent.toLowerCase() || '';
                
                if (title.includes(searchTerm) || author.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        } else {
            // For thesis items in waiting review section
            const thesisItems = document.querySelectorAll('.thesis-item');
            thesisItems.forEach(item => {
                const title = item.querySelector('.thesis-info strong')?.textContent.toLowerCase() || '';
                const author = item.querySelector('.thesis-info .meta')?.textContent.toLowerCase() || '';
                
                if (title.includes(searchTerm) || author.includes(searchTerm)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        }
    }
    
    if (searchButton) {
        searchButton.addEventListener('click', searchTheses);
    }
    
    if (searchInput) {
        searchInput.addEventListener('keyup', function(e) {
            if (e.key === 'Enter') {
                searchTheses();
            }
        });
    }
    
    // Handle window resize - close sidebar if open on large screens
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (window.innerWidth > 768 && sideNav && sideNav.classList.contains('open')) {
                closeSidebar();
            }
        }, 250);
    });
    
    // Close sidebar when clicking sidebar links on mobile
    const sideNavLinks = document.querySelectorAll('.side-nav a');
    sideNavLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                closeSidebar();
            }
        });
    });
    
    // Initialize Charts
    function initCharts() {
        // Get statistics from PHP (passed via data attributes)
        const chartData = document.getElementById('chartData');
        if (!chartData) return;
        
        const pendingCount = parseInt(chartData.dataset.pending) || 0;
        const forwardedCount = parseInt(chartData.dataset.forwarded) || 0;
        const rejectedCount = parseInt(chartData.dataset.rejected) || 0;
        const total = pendingCount + forwardedCount + rejectedCount;
        
        // Status Distribution Pie Chart
        const statusCtx = document.getElementById('statusChart');
        if (statusCtx && typeof Chart !== 'undefined') {
            new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Pending Review', 'Forwarded to Dean', 'Rejected'],
                    datasets: [{
                        data: [pendingCount, forwardedCount, rejectedCount],
                        backgroundColor: ['#f39c12', '#27ae60', '#e74c3c'],
                        borderWidth: 0,
                        hoverOffset: 10
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                font: {
                                    size: 12
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    },
                    cutout: '60%'
                }
            });
        }
        
        // Monthly Submissions Line Chart (Sample Data - Replace with actual database data)
        const monthlyCtx = document.getElementById('monthlyChart');
        if (monthlyCtx && typeof Chart !== 'undefined') {
            new Chart(monthlyCtx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                    datasets: [
                        {
                            label: 'Submissions',
                            data: [3, 5, 8, 6, 10, 12, 8, 15, 18, 14, 20, 25],
                            borderColor: '#FE4853',
                            backgroundColor: 'rgba(254, 72, 83, 0.1)',
                            tension: 0.4,
                            fill: true,
                            pointBackgroundColor: '#FE4853',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 4,
                            pointHoverRadius: 6
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return `Submissions: ${context.raw}`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: '#e2e8f0'
                            },
                            title: {
                                display: true,
                                text: 'Number of Theses',
                                color: '#6c757d'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            },
                            title: {
                                display: true,
                                text: 'Month',
                                color: '#6c757d'
                            }
                        }
                    }
                }
            });
        }
    }
    
    // Call chart initialization
    initCharts();
});