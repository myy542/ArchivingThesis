<?php
session_start();
include("../config/db.php");
include("../config/archive_manager.php");
include("includes/submission_functions.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION["user_id"])) {
    header("Location: /ArchivingThesis/authentication/login.php");
    exit;
}

$archiveManager = new ArchiveManager($conn);
$user_id = (int)$_SESSION["user_id"];

// Get or create student ID
$student_id = getStudentId($conn, $user_id);

// Get user data
$user = getUserData($conn, $user_id);
if (!$user) {
    session_destroy();
    header("Location: /ArchivingThesis/authentication/login.php");
    exit;
}

$first = trim($user["first_name"] ?? "");
$last  = trim($user["last_name"] ?? "");
$displayName = trim($first . " " . $last);
$initials = $first && $last ? strtoupper(substr($first, 0, 1) . substr($last, 0, 1)) : "U";

// Get notification count
$notificationCount = getNotificationCount($conn, $user_id);

// Handle form submission
$successMessage = "";
$formErrors = [];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['submit_thesis'])) {
    $result = handleThesisSubmission($conn, $user_id, $student_id, $first, $last, $_POST, $_FILES);
    
    if ($result['success']) {
        $_SESSION['submission_success'] = $result['message'];
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $formErrors = $result['errors'];
    }
}

// Check for success message from session
if (isset($_SESSION['submission_success'])) {
    $successMessage = $_SESSION['submission_success'];
    unset($_SESSION['submission_success']);
}

// Get recent submissions
$recentSubmissions = getRecentSubmissions($conn, $student_id);

$pageTitle = "Submit Thesis";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - Theses Archiving System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #f5f5f5;
            color: #000000;
            line-height: 1.6;
        }

        body.dark-mode {
            background: #2d2d2d;
            color: #e0e0e0;
        }

        /* Sidebar Styles */
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

        .sidebar-header {
            padding: 2rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }

        .sidebar-header h2 {
            font-size: 1.5rem;
            margin-bottom: 0.25rem;
            color: white;
        }

        .sidebar-header p {
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.9);
        }

        .sidebar-nav {
            flex: 1;
            padding: 1.5rem 0.5rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.875rem 1rem;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 0.25rem;
            transition: all 0.2s;
            font-weight: 500;
        }

        .nav-link i {
            width: 20px;
            color: white;
        }

        .nav-link:hover, .nav-link.active {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }

        .sidebar-footer {
            padding: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }

        .logout-btn {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.875rem 1rem;
            color: rgba(255, 255, 255, 0.9);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .theme-toggle {
            margin-bottom: 1rem;
        }

        .theme-toggle input {
            display: none;
        }

        .toggle-label {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 30px;
            cursor: pointer;
            position: relative;
        }

        .toggle-label i {
            font-size: 1rem;
            color: white;
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

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 0;
            min-height: 100vh;
            padding: 2rem;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding: 1rem;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(110, 110, 110, 0.1);
        }

        body.dark-mode .topbar {
            background: #3a3a3a;
        }

        .topbar h1 {
            font-size: 1.5rem;
            color: #732529;
        }

        body.dark-mode .topbar h1 {
            color: #FE4853;
        }

        .hamburger-menu {
            font-size: 1.5rem;
            cursor: pointer;
            color: #FE4853;
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .hamburger-menu:hover {
            background: rgba(254, 72, 83, 0.1);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .notification-container {
            position: relative;
        }

        .notification-bell {
            position: relative;
            font-size: 1.2rem;
            color: #6E6E6E;
            text-decoration: none;
            transition: color 0.3s;
        }

        .notification-bell:hover {
            color: #FE4853;
        }

        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #FE4853;
            color: white;
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 10px;
            min-width: 18px;
            text-align: center;
        }

        .avatar-container {
            position: relative;
        }

        .avatar-dropdown {
            position: relative;
            cursor: pointer;
        }

        .avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #FE4853 0%, #732529 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1rem;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            top: 55px;
            right: 0;
            background: white;
            min-width: 200px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 100;
        }

        body.dark-mode .dropdown-content {
            background: #3a3a3a;
        }

        .dropdown-content.show {
            display: block;
        }

        .dropdown-content a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            color: #333;
            text-decoration: none;
            transition: background 0.2s;
        }

        body.dark-mode .dropdown-content a {
            color: #e0e0e0;
        }

        .dropdown-content a:hover {
            background: #f5f5f5;
        }

        body.dark-mode .dropdown-content a:hover {
            background: #4a4a4a;
        }

        .dropdown-content hr {
            margin: 0;
            border: none;
            border-top: 1px solid #e0e0e0;
        }

        /* Submission Container */
        .submission-container {
            max-width: 900px;
            margin: 0 auto;
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .error-container {
            background: #f8d7da;
            color: #721c24;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .error-list {
            list-style: none;
            padding-left: 0;
        }

        .error-list li {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.3rem;
        }

        .submission-card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 2px 8px rgba(110, 110, 110, 0.1);
            margin-bottom: 2rem;
        }

        body.dark-mode .submission-card {
            background: #3a3a3a;
        }

        .submission-card h2 {
            color: #732529;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        body.dark-mode .submission-card h2 {
            color: #FE4853;
        }

        .form-note {
            color: #6E6E6E;
            font-size: 0.85rem;
            margin-bottom: 1.5rem;
        }

        .required {
            color: #FE4853;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #333;
        }

        body.dark-mode .form-group label {
            color: #e0e0e0;
        }

        .form-group label i {
            color: #FE4853;
            margin-right: 0.3rem;
        }

        .form-group input, 
        .form-group select, 
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
            font-family: inherit;
        }

        body.dark-mode .form-group input,
        body.dark-mode .form-group select,
        body.dark-mode .form-group textarea {
            background: #4a4a4a;
            border-color: #6E6E6E;
            color: #e0e0e0;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #FE4853;
            box-shadow: 0 0 0 3px rgba(254, 72, 83, 0.1);
        }

        .form-group textarea {
            min-height: 150px;
            resize: vertical;
        }

        .form-text {
            font-size: 0.75rem;
            color: #6E6E6E;
            margin-top: 0.25rem;
            display: block;
        }

        .file-upload-wrapper {
            border: 2px dashed #e0e0e0;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            background: #fafafa;
            cursor: pointer;
            transition: all 0.3s;
        }

        body.dark-mode .file-upload-wrapper {
            background: #4a4a4a;
            border-color: #6E6E6E;
        }

        .file-upload-wrapper:hover {
            border-color: #FE4853;
            background: #fef2f2;
        }

        .file-upload-info {
            margin-top: 0.5rem;
            font-size: 0.75rem;
            color: #6E6E6E;
        }

        .file-upload-info i {
            color: #FE4853;
        }

        .form-footer {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid #e0e0e0;
        }

        body.dark-mode .form-footer {
            border-top-color: #6E6E6E;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn.primary {
            background: #FE4853;
            color: white;
            flex: 1;
        }

        .btn.primary:hover {
            background: #732529;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(254, 72, 83, 0.3);
        }

        .btn.secondary {
            background: white;
            color: #6E6E6E;
            border: 2px solid #e0e0e0;
        }

        body.dark-mode .btn.secondary {
            background: #4a4a4a;
            color: #e0e0e0;
            border-color: #6E6E6E;
        }

        .btn.secondary:hover {
            background: #f5f5f5;
        }

        /* Recent Submissions */
        .recent-submissions {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(110, 110, 110, 0.1);
        }

        body.dark-mode .recent-submissions {
            background: #3a3a3a;
        }

        .recent-submissions h3 {
            color: #732529;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        body.dark-mode .recent-submissions h3 {
            color: #FE4853;
        }

        .submissions-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .submission-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            background: #f8fafc;
            border-radius: 8px;
            transition: all 0.2s;
        }

        body.dark-mode .submission-item {
            background: #4a4a4a;
        }

        .submission-item:hover {
            transform: translateX(5px);
            background: #f0f0f0;
        }

        .submission-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .submission-info h4 {
            margin: 0;
            font-size: 0.95rem;
            color: #333;
        }

        body.dark-mode .submission-info h4 {
            color: #e0e0e0;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-pending_coordinator {
            background: #cce5ff;
            color: #004085;
        }

        .status-approved {
            background: #d4edda;
            color: #155724;
        }

        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .status-archived {
            background: #d1ecf1;
            color: #0c5460;
        }

        .file-indicator {
            font-size: 0.7rem;
            color: #10b981;
        }

        .submission-item small {
            color: #6E6E6E;
            font-size: 0.7rem;
        }

        .mobile-menu-btn {
            position: fixed;
            top: 16px;
            right: 16px;
            z-index: 1001;
            border: none;
            background: #FE4853;
            color: white;
            padding: 12px 15px;
            border-radius: 10px;
            cursor: pointer;
            display: none;
            font-size: 1.2rem;
        }

        @media (max-width: 768px) {
            .mobile-menu-btn {
                display: block;
            }
            
            .main-content {
                padding: 1rem;
                margin-top: 60px;
            }
            
            .topbar {
                display: none;
            }
            
            .submission-card {
                padding: 1rem;
            }
            
            .form-footer {
                flex-direction: column;
            }
            
            .submission-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            
            .submission-info {
                flex-wrap: wrap;
            }
        }

        @media (max-width: 480px) {
            .submission-card h2 {
                font-size: 1.3rem;
            }
        }
    </style>
</head>
<body>

<div class="overlay" id="overlay"></div>
<button class="mobile-menu-btn" id="mobileMenuBtn"><i class="fas fa-bars"></i></button>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h2>Theses Archive</h2>
        <p>Student Portal</p>
    </div>
    <nav class="sidebar-nav">
        <a href="student_dashboard.php" class="nav-link">
            <i class="fas fa-home"></i> Dashboard
        </a>
        <a href="projects.php" class="nav-link">
            <i class="fas fa-folder-open"></i> My Projects
        </a>
        <a href="submission.php" class="nav-link active">
            <i class="fas fa-upload"></i> Submit Thesis
        </a>
        <a href="archived.php" class="nav-link">
            <i class="fas fa-archive"></i> Archived Theses
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

<div class="layout">
    <main class="main-content">
        <header class="topbar">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div class="hamburger-menu" id="hamburgerBtn">
                    <i class="fas fa-bars"></i>
                </div>
                <h1>Thesis Submission</h1>
            </div>
            <div class="user-info">
                <div class="notification-container">
                    <a href="notification.php" class="notification-bell">
                        <i class="fas fa-bell"></i>
                        <?php if ($notificationCount > 0): ?>
                            <span class="notification-badge"><?= $notificationCount ?></span>
                        <?php endif; ?>
                    </a>
                </div>
                <div class="avatar-container">
                    <div class="avatar-dropdown">
                        <div class="avatar" id="avatarBtn">
                            <?= htmlspecialchars($initials) ?>
                        </div>
                        <div class="dropdown-content" id="dropdownMenu">
                            <a href="profile.php"><i class="fas fa-user-circle"></i> Profile</a>
                            <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                            <hr>
                            <a href="/ArchivingThesis/authentication/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div class="submission-container">
            <?php if ($successMessage): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($successMessage) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($formErrors)): ?>
                <div class="error-container">
                    <ul class="error-list">
                        <?php foreach ($formErrors as $err): ?>
                            <li><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="submission-card">
                <h2><i class="fas fa-upload"></i> New Thesis Submission</h2>
                <p class="form-note">Fields marked with <span class="required">*</span> are required</p>

                <form method="POST" enctype="multipart/form-data" id="submissionForm">
                    <div class="form-group">
                        <label for="title"><i class="fas fa-heading"></i> Thesis Title <span class="required">*</span></label>
                        <input type="text" id="title" name="title" required
                               value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
                               placeholder="Enter the full title of your thesis"
                               minlength="5" maxlength="255">
                        <small class="form-text">Minimum 5 characters, maximum 255 characters</small>
                    </div>

                    <div class="form-group">
                        <label for="abstract"><i class="fas fa-align-left"></i> Abstract <span class="required">*</span></label>
                        <textarea id="abstract" name="abstract" required
                                  placeholder="Provide a comprehensive summary of your thesis (minimum 50 characters)"
                                  minlength="50" maxlength="5000"><?= htmlspecialchars($_POST['abstract'] ?? '') ?></textarea>
                        <small class="form-text">Minimum 50 characters, maximum 5000 characters</small>
                    </div>

                    <div class="form-group">
                        <label for="keywords"><i class="fas fa-tags"></i> Keywords <span class="required">*</span></label>
                        <input type="text" id="keywords" name="keywords" required
                               value="<?= htmlspecialchars($_POST['keywords'] ?? '') ?>"
                               placeholder="e.g., Machine Learning, Education, Data Analysis (separate with commas)">
                        <small class="form-text">Separate keywords with commas. At least 3 keywords.</small>
                    </div>

                    <div class="form-group">
                        <label for="department"><i class="fas fa-building"></i> Department <span class="required">*</span></label>
                        <select id="department" name="department" required>
                            <option value="">Select Department</option>
                            <option value="CS" <?= (isset($_POST['department']) && $_POST['department'] == 'CS') ? 'selected' : '' ?>>Computer Science</option>
                            <option value="IT" <?= (isset($_POST['department']) && $_POST['department'] == 'IT') ? 'selected' : '' ?>>Information Technology</option>
                            <option value="ENG" <?= (isset($_POST['department']) && $_POST['department'] == 'ENG') ? 'selected' : '' ?>>Engineering</option>
                            <option value="BUS" <?= (isset($_POST['department']) && $_POST['department'] == 'BUS') ? 'selected' : '' ?>>Business</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="year"><i class="fas fa-calendar"></i> Year <span class="required">*</span></label>
                        <select id="year" name="year" required>
                            <option value="">Select Year</option>
                            <option value="2024" <?= (isset($_POST['year']) && $_POST['year'] == '2024') ? 'selected' : '' ?>>2024</option>
                            <option value="2025" <?= (isset($_POST['year']) && $_POST['year'] == '2025') ? 'selected' : '' ?>>2025</option>
                            <option value="2026" <?= (isset($_POST['year']) && $_POST['year'] == '2026') ? 'selected' : '' ?>>2026</option>
                            <option value="2027" <?= (isset($_POST['year']) && $_POST['year'] == '2027') ? 'selected' : '' ?>>2027</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="adviser"><i class="fas fa-user-tie"></i> Thesis Adviser <span class="required">*</span></label>
                        <input type="text" id="adviser" name="adviser" required
                               value="<?= htmlspecialchars($_POST['adviser'] ?? '') ?>"
                               placeholder="Full name of your thesis adviser">
                    </div>

                    <div class="form-group">
                        <label for="manuscript"><i class="fas fa-file-pdf"></i> Upload Manuscript <span class="required">*</span></label>
                        <div class="file-upload-wrapper">
                            <input type="file" id="manuscript" name="manuscript" accept=".pdf" required>
                            <div class="file-upload-info">
                                <i class="fas fa-info-circle"></i>
                                <span>Accepted format: PDF only | Maximum size: 10MB</span>
                            </div>
                        </div>
                    </div>

                    <div class="form-footer">
                        <button type="submit" name="submit_thesis" class="btn primary">
                            <i class="fas fa-paper-plane"></i> Submit for Review
                        </button>
                        <button type="reset" class="btn secondary" onclick="return confirm('Are you sure you want to clear the form?')">
                            <i class="fas fa-undo"></i> Clear Form
                        </button>
                    </div>
                </form>
            </div>

            <?php if (!empty($recentSubmissions)): ?>
            <div class="recent-submissions">
                <h3><i class="fas fa-history"></i> Your Recent Submissions</h3>
                <div class="submissions-list">
                    <?php foreach ($recentSubmissions as $sub): ?>
                        <div class="submission-item">
                            <div class="submission-info">
                                <h4><?= htmlspecialchars($sub['title']) ?></h4>
                                <span class="status-badge status-<?= strtolower(str_replace(' ', '_', $sub['status'])) ?>">
                                    <?= ucfirst(htmlspecialchars($sub['status'])) ?>
                                </span>
                                <?php if (!empty($sub['file_path'])): ?>
                                    <span class="file-indicator" title="Manuscript uploaded">
                                        <i class="fas fa-file-pdf"></i> PDF
                                    </span>
                                <?php endif; ?>
                            </div>
                            <?php 
                            $dateField = isset($sub['created_at']) ? $sub['created_at'] : (isset($sub['date_submitted']) ? $sub['date_submitted'] : date('Y-m-d H:i:s'));
                            ?>
                            <small><?= date('M d, Y', strtotime($dateField)) ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
    // Dark Mode
    const darkToggle = document.getElementById('darkmode');
    if (darkToggle) {
        darkToggle.addEventListener('change', () => {
            document.body.classList.toggle('dark-mode');
            localStorage.setItem('darkMode', darkToggle.checked);
        });
        if (localStorage.getItem('darkMode') === 'true') {
            darkToggle.checked = true;
            document.body.classList.add('dark-mode');
        }
    }

    // Sidebar
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const mobileBtn = document.getElementById('mobileMenuBtn');

    function toggleSidebar() {
        sidebar.classList.toggle('show');
        overlay.classList.toggle('show');
    }

    if (hamburgerBtn) hamburgerBtn.addEventListener('click', toggleSidebar);
    if (mobileBtn) mobileBtn.addEventListener('click', toggleSidebar);
    if (overlay) overlay.addEventListener('click', toggleSidebar);

    // Avatar Dropdown
    const avatarBtn = document.getElementById('avatarBtn');
    const dropdownMenu = document.getElementById('dropdownMenu');

    if (avatarBtn) {
        avatarBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            dropdownMenu.classList.toggle('show');
        });
    }

    document.addEventListener('click', (e) => {
        if (!avatarBtn?.contains(e.target) && dropdownMenu) {
            dropdownMenu.classList.remove('show');
        }
    });

    // File input styling
    const fileInput = document.getElementById('manuscript');
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                const wrapper = this.closest('.file-upload-wrapper');
                wrapper.style.borderColor = '#10b981';
                setTimeout(() => {
                    wrapper.style.borderColor = '#e0e0e0';
                }, 2000);
            }
        });
    }
</script>

</body>
</html>