<?php
session_start();
include("../config/db.php");
include("includes/project_functions.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION["user_id"])) {
    header("Location: /ArchivingThesis/authentication/login.php");
    exit;
}

$user_id = (int)$_SESSION["user_id"];

// Check user role
if (!checkUserRole($conn, $user_id)) {
    header("Location: /ArchivingThesis/authentication/login.php");
    exit;
}

// Get user data
$user = getUserData($conn, $user_id);
$fullName = $user['fullName'];
$initials = $user['initials'];

// Get student ID
$student_id = getStudentId($conn, $user_id);

// Get projects
$projects = getStudentProjects($conn, $student_id);

// Get certificates
$certificates = getProjectCertificates($conn, $projects);

$pageTitle = "My Projects";
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title><?= htmlspecialchars($pageTitle) ?> - Theses Archiving System</title>
  <link rel="stylesheet" href="css/base.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="css/projects.css">
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
    <a href="projects.php" class="nav-link active">
      <i class="fas fa-folder-open"></i> My Projects
    </a>
    <a href="submission.php" class="nav-link">
      <i class="fas fa-upload"></i> Submit Thesis
    </a>
    <a href="archived.php" class="nav-link">
      <i class="fas fa-archive"></i> Archived Theses
    </a>
    <a href="profile.php" class="nav-link">
      <i class="fas fa-user-circle"></i> Profile
    </a>
  </nav>

  <div class="sidebar-footer">
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
        <h1>My Current Projects</h1>
      </div>

      <div class="user-info">
        <span class="user-name"><?= htmlspecialchars($fullName) ?></span>
        <div class="avatar"><?= htmlspecialchars($initials) ?></div>
      </div>
    </header>

    <div class="projects-container">
      <?php if (empty($projects)): ?>
        <div class="no-projects">
          <i class="fas fa-folder-open"></i>
          <h3>No Projects Yet</h3>
          <p>You haven't submitted any thesis projects yet.</p>
          <a href="submission.php" class="btn btn-primary" style="margin-top: 1rem;">
            <i class="fas fa-upload"></i> Submit Your First Thesis
          </a>
        </div>
      <?php else: ?>
        <?php foreach ($projects as $project): 
          $progress = calculateProgress($project['status'], $project['feedback_count']);
          $statusClass = getStatusClass($project['status']);
          $statusText = getStatusText($project['status']);
          $hasCertificate = isset($certificates[$project['thesis_id']]);
          $adviserName = !empty($project['adviser_name']) ? $project['adviser_name'] : 'Not Assigned';
        ?>
          <div class="project-card" id="project-<?= $project['thesis_id'] ?>">
            <div class="project-header">
              <h2>
                <?= htmlspecialchars($project['title']) ?>
                <?php if ($project['feedback_count'] > 0): ?>
                  <span class="feedback-badge">
                    <i class="fas fa-comment"></i> <?= $project['feedback_count'] ?>
                  </span>
                <?php endif; ?>
                <?php if ($hasCertificate): ?>
                  <span class="certificate-badge">
                    <i class="fas fa-certificate"></i> Certified
                  </span>
                <?php endif; ?>
              </h2>
              <span class="status <?= $statusClass ?>"><?= $statusText ?></span>
            </div>

            <div class="project-progress">
              <div class="progress-label">
                <span>Overall Progress</span>
                <span><?= $progress ?>%</span>
              </div>
              <div class="progress-bar">
                <div class="progress-fill" style="width: <?= $progress ?>%;"></div>
              </div>
            </div>

            <div class="project-meta">
                <div><i class="fas fa-user-tie"></i> <strong>Adviser:</strong> <?= htmlspecialchars($adviserName) ?></div>
                <div><i class="fas fa-tags"></i> <strong>Keywords:</strong> <?= htmlspecialchars($project['keywords'] ?? 'None') ?></div>
                <div><i class="fas fa-building"></i> <strong>Department:</strong> <?= htmlspecialchars($project['department'] ?? 'N/A') ?></div>
                <div><i class="fas fa-graduation-cap"></i> <strong>Course:</strong> <?= htmlspecialchars($project['course'] ?? 'N/A') ?></div>
                <div><i class="fas fa-calendar"></i> <strong>Year:</strong> <?= htmlspecialchars($project['year'] ?? 'N/A') ?></div>
                <div><i class="fas fa-calendar-alt"></i> <strong>Submitted:</strong> <?= date('F d, Y', strtotime($project['date_submitted'])) ?></div>
                
                <?php if (!empty($project['feedback_count'])): ?>
                    <div><i class="fas fa-comments"></i> <strong>Feedback Received:</strong> <?= $project['feedback_count'] ?></div>
                <?php endif; ?>
                
                <?php if ($hasCertificate): ?>
                    <div><i class="fas fa-download"></i> <strong>Certificate Downloaded:</strong> <?= $certificates[$project['thesis_id']]['downloaded_count'] ?> times</div>
                <?php endif; ?>
            </div>

            <div class="project-actions">
              <a href="view_project.php?id=<?= $project['thesis_id'] ?>" class="btn btn-primary">
                <i class="fas fa-eye"></i> View Details
              </a>
              
              <?php if (!empty($project['file_path'])): ?>
                <a href="../<?= htmlspecialchars($project['file_path']) ?>" class="btn btn-secondary" download>
                  <i class="fas fa-download"></i> Download Manuscript
                </a>
              <?php endif; ?>
              
              <?php if ($hasCertificate): ?>
                <a href="certificate.php?id=<?= $certificates[$project['thesis_id']]['certificate_id'] ?>" 
                   class="btn-certificate" 
                   target="_blank">
                  <i class="fas fa-certificate"></i> View Certificate
                </a>
              <?php endif; ?>
              
              <?php if ($project['status'] == 'pending'): ?>
                <span class="btn btn-secondary" style="opacity: 0.7; cursor: not-allowed;" title="Editing disabled while under review">
                  <i class="fas fa-edit"></i> Edit (Locked)
                </span>
              <?php endif; ?>
              
              <?php if ($project['status'] == 'approved'): ?>
                <a href="archive_thesis.php?id=<?= $project['thesis_id'] ?>" class="btn btn-success" onclick="return confirm('Archive this thesis?')">
                  <i class="fas fa-archive"></i> Archive
                </a>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </main>
</div>

<script src="js/projects.js"></script>
</body>
</html>