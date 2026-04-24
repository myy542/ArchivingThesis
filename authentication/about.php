<?php
session_start();
include("../config/db.php");
error_reporting(E_ALL);
ini_set('display_errors', 1);

// NO LOGIN REQUIRED - anyone can view this page

$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['role'] ?? '';
$user_first_name = $_SESSION['first_name'] ?? '';
$user_last_name = $_SESSION['last_name'] ?? '';

// Get user info if logged in
$fullName = '';
$initials = '';
if ($user_id) {
    $fullName = $user_first_name . ' ' . $user_last_name;
    $initials = strtoupper(substr($user_first_name, 0, 1) . substr($user_last_name, 0, 1));
}

$pageTitle = "About Us";
$is_logged_in = isset($_SESSION['user_id']);

// Get user role and dashboard link if logged in
$dashboardLink = '#';
if ($is_logged_in) {
    $roleQuery = "SELECT role_id FROM user_table WHERE user_id = ? LIMIT 1";
    $stmt = $conn->prepare($roleQuery);
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $userRole = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($userRole) {
        if ($userRole['role_id'] == 3) $dashboardLink = '../faculty/facultyDashboard.php';
        elseif ($userRole['role_id'] == 2) $dashboardLink = '../student/student_dashboard.php';
        elseif ($userRole['role_id'] == 1) $dashboardLink = '../admin/admindashboard.php';
        elseif ($userRole['role_id'] == 6) $dashboardLink = '../coordinator/coordinatorDashboard.php';
        elseif ($userRole['role_id'] == 4) $dashboardLink = '../departmentDeanDashboard/dean.php';
        elseif ($userRole['role_id'] == 5) $dashboardLink = '../librarian/librarian_dashboard.php';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - Web-Based Thesis Archiving System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- External CSS -->
    <link rel="stylesheet" href="css/about.css">
</head>
<body>
    <div class="theme-toggle" id="themeToggle"><i class="fas fa-moon"></i></div>

    <nav class="navbar">
        <div class="nav-container">
            <a href="homepage.php" class="logo">
                <div class="logo-icon">📚</div>
                <span>Thesis Archive</span>
            </a>
            <ul class="nav-links">
                <li><a href="homepage.php">Home</a></li>
                <li><a href="browse.php">Browse</a></li>
                <li><a href="about.php" class="active">About</a></li>
                <?php if ($is_logged_in): ?>
                    <li><a href="<?= $dashboardLink ?>">Dashboard</a></li>
                    <li><a href="../authentication/logout.php">Logout</a></li>
                <?php else: ?>
                    <li><a href="login.php">Login</a></li>
                    <li><a href="register.php">Register</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <main class="main-content">
        <!-- Hero Section -->
        <div class="about-hero">
            <h1>About Web-Based Thesis Archiving System</h1>
            <p>A comprehensive digital platform designed to streamline the management, storage, and retrieval of academic theses and research documents.</p>
        </div>

        <!-- Mission & Vision -->
        <div class="content-section">
            <div class="section-title">
                <i class="fas fa-bullseye"></i>
                <span>Mission & Vision</span>
            </div>
            <div class="section-text">
                <strong>Mission:</strong> To provide a secure, efficient, and user-friendly digital repository for academic theses that preserves scholarly work and makes it accessible to students, faculty, and researchers.
            </div>
            <div class="section-text">
                <strong>Vision:</strong> To become the premier thesis archiving platform that empowers academic institutions in preserving and sharing knowledge for future generations.
            </div>
        </div>

        <!-- What is the System -->
        <div class="content-section">
            <div class="section-title">
                <i class="fas fa-question-circle"></i>
                <span>What is the Web-Based Thesis Archiving System?</span>
            </div>
            <div class="section-text">
                The Web-Based Thesis Archiving System is a digital platform that allows academic institutions to manage, store, and retrieve thesis documents electronically. It provides a centralized repository where students can submit their theses, faculty advisers can review and provide feedback, research coordinators can evaluate submissions, deans can approve final outputs, librarians can manage the archiving process, and the system administrator oversees the entire platform.
            </div>
            <div class="section-text">
                This system eliminates the need for physical storage, reduces paperwork, and ensures that academic research is preserved for future reference. It streamlines the entire thesis workflow from submission to final archiving.
            </div>
        </div>

        <!-- Key Features -->
        <div class="content-section">
            <div class="section-title">
                <i class="fas fa-star"></i>
                <span>Key Features</span>
            </div>
            <div class="features-grid">
                <div class="feature-box">
                    <i class="fas fa-upload"></i>
                    <h3>Thesis Submission</h3>
                    <p>Students can easily submit their thesis documents with complete details and abstract.</p>
                </div>
                <div class="feature-box">
                    <i class="fas fa-chalkboard-user"></i>
                    <h3>Adviser Review</h3>
                    <p>Faculty advisers can review submissions and provide feedback or forward to coordinators.</p>
                </div>
                <div class="feature-box">
                    <i class="fas fa-clipboard-list"></i>
                    <h3>Coordinator Evaluation</h3>
                    <p>Research coordinators evaluate theses and forward approved ones to the dean.</p>
                </div>
                <div class="feature-box">
                    <i class="fas fa-gavel"></i>
                    <h3>Dean Approval</h3>
                    <p>Deans can approve or reject theses, ensuring quality academic standards.</p>
                </div>
                <div class="feature-box">
                    <i class="fas fa-archive"></i>
                    <h3>Librarian Archiving</h3>
                    <p>Librarians manage the final archiving process and organize the digital repository.</p>
                </div>
                <div class="feature-box">
                    <i class="fas fa-user-cog"></i>
                    <h3>Admin Management</h3>
                    <p>System administrator manages user accounts, system settings, and overall platform maintenance.</p>
                </div>
                <div class="feature-box">
                    <i class="fas fa-download"></i>
                    <h3>Easy Retrieval</h3>
                    <p>Users can search and download archived theses by title, keywords, author, or department.</p>
                </div>
            </div>
        </div>

        <!-- System Users -->
        <div class="content-section">
            <div class="section-title">
                <i class="fas fa-users"></i>
                <span>System Users & Their Roles</span>
            </div>
            <div class="users-grid">
                <div class="user-card">
                    <div class="user-icon"><i class="fas fa-user-graduate"></i></div>
                    <h3>Student</h3>
                    <p>Submits thesis, checks feedback, tracks approval status, and views archived theses.</p>
                </div>
                <div class="user-card">
                    <div class="user-icon"><i class="fas fa-chalkboard-user"></i></div>
                    <h3>Faculty Adviser</h3>
                    <p>Reviews student theses, provides feedback, and forwards approved theses to coordinator.</p>
                </div>
                <div class="user-card">
                    <div class="user-icon"><i class="fas fa-clipboard-list"></i></div>
                    <h3>Research Coordinator</h3>
                    <p>Evaluates theses, approves for dean review, or returns for revision.</p>
                </div>
                <div class="user-card">
                    <div class="user-icon"><i class="fas fa-gavel"></i></div>
                    <h3>Dean</h3>
                    <p>Final approval authority for thesis submissions before archiving.</p>
                </div>
                <div class="user-card">
                    <div class="user-icon"><i class="fas fa-book"></i></div>
                    <h3>Librarian</h3>
                    <p>Manages the final archiving process, organizes digital repository, and ensures proper categorization.</p>
                </div>
                <div class="user-card">
                    <div class="user-icon"><i class="fas fa-user-cog"></i></div>
                    <h3>Administrator</h3>
                    <p>Oversees the entire system, manages user accounts, configures system settings, and monitors platform activity.</p>
                </div>
            </div>
        </div>

        <!-- Thesis Workflow -->
        <div class="content-section">
            <div class="section-title">
                <i class="fas fa-flowchart"></i>
                <span>Thesis Workflow</span>
            </div>
            <div class="workflow-steps">
                <div class="workflow-step">
                    <div class="step-number">1</div>
                    <h4>Student Submission</h4>
                    <p>Student submits thesis for review</p>
                </div>
                <div class="workflow-step">
                    <div class="step-number">2</div>
                    <h4>Adviser Review</h4>
                    <p>Adviser reviews and provides feedback</p>
                </div>
                <div class="workflow-step">
                    <div class="step-number">3</div>
                    <h4>Coordinator Evaluation</h4>
                    <p>Coordinator evaluates and forwards to dean</p>
                </div>
                <div class="workflow-step">
                    <div class="step-number">4</div>
                    <h4>Dean Approval</h4>
                    <p>Dean approves or rejects the thesis</p>
                </div>
                <div class="workflow-step">
                    <div class="step-number">5</div>
                    <h4>Librarian Archiving</h4>
                    <p>Librarian finalizes archiving process</p>
                </div>
                <div class="workflow-step">
                    <div class="step-number">6</div>
                    <h4>Admin Oversight</h4>
                    <p>Admin monitors and manages the system</p>
                </div>
            </div>
        </div>

        <!-- System Impact -->
        <div class="content-section">
            <div class="section-title">
                <i class="fas fa-chart-line"></i>
                <span>System Impact</span>
            </div>
            <div class="stats-grid">
                <div class="stat-box">
                    <h3>500+</h3>
                    <p>Archived Theses</p>
                </div>
                <div class="stat-box">
                    <h3>50+</h3>
                    <p>Faculty Users</p>
                </div>
                <div class="stat-box">
                    <h3>300+</h3>
                    <p>Student Users</p>
                </div>
                <div class="stat-box">
                    <h3>24/7</h3>
                    <p>Availability</p>
                </div>
            </div>
        </div>

        <!-- Technology Stack -->
        <div class="content-section">
            <div class="section-title">
                <i class="fas fa-code"></i>
                <span>Technology Stack</span>
            </div>
            <div class="features-grid">
                <div class="feature-box">
                    <i class="fab fa-php"></i>
                    <h3>PHP</h3>
                    <p>Backend processing and server-side logic</p>
                </div>
                <div class="feature-box">
                    <i class="fas fa-database"></i>
                    <h3>MySQL</h3>
                    <p>Database management and data storage</p>
                </div>
                <div class="feature-box">
                    <i class="fab fa-html5"></i>
                    <h3>HTML5/CSS3</h3>
                    <p>Frontend structure and styling</p>
                </div>
                <div class="feature-box">
                    <i class="fab fa-js"></i>
                    <h3>JavaScript</h3>
                    <p>Interactive features and dynamic content</p>
                </div>
            </div>
        </div>
    </main>

    <footer>
        <p>&copy; 2024 Web-Based Thesis Archiving System. All rights reserved.</p>
        <p>Empowering academic research through digital preservation</p>
    </footer>

    <!-- Pass PHP variables to JavaScript -->
    <script>
        window.isLoggedIn = <?php echo $is_logged_in ? 'true' : 'false'; ?>;
    </script>
    
    <!-- External JavaScript -->
    <script src="js/about.js"></script>
</body>
</html>