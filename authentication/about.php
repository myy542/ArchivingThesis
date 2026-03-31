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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - Web-Based Thesis Archiving System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #fef2f2;
            color: #1f2937;
        }

        body.dark-mode {
            background: #0f172a;
            color: #e5e7eb;
        }

        /* Top Navigation - RED BACKGROUND */
        .top-nav {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: linear-gradient(135deg, #b71c1c 0%, #d32f2f 100%);
            padding: 0.8rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            z-index: 100;
            border-bottom: none;
            flex-wrap: wrap;
        }

        body.dark-mode .top-nav {
            background: linear-gradient(135deg, #b71c1c 0%, #d32f2f 100%);
        }

        .logo {
            font-size: 1.3rem;
            font-weight: 700;
            color: white;
            text-decoration: none;
        }

        .logo span {
            color: #ffcdd2;
        }

        .nav-links {
            display: flex;
            gap: 2rem;
            list-style: none;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: 0.3s;
            opacity: 0.9;
        }

        .nav-links a:hover {
            opacity: 1;
            transform: translateY(-2px);
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .profile-wrapper {
            position: relative;
        }

        .profile-trigger {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 40px;
            transition: background 0.3s;
        }

        .profile-trigger:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .profile-name {
            font-weight: 500;
            color: white;
        }

        .profile-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        .profile-dropdown {
            position: absolute;
            top: 55px;
            right: 0;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            min-width: 200px;
            display: none;
            overflow: hidden;
            z-index: 1000;
            border: 1px solid #ffcdd2;
        }

        body.dark-mode .profile-dropdown {
            background: #1e293b;
            border-color: #334155;
        }

        .profile-dropdown.show {
            display: block;
            animation: fadeIn 0.2s ease;
        }

        .profile-dropdown a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: #1f2937;
            text-decoration: none;
            transition: background 0.2s;
        }

        body.dark-mode .profile-dropdown a {
            color: #e5e7eb;
        }

        .profile-dropdown a:hover {
            background: #ffebee;
        }

        .profile-dropdown hr {
            margin: 0;
            border-color: #ffcdd2;
        }

        /* Main Content */
        .main-content {
            margin-top: 80px;
            padding: 40px 30px;
            max-width: 1200px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Hero Section */
        .about-hero {
            background: linear-gradient(135deg, #b71c1c 0%, #d32f2f 100%);
            border-radius: 24px;
            padding: 50px 40px;
            text-align: center;
            color: white;
            margin-bottom: 50px;
        }

        .about-hero h1 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 20px;
        }

        .about-hero p {
            font-size: 1.1rem;
            opacity: 0.95;
            max-width: 800px;
            margin: 0 auto;
            line-height: 1.6;
        }

        /* Content Sections */
        .content-section {
            background: white;
            border-radius: 20px;
            padding: 40px;
            margin-bottom: 40px;
            border: 1px solid #ffcdd2;
        }

        body.dark-mode .content-section {
            background: #1e293b;
            border-color: #334155;
        }

        .section-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: #d32f2f;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-title i {
            font-size: 2rem;
        }

        .section-text {
            color: #4b5563;
            line-height: 1.8;
            font-size: 1rem;
            margin-bottom: 20px;
        }

        body.dark-mode .section-text {
            color: #cbd5e1;
        }

        /* Features Grid */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
            margin-top: 20px;
        }

        .feature-box {
            background: #fef2f2;
            border-radius: 16px;
            padding: 25px;
            text-align: center;
            transition: all 0.3s;
        }

        body.dark-mode .feature-box {
            background: #334155;
        }

        .feature-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(211, 47, 47, 0.15);
        }

        .feature-box i {
            font-size: 2.5rem;
            color: #d32f2f;
            margin-bottom: 15px;
        }

        .feature-box h3 {
            font-size: 1.2rem;
            margin-bottom: 10px;
            color: #1f2937;
        }

        body.dark-mode .feature-box h3 {
            color: #e5e7eb;
        }

        .feature-box p {
            color: #6b7280;
            font-size: 0.9rem;
            line-height: 1.5;
        }

        body.dark-mode .feature-box p {
            color: #94a3b8;
        }

        /* Users Grid */
        .users-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }

        .user-card {
            background: #fef2f2;
            border-radius: 16px;
            padding: 25px;
            text-align: center;
            transition: all 0.3s;
        }

        body.dark-mode .user-card {
            background: #334155;
        }

        .user-card:hover {
            transform: translateY(-5px);
        }

        .user-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #b71c1c 0%, #d32f2f 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            color: white;
            font-size: 1.8rem;
        }

        .user-card h3 {
            font-size: 1.1rem;
            margin-bottom: 8px;
            color: #1f2937;
        }

        body.dark-mode .user-card h3 {
            color: #e5e7eb;
        }

        .user-card p {
            color: #6b7280;
            font-size: 0.85rem;
            line-height: 1.4;
        }

        body.dark-mode .user-card p {
            color: #94a3b8;
        }

        /* Workflow */
        .workflow-steps {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 20px;
            margin-top: 20px;
        }

        .workflow-step {
            flex: 1;
            min-width: 150px;
            text-align: center;
            position: relative;
        }

        .step-number {
            width: 40px;
            height: 40px;
            background: #d32f2f;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin: 0 auto 15px;
        }

        .workflow-step h4 {
            margin-bottom: 8px;
            color: #1f2937;
        }

        body.dark-mode .workflow-step h4 {
            color: #e5e7eb;
        }

        .workflow-step p {
            font-size: 0.8rem;
            color: #6b7280;
        }

        body.dark-mode .workflow-step p {
            color: #94a3b8;
        }

        /* Stats */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-top: 20px;
        }

        .stat-box {
            background: #fef2f2;
            border-radius: 16px;
            padding: 25px;
            text-align: center;
        }

        body.dark-mode .stat-box {
            background: #334155;
        }

        .stat-box h3 {
            font-size: 2rem;
            font-weight: 800;
            color: #d32f2f;
            margin-bottom: 5px;
        }

        .stat-box p {
            color: #6b7280;
            font-size: 0.9rem;
        }

        /* Footer */
        footer {
            background: #1e293b;
            color: #e2e8f0;
            padding: 2rem;
            text-align: center;
            margin-top: 40px;
        }

        body.dark-mode footer {
            background: #0f172a;
        }

        footer p {
            margin: 0.5rem 0;
        }

        footer a {
            color: #fbbf24;
            text-decoration: none;
        }

        footer a:hover {
            text-decoration: underline;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .top-nav {
                padding: 0.6rem 1rem;
                flex-direction: column;
                gap: 10px;
            }
            
            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
                gap: 1rem;
            }
            
            .main-content {
                padding: 20px 15px;
                margin-top: 100px;
            }
            
            .about-hero {
                padding: 30px 20px;
            }
            
            .about-hero h1 {
                font-size: 1.8rem;
            }
            
            .section-title {
                font-size: 1.4rem;
            }
            
            .content-section {
                padding: 25px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .profile-name {
                display: none;
            }
            
            .workflow-steps {
                flex-direction: column;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .features-grid {
                grid-template-columns: 1fr;
            }
            
            .users-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <header class="top-nav">
        <div class="logo">Thesis<span>Manager</span></div>
        <div class="nav-links">
            <a href="homepage.php">Home</a>
            <a href="browse.php">Browse</a>
            <a href="about.php" style="color: #ffcdd2; font-weight: 600;">About</a>
            <?php if ($is_logged_in): ?>
                <a href="../student/studentDashboard.php">Dashboard</a>
                <a href="logout.php">Logout</a>
            <?php else: ?>
                <a href="login.php">Login</a>
                <a href="register.php">Register</a>
            <?php endif; ?>
        </div>
        <?php if ($is_logged_in): ?>
        <div class="nav-right">
            <div class="profile-wrapper" id="profileWrapper">
                <div class="profile-trigger">
                    <span class="profile-name"><?= htmlspecialchars($fullName) ?></span>
                    <div class="profile-avatar"><?= htmlspecialchars($initials) ?></div>
                </div>
                <div class="profile-dropdown" id="profileDropdown">
                    <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                    <hr>
                    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </header>

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
        <p>© <?= date('Y') ?> Web-Based Thesis Archiving System</p>
        <p>Preserving Knowledge, Empowering Research</p>
        <p><a href="about.php">About Us</a> | <a href="contact.php">Contact</a> | <a href="privacy.php">Privacy Policy</a></p>
    </footer>

    <script>
        // Profile dropdown
        const profileWrapper = document.getElementById('profileWrapper');
        const profileDropdown = document.getElementById('profileDropdown');

        if (profileWrapper && profileDropdown) {
            profileWrapper.addEventListener('click', function(e) {
                e.stopPropagation();
                profileDropdown.classList.toggle('show');
            });

            document.addEventListener('click', function(e) {
                if (profileDropdown.classList.contains('show') && 
                    !profileWrapper.contains(e.target)) {
                    profileDropdown.classList.remove('show');
                }
            });
        }

        // Dark Mode
        const darkMode = localStorage.getItem('darkMode') === 'true';
        if (darkMode) {
            document.body.classList.add('dark-mode');
        }
    </script>
</body>
</html>