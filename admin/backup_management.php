<?php
    session_start();
    include("../config/db.php");

    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    // LOGIN VALIDATION - ONLY ADMIN
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        header("Location: /ArchivingThesis/authentication/login.php");
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $first_name = $_SESSION['first_name'] ?? '';
    $last_name = $_SESSION['last_name'] ?? '';
    $fullName = $first_name . " " . $last_name;
    $initials = strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));

    // AUDIT LOGS TABLE
    $conn->query("CREATE TABLE IF NOT EXISTS audit_logs (
        audit_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        action_type VARCHAR(255),
        table_name VARCHAR(100),
        record_id INT,
        description TEXT,
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $conn->query("ALTER TABLE audit_logs ADD COLUMN IF NOT EXISTS ip_address VARCHAR(45) AFTER description");

    function logAdminAction($conn, $user_id, $action, $table, $record_id, $description)
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        if ($ip == '::1') $ip = '127.0.0.1';
        $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action_type, table_name, record_id, description, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ississ", $user_id, $action, $table, $record_id, $description, $ip);
        $stmt->execute();
        $stmt->close();
    }

    logAdminAction($conn, $user_id, "Admin accessed backup page", "backup", 0, "Admin opened backup management");

    // ========== CONFIGURATION ==========
    $base_path = dirname(__DIR__);
    
    // Uploads path
    if (!defined('UPLOADS_PATH')) {
        $uploads_candidates = [
            $base_path . '/uploads',
            __DIR__ . '/uploads'
        ];
        $found = false;
        foreach ($uploads_candidates as $candidate) {
            if (is_dir($candidate)) {
                define('UPLOADS_PATH', realpath($candidate));
                $found = true;
                break;
            }
        }
        if (!$found) define('UPLOADS_PATH', $base_path . '/uploads');
    }
    
    if (!is_dir(UPLOADS_PATH)) {
        mkdir(UPLOADS_PATH, 0777, true);
    }
    
    // Local backup path
    if (!defined('LOCAL_BACKUP_PATH')) {
        define('LOCAL_BACKUP_PATH', $base_path . '/backup_storage');
    }
    
    if (!is_dir(LOCAL_BACKUP_PATH)) {
        mkdir(LOCAL_BACKUP_PATH, 0777, true);
    }

    // Rclone path detection
    $rclone_paths = [
        'C:\\xampp\\htdocs\\rclone-v1.73.5-windows-amd64\\rclone.exe',
        $base_path . '/rclone-v1.73.5-windows-amd64/rclone.exe',
        'C:\\xampp\\htdocs\\rclone\\rclone.exe',
        'rclone'
    ];
    
    $rclone_available = false;
    $rclone_path_used = 'rclone';
    foreach ($rclone_paths as $path) {
        if (file_exists($path)) {
            define('RCLONE_PATH', $path);
            $rclone_available = true;
            $rclone_path_used = $path;
            break;
        }
    }
    
    if (!defined('RCLONE_PATH')) {
        define('RCLONE_PATH', 'rclone');
    }
    
    if (!defined('GDRIVE_REMOTE')) {
        define('GDRIVE_REMOTE', 'gdrive:ThesesFOLDER');
    }
    
    // Check Google Drive configuration
    $gdrive_configured = false;
    $gdrive_backup_files = [];
    
    if ($rclone_available && file_exists(RCLONE_PATH)) {
        $ver = shell_exec(RCLONE_PATH . " version 2>&1");
        if (strpos($ver, 'rclone') !== false) {
            $remote_name = explode(':', GDRIVE_REMOTE)[0];
            $remotes = shell_exec(RCLONE_PATH . " listremotes 2>&1");
            if (strpos($remotes, $remote_name) !== false) {
                $gdrive_configured = true;
                $cmd = RCLONE_PATH . " ls " . escapeshellarg(GDRIVE_REMOTE) . " 2>&1";
                exec($cmd, $output, $status);
                if ($status === 0) {
                    foreach ($output as $line) {
                        if (preg_match('/^\s*\d+\s+(.+)$/', $line, $matches)) {
                            $gdrive_backup_files[] = trim($matches[1]);
                        }
                    }
                }
            }
        }
    }

    // ========== HELPER FUNCTIONS ==========
    function copyFileToDest($src, $dest_folder, $dest_filename = null)
    {
        if (!file_exists($src)) {
            return ['status' => 'error', 'message' => "Source file not found: $src"];
        }
        if (!is_dir($dest_folder)) {
            mkdir($dest_folder, 0777, true);
        }
        $filename = $dest_filename ?: basename($src);
        $dst = $dest_folder . DIRECTORY_SEPARATOR . $filename;
        if (copy($src, $dst)) {
            return ['status' => 'success', 'message' => "Copied to $dst"];
        } else {
            return ['status' => 'error', 'message' => "Failed to copy to $dst"];
        }
    }

    function hasUploadFiles()
    {
        if (!is_dir(UPLOADS_PATH)) return false;
        $files = scandir(UPLOADS_PATH);
        if ($files === false) return false;
        foreach ($files as $file) {
            if ($file != '.' && $file != '..' && is_file(UPLOADS_PATH . DIRECTORY_SEPARATOR . $file)) {
                return true;
            }
        }
        return false;
    }

    function backupAllLocal()
    {
        if (!hasUploadFiles()) {
            return ['status' => 'error', 'output' => 'No files found in the uploads folder.'];
        }
        if (!is_dir(UPLOADS_PATH)) {
            return ['status' => 'error', 'output' => 'Uploads folder not found'];
        }
        
        $remote = LOCAL_BACKUP_PATH;
        if (!is_dir($remote)) mkdir($remote, 0777, true);
        
        $output = '';
        $count = 0;
        $files = scandir(UPLOADS_PATH);
        if ($files === false) {
            return ['status' => 'error', 'output' => 'Cannot read uploads directory'];
        }
        
        foreach ($files as $file) {
            if ($file == '.' || $file == '..') continue;
            $src = UPLOADS_PATH . DIRECTORY_SEPARATOR . $file;
            $dst = $remote . DIRECTORY_SEPARATOR . $file;
            if (is_file($src)) {
                if (copy($src, $dst)) {
                    $output .= "Copied: $file\n";
                    $count++;
                } else {
                    $output .= "Failed: $file\n";
                }
            }
        }
        return ['status' => 'success', 'output' => "Backed up $count file(s).\n$output"];
    }
    
    function backupFileLocal($source_path)
    {
        global $base_path;
        $possible_paths = [
            $base_path . '/' . $source_path,
            UPLOADS_PATH . '/' . basename($source_path),
            $source_path
        ];
        
        $src = null;
        foreach ($possible_paths as $path) {
            if (file_exists($path)) {
                $src = $path;
                break;
            }
        }
        
        if (!$src) {
            return ['status' => 'error', 'message' => "File not found: " . basename($source_path)];
        }
        return copyFileToDest($src, LOCAL_BACKUP_PATH);
    }

    function backupToGoogleDrive($source_path = null, $is_full_backup = false)
    {
        global $base_path;
        
        if ($is_full_backup && !hasUploadFiles()) {
            return ['status' => 'error', 'message' => 'No files found in uploads folder.'];
        }
        
        if (!RCLONE_PATH || !file_exists(RCLONE_PATH)) {
            return ['status' => 'error', 'message' => 'rclone not found at: ' . RCLONE_PATH];
        }
        
        $remote_name = explode(':', GDRIVE_REMOTE)[0];
        $remotes = shell_exec(RCLONE_PATH . " listremotes 2>&1");
        if (strpos($remotes, $remote_name) === false) {
            return ['status' => 'error', 'message' => "Google Drive remote '$remote_name' not configured. Run 'rclone config'."];
        }
        
        if ($is_full_backup) {
            $cmd = RCLONE_PATH . " sync " . escapeshellarg(UPLOADS_PATH) . " " . escapeshellarg(GDRIVE_REMOTE) . " --verbose 2>&1";
        } else {
            $possible_paths = [
                $base_path . '/' . $source_path,
                UPLOADS_PATH . '/' . basename($source_path)
            ];
            $src = null;
            foreach ($possible_paths as $path) {
                if (file_exists($path)) {
                    $src = $path;
                    break;
                }
            }
            if (!$src) {
                return ['status' => 'error', 'message' => "File not found: " . basename($source_path)];
            }
            $cmd = RCLONE_PATH . " copy " . escapeshellarg($src) . " " . escapeshellarg(GDRIVE_REMOTE) . " --verbose 2>&1";
        }
        
        exec($cmd, $output, $status);
        $out = implode("\n", $output);
        if ($status === 0) {
            return ['status' => 'success', 'message' => "Backup successful.\n$out"];
        } else {
            return ['status' => 'error', 'message' => "Backup failed (code $status).\n$out"];
        }
    }

    function restoreFromGoogleDrive($filename = null, $is_full_restore = false)
    {
        if (!RCLONE_PATH || !file_exists(RCLONE_PATH)) {
            return ['status' => 'error', 'message' => 'rclone not found.'];
        }
        
        if (!is_dir(UPLOADS_PATH)) {
            mkdir(UPLOADS_PATH, 0777, true);
        }
        
        if ($is_full_restore) {
            $cmd = RCLONE_PATH . " copy " . escapeshellarg(GDRIVE_REMOTE) . " " . escapeshellarg(UPLOADS_PATH) . " --verbose 2>&1";
        } else {
            if (!$filename) return ['status' => 'error', 'message' => "No filename specified."];
            $cmd = RCLONE_PATH . " copy " . escapeshellarg(GDRIVE_REMOTE . "/" . $filename) . " " . escapeshellarg(UPLOADS_PATH) . " --verbose 2>&1";
        }
        
        exec($cmd, $output, $status);
        $out = implode("\n", $output);
        if ($status === 0) {
            return ['status' => 'success', 'message' => "Restore successful.\n$out"];
        } else {
            return ['status' => 'error', 'message' => "Restore failed (code $status).\n$out"];
        }
    }

    // ========== HANDLE POST REQUESTS ==========
    $local_result = $local_error = $gdrive_result = $gdrive_error = $restore_result = $restore_error = null;
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['backup_all_local'])) {
            $res = backupAllLocal();
            if ($res['status'] === 'success') {
                $local_result = "✅ " . nl2br(htmlspecialchars($res['output']));
                logAdminAction($conn, $user_id, "Local full backup", "backup", 0, "Admin performed local full backup");
            } else {
                $local_error = "❌ " . $res['output'];
            }
        }
        
        if (isset($_POST['backup_single_local']) && !empty($_POST['file_path_local'])) {
            $res = backupFileLocal($_POST['file_path_local']);
            if ($res['status'] === 'success') {
                $local_result = "✅ File backed up locally.";
                logAdminAction($conn, $user_id, "Local single backup", "backup", 0, "Admin backed up file locally");
            } else {
                $local_error = "❌ " . $res['message'];
            }
        }
        
        if (isset($_POST['backup_all_gdrive'])) {
            $res = backupToGoogleDrive(null, true);
            if ($res['status'] === 'success') {
                $gdrive_result = "✅ " . nl2br(htmlspecialchars($res['message']));
                logAdminAction($conn, $user_id, "Google Drive full backup", "backup", 0, "Admin performed full backup to Google Drive");
            } else {
                $gdrive_error = "❌ " . $res['message'];
            }
        }
        
        if (isset($_POST['backup_single_gdrive']) && !empty($_POST['file_path_gdrive'])) {
            $res = backupToGoogleDrive($_POST['file_path_gdrive'], false);
            if ($res['status'] === 'success') {
                $gdrive_result = "✅ " . nl2br(htmlspecialchars($res['message']));
                logAdminAction($conn, $user_id, "Google Drive single backup", "backup", 0, "Admin backed up file to Google Drive");
            } else {
                $gdrive_error = "❌ " . $res['message'];
            }
        }
        
        if (isset($_POST['restore_all_gdrive'])) {
            $res = restoreFromGoogleDrive(null, true);
            if ($res['status'] === 'success') {
                $restore_result = "✅ " . nl2br(htmlspecialchars($res['message']));
                logAdminAction($conn, $user_id, "Full restore", "backup", 0, "Admin performed full restore");
            } else {
                $restore_error = "❌ " . $res['message'];
            }
        }
        
        if (isset($_POST['restore_single_gdrive']) && !empty($_POST['restore_filename'])) {
            $res = restoreFromGoogleDrive(basename($_POST['restore_filename']), false);
            if ($res['status'] === 'success') {
                $restore_result = "✅ " . nl2br(htmlspecialchars($res['message']));
                logAdminAction($conn, $user_id, "Single restore", "backup", 0, "Admin restored file");
            } else {
                $restore_error = "❌ " . $res['message'];
            }
        }
    }

    // ========== GET ARCHIVED THESES - FIXED: use is_archived instead of status ====================
    $thesis_files = [];
    // FIXED: Use is_archived = 1 instead of status = 'archived'
    $thesis_query = $conn->query("SELECT thesis_id, title, file_path FROM thesis_table WHERE file_path IS NOT NULL AND file_path != '' AND is_archived = 1");
    if ($thesis_query && $thesis_query->num_rows > 0) {
        while ($row = $thesis_query->fetch_assoc()) {
            $possible_paths = [
                $base_path . '/' . $row['file_path'],
                $base_path . '/uploads/' . basename($row['file_path']),
                UPLOADS_PATH . '/' . basename($row['file_path'])
            ];
            
            foreach ($possible_paths as $path) {
                if (file_exists($path)) {
                    $thesis_files[] = $row;
                    break;
                }
            }
        }
    }

    // Get notification count
    $notificationCount = 0;
    $notif_check = $conn->query("SHOW TABLES LIKE 'notifications'");
    if ($notif_check && $notif_check->num_rows > 0) {
        $col_check = $conn->query("SHOW COLUMNS FROM notifications LIKE 'is_read'");
        if ($col_check && $col_check->num_rows > 0) {
            $n = $conn->prepare("SELECT COUNT(*) as c FROM notifications WHERE user_id = ? AND is_read = 0");
            $n->bind_param("i", $user_id);
            $n->execute();
            $res = $n->get_result();
            if ($row = $res->fetch_assoc()) $notificationCount = $row['c'];
            $n->close();
        }
    }
    
    // Get recent notifications
    $recentNotifications = [];
    $notif_list = $conn->prepare("SELECT notification_id, message, type, link, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
    $notif_list->bind_param("i", $user_id);
    $notif_list->execute();
    $notif_result = $notif_list->get_result();
    while ($row = $notif_result->fetch_assoc()) {
        $recentNotifications[] = $row;
    }
    $notif_list->close();
    
    // Do not close the connection here since we need it for the rest of the page
    // $conn->close(); - REMOVED to avoid issues
    ?>
 <!DOCTYPE html>
 <html lang="en">

 <head>
     <meta charset="UTF-8">
     <meta name="viewport" content="width=device-width, initial-scale=1.0">
     <title>Backup Management | Admin</title>
     <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
     <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
     <style>
         /* ===== SAME STYLES AS YOUR ADMIN DASHBOARD ===== */
         * {
             margin: 0;
             padding: 0;
             box-sizing: border-box;
         }

         body {
             font-family: 'Inter', sans-serif;
             background: #fef2f2;
             color: #1f2937;
             overflow-x: hidden;
         }

         .top-nav {
             position: fixed;
             top: 0;
             right: 0;
             left: 0;
             height: 70px;
             background: white;
             display: flex;
             align-items: center;
             justify-content: space-between;
             padding: 0 32px;
             box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
             z-index: 99;
             border-bottom: 1px solid #ffcdd2;
         }

         .nav-left {
             display: flex;
             align-items: center;
             gap: 24px;
         }

         .hamburger {
             display: flex;
             flex-direction: column;
             gap: 5px;
             width: 40px;
             height: 40px;
             background: #fef2f2;
             border: none;
             border-radius: 8px;
             cursor: pointer;
             padding: 12px;
             align-items: center;
             justify-content: center;
         }

         .hamburger span {
             display: block;
             width: 20px;
             height: 2px;
             background: #dc2626;
             border-radius: 2px;
             transition: 0.3s;
         }

         .logo {
             font-size: 1.3rem;
             font-weight: 700;
             color: #d32f2f;
         }

         .logo span {
             color: #d32f2f;
         }

         .search-area {
             display: flex;
             align-items: center;
             background: #fef2f2;
             padding: 8px 16px;
             border-radius: 40px;
             gap: 10px;
             border: 1px solid #ffcdd2;
         }

         .search-area input {
             border: none;
             background: none;
             outline: none;
             font-size: 0.85rem;
             width: 220px;
         }

         .nav-right {
             display: flex;
             align-items: center;
             gap: 20px;
         }

         .notification-icon {
             position: relative;
             cursor: pointer;
             width: 40px;
             height: 40px;
             background: #fef2f2;
             border-radius: 50%;
             display: flex;
             align-items: center;
             justify-content: center;
         }

         .notification-badge {
             position: absolute;
             top: -5px;
             right: -5px;
             background: #ef4444;
             color: white;
             font-size: 0.6rem;
             font-weight: 600;
             min-width: 18px;
             height: 18px;
             border-radius: 10px;
             display: flex;
             align-items: center;
             justify-content: center;
         }

         .profile-wrapper {
             position: relative;
         }

         .profile-trigger {
             display: flex;
             align-items: center;
             gap: 12px;
             cursor: pointer;
             padding: 5px 12px;
             border-radius: 40px;
             transition: background 0.3s;
         }

         .profile-trigger:hover {
             background: #ffebee;
         }

         .profile-name {
             font-weight: 500;
             color: #1f2937;
             font-size: 0.9rem;
         }

         .profile-avatar {
             width: 38px;
             height: 38px;
             background: linear-gradient(135deg, #dc2626, #5b3b3b);
             border-radius: 50%;
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
             z-index: 100;
             border: 1px solid #ffcdd2;
         }

         .profile-dropdown.show {
             display: block;
             animation: fadeIn 0.2s;
         }

         .profile-dropdown a {
             display: flex;
             align-items: center;
             gap: 12px;
             padding: 12px 18px;
             text-decoration: none;
             color: #1f2937;
             transition: 0.2s;
             font-size: 0.85rem;
         }

         .profile-dropdown a:hover {
             background: #ffebee;
             color: #dc2626;
         }

         .sidebar {
             position: fixed;
             top: 0;
             left: -300px;
             width: 280px;
             height: 100%;
             background: linear-gradient(180deg, #b71c1c 0%, #d32f2f 100%);
             display: flex;
             flex-direction: column;
             z-index: 1000;
             transition: left 0.3s ease;
             box-shadow: 4px 0 15px rgba(0, 0, 0, 0.1);
         }

         .sidebar.open {
             left: 0;
         }

         .logo-container {
             padding: 28px 24px;
             border-bottom: 1px solid rgba(255, 255, 255, 0.2);
             text-align: center;
         }

         .logo-container .logo {
             color: white;
             font-size: 1.4rem;
         }

         .logo-container .logo span {
             color: #ffcdd2;
         }

         .admin-label {
             font-size: 0.7rem;
             color: #ffcdd2;
             margin-top: 5px;
             letter-spacing: 1px;
         }

         .nav-menu {
             flex: 1;
             padding: 24px 16px;
             display: flex;
             flex-direction: column;
             gap: 6px;
         }

         .nav-item {
             display: flex;
             align-items: center;
             gap: 14px;
             padding: 12px 16px;
             border-radius: 12px;
             text-decoration: none;
             color: #ffebee;
             transition: all 0.2s;
             font-weight: 500;
         }

         .nav-item i {
             width: 22px;
             font-size: 1.1rem;
         }

         .nav-item:hover,
         .nav-item.active {
             background: rgba(255, 255, 255, 0.25);
             color: white;
             transform: translateX(5px);
         }

         .dashboard-links {
             padding: 16px;
             border-top: 1px solid rgba(255, 255, 255, 0.15);
             border-bottom: 1px solid rgba(255, 255, 255, 0.15);
             margin: 5px 0;
         }

         .dashboard-links-header {
             display: flex;
             align-items: center;
             gap: 10px;
             padding: 8px 12px;
             color: #ffcdd2;
             font-size: 0.7rem;
             text-transform: uppercase;
             letter-spacing: 1px;
             margin-bottom: 8px;
         }

         .dashboard-link {
             display: flex;
             align-items: center;
             gap: 12px;
             padding: 8px 12px;
             border-radius: 10px;
             text-decoration: none;
             color: #ffebee;
             font-size: 0.8rem;
             transition: all 0.2s;
         }

         .dashboard-link .link-icon {
             margin-left: auto;
             font-size: 0.7rem;
             opacity: 0.7;
         }

         .nav-footer {
             padding: 20px 16px;
             border-top: 1px solid rgba(255, 255, 255, 0.15);
         }

         .theme-toggle {
             margin-bottom: 15px;
         }

         .theme-toggle input {
             display: none;
         }

         .toggle-label {
             display: flex;
             align-items: center;
             gap: 12px;
             cursor: pointer;
             position: relative;
             width: 55px;
             height: 28px;
             background: rgba(255, 255, 255, 0.25);
             border-radius: 30px;
         }

         .toggle-label i {
             position: absolute;
             top: 50%;
             transform: translateY(-50%);
             font-size: 12px;
             z-index: 1;
         }

         .toggle-label i:first-child {
             left: 8px;
             color: #f39c12;
         }

         .toggle-label i:last-child {
             right: 8px;
             color: #f1c40f;
         }

         .toggle-label .slider {
             position: absolute;
             top: 3px;
             left: 3px;
             width: 22px;
             height: 22px;
             background: white;
             border-radius: 50%;
             transition: transform 0.3s;
         }

         #darkmode:checked+.toggle-label .slider {
             transform: translateX(27px);
         }

         .logout-btn {
             display: flex;
             align-items: center;
             gap: 12px;
             padding: 10px 12px;
             text-decoration: none;
             color: #ffebee;
             border-radius: 10px;
             transition: all 0.2s;
         }

         .logout-btn:hover {
             background: rgba(255, 255, 255, 0.15);
             color: white;
         }

         .main-content {
             margin-left: 0;
             margin-top: 70px;
             padding: 30px;
             transition: margin-left 0.3s;
         }

         .sidebar-overlay {
             position: fixed;
             top: 0;
             left: 0;
             width: 100%;
             height: 100%;
             background: rgba(0, 0, 0, 0.5);
             z-index: 999;
             display: none;
         }

         .sidebar-overlay.show {
             display: block;
         }

         .welcome-banner {
             background: linear-gradient(135deg, #b71c1c, #d32f2f);
             border-radius: 20px;
             padding: 30px 35px;
             margin-bottom: 30px;
             color: white;
             display: flex;
             justify-content: space-between;
             align-items: center;
         }

         .welcome-info h1 {
             font-size: 1.6rem;
             font-weight: 700;
             margin-bottom: 8px;
         }

         .admin-info {
             text-align: right;
         }

         .admin-name {
             font-size: 1rem;
             font-weight: 600;
             margin-bottom: 4px;
         }

         .admin-since {
             font-size: 0.7rem;
             opacity: 0.8;
         }

         .backup-section {
             background: white;
             border-radius: 20px;
             padding: 25px;
             margin-top: 25px;
             border: 1px solid #ffcdd2;
             transition: all 0.3s;
         }

         .backup-section h3 {
             font-size: 1.2rem;
             font-weight: 600;
             color: #d32f2f;
             margin-bottom: 20px;
             display: flex;
             align-items: center;
             gap: 10px;
         }

         .backup-layout {
             display: flex;
             gap: 30px;
             flex-wrap: wrap;
             margin-top: 15px;
         }

         .backup-col {
             flex: 1;
             min-width: 300px;
         }

         .backup-card-item {
             background: #ffebee;
             border-radius: 16px;
             padding: 20px;
             transition: 0.2s;
             height: 100%;
             display: flex;
             flex-direction: column;
         }

         .backup-card-item h4 {
             margin-bottom: 15px;
             color: #b91c1c;
             display: flex;
             align-items: center;
             gap: 8px;
             font-size: 1.1rem;
             border-bottom: 1px solid #ffcdd2;
             padding-bottom: 10px;
         }

         .btn-backup {
             background: #d32f2f;
             color: white;
             border: none;
             padding: 10px 20px;
             border-radius: 40px;
             font-weight: 600;
             cursor: pointer;
             transition: 0.2s;
             font-size: 0.85rem;
             margin-right: 8px;
             margin-bottom: 8px;
         }

         .btn-backup:hover {
             background: #b71c1c;
             transform: translateY(-2px);
         }

         .btn-secondary {
             background: #475569;
         }

         .file-select {
             width: 100%;
             padding: 10px;
             border-radius: 12px;
             border: 1px solid #ffcdd2;
             margin: 15px 0;
             background: white;
         }

         .alert-success {
             background: #d1fae5;
             color: #065f46;
             padding: 15px;
             border-radius: 12px;
             margin-bottom: 20px;
             border-left: 4px solid #10b981;
         }

         .alert-error {
             background: #fee2e2;
             color: #991b1b;
             padding: 15px;
             border-radius: 12px;
             margin-bottom: 20px;
             border-left: 4px solid #ef4444;
         }

         .alert-info {
             background: #dbeafe;
             color: #1e40af;
             padding: 12px;
             border-radius: 12px;
             margin-bottom: 15px;
             border-left: 4px solid #3b82f6;
         }

         body.dark-mode .backup-section,
         body.dark-mode .stat-card,
         body.dark-mode .stat-card-small,
         body.dark-mode .chart-card,
         body.dark-mode .info-card {
             background: #1e293b;
             border-color: #334155;
         }

         body.dark-mode .backup-card-item {
             background: #334155;
         }

         body.dark-mode .backup-card-item h4 {
             color: #fecaca;
             border-bottom-color: #475569;
         }

         body.dark-mode .file-select {
             background: #0f172a;
             border-color: #475569;
             color: white;
         }

         body.dark-mode .alert-success {
             background: #064e3b;
             color: #a7f3d0;
         }

         body.dark-mode .alert-error {
             background: #7f1d1d;
             color: #fecaca;
         }

         body.dark-mode .alert-info {
             background: #1e3a8a;
             color: #bfdbfe;
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

         @media (max-width: 768px) {
             .main-content {
                 padding: 20px;
             }

             .backup-layout {
                 flex-direction: column;
             }

             .search-area {
                 display: none;
             }

             .profile-name {
                 display: none;
             }

             .welcome-banner {
                 flex-direction: column;
                 text-align: center;
             }

             .admin-info {
                 text-align: center;
             }
         }

         body.dark-mode {
             background: #0f172a;
         }

         body.dark-mode .top-nav {
             background: #1e293b;
             border-bottom-color: #334155;
         }

         body.dark-mode .logo {
             color: #fecaca;
         }

         body.dark-mode .profile-name {
             color: #e5e7eb;
         }

         body.dark-mode .profile-dropdown {
             background: #1e293b;
             border-color: #334155;
         }

         .folder-link {
             display: inline-flex;
             align-items: center;
             gap: 6px;
             background: #e2e8f0;
             padding: 6px 12px;
             border-radius: 20px;
             text-decoration: none;
             color: #1f2937;
             font-size: 0.75rem;
             margin-top: 10px;
         }

         body.dark-mode .folder-link {
             background: #475569;
             color: #e5e7eb;
         }
     </style>
 </head>

 <body>
     <div class="sidebar-overlay" id="sidebarOverlay"></div>
     <header class="top-nav">
         <div class="nav-left">
             <button class="hamburger" id="hamburgerBtn"><span></span><span></span><span></span></button>
             <div class="logo">Thesis<span>Manager</span></div>
             <div class="search-area"><i class="fas fa-search"></i><input type="text" id="searchInput" placeholder="Search..."></div>
         </div>
         <div class="nav-right">
             <div class="notification-icon"><i class="far fa-bell"></i><?php if ($notificationCount > 0): ?><span class="notification-badge"><?= $notificationCount ?></span><?php endif; ?></div>
             <div class="profile-wrapper" id="profileWrapper">
                 <div class="profile-trigger"><span class="profile-name"><?= htmlspecialchars($fullName) ?></span>
                     <div class="profile-avatar"><?= htmlspecialchars($initials) ?></div>
                 </div>
                 <div class="profile-dropdown" id="profileDropdown">
                     <a href="profile.php"><i class="fas fa-user"></i> Profile</a>
                     <a href="#"><i class="fas fa-cog"></i> Settings</a>
                     <hr><a href="/ArchivingThesis/authentication/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                 </div>
             </div>
         </div>
     </header>
     <aside class="sidebar" id="sidebar">
         <div class="logo-container">
             <div class="logo">Thesis<span>Manager</span></div>
             <div class="admin-label">ADMINISTRATOR</div>
         </div>
         <div class="nav-menu">
             <a href="admindashboard.php" class="nav-item"><i class="fas fa-th-large"></i><span>Dashboard</span></a>
             <a href="users.php" class="nav-item"><i class="fas fa-users"></i><span>Users</span></a>
             <a href="audit_logs.php" class="nav-item"><i class="fas fa-history"></i><span>Audit Logs</span></a>
             <a href="theses.php" class="nav-item"><i class="fas fa-file-alt"></i><span>Theses</span></a>
             <a href="backup_management.php" class="nav-item active"><i class="fas fa-database"></i><span>Backup</span></a>
         </div>
         <div class="dashboard-links">
             <div class="dashboard-links-header"><i class="fas fa-chalkboard-user"></i><span>Quick Access</span></div>
             <?php
                $dashboards = [
                    1 => ['name' => 'Admin', 'icon' => 'fa-user-shield', 'color' => '#d32f2f', 'folder' => 'admin', 'file' => 'admindashboard.php'],
                    2 => ['name' => 'Student', 'icon' => 'fa-user-graduate', 'color' => '#1976d2', 'folder' => 'student', 'file' => 'student_dashboard.php'],
                    3 => ['name' => 'Research Adviser', 'icon' => 'fa-chalkboard-user', 'color' => '#388e3c', 'folder' => 'faculty', 'file' => 'facultyDashboard.php'],
                    4 => ['name' => 'Dean', 'icon' => 'fa-user-tie', 'color' => '#f57c00', 'folder' => 'departmentDeanDashboard', 'file' => 'dean.php'],
                    5 => ['name' => 'Librarian', 'icon' => 'fa-book-reader', 'color' => '#7b1fa2', 'folder' => 'librarian', 'file' => 'librarian_dashboard.php'],
                    6 => ['name' => 'Coordinator', 'icon' => 'fa-clipboard-list', 'color' => '#e67e22', 'folder' => 'coordinator', 'file' => 'coordinatorDashboard.php']
                ];
                foreach ($dashboards as $dashboard): ?>
                 <a href="/ArchivingThesis/<?= $dashboard['folder'] ?>/<?= $dashboard['file'] ?>" class="dashboard-link" target="_blank"><i class="fas <?= $dashboard['icon'] ?>" style="color: <?= $dashboard['color'] ?>"></i><span><?= $dashboard['name'] ?> Dashboard</span><i class="fas fa-external-link-alt link-icon"></i></a>
             <?php endforeach; ?>
         </div>
         <div class="nav-footer">
             <div class="theme-toggle"><input type="checkbox" id="darkmode"><label for="darkmode" class="toggle-label"><i class="fas fa-sun"></i><i class="fas fa-moon"></i><span class="slider"></span></label></div>
             <a href="/ArchivingThesis/authentication/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
         </div>
     </aside>
     <main class="main-content">
         <div class="welcome-banner">
             <div class="welcome-info">
                 <h1>Backup Management</h1>
                 <p>Secure your thesis files – local & Google Drive</p>
             </div>
             <div class="admin-info">
                 <div class="admin-name"><?= htmlspecialchars($fullName) ?></div>
                 <div class="admin-since">Admin since <?= date('F Y') ?></div>
             </div>
         </div>

         <div class="backup-section">
             <h3><i class="fas fa-database"></i> Backup & Restore</h3>

             <!-- LOCAL BACKUP -->
             <div style="margin-bottom: 30px;">
                 <h4 style="color: #d32f2f; margin-bottom: 15px;"><i class="fas fa-hdd"></i> Local Backup (PC)</h4>
                 <?php if ($local_result): ?><div class="alert-success"><?= htmlspecialchars($local_result) ?></div><?php endif; ?>
                 <?php if ($local_error): ?><div class="alert-error"><?= htmlspecialchars($local_error) ?></div><?php endif; ?>
                 <div class="backup-layout">
                     <div class="backup-col">
                         <div class="backup-card-item">
                             <h4><i class="fas fa-cloud-upload-alt"></i> Full Backup</h4>
                             <p>Backup all thesis files to: <strong><?= htmlspecialchars(basename(LOCAL_BACKUP_PATH)) ?></strong></p>
                             <form method="POST"><button type="submit" name="backup_all_local" class="btn-backup"><i class="fas fa-play"></i> Run Full Backup</button></form>
                             <button onclick="copyLocalPath()" class="btn-backup btn-secondary"><i class="fas fa-copy"></i> Copy Folder Path</button>
                             <script>
                                 function copyLocalPath() {
                                     navigator.clipboard.writeText("<?= addslashes(LOCAL_BACKUP_PATH) ?>");
                                     alert("Path copied: <?= addslashes(LOCAL_BACKUP_PATH) ?>");
                                 }
                             </script>
                         </div>
                     </div>
                     <div class="backup-col">
                         <div class="backup-card-item">
                             <h4><i class="fas fa-file"></i> Single File Backup</h4>
                             <p>Select an ARCHIVED thesis to backup locally.</p>
                             <form method="POST">
                                 <select name="file_path_local" class="file-select" required>
                                     <option value="">-- Choose an archived thesis --</option>
                                     <?php foreach ($thesis_files as $thesis): ?>
                                         <option value="<?= htmlspecialchars($thesis['file_path']) ?>"><?= htmlspecialchars($thesis['title']) ?> (<?= htmlspecialchars(basename($thesis['file_path'])) ?>)</option>
                                     <?php endforeach; ?>
                                 </select>
                                 <button type="submit" name="backup_single_local" class="btn-backup"><i class="fas fa-file-export"></i> Backup Selected</button>
                             </form>
                         </div>
                     </div>
                 </div>
             </div>

             <hr style="margin: 20px 0; border-color: #ffcdd2;">

             <!-- GOOGLE DRIVE BACKUP & RESTORE -->
             <div>
                 <h4 style="color: #d32f2f; margin-bottom: 15px;"><i class="fab fa-google-drive"></i> Google Drive Backup & Restore</h4>
                 <?php if ($gdrive_result): ?><div class="alert-success"><?= nl2br(htmlspecialchars($gdrive_result)) ?></div><?php endif; ?>
                 <?php if ($gdrive_error): ?><div class="alert-error"><?= nl2br(htmlspecialchars($gdrive_error)) ?></div><?php endif; ?>
                 <?php if ($restore_result): ?><div class="alert-success"><?= nl2br(htmlspecialchars($restore_result)) ?></div><?php endif; ?>
                 <?php if ($restore_error): ?><div class="alert-error"><?= nl2br(htmlspecialchars($restore_error)) ?></div><?php endif; ?>

                 <?php if (!$rclone_available): ?>
                     <div class="alert-info"><i class="fas fa-exclamation-triangle"></i> rclone not found. Expected: <code><?= htmlspecialchars(RCLONE_PATH) ?></code>. Please install rclone.</div>
                 <?php elseif (!$gdrive_configured): ?>
                     <div class="alert-info"><i class="fas fa-exclamation-triangle"></i> Google Drive remote not configured. Run <code>rclone config</code> (create remote named "gdrive").</div>
                 <?php endif; ?>

                 <div class="backup-layout">
                     <!-- LEFT: Backup to Google Drive -->
                     <div class="backup-col">
                         <div class="backup-card-item">
                             <h4><i class="fas fa-cloud-upload-alt"></i> Backup to Google Drive</h4>
                             <p>Sync entire uploads folder to: <strong><?= htmlspecialchars(GDRIVE_REMOTE) ?></strong></p>
                             <form method="POST"><button type="submit" name="backup_all_gdrive" class="btn-backup" <?= (!$rclone_available || !$gdrive_configured) ? 'disabled' : '' ?>><i class="fab fa-google-drive"></i> Sync to Google Drive</button></form>
                             <div style="margin-top: 15px;"></div>
                             <p>Select an ARCHIVED thesis to upload individually.</p>
                             <form method="POST">
                                 <select name="file_path_gdrive" class="file-select" required>
                                     <option value="">-- Choose an archived thesis --</option>
                                     <?php foreach ($thesis_files as $thesis): ?>
                                         <option value="<?= htmlspecialchars($thesis['file_path']) ?>"><?= htmlspecialchars($thesis['title']) ?> (<?= htmlspecialchars(basename($thesis['file_path'])) ?>)</option>
                                     <?php endforeach; ?>
                                 </select>
                                 <button type="submit" name="backup_single_gdrive" class="btn-backup" <?= (!$rclone_available || !$gdrive_configured) ? 'disabled' : '' ?>><i class="fab fa-google-drive"></i> Upload Single File</button>
                             </form>
                             <a href="https://drive.google.com/drive/my-drive" target="_blank" class="folder-link"><i class="fab fa-google-drive"></i> Open Google Drive</a>
                         </div>
                     </div>

                     <!-- RIGHT: Restore from Google Drive -->
                     <div class="backup-col">
                         <div class="backup-card-item">
                             <h4><i class="fas fa-download"></i> Restore from Google Drive</h4>
                             <p>Restore all backup files back to the uploads folder.</p>
                             <form method="POST"><button type="submit" name="restore_all_gdrive" class="btn-backup" <?= (!$rclone_available || !$gdrive_configured) ? 'disabled' : '' ?>><i class="fas fa-sync-alt"></i> Full Restore</button></form>
                             <div style="margin-top: 15px;"></div>
                             <p>Select a backup file to restore individually.</p>
                             <form method="POST">
                                 <select name="restore_filename" class="file-select" required>
                                     <option value="">-- Choose a backup file --</option>
                                     <?php foreach ($gdrive_backup_files as $file): ?>
                                         <option value="<?= htmlspecialchars($file) ?>"><?= htmlspecialchars($file) ?></option>
                                     <?php endforeach; ?>
                                 </select>
                                 <button type="submit" name="restore_single_gdrive" class="btn-backup" <?= (!$rclone_available || !$gdrive_configured) ? 'disabled' : '' ?>><i class="fas fa-download"></i> Restore Selected</button>
                             </form>
                             <?php if ($rclone_available && $gdrive_configured && empty($gdrive_backup_files)): ?>
                                 <p class="alert-info" style="margin-top:10px;"><i class="fas fa-info-circle"></i> No backup files found in Google Drive.</p>
                             <?php endif; ?>
                         </div>
                     </div>
                 </div>
                 <?php if ($rclone_available && $gdrive_configured): ?>
                     <div class="alert-info" style="margin-top:15px;"><i class="fas fa-info-circle"></i> Files are synced to your Google Drive folder "<strong>ThesesFOLDER</strong>". <a href="https://drive.google.com/drive/my-drive" target="_blank">Click here</a> to open.</div>
                 <?php endif; ?>
             </div>
         </div>
     </main>

     <script>
         const hamburgerBtn = document.getElementById('hamburgerBtn');
         const sidebar = document.getElementById('sidebar');
         const overlay = document.getElementById('sidebarOverlay');
         const profileWrapper = document.getElementById('profileWrapper');
         const profileDropdown = document.getElementById('profileDropdown');
         const darkToggle = document.getElementById('darkmode');

         function openSidebar() {
             sidebar.classList.add('open');
             overlay.classList.add('show');
             document.body.style.overflow = 'hidden';
         }

         function closeSidebar() {
             sidebar.classList.remove('open');
             overlay.classList.remove('show');
             document.body.style.overflow = '';
         }
         hamburgerBtn?.addEventListener('click', (e) => {
             e.stopPropagation();
             sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
         });
         overlay?.addEventListener('click', closeSidebar);
         document.addEventListener('keydown', (e) => {
             if (e.key === 'Escape') closeSidebar();
         });
         profileWrapper?.addEventListener('click', (e) => {
             e.stopPropagation();
             profileDropdown.classList.toggle('show');
         });
         document.addEventListener('click', () => profileDropdown?.classList.remove('show'));
         if (localStorage.getItem('darkMode') === 'true') {
             document.body.classList.add('dark-mode');
             if (darkToggle) darkToggle.checked = true;
         }
         darkToggle?.addEventListener('change', function() {
             if (this.checked) {
                 document.body.classList.add('dark-mode');
                 localStorage.setItem('darkMode', 'true');
             } else {
                 document.body.classList.remove('dark-mode');
                 localStorage.setItem('darkMode', 'false');
             }
         });
     </script>
 </body>

 </html>