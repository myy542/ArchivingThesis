<?php
session_start();
include("config/db.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

// CHECK IF USER IS LOGGED IN
$is_logged_in = isset($_SESSION['user_id']);

// Get thesis ID from URL
$thesis_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($thesis_id == 0) {
    header("Location: homepage.php");
    exit;
}

// Get thesis details
$thesis_query = "SELECT t.*, u.first_name, u.last_name, u.email 
                 FROM thesis_table t
                 JOIN user_table u ON t.student_id = u.user_id
                 WHERE t.thesis_id = ?";
$thesis_stmt = $conn->prepare($thesis_query);
$thesis_stmt->bind_param("i", $thesis_id);
$thesis_stmt->execute();
$thesis_result = $thesis_stmt->get_result();
$thesis = $thesis_result->fetch_assoc();
$thesis_stmt->close();

if (!$thesis) {
    header("Location: homepage.php");
    exit;
}

// Get archive details if exists
$archive = null;
$check_archive_table = $conn->query("SHOW TABLES LIKE 'archive_table'");
if ($check_archive_table && $check_archive_table->num_rows > 0) {
    $archive_query = "SELECT * FROM archive_table WHERE thesis_id = ?";
    $archive_stmt = $conn->prepare($archive_query);
    $archive_stmt->bind_param("i", $thesis_id);
    $archive_stmt->execute();
    $archive_result = $archive_stmt->get_result();
    $archive = $archive_result->fetch_assoc();
    $archive_stmt->close();
}

$pageTitle = "View Thesis - " . htmlspecialchars($thesis['title']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | Thesis Archiving System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- External CSS -->
    <link rel="stylesheet" href="css/view-thesis.css">
</head>
<body>
    <div class="theme-toggle" id="themeToggle"><i class="fas fa-moon"></i></div>

    <nav class="navbar">
        <div class="nav-container">
            <a href="homepage.php" class="logo"><div class="logo-icon">📚</div><span>Thesis Archive</span></a>
            <ul class="nav-links">
                <li><a href="homepage.php">Home</a></li>
                <li><a href="authentication/browse.php">Browse Archive</a></li>
                <li><a href="about.php">About</a></li>
                <?php if ($is_logged_in): ?>
                    <li><a href="authentication/logout.php">Logout</a></li>
                <?php else: ?>
                    <li><a href="authentication/login.php">Login</a></li>
                    <li><a href="authentication/register.php">Register</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <div class="container">
        <a href="javascript:history.back()" class="back-btn"><i class="fas fa-arrow-left"></i> Back</a>

        <?php if (!$is_logged_in): ?>
            <!-- LOGIN REQUIRED BANNER -->
            <div class="login-required-banner">
                <i class="fas fa-lock"></i>
                <h2>Login Required to View Full Thesis</h2>
                <p>You need to be logged in to view the complete thesis details, abstract, and download the PDF file.</p>
                <div class="login-buttons">
                    <a href="authentication/login.php" class="btn-login"><i class="fas fa-sign-in-alt"></i> Login to Your Account</a>
                    <a href="authentication/register.php" class="btn-register"><i class="fas fa-user-plus"></i> Create New Account</a>
                </div>
            </div>

            <!-- Show limited preview for non-logged in users -->
            <div class="thesis-card">
                <div class="thesis-header">
                    <h1 class="thesis-title"><?= htmlspecialchars($thesis['title']) ?></h1>
                    <span class="status-badge"><i class="fas fa-check-circle"></i> Archived</span>
                </div>
                <div class="info-grid">
                    <div class="info-item"><div class="info-label">Author</div><div class="info-value"><?= htmlspecialchars($thesis['first_name'] . ' ' . $thesis['last_name']) ?></div></div>
                    <div class="info-item"><div class="info-label">Department</div><div class="info-value"><?= htmlspecialchars($thesis['department'] ?? 'N/A') ?></div></div>
                    <div class="info-item"><div class="info-label">Year</div><div class="info-value"><?= htmlspecialchars($thesis['year'] ?? 'N/A') ?></div></div>
                    <div class="info-item"><div class="info-label">Date Submitted</div><div class="info-value"><?= date('F d, Y', strtotime($thesis['date_submitted'])) ?></div></div>
                </div>
                <div class="abstract-section">
                    <h3><i class="fas fa-align-left"></i> Abstract (Preview)</h3>
                    <div class="abstract-text">
                        <?= htmlspecialchars(substr($thesis['abstract'], 0, 300)) ?>...
                        <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e0e0e0; text-align: center;">
                            <i class="fas fa-lock"></i> <strong>Full abstract is available after login.</strong>
                        </div>
                    </div>
                </div>
                <div class="file-section" style="justify-content: center;">
                    <div class="file-info">
                        <i class="fas fa-file-pdf"></i>
                        <div><strong>Manuscript File</strong><br><small><?= !empty($thesis['file_path']) ? basename($thesis['file_path']) : 'No file uploaded' ?></small></div>
                    </div>
                </div>
                <div style="text-align: center; padding: 1rem; background: #fef2f2; border-radius: 8px;">
                    <i class="fas fa-lock"></i> <strong>Please login or register to view the complete thesis and download the PDF file.</strong>
                </div>
            </div>

        <?php else: ?>
            <!-- FULL THESIS DETAILS -->
            <div class="thesis-card">
                <div class="thesis-header">
                    <h1 class="thesis-title"><?= htmlspecialchars($thesis['title']) ?></h1>
                    <span class="status-badge"><i class="fas fa-check-circle"></i> Archived</span>
                </div>

                <div class="info-grid">
                    <div class="info-item"><div class="info-label">Author</div><div class="info-value"><?= htmlspecialchars($thesis['first_name'] . ' ' . $thesis['last_name']) ?></div></div>
                    <div class="info-item"><div class="info-label">Email</div><div class="info-value"><?= htmlspecialchars($thesis['email']) ?></div></div>
                    <div class="info-item"><div class="info-label">Adviser</div><div class="info-value"><?= htmlspecialchars($thesis['adviser'] ?? 'N/A') ?></div></div>
                    <div class="info-item"><div class="info-label">Department</div><div class="info-value"><?= htmlspecialchars($thesis['department'] ?? 'N/A') ?></div></div>
                    <div class="info-item"><div class="info-label">Year</div><div class="info-value"><?= htmlspecialchars($thesis['year'] ?? 'N/A') ?></div></div>
                    <div class="info-item"><div class="info-label">Date Submitted</div><div class="info-value"><?= date('F d, Y', strtotime($thesis['date_submitted'])) ?></div></div>
                </div>

                <div class="abstract-section">
                    <h3><i class="fas fa-align-left"></i> Abstract</h3>
                    <div class="abstract-text"><?= nl2br(htmlspecialchars($thesis['abstract'])) ?></div>
                </div>

                <?php if (!empty($thesis['keywords'])): ?>
                <div class="keywords-section">
                    <h3><i class="fas fa-tags"></i> Keywords</h3>
                    <?php 
                    $keywords = explode(',', $thesis['keywords']);
                    foreach ($keywords as $kw): 
                        $kw = trim($kw);
                        if (!empty($kw)):
                    ?>
                        <span class="keyword"><?= htmlspecialchars($kw) ?></span>
                    <?php endif; endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- File Section with Download -->
                <div class="file-section">
                    <div class="file-info">
                        <i class="fas fa-file-pdf"></i>
                        <div><strong>Manuscript File</strong><br><small><?= !empty($thesis['file_path']) ? basename($thesis['file_path']) : 'No file uploaded' ?></small></div>
                    </div>
                    <?php if (!empty($thesis['file_path'])): ?>
                        <a href="<?= htmlspecialchars($thesis['file_path']) ?>" class="btn-download" download><i class="fas fa-download"></i> Download PDF</a>
                    <?php else: ?>
                        <span class="btn-download" style="background:#6c757d; cursor:not-allowed;"><i class="fas fa-download"></i> File Not Available</span>
                    <?php endif; ?>
                </div>

                <!-- PDF Viewer -->
                <?php if (!empty($thesis['file_path'])): 
                    $full_file_path = $thesis['file_path'];
                    if (file_exists($full_file_path)):
                ?>
                <div class="pdf-viewer">
                    <iframe src="<?= htmlspecialchars($full_file_path) ?>"></iframe>
                </div>
                <?php else: ?>
                <div class="pdf-viewer">
                    <div class="pdf-error"><i class="fas fa-file-pdf"></i><p>PDF file not found on server.</p><p>Path: <?= htmlspecialchars($full_file_path) ?></p></div>
                </div>
                <?php endif; ?>
                <?php else: ?>
                <div class="pdf-viewer">
                    <div class="pdf-error"><i class="fas fa-file-pdf"></i><p>No manuscript file uploaded.</p></div>
                </div>
                <?php endif; ?>

                <!-- Archive Information -->
                <?php if ($archive): ?>
                <div class="archive-info">
                    <h4><i class="fas fa-archive"></i> Archive Information</h4>
                    <div class="archive-details">
                        <div><div class="info-label">Archived Date</div><div class="info-value"><?= date('F d, Y', strtotime($thesis['archived_date'] ?? $thesis['date_submitted'])) ?></div></div>
                        <div><div class="info-label">Retention Period</div><div class="info-value"><?= $archive['retention_period'] ?? '5' ?> years</div></div>
                        <div><div class="info-label">Access Level</div><div class="info-value"><?= ucfirst($archive['access_level'] ?? 'Public') ?></div></div>
                    </div>
                    <?php if (!empty($archive['archive_notes'])): ?>
                    <div style="margin-top: 1rem;"><div class="info-label">Archive Notes</div><div class="info-value"><?= htmlspecialchars($archive['archive_notes']) ?></div></div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Pass PHP variables to JavaScript -->
    <script>
        window.isLoggedIn = <?php echo $is_logged_in ? 'true' : 'false'; ?>;
    </script>
    
    <!-- External JavaScript -->
    <script src="js/view-thesis.js"></script>
</body>
</html>