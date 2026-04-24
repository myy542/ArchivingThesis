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
const roleSelect = document.getElementById('roleSelect');
const departmentGroup = document.getElementById('departmentGroup');
const departmentSelect = document.getElementById('departmentSelect');

// Chart instances
let distChartInstance = null;
let regChartInstance = null;

// Department Field Toggle
function toggleDepartmentField() {
    if (!roleSelect) return;
    const selectedRole = parseInt(roleSelect.value);
    const needsDepartment = (selectedRole === 2 || selectedRole === 3);
    
    if (departmentGroup) {
        if (needsDepartment) {
            departmentGroup.style.display = 'block';
            if (departmentSelect) departmentSelect.required = true;
        } else {
            departmentGroup.style.display = 'none';
            if (departmentSelect) {
                departmentSelect.required = false;
                departmentSelect.value = '';
            }
        }
    }
}

if (roleSelect) {
    roleSelect.addEventListener('change', toggleDepartmentField);
}

window.toggleDepartmentField = toggleDepartmentField;

// Modal Functions
window.openAddUserModal = function() {
    const modal = document.getElementById('addUserModal');
    if (modal) {
        modal.style.display = 'flex';
        const form = document.getElementById('addUserForm');
        if (form) form.reset();
        if (roleSelect) roleSelect.value = '1';
        toggleDepartmentField();
        const passwordField = document.querySelector('#addUserModal input[name="password"]');
        if (passwordField) passwordField.required = true;
        const passwordRequired = document.getElementById('passwordRequired');
        if (passwordRequired) passwordRequired.style.display = 'inline';
        const passwordNote = document.getElementById('passwordNote');
        if (passwordNote) passwordNote.style.display = 'none';
    }
};

window.closeAddUserModal = function() {
    const modal = document.getElementById('addUserModal');
    if (modal) modal.style.display = 'none';
};

const addUserBtn = document.querySelector('.add-user-btn');
if (addUserBtn) {
    addUserBtn.addEventListener('click', function(e) {
        e.preventDefault();
        window.openAddUserModal();
    });
}

window.addEventListener('click', function(e) {
    const modal = document.getElementById('addUserModal');
    if (e.target === modal) {
        closeAddUserModal();
    }
});

// SIDEBAR FUNCTIONS
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

if (hamburgerBtn) {
    hamburgerBtn.addEventListener('click', toggleSidebar);
}

if (sidebarOverlay) {
    sidebarOverlay.addEventListener('click', closeSidebar);
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        if (sidebar.classList.contains('open')) closeSidebar();
        if (profileDropdown && profileDropdown.classList.contains('show')) profileDropdown.classList.remove('show');
        if (notificationDropdown && notificationDropdown.classList.contains('show')) notificationDropdown.classList.remove('show');
        const addUserModal = document.getElementById('addUserModal');
        if (addUserModal && addUserModal.style.display === 'flex') closeAddUserModal();
    }
});

window.addEventListener('resize', function() {
    if (window.innerWidth > 768 && sidebar.classList.contains('open')) {
        closeSidebar();
    }
});

// PROFILE DROPDOWN
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

// NOTIFICATION FUNCTIONS
if (notificationIcon) {
    notificationIcon.addEventListener('click', function(e) {
        e.stopPropagation();
        notificationDropdown.classList.toggle('show');
        if (profileDropdown.classList.contains('show')) {
            profileDropdown.classList.remove('show');
        }
    });
}

document.addEventListener('click', function(e) {
    if (notificationIcon && !notificationIcon.contains(e.target) && notificationDropdown) {
        notificationDropdown.classList.remove('show');
    }
});

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

if (notificationList) {
    notificationList.addEventListener('click', function(e) {
        const notificationItem = e.target.closest('.notification-item');
        if (notificationItem && !notificationItem.classList.contains('empty')) {
            const notifId = notificationItem.dataset.id;
            const link = notificationItem.dataset.link;
            if (notifId && notificationItem.classList.contains('unread')) {
                markNotificationAsRead(notifId, notificationItem);
            }
            if (link && link !== '#') {
                setTimeout(() => {
                    window.location.href = link;
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

if (notificationBadge && notificationBadge.textContent === '') {
    notificationBadge.style.display = 'none';
} else if (notificationBadge) {
    notificationBadge.style.display = 'flex';
}

// DARK MODE
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

// CHARTS
function initCharts() {
    // User Distribution Chart
    const distCtx = document.getElementById('userDistributionChart');
    if (distCtx && window.userData) {
        if (window.distChartInstance) window.distChartInstance.destroy();
        
        window.distChartInstance = new Chart(distCtx, {
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
                    legend: {
                        position: 'bottom',
                        labels: { 
                            font: { size: 11 },
                            boxWidth: 10,
                            padding: 10
                        }
                    },
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
                    padding: { top: 20, bottom: 20, left: 20, right: 20 }
                }
            }
        });
    }

    // Registration Trend Chart
    const regCtx = document.getElementById('registrationChart');
    if (regCtx && window.userData) {
        if (window.regChartInstance) window.regChartInstance.destroy();
        
        const maxValue = Math.max(...window.userData.monthlyData, 1);
        const yAxisMax = Math.ceil(maxValue * 1.2);
        
        window.regChartInstance = new Chart(regCtx, {
            type: 'line',
            data: {
                labels: window.userData.months || ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [{
                    label: 'New Users',
                    data: window.userData.monthlyData || [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
                    borderColor: '#dc2626',
                    backgroundColor: 'rgba(220, 38, 38, 0.05)',
                    borderWidth: 3,
                    pointBackgroundColor: '#dc2626',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 8,
                    pointHoverBackgroundColor: '#991b1b',
                    fill: true,
                    tension: 0.3,
                    spanGaps: true
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
                                return `New Users: ${ctx.raw}`;
                            }
                        },
                        backgroundColor: '#1f2937',
                        titleColor: '#fef2f2',
                        bodyColor: '#fef2f2',
                        padding: 10,
                        cornerRadius: 8
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: yAxisMax,
                        grid: {
                            color: '#fee2e2',
                            drawBorder: true,
                            borderDash: [5, 5],
                            lineWidth: 1
                        },
                        ticks: { 
                            stepSize: 1, 
                            precision: 0,
                            color: '#6b7280',
                            font: { size: 11, weight: '500' }
                        },
                        title: { 
                            display: true, 
                            text: 'Number of Users', 
                            font: { size: 10, weight: '500' },
                            color: '#9ca3af',
                            padding: { bottom: 10 }
                        }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { 
                            color: '#6b7280',
                            font: { size: 11, weight: '500' },
                            maxRotation: 45,
                            minRotation: 45
                        },
                        title: { 
                            display: true, 
                            text: 'Months', 
                            font: { size: 10, weight: '500' },
                            color: '#9ca3af',
                            padding: { top: 10 }
                        }
                    }
                },
                elements: {
                    line: { borderJoin: 'round', borderCap: 'round' },
                    point: { hitRadius: 10, hoverRadius: 8 }
                },
                layout: {
                    padding: { top: 15, bottom: 15, left: 10, right: 10 }
                }
            }
        });
    }
}

// Initialize
function init() {
    initDarkMode();
    initCharts();
    console.log('Admin Dashboard Initialized');
}

init();