<?php
session_start();
include("config/db.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

// CHECK IF USER IS LOGGED IN - REDIRECT TO LOGIN IF NOT
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
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif; background: #f5f5f5; color: #000000; line-height: 1.6; }
        body.dark-mode { background: #2d2d2d; color: #e0e0e0; }

        /* Navigation */
        .navbar { background: linear-gradient(135deg, #FE4853 0%, #732529 100%); color: white; padding: 1rem 2rem; box-shadow: 0 2px 10px rgba(0,0,0,0.2); position: sticky; top: 0; z-index: 1000; }
        .nav-container { max-width: 1400px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; }
        .logo { display: flex; align-items: center; gap: 10px; font-size: 1.5rem; font-weight: bold; color: white; text-decoration: none; }
        .logo-icon { width: 40px; height: 40px; background: white; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #FE4853; font-size: 1.2rem; }
        .nav-links { display: flex; gap: 1rem; list-style: none; flex-wrap: wrap; }
        .nav-links a { color: white; text-decoration: none; font-weight: 500; transition: all 0.3s; padding: 0.5rem 1rem; border-radius: 6px; opacity: 0.9; }
        .nav-links a:hover, .nav-links a.active { background: rgba(255, 255, 255, 0.2); opacity: 1; }

        /* Container */
        .container { max-width: 1200px; margin: 2rem auto; padding: 0 2rem; }

        /* Back Button */
        .back-btn { display: inline-flex; align-items: center; gap: 8px; background: #6c757d; color: white; padding: 0.5rem 1rem; border-radius: 6px; text-decoration: none; margin-bottom: 1.5rem; transition: all 0.3s; }
        .back-btn:hover { background: #5a6268; transform: translateX(-3px); }

        /* Login Required Banner */
        .login-required-banner { background: linear-gradient(135deg, #FE4853 0%, #732529 100%); border-radius: 12px; padding: 3rem; text-align: center; color: white; margin-bottom: 2rem; }
        .login-required-banner i { font-size: 3rem; margin-bottom: 1rem; }
        .login-required-banner h2 { font-size: 1.8rem; margin-bottom: 1rem; }
        .login-required-banner p { margin-bottom: 1.5rem; opacity: 0.9; }
        .login-buttons { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; }
        .btn-login { background: white; color: #FE4853; padding: 0.75rem 2rem; border-radius: 8px; text-decoration: none; font-weight: 600; transition: all 0.3s; }
        .btn-login:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
        .btn-register { background: rgba(255,255,255,0.2); color: white; padding: 0.75rem 2rem; border-radius: 8px; text-decoration: none; font-weight: 600; transition: all 0.3s; border: 1px solid white; }
        .btn-register:hover { background: white; color: #FE4853; transform: translateY(-2px); }

        /* Thesis Card (Hidden for non-logged in users) */
        .thesis-card { background: white; border-radius: 12px; padding: 2rem; box-shadow: 0 2px 8px rgba(110, 110, 110, 0.1); margin-bottom: 2rem; }
        body.dark-mode .thesis-card { background: #3a3a3a; }
        .thesis-header { margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 2px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; }
        body.dark-mode .thesis-header { border-bottom-color: #6E6E6E; }
        .thesis-title { font-size: 1.8rem; font-weight: 700; color: #732529; }
        body.dark-mode .thesis-title { color: #FE4853; }
        .status-badge { display: inline-block; padding: 0.3rem 0.8rem; background: #10b981; color: white; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }

        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .info-item { background: #f8fafc; padding: 1rem; border-radius: 8px; }
        body.dark-mode .info-item { background: #4a4a4a; }
        .info-label { font-size: 0.75rem; color: #6E6E6E; margin-bottom: 0.3rem; text-transform: uppercase; letter-spacing: 1px; }
        .info-value { font-size: 1rem; font-weight: 500; }

        .abstract-section { margin-bottom: 2rem; }
        .abstract-section h3 { color: #732529; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
        body.dark-mode .abstract-section h3 { color: #FE4853; }
        .abstract-text { background: #f8fafc; padding: 1.5rem; border-radius: 8px; line-height: 1.8; }
        body.dark-mode .abstract-text { background: #4a4a4a; }

        .keywords-section { margin-bottom: 2rem; }
        .keywords-section h3 { color: #732529; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
        body.dark-mode .keywords-section h3 { color: #FE4853; }
        .keyword { display: inline-block; padding: 0.3rem 0.8rem; background: #fef2f2; color: #FE4853; border-radius: 20px; font-size: 0.8rem; margin-right: 0.5rem; margin-bottom: 0.5rem; }
        body.dark-mode .keyword { background: #4a4a4a; color: #FE4853; }

        .file-section { background: #f8fafc; border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; }
        body.dark-mode .file-section { background: #4a4a4a; }
        .file-info { display: flex; align-items: center; gap: 1rem; }
        .file-info i { font-size: 2rem; color: #FE4853; }
        .btn-download { background: #10b981; color: white; padding: 0.75rem 1.5rem; border-radius: 8px; text-decoration: none; font-weight: 500; display: inline-flex; align-items: center; gap: 0.5rem; transition: all 0.3s; }
        .btn-download:hover { background: #059669; transform: translateY(-2px); }

        .pdf-viewer { margin-top: 1rem; border-radius: 12px; overflow: hidden; border: 1px solid #e0e0e0; background: white; }
        .pdf-viewer iframe { width: 100%; height: 700px; border: none; }
        .pdf-error { padding: 3rem; text-align: center; color: #6E6E6E; background: #fef2f2; }
        .pdf-error i { font-size: 3rem; margin-bottom: 1rem; color: #FE4853; }

        .archive-info { background: #e8f5e9; border-radius: 12px; padding: 1.5rem; margin-top: 2rem; border-left: 4px solid #10b981; }
        body.dark-mode .archive-info { background: #1a3a1a; }
        .archive-info h4 { color: #2e7d32; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
        .archive-details { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-top: 1rem; }

        .theme-toggle { position: fixed; bottom: 20px; right: 20px; width: 50px; height: 50px; background: #FE4853; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 4px 12px rgba(254, 72, 83, 0.3); z-index: 1000; transition: all 0.3s; }
        .theme-toggle:hover { transform: scale(1.1); }

        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .thesis-card { padding: 1rem; }
            .thesis-title { font-size: 1.3rem; }
            .info-grid { grid-template-columns: 1fr; }
            .pdf-viewer iframe { height: 400px; }
            .login-required-banner { padding: 2rem; }
            .login-required-banner h2 { font-size: 1.3rem; }
        }
    </style>
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
            <!-- LOGIN REQUIRED BANNER - Show only for non-logged in users -->
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
            <!-- FULL THESIS DETAILS - Show only for logged in users -->
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

    <script>
        const toggle = document.getElementById('themeToggle');
        if (toggle) {
            toggle.addEventListener('click', () => {
                document.body.classList.toggle('dark-mode');
                const icon = toggle.querySelector('i');
                if (document.body.classList.contains('dark-mode')) {
                    icon.classList.remove('fa-moon'); icon.classList.add('fa-sun');
                    localStorage.setItem('darkMode', 'true');
                } else {
                    icon.classList.remove('fa-sun'); icon.classList.add('fa-moon');
                    localStorage.setItem('darkMode', 'false');
                }
            });
            if (localStorage.getItem('darkMode') === 'true') {
                document.body.classList.add('dark-mode');
                toggle.querySelector('i').classList.remove('fa-moon');
                toggle.querySelector('i').classList.add('fa-sun');
            }
        }
    </script>
</body>
</html>