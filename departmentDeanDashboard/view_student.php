<?php
session_start();
include("../config/db.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

// LOGIN VALIDATION
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'dean') {
    header("Location: /ArchivingThesis/authentication/login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['first_name'] ?? '';
$last_name = $_SESSION['last_name'] ?? '';
$fullName = $first_name . " " . $last_name;
$initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));

// Get student ID from URL
$student_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($student_id == 0) {
    header("Location: dean.php?section=students");
    exit;
}

// Get student details - wala nay created_at
$student_query = "SELECT user_id, first_name, last_name, email, contact_number, address, birth_date, status 
                  FROM user_table 
                  WHERE user_id = ? AND role_id = 2";
$student_stmt = $conn->prepare($student_query);
$student_stmt->bind_param("i", $student_id);
$student_stmt->execute();
$student_result = $student_stmt->get_result();
$student = $student_result->fetch_assoc();
$student_stmt->close();

if (!$student) {
    header("Location: dean.php?section=students");
    exit;
}

// Get student's theses
$theses_query = "SELECT thesis_id, title, adviser, department, year, status, date_submitted 
                  FROM thesis_table 
                  WHERE student_id = ? 
                  ORDER BY date_submitted DESC";
$theses_stmt = $conn->prepare($theses_query);
$theses_stmt->bind_param("i", $student_id);
$theses_stmt->execute();
$theses_result = $theses_stmt->get_result();
$theses = [];
while ($row = $theses_result->fetch_assoc()) {
    $theses[] = $row;
}
$theses_stmt->close();

$pageTitle = "Student Details";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> | Dean Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #fef2f2; color: #1f2937; }
        
        .container { max-width: 1000px; margin: 0 auto; padding: 2rem; }
        .back-link { display: inline-flex; align-items: center; gap: 0.5rem; color: #dc2626; text-decoration: none; margin-bottom: 1.5rem; }
        .back-link:hover { text-decoration: underline; }
        
        .card { background: white; border-radius: 24px; padding: 2rem; margin-bottom: 2rem; border: 1px solid #fee2e2; }
        .card-header { border-bottom: 2px solid #f0f0f0; padding-bottom: 1rem; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; }
        .card-header h1 { font-size: 1.5rem; color: #991b1b; }
        .status-badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
        .status-Active { background: #d4edda; color: #155724; }
        .status-Inactive { background: #f8d7da; color: #721c24; }
        
        .info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; margin-bottom: 1.5rem; }
        .info-item { background: #f8fafc; padding: 1rem; border-radius: 12px; }
        .info-label { font-size: 0.7rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.25rem; }
        .info-value { font-size: 1rem; font-weight: 600; color: #1f2937; }
        
        .theses-table { width: 100%; border-collapse: collapse; }
        .theses-table th { text-align: left; padding: 0.75rem; background: #f8fafc; color: #6b7280; font-size: 0.7rem; text-transform: uppercase; border-bottom: 1px solid #fee2e2; }
        .theses-table td { padding: 0.75rem; border-bottom: 1px solid #fef2f2; font-size: 0.85rem; }
        .theses-table tr:hover { background: #fef2f2; }
        
        .btn-view { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.25rem 0.75rem; background: #dc2626; color: white; text-decoration: none; border-radius: 20px; font-size: 0.7rem; }
        .btn-view:hover { background: #991b1b; }
        
        .empty-state { text-align: center; padding: 2rem; color: #9ca3af; }
        .empty-state i { font-size: 3rem; margin-bottom: 1rem; color: #dc2626; }
        
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .info-grid { grid-template-columns: 1fr; }
            .theses-table { display: block; overflow-x: auto; }
        }
        
        body.dark-mode { background: #1a1a1a; }
        body.dark-mode .card { background: #2d2d2d; border-color: #991b1b; }
        body.dark-mode .card-header h1 { color: #fecaca; }
        body.dark-mode .info-item { background: #3d3d3d; }
        body.dark-mode .info-value { color: #e5e7eb; }
        body.dark-mode .theses-table th { background: #3d3d3d; color: #9ca3af; border-bottom-color: #991b1b; }
        body.dark-mode .theses-table td { border-bottom-color: #3d3d3d; color: #e5e7eb; }
        body.dark-mode .theses-table tr:hover { background: #3d3d3d; }
        body.dark-mode .status-Active { background: #1a3a1a; color: #86efac; }
        body.dark-mode .status-Inactive { background: #3a1a1a; color: #fca5a5; }
    </style>
</head>
<body>
    <div class="container">
        <a href="dean.php?section=students" class="back-link"><i class="fas fa-arrow-left"></i> Back to Students</a>
        
        <div class="card">
            <div class="card-header">
                <h1><i class="fas fa-user-graduate"></i> <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></h1>
                <span class="status-badge status-<?= $student['status'] ?>"><?= $student['status'] ?></span>
            </div>
            
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-envelope"></i> Email</div>
                    <div class="info-value"><?= htmlspecialchars($student['email']) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-phone"></i> Contact Number</div>
                    <div class="info-value"><?= htmlspecialchars($student['contact_number'] ?? 'Not provided') ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-calendar-alt"></i> Birth Date</div>
                    <div class="info-value"><?= !empty($student['birth_date']) ? date('F d, Y', strtotime($student['birth_date'])) : 'Not provided' ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-map-marker-alt"></i> Address</div>
                    <div class="info-value"><?= htmlspecialchars($student['address'] ?? 'Not provided') ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label"><i class="fas fa-user-id"></i> Student ID</div>
                    <div class="info-value">STU-<?= str_pad($student['user_id'], 5, '0', STR_PAD_LEFT) ?></div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h1><i class="fas fa-file-alt"></i> Thesis Submissions</h1>
                <span class="status-badge">Total: <?= count($theses) ?></span>
            </div>
            
            <?php if (empty($theses)): ?>
                <div class="empty-state">
                    <i class="fas fa-folder-open"></i>
                    <p>No thesis submissions yet.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="theses-table">
                        <thead>
                            <tr>
                                <th>Thesis Title</th>
                                <th>Adviser</th>
                                <th>Department</th>
                                <th>Year</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($theses as $thesis): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($thesis['title']) ?></strong></td>
                                <td><?= htmlspecialchars($thesis['adviser'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($thesis['department'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($thesis['year'] ?? 'N/A') ?></td>
                                <td><span class="status-badge" style="background:#cce5ff; color:#004085;"><?= ucfirst(str_replace('_', ' ', $thesis['status'])) ?></span></td>
                                <td><a href="reviewThesis.php?id=<?= $thesis['thesis_id'] ?>" class="btn-view"><i class="fas fa-eye"></i> View</a></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        const darkToggle = document.getElementById('darkmode');
        if(darkToggle){
            darkToggle.addEventListener('change',()=>{
                document.body.classList.toggle('dark-mode');
                localStorage.setItem('darkMode',darkToggle.checked);
            });
            if(localStorage.getItem('darkMode')==='true'){
                darkToggle.checked=true;
                document.body.classList.add('dark-mode');
            }
        }
    </script>
</body>
</html>