<?php
session_start();
include("../config/db.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Sample data - replace with actual database queries
$user_id = $_SESSION["user_id"] ?? 1;
$first_name = "Jason";
$last_name = "Santos";
$fullName = "$first_name $last_name";
$initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));

// Dashboard stats
$requestsCount = 24;
$pointsCount = 86;
$ratingScore = 4.5;
$completedRequests = 164;

// Skills progress
$skills = [
    ['name' => 'Programming', 'progress' => 80],
    ['name' => 'Editing', 'progress' => 75],
    ['name' => 'Design', 'progress' => 85],
    ['name' => 'Marketing', 'progress' => 65],
];

// Recent projects
$projects = [
    ['title' => 'Design UI/UX for X Company', 'creator' => 'Request Managers', 'rating' => 4.5],
    ['title' => 'Developing mobile App', 'creator' => 'Request Manager', 'rating' => 4.5],
    ['title' => 'Editing Video Company', 'creator' => 'Request Managers', 'rating' => 4.5],
    ['title' => 'Design UI/UX for X...', 'creator' => 'Request Managers', 'rating' => 4.5],
];

// Leaderboard
$leaderboard = [
    ['name' => 'John Doe', 'points' => 1240],
    ['name' => 'Jane Smith', 'points' => 980],
    ['name' => 'Mike Johnson', 'points' => 875],
    ['name' => 'Sarah Williams', 'points' => 720],
    ['name' => 'David Brown', 'points' => 650],
];

// Recommended skills
$recommendedSkills = [
    ['name' => 'Accounting', 'action' => 'Learn Skill'],
    ['name' => 'Copywriting', 'action' => 'Learn Skill'],
    ['name' => 'Finance', 'action' => 'Learn Skill'],
];

$pageTitle = "Coordinator Dashboard";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Thesis Archiving System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #f5f7fb;
            color: #1e293b;
        }

        body.dark-mode {
            background: #0f172a;
            color: #e5e7eb;
        }

        /* Layout */
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #FE4853 0%, #732529 100%);
            color: white;
            padding: 2rem 1.5rem;
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            transition: transform 0.3s ease;
            z-index: 1000;
        }

        .sidebar-logo h2 {
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
            font-weight: 700;
        }

        .sidebar-logo p {
            font-size: 0.8rem;
            opacity: 0.8;
            margin-bottom: 2rem;
        }

        .sidebar-nav {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.2s;
            font-weight: 500;
        }

        .nav-item:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .nav-item.active {
            background: rgba(255, 255, 255, 0.3);
            font-weight: 600;
        }

        .nav-item i {
            width: 24px;
            font-size: 1.2rem;
        }

        .sidebar-footer {
            margin-top: auto;
            padding-top: 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 2rem;
        }

        /* Topbar */
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .welcome h1 {
            font-size: 1.8rem;
            color: #732529;
            margin-bottom: 0.25rem;
        }

        body.dark-mode .welcome h1 {
            color: #FE4853;
        }

        .welcome p {
            color: #6b7280;
        }

        .search-bar {
            display: flex;
            align-items: center;
            background: white;
            border-radius: 30px;
            padding: 0.5rem 1rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        body.dark-mode .search-bar {
            background: #1e293b;
        }

        .search-bar input {
            border: none;
            outline: none;
            padding: 0.5rem;
            font-size: 0.9rem;
            width: 200px;
            background: transparent;
        }

        .search-bar i {
            color: #FE4853;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .avatar {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #FE4853, #732529);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
            color: white;
            cursor: pointer;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.2s;
        }

        body.dark-mode .stat-card {
            background: #1e293b;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .stat-info h3 {
            font-size: 2rem;
            font-weight: 700;
            color: #732529;
        }

        body.dark-mode .stat-info h3 {
            color: #FE4853;
        }

        .stat-info p {
            color: #6b7280;
            font-size: 0.85rem;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            background: rgba(254, 72, 83, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: #FE4853;
        }

        /* Two Column Layout */
        .two-columns {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        /* Cards */
        .card {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        body.dark-mode .card {
            background: #1e293b;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .card-header h3 {
            font-size: 1.2rem;
            font-weight: 600;
            color: #732529;
        }

        body.dark-mode .card-header h3 {
            color: #FE4853;
        }

        .card-header a {
            color: #FE4853;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
        }

        /* Skills Progress */
        .skill-item {
            margin-bottom: 1.2rem;
        }

        .skill-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .progress-bar {
            height: 8px;
            background: #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #FE4853, #732529);
            border-radius: 10px;
            transition: width 0.3s ease;
        }

        /* Rating Stars */
        .rating {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            margin-top: 0.5rem;
        }

        .rating i {
            color: #fbbf24;
            font-size: 0.9rem;
        }

        .rating span {
            margin-left: 0.5rem;
            color: #6b7280;
            font-size: 0.85rem;
        }

        /* Project Items */
        .project-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .project-item:last-child {
            border-bottom: none;
        }

        .project-info h4 {
            font-size: 1rem;
            margin-bottom: 0.25rem;
        }

        .project-info p {
            font-size: 0.8rem;
            color: #6b7280;
        }

        /* Leaderboard */
        .leaderboard-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.8rem 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .leaderboard-rank {
            font-weight: 700;
            color: #FE4853;
            width: 40px;
        }

        .leaderboard-name {
            flex: 1;
        }

        .leaderboard-points {
            font-weight: 600;
        }

        /* Recommended Skills */
        .skill-tag {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.8rem 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .skill-tag:last-child {
            border-bottom: none;
        }

        .skill-tag a {
            color: #FE4853;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
        }

        /* Mobile Menu */
        .mobile-menu-btn {
            position: fixed;
            top: 16px;
            right: 16px;
            z-index: 1001;
            background: #FE4853;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 10px;
            cursor: pointer;
            display: none;
            font-size: 1.2rem;
        }

        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 999;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .two-columns {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            .mobile-menu-btn {
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .topbar {
                flex-direction: column;
                align-items: flex-start;
            }
            .search-bar {
                width: 100%;
            }
            .search-bar input {
                width: 100%;
            }
        }
    </style>
</head>
<body>

<div class="overlay" id="overlay"></div>
<button class="mobile-menu-btn" id="mobileMenuBtn">
    <i class="fas fa-bars"></i>
</button>

<div class="dashboard-container">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-logo">
            <h2>Sahara</h2>
            <p>Dashboard</p>
        </div>

        <nav class="sidebar-nav">
            <a href="#" class="nav-item active">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <a href="#" class="nav-item">
                <i class="fas fa-chart-line"></i> Analytics
            </a>
            <a href="#" class="nav-item">
                <i class="fas fa-users"></i> Users
            </a>
            <a href="#" class="nav-item">
                <i class="fas fa-folder-open"></i> Projects
            </a>
            <a href="#" class="nav-item">
                <i class="fas fa-trophy"></i> Leaderboard
            </a>
            <a href="#" class="nav-item">
                <i class="fas fa-cog"></i> Settings
            </a>
        </nav>

        <div class="sidebar-footer">
            <a href="#" class="nav-item">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <div class="topbar">
            <div class="welcome">
                <h1>Welcome back, <?= htmlspecialchars($first_name) ?>!</h1>
                <p>Keep it up and improve your skills!</p>
            </div>
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Search..">
            </div>
            <div class="user-info">
                <div class="avatar" id="avatarBtn"><?= $initials ?></div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?= $requestsCount ?></h3>
                    <p>Requests</p>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-tasks"></i>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?= $pointsCount ?></h3>
                    <p>Points</p>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-star"></i>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?= $ratingScore ?></h3>
                    <p>Rating</p>
                    <div class="rating">
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star"></i>
                        <i class="fas fa-star-half-alt"></i>
                    </div>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?= $completedRequests ?></h3>
                    <p>Completed requests</p>
                </div>
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
            </div>
        </div>

        <!-- Two Columns -->
        <div class="two-columns">
            <!-- Left Column -->
            <div>
                <!-- Your Skills -->
                <div class="card">
                    <div class="card-header">
                        <h3>Your skills</h3>
                        <a href="#">See More →</a>
                    </div>
                    <?php foreach ($skills as $skill): ?>
                    <div class="skill-item">
                        <div class="skill-info">
                            <span><?= $skill['name'] ?></span>
                            <span><?= $skill['progress'] ?>%</span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?= $skill['progress'] ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Project Agreement -->
                <div class="card" style="margin-top: 1.5rem;">
                    <div class="card-header">
                        <h3>Project Agreement</h3>
                        <a href="#">See More →</a>
                    </div>
                    <?php foreach (array_slice($projects, 0, 3) as $project): ?>
                    <div class="project-item">
                        <div class="project-info">
                            <h4><?= $project['title'] ?></h4>
                            <p>Created By <?= $project['creator'] ?></p>
                            <div class="rating">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <?php if ($i <= floor($project['rating'])): ?>
                                        <i class="fas fa-star"></i>
                                    <?php elseif ($i - $project['rating'] <= 0.5): ?>
                                        <i class="fas fa-star-half-alt"></i>
                                    <?php else: ?>
                                        <i class="far fa-star"></i>
                                    <?php endif; ?>
                                <?php endfor; ?>
                                <span><?= $project['rating'] ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Right Column -->
            <div>
                <!-- Leaderboard -->
                <div class="card">
                    <div class="card-header">
                        <h3>Leaderboard</h3>
                        <a href="#">See More →</a>
                    </div>
                    <?php foreach ($leaderboard as $index => $user): ?>
                    <div class="leaderboard-item">
                        <div class="leaderboard-rank">#<?= $index + 1 ?></div>
                        <div class="leaderboard-name"><?= $user['name'] ?></div>
                        <div class="leaderboard-points"><?= $user['points'] ?> pts</div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Recommended Skills -->
                <div class="card" style="margin-top: 1.5rem;">
                    <div class="card-header">
                        <h3>Recommended skills?</h3>
                        <a href="#">See More →</a>
                    </div>
                    <?php foreach ($recommendedSkills as $skill): ?>
                    <div class="skill-tag">
                        <span><?= $skill['name'] ?></span>
                        <a href="#"><?= $skill['action'] ?> →</a>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Employee -->
                <div class="card" style="margin-top: 1.5rem;">
                    <div class="card-header">
                        <h3>Employee</h3>
                        <a href="#">See More →</a>
                    </div>
                    <div class="project-item">
                        <div class="project-info">
                            <h4>Design UI/UX for X...</h4>
                            <p>Created By Request Managers</p>
                            <div class="rating">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star-half-alt"></i>
                                <span>4.5</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
    // Mobile menu toggle
    const mobileBtn = document.getElementById('mobileMenuBtn');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');

    function toggleSidebar() {
        sidebar.classList.toggle('show');
        overlay.classList.toggle('show');
        const icon = mobileBtn.querySelector('i');
        if (sidebar.classList.contains('show')) {
            icon.classList.remove('fa-bars');
            icon.classList.add('fa-times');
        } else {
            icon.classList.remove('fa-times');
            icon.classList.add('fa-bars');
        }
    }

    if (mobileBtn) mobileBtn.addEventListener('click', toggleSidebar);
    if (overlay) overlay.addEventListener('click', toggleSidebar);

    // Close sidebar on link click (mobile)
    document.querySelectorAll('.nav-item').forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 768) toggleSidebar();
        });
    });
</script>

</body>
</html>