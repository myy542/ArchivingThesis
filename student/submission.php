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

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $result = handleThesisSubmission($conn, $user_id, $student_id, $first, $last, $_POST, $_FILES);
    
    if ($result['success']) {
        $successMessage = $result['message'];
        $_POST = []; // Clear form
    } else {
        $formErrors = $result['errors'];
    }
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
  <link rel="stylesheet" href="css/base.css">
  <link rel="stylesheet" href="css/submission.css">
</head>
<body>

<!-- OVERLAY -->
<div class="overlay" id="overlay"></div>

<!-- MOBILE MENU BUTTON -->
<button class="mobile-menu-btn" id="mobileMenuBtn">
    <i class="fas fa-bars"></i>
</button>

<!-- SIDEBAR -->
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
          <a href="notification.php" class="notification-bell" id="notificationBell">
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
          <i class="fas fa-check-circle"></i>
          <?= htmlspecialchars($successMessage) ?>
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
        <p class="form-note">Fields marked with <span class="required"></span> are required</p>

        <form method="POST" enctype="multipart/form-data" id="submissionForm">

          <div class="form-group">
            <label for="title">
              <i class="fas fa-heading"></i> Thesis Title <span class="required"></span>
            </label>
            <input type="text" id="title" name="title" required
                   value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"
                   placeholder="Enter the full title of your thesis"
                   minlength="5" maxlength="255">
            <small class="form-text">Minimum 5 characters, maximum 255 characters</small>
          </div>

          <div class="form-group">
            <label for="abstract">
              <i class="fas fa-align-left"></i> Abstract <span class="required"></span>
            </label>
            <textarea id="abstract" name="abstract" required
                      placeholder="Provide a comprehensive summary of your thesis (minimum 50 characters)"
                      minlength="50" maxlength="5000"><?= htmlspecialchars($_POST['abstract'] ?? '') ?></textarea>
            <small class="form-text">Minimum 50 characters, maximum 5000 characters</small>
          </div>

          <div class="form-group">
            <label for="keywords">
              <i class="fas fa-tags"></i> Keywords <span class="required"></span>
            </label>
            <input type="text" id="keywords" name="keywords" required
                   value="<?= htmlspecialchars($_POST['keywords'] ?? '') ?>"
                   placeholder="e.g., Machine Learning, Education, Data Analysis (separate with commas)">
            <small class="form-text">Separate keywords with commas. At least 3 keywords.</small>
          </div>

          <div class="form-group">
            <label for="department">
              <i class="fas fa-building"></i> Department <span class="required"></span>
            </label>
            <select id="department" name="department" required>
                <option value="">Select Department</option>
                <option value="CS" <?= (isset($_POST['department']) && $_POST['department'] == 'CS') ? 'selected' : '' ?>>Computer Science</option>
                <option value="IT" <?= (isset($_POST['department']) && $_POST['department'] == 'IT') ? 'selected' : '' ?>>Information Technology</option>
                <option value="ENG" <?= (isset($_POST['department']) && $_POST['department'] == 'ENG') ? 'selected' : '' ?>>Engineering</option>
                <option value="BUS" <?= (isset($_POST['department']) && $_POST['department'] == 'BUS') ? 'selected' : '' ?>>Business</option>
            </select>
          </div>

          <div class="form-group">
            <label for="year">
              <i class="fas fa-calendar"></i> Year <span class="required"></span>
            </label>
            <select id="year" name="year" required>
                <option value="">Select Year</option>
                <option value="2024" <?= (isset($_POST['year']) && $_POST['year'] == '2024') ? 'selected' : '' ?>>2024</option>
                <option value="2025" <?= (isset($_POST['year']) && $_POST['year'] == '2025') ? 'selected' : '' ?>>2025</option>
                <option value="2026" <?= (isset($_POST['year']) && $_POST['year'] == '2026') ? 'selected' : '' ?>>2026</option>
                <option value="2027" <?= (isset($_POST['year']) && $_POST['year'] == '2027') ? 'selected' : '' ?>>2027</option>
            </select>
          </div>

          <div class="form-group">
            <label for="adviser">
              <i class="fas fa-user-tie"></i> Thesis Adviser <span class="required"></span>
            </label>
            <input type="text" id="adviser" name="adviser" required
                   value="<?= htmlspecialchars($_POST['adviser'] ?? '') ?>"
                   placeholder="Full name of your thesis adviser">
          </div>

          <div class="form-group">
            <label for="manuscript">
              <i class="fas fa-file-pdf"></i> Upload Manuscript <span class="required"></span>
            </label>
            <div class="file-upload-wrapper">
              <input type="file" id="manuscript" name="manuscript" accept=".pdf" required>
              <div class="file-upload-info">
                <i class="fas fa-info-circle"></i>
                <span>Accepted format: PDF only | Maximum size: 10MB | File will be saved in uploads/manuscripts/ folder</span>
              </div>
            </div>
          </div>

          <div class="form-footer">
            <button type="submit" class="btn primary" id="submitBtn">
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
                <span class="status-badge status-<?= strtolower($sub['status']) ?>">
                  <?= ucfirst(htmlspecialchars($sub['status'])) ?>
                </span>
                <?php if (!empty($sub['file_path'])): ?>
                  <span class="file-indicator" title="Manuscript uploaded">
                    <i class="fas fa-file-pdf"></i> PDF
                  </span>
                <?php endif; ?>
              </div>
              <small><?= date('M d, Y', strtotime($sub['date_submitted'])) ?></small>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

    </div>
  </main>
</div>

<script src="js/submission.js"></script>
</body>
</html>