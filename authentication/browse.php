<?php
session_start();
include("../config/db.php");

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

$is_logged_in = isset($_SESSION['user_id']);

// Pagination variables
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 8;
$offset = ($page - 1) * $limit;

// Search and filter variables
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$year = isset($_GET['year']) ? trim($_GET['year']) : '';
$department = isset($_GET['department']) ? trim($_GET['department']) : '';

// Only show archived theses
$sql = "SELECT t.*, u.first_name, u.last_name 
        FROM thesis_table t
        JOIN user_table u ON t.student_id = u.user_id
        WHERE t.status = 'archived'";

$countSql = "SELECT COUNT(*) as total 
             FROM thesis_table t
             JOIN user_table u ON t.student_id = u.user_id
             WHERE t.status = 'archived'";

$params = [];
$countParams = [];
$types = "";
$countTypes = "";

// Add search condition
if (!empty($search)) {
    $sql .= " AND (t.title LIKE ? OR t.abstract LIKE ? OR t.keywords LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
    $countSql .= " AND (t.title LIKE ? OR t.abstract LIKE ? OR t.keywords LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
    $searchTerm = "%$search%";
    
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "sssss";
    
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
    $countParams[] = $searchTerm;
    $countTypes .= "sssss";
}

// Add year filter
if (!empty($year)) {
    $sql .= " AND YEAR(t.date_submitted) = ?";
    $countSql .= " AND YEAR(t.date_submitted) = ?";
    
    $params[] = $year;
    $types .= "s";
    
    $countParams[] = $year;
    $countTypes .= "s";
}

// Add department filter
if (!empty($department)) {
    $sql .= " AND t.department = ?";
    $countSql .= " AND t.department = ?";
    
    $params[] = $department;
    $types .= "s";
    
    $countParams[] = $department;
    $countTypes .= "s";
}

// Get total count for pagination
$stmt = $conn->prepare($countSql);
if (!empty($countParams)) {
    $stmt->bind_param($countTypes, ...$countParams);
}
$stmt->execute();
$totalResult = $stmt->get_result()->fetch_assoc();
$totalTheses = $totalResult['total'];
$totalPages = ceil($totalTheses / $limit);
$stmt->close();

// Add pagination to main query
$sql .= " ORDER BY t.archived_date DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

// Get thesis for current page
$theses = [];
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $theses[] = $row;
}
$stmt->close();

// Get unique years for filter dropdown
$years = [];
$yearQuery = "SELECT DISTINCT YEAR(date_submitted) as year FROM thesis_table WHERE status = 'archived' ORDER BY year DESC";
$yearResult = $conn->query($yearQuery);
if ($yearResult) {
    while ($row = $yearResult->fetch_assoc()) {
        $years[] = $row['year'];
    }
}

// Get unique departments for filter dropdown
$departments = [];
$deptQuery = "SELECT DISTINCT department FROM thesis_table WHERE department IS NOT NULL AND department != '' AND status = 'archived' ORDER BY department";
$deptResult = $conn->query($deptQuery);
if ($deptResult) {
    while ($row = $deptResult->fetch_assoc()) {
        $departments[] = $row['department'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Archived Theses - Thesis Archiving System</title>
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
        .nav-links a.active { background: rgba(255, 255, 255, 0.3); font-weight: 600; }

        /* Container */
        .container { max-width: 1400px; margin: 2rem auto; padding: 0 2rem; }

        /* Browse Header */
        .browse-header { background: white; border-radius: 12px; padding: 2.5rem; box-shadow: 0 2px 8px rgba(110, 110, 110, 0.1); margin-bottom: 2rem; text-align: center; }
        body.dark-mode .browse-header { background: #3a3a3a; }
        .browse-header h1 { color: #732529; font-size: 2.2rem; margin-bottom: 0.5rem; }
        body.dark-mode .browse-header h1 { color: #FE4853; }
        .browse-header p { color: #6E6E6E; font-size: 1.1rem; }

        /* Search Section */
        .search-section { background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 2px 8px rgba(110, 110, 110, 0.1); margin-bottom: 2rem; }
        body.dark-mode .search-section { background: #3a3a3a; }
        .search-bar { display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; }
        .search-input { flex: 1; padding: 0.75rem 1rem; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 1rem; min-width: 250px; }
        body.dark-mode .search-input { background: #4a4a4a; border-color: #6E6E6E; color: #e0e0e0; }
        .search-input:focus { outline: none; border-color: #FE4853; box-shadow: 0 0 0 3px rgba(254, 72, 83, 0.1); }
        .search-btn { padding: 0.75rem 2rem; background: #FE4853; color: white; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; }
        .search-btn:hover { background: #732529; transform: translateY(-2px); }

        /* Filter Bar */
        .filter-bar { display: flex; gap: 1rem; flex-wrap: wrap; align-items: center; }
        .filter-select { padding: 0.75rem 1rem; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 0.95rem; background: white; min-width: 200px; flex: 1; cursor: pointer; }
        body.dark-mode .filter-select { background: #4a4a4a; border-color: #6E6E6E; color: #e0e0e0; }
        .clear-btn { padding: 0.75rem 1.5rem; background: white; color: #FE4853; border: 2px solid #FE4853; border-radius: 8px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; }
        body.dark-mode .clear-btn { background: #4a4a4a; color: #FE4853; border-color: #FE4853; }
        .clear-btn:hover { background: #FE4853; color: white; }

        /* Results Info */
        .results-info { background: white; border-radius: 8px; padding: 1rem 1.5rem; box-shadow: 0 2px 8px rgba(110, 110, 110, 0.1); margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; }
        body.dark-mode .results-info { background: #3a3a3a; }
        .results-info p { color: #6E6E6E; }
        .results-info strong { color: #FE4853; }

        /* Thesis Grid */
        .thesis-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .thesis-card { background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 2px 8px rgba(110, 110, 110, 0.1); transition: all 0.3s; display: flex; flex-direction: column; border-left: 4px solid #10b981; }
        body.dark-mode .thesis-card { background: #3a3a3a; }
        .thesis-card:hover { transform: translateY(-4px); box-shadow: 0 8px 20px rgba(16, 185, 129, 0.15); }
        .thesis-title { color: #732529; font-size: 1.2rem; font-weight: 600; line-height: 1.4; margin-bottom: 0.5rem; }
        body.dark-mode .thesis-title { color: #FE4853; }
        .thesis-meta { display: flex; gap: 1rem; margin-bottom: 1rem; color: #6E6E6E; font-size: 0.85rem; flex-wrap: wrap; }
        .thesis-meta span { display: flex; align-items: center; gap: 0.3rem; }
        .thesis-meta i { color: #FE4853; width: 16px; }
        .thesis-abstract { color: #333; line-height: 1.6; margin-bottom: 1rem; font-size: 0.9rem; max-height: 80px; overflow: hidden; position: relative; }
        body.dark-mode .thesis-abstract { color: #e0e0e0; }
        .thesis-abstract::after { content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 30px; background: linear-gradient(transparent, white); }
        body.dark-mode .thesis-abstract::after { background: linear-gradient(transparent, #3a3a3a); }
        .thesis-keywords { margin-bottom: 1rem; padding-top: 0.5rem; border-top: 1px solid #e0e0e0; }
        body.dark-mode .thesis-keywords { border-top-color: #6E6E6E; }
        .keyword { display: inline-block; padding: 0.2rem 0.6rem; background: #f8fafc; color: #6E6E6E; border-radius: 4px; font-size: 0.75rem; margin-right: 0.3rem; margin-bottom: 0.3rem; }
        body.dark-mode .keyword { background: #4a4a4a; color: #e0e0e0; }
        .status-badge { display: inline-block; padding: 0.2rem 0.6rem; background: #10b981; color: white; border-radius: 4px; font-size: 0.7rem; font-weight: 600; margin-bottom: 0.5rem; }

        /* Thesis Actions */
        .thesis-actions { display: flex; gap: 0.5rem; margin-top: auto; }
        .btn-view, .btn-download { flex: 1; padding: 0.6rem; border: none; border-radius: 6px; font-weight: 500; font-size: 0.85rem; text-decoration: none; text-align: center; display: inline-flex; align-items: center; justify-content: center; gap: 0.3rem; transition: all 0.3s; cursor: pointer; }
        .btn-view { background: #FE4853; color: white; }
        .btn-view:hover { background: #732529; transform: translateY(-1px); }
        .btn-download { background: #10b981; color: white; }
        .btn-download:hover { background: #059669; transform: translateY(-1px); }

        /* Login Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center; }
        .modal.show { display: flex; }
        .modal-content { background: white; padding: 2rem; border-radius: 12px; max-width: 400px; width: 90%; text-align: center; }
        body.dark-mode .modal-content { background: #3a3a3a; }
        .modal-icon { font-size: 3rem; color: #FE4853; margin-bottom: 1rem; }
        .modal h3 { color: #732529; margin-bottom: 1rem; }
        body.dark-mode .modal h3 { color: #FE4853; }
        .modal p { margin-bottom: 1.5rem; color: #6E6E6E; }
        .modal-actions { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; }
        .modal-actions .btn { padding: 0.75rem 1.5rem; border-radius: 8px; text-decoration: none; font-weight: 500; }
        .btn-login-modal { background: #FE4853; color: white; }
        .btn-register-modal { background: #10b981; color: white; }
        .modal-close { margin-top: 1rem; background: none; border: none; color: #6E6E6E; cursor: pointer; font-size: 0.9rem; }

        /* No Results */
        .no-results { background: white; border-radius: 12px; padding: 4rem; text-align: center; grid-column: 1 / -1; }
        body.dark-mode .no-results { background: #3a3a3a; }
        .no-results i { font-size: 4rem; color: #FE4853; margin-bottom: 1rem; opacity: 0.5; }
        .no-results h3 { color: #732529; margin-bottom: 0.5rem; font-size: 1.5rem; }
        body.dark-mode .no-results h3 { color: #FE4853; }

        /* Pagination */
        .pagination { display: flex; justify-content: center; gap: 0.5rem; margin-top: 2rem; flex-wrap: wrap; }
        .page-btn { padding: 0.6rem 1rem; background: white; color: #6E6E6E; border: 2px solid #e0e0e0; border-radius: 6px; font-weight: 500; text-decoration: none; display: inline-block; }
        body.dark-mode .page-btn { background: #3a3a3a; color: #e0e0e0; border-color: #6E6E6E; }
        .page-btn:hover:not(.disabled) { background: #FE4853; color: white; border-color: #FE4853; }
        .page-btn.disabled { opacity: 0.5; cursor: not-allowed; pointer-events: none; }
        .page-active { background: #FE4853; color: white; border-color: #FE4853; }

        /* Dark mode toggle */
        .theme-toggle { position: fixed; bottom: 20px; right: 20px; width: 50px; height: 50px; background: #FE4853; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 4px 12px rgba(254, 72, 83, 0.3); z-index: 1000; transition: all 0.3s; }
        .theme-toggle:hover { transform: scale(1.1); }

        @media (max-width: 768px) {
            .nav-container { flex-direction: column; align-items: flex-start; }
            .nav-links { width: 100%; justify-content: flex-start; }
            .container { padding: 1rem; }
            .browse-header h1 { font-size: 1.8rem; }
            .search-bar { flex-direction: column; }
            .filter-bar { flex-direction: column; align-items: stretch; }
            .filter-select { width: 100%; }
            .results-info { flex-direction: column; text-align: center; }
            .thesis-grid { grid-template-columns: 1fr; }
            .thesis-meta { flex-direction: column; gap: 0.3rem; }
            .thesis-actions { flex-direction: column; }
            .modal-actions { flex-direction: column; }
        }
        @media (max-width: 480px) {
            .browse-header { padding: 1.5rem; }
            .browse-header h1 { font-size: 1.5rem; }
            .thesis-card { padding: 1rem; }
        }
    </style>
</head>
<body>
    <div class="theme-toggle" id="themeToggle"><i class="fas fa-moon"></i></div>

    <!-- Login Modal -->
    <div class="modal" id="loginModal">
        <div class="modal-content">
            <div class="modal-icon"><i class="fas fa-lock"></i></div>
            <h3>Login Required</h3>
            <p>You need to be logged in to view full thesis details and download PDF files.</p>
            <div class="modal-actions">
                <a href="login.php" class="btn btn-login-modal"><i class="fas fa-sign-in-alt"></i> Login</a>
                <a href="register.php" class="btn btn-register-modal"><i class="fas fa-user-plus"></i> Register</a>
            </div>
            <button class="modal-close" onclick="hideLoginModal()">Close</button>
        </div>
    </div>

    <nav class="navbar">
        <div class="nav-container">
            <a href="../homepage.php" class="logo"><div class="logo-icon">📚</div><span>Thesis Archive</span></a>
            <ul class="nav-links">
                <li><a href="../homepage.php">Home</a></li>
                <li><a href="browse.php" class="active">Browse Archive</a></li>
                <li><a href="../about.php">About</a></li>
                <?php if ($is_logged_in): ?>
                    <?php
                    $roleQuery = "SELECT role_id FROM user_table WHERE user_id = ? LIMIT 1";
                    $stmt = $conn->prepare($roleQuery);
                    $stmt->bind_param("i", $_SESSION['user_id']);
                    $stmt->execute();
                    $userRole = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    
                    $dashboardLink = '#';
                    if ($userRole) {
                        if ($userRole['role_id'] == 3) $dashboardLink = '../faculty/facultyDashboard.php';
                        elseif ($userRole['role_id'] == 2) $dashboardLink = '../student/student_dashboard.php';
                        elseif ($userRole['role_id'] == 1) $dashboardLink = '../admin/admindashboard.php';
                        elseif ($userRole['role_id'] == 6) $dashboardLink = '../coordinator/coordinatorDashboard.php';
                        elseif ($userRole['role_id'] == 4) $dashboardLink = '../departmentDeanDashboard/dean.php';
                        elseif ($userRole['role_id'] == 5) $dashboardLink = '../librarian/librarian_dashboard.php';
                    }
                    ?>
                    <li><a href="<?= $dashboardLink ?>">Dashboard</a></li>
                    <li><a href="../authentication/logout.php">Logout</a></li>
                <?php else: ?>
                    <li><a href="login.php">Login</a></li>
                    <li><a href="register.php">Register</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <div class="container">
        <div class="browse-header">
            <h1><i class="fas fa-archive"></i> Archived Theses</h1>
            <p>Browse through our collection of permanently archived academic theses</p>
        </div>

        <form method="GET" action="browse.php" class="search-section">
            <div class="search-bar">
                <input type="text" class="search-input" name="search" placeholder="Search by title, author, or keywords..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit" class="search-btn"><i class="fas fa-search"></i> Search</button>
            </div>
            <div class="filter-bar">
                <select name="year" class="filter-select">
                    <option value="">All Years</option>
                    <?php foreach ($years as $y): ?>
                        <option value="<?= $y ?>" <?= $year == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="department" class="filter-select">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= htmlspecialchars($dept) ?>" <?= $department == $dept ? 'selected' : '' ?>><?= htmlspecialchars($dept) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($search) || !empty($year) || !empty($department)): ?>
                    <a href="browse.php" class="clear-btn"><i class="fas fa-times"></i> Clear Filters</a>
                <?php endif; ?>
            </div>
        </form>

        <div class="results-info">
            <p><i class="fas fa-archive"></i> Found <strong><?= $totalTheses ?></strong> archived theses</p>
            <?php if ($totalPages > 1): ?>
                <p>Page <strong><?= $page ?></strong> of <strong><?= $totalPages ?></strong></p>
            <?php endif; ?>
        </div>

        <div class="thesis-grid">
            <?php if (empty($theses)): ?>
                <div class="no-results">
                    <i class="fas fa-search"></i>
                    <h3>No archived theses found</h3>
                    <p>Try adjusting your search or filter criteria</p>
                </div>
            <?php else: ?>
                <?php foreach ($theses as $thesis): ?>
                    <?php 
                    $file_path = '../' . $thesis['file_path'];
                    $file_exists = file_exists($file_path) && !empty($thesis['file_path']);
                    ?>
                    <div class="thesis-card">
                        <div class="thesis-header">
                            <span class="status-badge"><i class="fas fa-check-circle"></i> Archived</span>
                            <h3 class="thesis-title"><?= htmlspecialchars($thesis['title']) ?></h3>
                        </div>
                        <div class="thesis-meta">
                            <span><i class="fas fa-user"></i> <?= htmlspecialchars($thesis['first_name'] . ' ' . $thesis['last_name']) ?></span>
                            <span><i class="fas fa-user-tie"></i> Adviser: <?= htmlspecialchars($thesis['adviser'] ?? 'N/A') ?></span>
                            <span><i class="fas fa-building"></i> <?= htmlspecialchars($thesis['department'] ?? 'N/A') ?></span>
                            <span><i class="fas fa-calendar"></i> <?= date('F Y', strtotime($thesis['date_submitted'])) ?></span>
                        </div>
                        <p class="thesis-abstract"><?= htmlspecialchars(substr($thesis['abstract'], 0, 200)) ?>...</p>
                        <?php if (!empty($thesis['keywords'])): ?>
                            <div class="thesis-keywords">
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
                        <div class="thesis-actions">
                            <?php if ($is_logged_in): ?>
                                <a href="../view-thesis.php?id=<?= $thesis['thesis_id'] ?>" class="btn-view"><i class="fas fa-eye"></i> View Full Thesis</a>
                                <?php if ($file_exists): ?>
                                    <a href="<?= htmlspecialchars($file_path) ?>" class="btn-download" download><i class="fas fa-download"></i> Download PDF</a>
                                <?php else: ?>
                                    <span class="btn-download" style="background:#6c757d; cursor:not-allowed;"><i class="fas fa-download"></i> File Not Found</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <button class="btn-view" onclick="showLoginModal(); return false;"><i class="fas fa-eye"></i> View Full Thesis</button>
                                <button class="btn-download" onclick="showLoginModal(); return false;"><i class="fas fa-download"></i> Download PDF</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&year=<?= urlencode($year) ?>&department=<?= urlencode($department) ?>" class="page-btn"><i class="fas fa-chevron-left"></i> Previous</a>
                <?php else: ?>
                    <span class="page-btn disabled"><i class="fas fa-chevron-left"></i> Previous</span>
                <?php endif; ?>
                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                for ($i = $startPage; $i <= $endPage; $i++):
                ?>
                    <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&year=<?= urlencode($year) ?>&department=<?= urlencode($department) ?>" class="page-btn <?= $i == $page ? 'page-active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&year=<?= urlencode($year) ?>&department=<?= urlencode($department) ?>" class="page-btn">Next <i class="fas fa-chevron-right"></i></a>
                <?php else: ?>
                    <span class="page-btn disabled">Next <i class="fas fa-chevron-right"></i></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Modal functions
        function showLoginModal() {
            document.getElementById('loginModal').classList.add('show');
        }

        function hideLoginModal() {
            document.getElementById('loginModal').classList.remove('show');
        }

        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('loginModal');
            if (event.target === modal) {
                hideLoginModal();
            }
        });

        // Dark mode toggle
        const toggle = document.getElementById('themeToggle');
        if (toggle) {
            toggle.addEventListener('click', () => {
                document.body.classList.toggle('dark-mode');
                const icon = toggle.querySelector('i');
                if (document.body.classList.contains('dark-mode')) {
                    icon.classList.remove('fa-moon');
                    icon.classList.add('fa-sun');
                    localStorage.setItem('darkMode', 'true');
                } else {
                    icon.classList.remove('fa-sun');
                    icon.classList.add('fa-moon');
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