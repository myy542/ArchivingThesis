<?php
session_start();
include("../config/db.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

// For design purposes - kita lang sa design
$user_id = 1; // Sample user ID
$first = "Maria";
$last = "Santos";
$fullName = "Maria Santos";
$initials = "MS";
$notificationCount = 3; // Sample notification count


$studentCount = 342;
$facultyCount = 28;
$totalProjects = 87;
$pendingReviews = 11;
$completedTheses = 42;
$ongoingProjects = 34;
$upcomingDefenses = 4;
$approvedThisSem = 23;

// Sample faculty workload
$facultyWorkload = [
    ['name' => 'Dr. Maria Santos', 'workload' => 10],
    ['name' => 'Prof. Juan Cruz', 'workload' => 9],
    ['name' => 'Dr. Ana Reyes', 'workload' => 8],
    ['name' => 'Prof. Pedro Garcia', 'workload' => 7],
    ['name' => 'Dr. Lisa Villanueva', 'workload' => 6],
];

$pageTitle = "Librarian Dashboard";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Theses Archiving System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../student/css/base.css">
    <link rel="stylesheet" href="css/librarian_dashboard.css">
    <style>
        /* Additional styles para sure na visible ang hamburger */
        .hamburger-menu {
            display: flex !important;
            cursor: pointer;
        }
        
        /* Sidebar styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: -300px;
            width: 280px;
            height: 100vh;
            background: linear-gradient(180deg, #FE4853 0%, #732529 100%);
            color: white;
            display: flex;
            flex-direction: column;
            z-index: 1000;
            transition: left 0.3s ease;
            box-shadow: 5px 0 20px rgba(0,0,0,0.3);
        }

        .sidebar.show {
            left: 0;
        }

        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
        }

        .overlay.show {
            display: block;
        }

        /* Mobile menu button */
        .mobile-menu-btn {
            position: fixed;
            top: 16px;
            right: 16px;
            z-index: 1001;
            border: none;
            background: #FE4853;
            color: #fff;
            padding: 12px 15px;
            border-radius: 10px;
            cursor: pointer;
            display: none;
            font-size: 1.2rem;
            box-shadow: 0 4px 12px rgba(254, 72, 83, 0.3);
            border: 1px solid white;
        }

        @media (max-width: 768px) {
            .mobile-menu-btn {
                display: flex;
                align-items: center;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

<!-- OVERLAY -->
<div class="overlay" id="overlay"></div>

<!-- MOBILE MENU BUTTON (para sa phone) -->
<button class="mobile-menu-btn" id="mobileMenuBtn">
    <i class="fas fa-bars"></i>
</button>

<div class="layout">
    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h2>ThesisManager</h2>
            <p>LIBRARIAN</p>
        </div>

        <nav class="sidebar-nav">
            <a href="librarian_dashboard.php" class="nav-link active">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <a href="#" class="nav-link">
                <i class="fas fa-folder-open"></i> Projects
            </a>
            <a href="#" class="nav-link">
                <i class="fas fa-archive"></i> Archive
            </a>
            <a href="#" class="nav-link">
                <i class="fas fa-cog"></i> Department
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="theme-toggle">
                <input type="checkbox" id="darkmode" />
                <label for="darkmode" class="toggle-label">
                    <i class="fas fa-sun"></i>
                    <i class="fas fa-moon"></i>
                    <span class="slider"></span>
                </label>
            </div>
            <a href="/ArchivingThesis/authentication/logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </aside>

    <main class="main-content">
        <!-- TOPBAR -->
        <header class="topbar">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <!-- Three-line menu (hamburger) -->
                <div class="hamburger-menu" id="hamburgerBtn">
                    <i class="fas fa-bars"></i>
                </div>
                <div class="dashboard-header">
                    <h1>ThesisManager</h1>
                    <p>LIBRARIAN • Department Dashboard • Overview of faculty, students, and projects</p>
                </div>
            </div>

            <div class="user-info">
                <a href="#" class="notification-bell">
                    <i class="fas fa-bell"></i>
                    <?php if ($notificationCount > 0): ?>
                        <span class="notification-badge"><?= $notificationCount ?></span>
                    <?php endif; ?>
                </a>
                
                <div class="avatar-container">
                    <div class="avatar-dropdown">
                        <div class="avatar" id="avatarBtn">
                            <?= htmlspecialchars($initials) ?>
                        </div>
                        <div class="dropdown-content" id="dropdownMenu">
                            <a href="#"><i class="fas fa-user-circle"></i> Profile</a>
                            <a href="#"><i class="fas fa-cog"></i> Settings</a>
                            <hr>
                            <a href="/ArchivingThesis/authentication/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- STATS CARDS -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="stat-content">
                    <h3><?= number_format($studentCount) ?></h3>
                    <p>Students</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>
                <div class="stat-content">
                    <h3><?= number_format($facultyCount) ?></h3>
                    <p>Faculty</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-folder-open"></i>
                </div>
                <div class="stat-content">
                    <h3><?= number_format($totalProjects) ?></h3>
                    <p>Total Projects</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3><?= number_format($pendingReviews) ?></h3>
                    <p>Pending Reviews</p>
                </div>
            </div>
        </div>

        <!-- QUICK STATS ROW -->
        <div class="quick-stats">
            <div class="quick-stat-card">
                <div class="quick-stat-info">
                    <h4><?= number_format($completedTheses) ?></h4>
                    <p>completed theses & projects</p>
                </div>
                <div class="quick-stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>

            <div class="quick-stat-card">
                <div class="quick-stat-info">
                    <h4><?= number_format($ongoingProjects) ?></h4>
                    <p>active projects</p>
                </div>
                <div class="quick-stat-icon">
                    <i class="fas fa-play-circle"></i>
                </div>
            </div>

            <div class="quick-stat-card">
                <div class="quick-stat-info">
                    <h4><?= number_format($upcomingDefenses) ?></h4>
                    <p>upcoming defenses</p>
                </div>
                <div class="quick-stat-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
            </div>

            <div class="quick-stat-card">
                <div class="quick-stat-info">
                    <h4><?= number_format($approvedThisSem) ?></h4>
                    <p>theses this sem</p>
                </div>
                <div class="quick-stat-icon">
                    <i class="fas fa-award"></i>
                </div>
            </div>
        </div>

        <!-- CHARTS ROW -->
        <div class="charts-row">
            <div class="chart-card">
                <div class="chart-header">
                    <h3>Project Status Distribution</h3>
                    <select>
                        <option>This Year</option>
                        <option>This Semester</option>
                        <option>All Time</option>
                    </select>
                </div>
                <div class="chart-container">
                    <canvas id="projectStatusChart"></canvas>
                </div>
            </div>

            <div class="faculty-workload">
                <h3>Faculty Workload</h3>
                <div class="workload-list">
                    <?php foreach ($facultyWorkload as $faculty): ?>
                    <div class="workload-item">
                        <div class="workload-avatar">
                            <?= substr($faculty['name'], -2) ?>
                        </div>
                        <div class="workload-info">
                            <h4><?= htmlspecialchars($faculty['name']) ?></h4>
                            <div class="workload-bar">
                                <div class="workload-fill" style="width: <?= $faculty['workload'] * 10 ?>%"></div>
                            </div>
                        </div>
                        <div class="workload-value"><?= $faculty['workload'] ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- RECENT ACTIVITY -->
        <div class="recent-activity">
            <h3>Recent Activity</h3>
            <div class="activity-list">
                <div class="activity-item">
                    <div class="activity-icon">
                        <i class="fas fa-upload"></i>
                    </div>
                    <div class="activity-content">
                        <p>New thesis submitted by John Doe</p>
                        <span class="activity-time">2 minutes ago</span>
                    </div>
                </div>
                <div class="activity-item">
                    <div class="activity-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="activity-content">
                        <p>Thesis approved: "Machine Learning in Education"</p>
                        <span class="activity-time">1 hour ago</span>
                    </div>
                </div>
                <div class="activity-item">
                    <div class="activity-icon">
                        <i class="fas fa-comment"></i>
                    </div>
                    <div class="activity-content">
                        <p>Feedback given on thesis by Dr. Santos</p>
                        <span class="activity-time">3 hours ago</span>
                    </div>
                </div>
                <div class="activity-item">
                    <div class="activity-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div class="activity-content">
                        <p>New faculty account created</p>
                        <span class="activity-time">1 day ago</span>
                    </div>
                </div>
            </div>
        </div>

    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// =============== SIMPLE HAMBURGER MENU TOGGLE ===============
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded - hamburger menu initializing...');
    
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const mobileBtn = document.getElementById('mobileMenuBtn');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    
    console.log('Hamburger button:', hamburgerBtn);
    console.log('Mobile button:', mobileBtn);
    console.log('Sidebar:', sidebar);
    console.log('Overlay:', overlay);
    
    // Function to toggle sidebar
    function toggleSidebar() {
        console.log('Toggle sidebar called');
        sidebar.classList.toggle('show');
        overlay.classList.toggle('show');
        
        // Change icon
        const hamburgerIcon = hamburgerBtn?.querySelector('i');
        const mobileIcon = mobileBtn?.querySelector('i');
        
        if (sidebar.classList.contains('show')) {
            console.log('Sidebar opened');
            if (hamburgerIcon) {
                hamburgerIcon.classList.remove('fa-bars');
                hamburgerIcon.classList.add('fa-times');
            }
            if (mobileIcon) {
                mobileIcon.classList.remove('fa-bars');
                mobileIcon.classList.add('fa-times');
            }
        } else {
            console.log('Sidebar closed');
            if (hamburgerIcon) {
                hamburgerIcon.classList.remove('fa-times');
                hamburgerIcon.classList.add('fa-bars');
            }
            if (mobileIcon) {
                mobileIcon.classList.remove('fa-times');
                mobileIcon.classList.add('fa-bars');
            }
        }
    }
    
    // Add click events
    if (hamburgerBtn) {
        hamburgerBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            toggleSidebar();
        });
        console.log('Click event added to hamburger button');
    }
    
    if (mobileBtn) {
        mobileBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            toggleSidebar();
        });
        console.log('Click event added to mobile button');
    }
    
    // Close when clicking overlay
    if (overlay) {
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
            
            const hamburgerIcon = hamburgerBtn?.querySelector('i');
            const mobileIcon = mobileBtn?.querySelector('i');
            
            if (hamburgerIcon) {
                hamburgerIcon.classList.remove('fa-times');
                hamburgerIcon.classList.add('fa-bars');
            }
            if (mobileIcon) {
                mobileIcon.classList.remove('fa-times');
                mobileIcon.classList.add('fa-bars');
            }
        });
    }
    
    // Close when clicking nav links on mobile
    const navLinks = document.querySelectorAll('.nav-link');
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
                
                const hamburgerIcon = hamburgerBtn?.querySelector('i');
                const mobileIcon = mobileBtn?.querySelector('i');
                
                if (hamburgerIcon) {
                    hamburgerIcon.classList.remove('fa-times');
                    hamburgerIcon.classList.add('fa-bars');
                }
                if (mobileIcon) {
                    mobileIcon.classList.remove('fa-times');
                    mobileIcon.classList.add('fa-bars');
                }
            }
        });
    });
});

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

// Charts
document.addEventListener('DOMContentLoaded', function() {
    // Project Status Chart
    const ctx = document.getElementById('projectStatusChart');
    if (ctx) {
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
    }
});
</script>

</body>
</html>