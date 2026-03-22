// Dark mode toggle
const toggle = document.getElementById('darkmode');
if (toggle) {
    toggle.addEventListener('change', () => {
        document.body.classList.toggle('dark-mode');
        localStorage.setItem('darkMode', toggle.checked);
    });
    if (localStorage.getItem('darkMode') === 'true') {
        toggle.checked = true;
        document.body.classList.add('dark-mode');
    }
}

// Avatar dropdown
const avatarBtn = document.getElementById('avatarBtn');
const dropdownMenu = document.getElementById('dropdownMenu');

if (avatarBtn) {
    avatarBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        dropdownMenu.classList.toggle('show');
    });
}

window.addEventListener('click', function() {
    if (dropdownMenu) dropdownMenu.classList.remove('show');
});

if (dropdownMenu) {
    dropdownMenu.addEventListener('click', function(e) {
        e.stopPropagation();
    });
}

// =============== HAMBURGER MENU TOGGLE ===============
const mobileBtn = document.getElementById('mobileMenuBtn');
const sidebar = document.getElementById('sidebar');
const overlay = document.getElementById('overlay');
const hamburgerBtn = document.getElementById('hamburgerBtn');

function toggleSidebar() {
    sidebar.classList.toggle('show');
    overlay.classList.toggle('show');
    
    // Change icon from bars to times and vice versa
    const mobileIcon = mobileBtn?.querySelector('i');
    const hamburgerIcon = hamburgerBtn?.querySelector('i');
    
    if (sidebar.classList.contains('show')) {
        // Sidebar is open - change to X icon
        if (mobileIcon) {
            mobileIcon.classList.remove('fa-bars');
            mobileIcon.classList.add('fa-times');
        }
        if (hamburgerIcon) {
            hamburgerIcon.classList.remove('fa-bars');
            hamburgerIcon.classList.add('fa-times');
        }
    } else {
        // Sidebar is closed - change back to bars
        if (mobileIcon) {
            mobileIcon.classList.remove('fa-times');
            mobileIcon.classList.add('fa-bars');
        }
        if (hamburgerIcon) {
            hamburgerIcon.classList.remove('fa-times');
            hamburgerIcon.classList.add('fa-bars');
        }
    }
}

// Add click event to both hamburger buttons (desktop and mobile)
if (mobileBtn) mobileBtn.addEventListener('click', toggleSidebar);
if (hamburgerBtn) hamburgerBtn.addEventListener('click', toggleSidebar);

// Close sidebar when clicking on overlay
if (overlay) {
    overlay.addEventListener('click', function() {
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
        
        // Reset icons back to bars
        const mobileIcon = mobileBtn?.querySelector('i');
        const hamburgerIcon = hamburgerBtn?.querySelector('i');
        
        if (mobileIcon) {
            mobileIcon.classList.remove('fa-times');
            mobileIcon.classList.add('fa-bars');
        }
        if (hamburgerIcon) {
            hamburgerIcon.classList.remove('fa-times');
            hamburgerIcon.classList.add('fa-bars');
        }
    });
}

// Close sidebar when clicking a nav link (for mobile)
const navLinks = document.querySelectorAll('.nav-link');
navLinks.forEach(link => {
    link.addEventListener('click', function() {
        if (window.innerWidth <= 768) {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
            
            // Reset icons
            const mobileIcon = mobileBtn?.querySelector('i');
            const hamburgerIcon = hamburgerBtn?.querySelector('i');
            
            if (mobileIcon) {
                mobileIcon.classList.remove('fa-times');
                mobileIcon.classList.add('fa-bars');
            }
            if (hamburgerIcon) {
                hamburgerIcon.classList.remove('fa-times');
                hamburgerIcon.classList.add('fa-bars');
            }
        }
    });
});

// Charts
document.addEventListener('DOMContentLoaded', function() {
    // Project Status Chart
    const ctx = document.getElementById('projectStatusChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Approved', 'Pending', 'Rejected', 'Archived'],
            datasets: [{
                data: [23, 11, 5, 42],
                backgroundColor: ['#10b981', '#f59e0b', '#ef4444', '#6b7280'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 15,
                        usePointStyle: true,
                        pointStyle: 'circle',
                        color: document.body.classList.contains('dark-mode') ? '#e5e7eb' : '#0f172a'
                    }
                }
            },
            cutout: '70%'
        }
    });
});