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

// Only show archived theses (use is_archived instead of status)
$sql = "SELECT t.*, u.first_name, u.last_name 
        FROM thesis_table t
        JOIN user_table u ON t.student_id = u.user_id
        WHERE t.is_archived = 1";

$countSql = "SELECT COUNT(*) as total 
             FROM thesis_table t
             JOIN user_table u ON t.student_id = u.user_id
             WHERE t.is_archived = 1";

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

// Get unique years for filter dropdown (use is_archived = 1)
$years = [];
$yearQuery = "SELECT DISTINCT YEAR(date_submitted) as year FROM thesis_table WHERE is_archived = 1 ORDER BY year DESC";
$yearResult = $conn->query($yearQuery);
if ($yearResult) {
    while ($row = $yearResult->fetch_assoc()) {
        $years[] = $row['year'];
    }
}

// Get unique departments for filter dropdown (use is_archived = 1)
$departments = [];
$deptQuery = "SELECT DISTINCT department FROM thesis_table WHERE department IS NOT NULL AND department != '' AND is_archived = 1 ORDER BY department";
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
    <!-- External CSS -->
    <link rel="stylesheet" href="css/browse.css">
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
            <button class="modal-close" id="modalCloseBtn">Close</button>
        </div>
    </div>

    <nav class="navbar">
        <div class="nav-container">
            <a href="../homepage.php" class="logo"><div class="logo-icon">📚</div><span>Thesis Archive</span></a>
            <ul class="nav-links">
                <li><a href="homepage.php">Home</a></li>
                <li><a href="browse.php" class="active">Browse Archive</a></li>
                <li><a href="about.php">About</a></li>
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

        <form method="GET" action="browse.php" class="search-section" id="searchForm">
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

        <div class="thesis-grid" id="thesisGrid">
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
                                <button class="btn-view" data-action="view"><i class="fas fa-eye"></i> View Full Thesis</button>
                                <button class="btn-download" data-action="download"><i class="fas fa-download"></i> Download PDF</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="pagination" id="pagination">
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

    <!-- Pass PHP variables to JavaScript -->
    <script>
        window.isLoggedIn = <?php echo $is_logged_in ? 'true' : 'false'; ?>;
        window.searchParams = {
            search: '<?php echo addslashes($search); ?>',
            year: '<?php echo addslashes($year); ?>',
            department: '<?php echo addslashes($department); ?>',
            page: <?php echo $page; ?>
        };
    </script>
    
    <!-- External JavaScript -->
    <script src="js/browse.js"></script>
</body>
</html>