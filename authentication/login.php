<?php
session_start();
include("../config/db.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

$message = "";
$message_type = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $message = "Username and password are required.";
        $message_type = "error";
    } else {

        $stmt = $conn->prepare("
            SELECT user_id, role_id, first_name, last_name, username, email, password, status
            FROM user_table
            WHERE username = ? OR email = ?
            LIMIT 1
        ");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            
            $role_id = (int)$row['role_id'];
            $status = (string)($row['status'] ?? 'Pending');
            
            if ($status !== "Active") {
                $message = "Your account is inactive/pending. Contact admin.";
                $message_type = "error";
            } else {

                if (password_verify($password, $row['password'])) {

                    $_SESSION['user_id'] = (int)$row['user_id'];
                    $_SESSION['role_id'] = $role_id;
                    $_SESSION['username'] = $row['username'];
                    $_SESSION['first_name'] = $row['first_name'];
                    $_SESSION['last_name']  = $row['last_name'];
                    $_SESSION['login_time'] = date('Y-m-d H:i:s');
                    
                    $redirect = null;
                    
                    // ============================================================
                    // CORRECT ROLE MAPPING - I-ADJUST NI BASE SA IMO DATABASE
                    // ============================================================
                    switch ($role_id) {
                        case 1:
                            $_SESSION['role'] = 'admin';
                            $redirect = "/ArchivingThesis/admin/admindashboard.php";
                            break;
                        case 2:
                            $_SESSION['role'] = 'student';
                            $redirect = "/ArchivingThesis/student/student_dashboard.php";
                            break;
                        case 3:
                            $_SESSION['role'] = 'faculty';
                            $redirect = "/ArchivingThesis/faculty/facultyDashboard.php";
                            break;
                        case 4:
                            $_SESSION['role'] = 'dean';
                            $redirect = "/ArchivingThesis/departmentDeanDashboard/dean.php";
                            break;
                        case 5:
                            $_SESSION['role'] = 'librarian';
                            $redirect = "/ArchivingThesis/librarian/librarian_dashboard.php";
                            break;
                        case 6:
                            $_SESSION['role'] = 'coordinator';
                            
                            // Get coordinator's department info
                            $dept_query = "SELECT d.department_name, d.department_code, dc.position 
                                           FROM department_coordinator dc 
                                           JOIN department_table d ON dc.department_id = d.department_id 
                                           WHERE dc.user_id = ?";
                            $dept_stmt = $conn->prepare($dept_query);
                            $dept_stmt->bind_param("i", $row['user_id']);
                            $dept_stmt->execute();
                            $dept_result = $dept_stmt->get_result();
                            $dept_data = $dept_result->fetch_assoc();
                            
                            $_SESSION['department'] = $dept_data['department_name'] ?? 'Research Department';
                            $_SESSION['position'] = $dept_data['position'] ?? 'Research Coordinator';
                            $dept_stmt->close();
                            
                            $redirect = "/ArchivingThesis/coordinator/coordinatorDashboard.php";
                            break;
                        default:
                            $message = "Invalid user role (ID: $role_id). Please contact administrator.";
                            $message_type = "error";
                            break;
                    }

                    // Check if redirect is set and no error message
                    if ($redirect && empty($message)) {
                        header("Location: $redirect");
                        exit;
                    }

                } else {
                    $message = "Invalid username/email or password.";
                    $message_type = "error";
                }
            }

        } else {
            $message = "Invalid username/email or password.";
            $message_type = "error";
        }

        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Thesis Archiving System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Symbols+Outlined" rel="stylesheet">
    <link rel="stylesheet" href="css/login.css">
</head>
<body>
    <!-- Dark Mode Toggle -->
    <div class="theme-toggle" id="themeToggle">
        <span class="material-symbols-outlined">dark_mode</span>
    </div>

    <nav class="navbar">
        <div class="nav-container">
            <a href="homepage.php" class="logo">
                <span class="material-symbols-outlined">book</span>
                Web-Based Thesis Archiving System
            </a>
            <ul class="nav-links">
                <li><a href="homepage.php">Home</a></li>
                <li><a href="browse.php">Browse Thesis</a></li>
                <li><a href="about.php">About</a></li>
                <li><a href="login.php" class="active">Login</a></li>
                <li><a href="register.php">Register</a></li>
            </ul>
        </div>
    </nav>

    <div class="container">
        <div class="login-container">
            <div class="login-header">
                <span class="material-symbols-outlined login-icon">lock</span>
                <h2>Login to Your Account</h2>
                <p>Enter your credentials to access the system</p>
            </div>

            <?php if (!empty($message)) : ?>
                <div class="message <?php echo $message_type; ?>">
                    <span class="message-icon">
                        <?php echo ($message_type === 'success') ? '✓' : '✕'; ?>
                    </span>
                    <span class="message-text"><?php echo htmlspecialchars($message); ?></span>
                </div>
            <?php endif; ?>

            <form class="login-form" id="loginForm" method="post">
                <div class="form-group">
                    <label for="username">Username or Email</label>
                    <div class="input-wrapper">
                        <span class="material-symbols-outlined input-icon">person</span>
                        <input type="text" id="username" name="username" placeholder="Enter your username or email" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper">
                        <span class="material-symbols-outlined input-icon">lock</span>
                        <input type="password" id="password" name="password" placeholder="Enter your password" required>
                        <span class="material-symbols-outlined password-toggle" id="login-toggle">visibility_off</span>
                    </div>
                </div>

                <div class="form-options">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember">
                        <span>Remember me</span>
                    </label>
                    <a href="forgot_password.php" class="forgot-link">Forgot password?</a>
                </div>

                <button type="submit" class="btn-login">Login</button>

                <div class="register-link">
                    <p>Don't have an account? <a href="register.php">Register here</a></p>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Password toggle
        const loginToggle = document.getElementById('login-toggle');
        const loginPass = document.getElementById('password');

        if (loginToggle && loginPass) {
            loginToggle.addEventListener('click', () => {
                if (loginPass.type === 'password') {
                    loginPass.type = 'text';
                    loginToggle.textContent = 'visibility';
                } else {
                    loginPass.type = 'password';
                    loginToggle.textContent = 'visibility_off';
                }
            });
        }

        // Dark mode toggle
        const toggle = document.getElementById('themeToggle');
        if (toggle) {
            toggle.addEventListener('click', () => {
                document.body.classList.toggle('dark-mode');
                const icon = toggle.querySelector('span');
                if (document.body.classList.contains('dark-mode')) {
                    icon.textContent = 'light_mode';
                    localStorage.setItem('darkMode', 'true');
                } else {
                    icon.textContent = 'dark_mode';
                    localStorage.setItem('darkMode', 'false');
                }
            });
            
            if (localStorage.getItem('darkMode') === 'true') {
                document.body.classList.add('dark-mode');
                toggle.querySelector('span').textContent = 'light_mode';
            }
        }
    </script>
</body>
</html>