<?php
session_start();
include("../config/db.php");

// Optional: Include email only if file exists
if (file_exists(__DIR__ . '/../config/smtp_config.php')) {
    require_once __DIR__ . '/../config/smtp_config.php';
    $emailEnabled = true;
} else {
    $emailEnabled = false;
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

$message = "";
$success = "";

// Create pending_invitations table if not exists
$conn->query("CREATE TABLE IF NOT EXISTS pending_invitations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    invited_by INT NOT NULL,
    invited_by_name VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

// Check if column exists, if not add it
$check_column = $conn->query("SHOW COLUMNS FROM pending_invitations LIKE 'invited_by_name'");
if ($check_column->num_rows == 0) {
    $conn->query("ALTER TABLE pending_invitations ADD COLUMN invited_by_name VARCHAR(255) AFTER invited_by");
}

// Check if department column exists in user_table - if not, add it
$check_dept_column = $conn->query("SHOW COLUMNS FROM user_table LIKE 'department'");
if ($check_dept_column->num_rows == 0) {
    // Add department column if it doesn't exist
    $conn->query("ALTER TABLE user_table ADD COLUMN department VARCHAR(100) DEFAULT NULL AFTER address");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $cpassword = $_POST['cpassword'] ?? '';
    $role_id = 2;
    $contact_number = trim($_POST['contact_number'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $department = trim($_POST['department'] ?? '');  // Changed from department_id to department
    
    // Co-author invitation fields (optional)
    $invite_coauthors = trim($_POST['invite_coauthors'] ?? '');
    
    // Create full name
    $full_name = $first_name . " " . $last_name;
    
    // Validation
    if (empty($first_name) || empty($last_name) || empty($email) || empty($username) || empty($password)) {
        $message = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email address format.";
    } elseif (strlen($password) < 6) {
        $message = "Password must be at least 6 characters.";
    } elseif ($password !== $cpassword) {
        $message = "Passwords do not match.";
    } elseif (!empty($contact_number) && !preg_match('/^[0-9]{10,11}$/', $contact_number)) {
        $message = "Invalid contact number. Must be 10-11 digits.";
    } elseif (empty($department)) {
        $message = "Please select a department.";
    } else {
        // Check if email exists
        $check_email = $conn->prepare("SELECT user_id FROM user_table WHERE email = ?");
        $check_email->bind_param("s", $email);
        $check_email->execute();
        $email_exists = $check_email->get_result()->num_rows > 0;
        $check_email->close();
        
        // Check if username exists
        $check_user = $conn->prepare("SELECT user_id FROM user_table WHERE username = ?");
        $check_user->bind_param("s", $username);
        $check_user->execute();
        $username_exists = $check_user->get_result()->num_rows > 0;
        $check_user->close();
        
        // Check if contact number exists
        $check_contact = $conn->prepare("SELECT user_id FROM user_table WHERE contact_number = ?");
        $check_contact->bind_param("s", $contact_number);
        $check_contact->execute();
        $contact_exists = $check_contact->get_result()->num_rows > 0;
        $check_contact->close();
        
        if ($email_exists) {
            $message = "Email already registered.";
        } elseif ($username_exists) {
            $message = "Username already taken.";
        } elseif ($contact_exists && !empty($contact_number)) {
            $message = "Contact number already registered.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user with department (text field, not foreign key)
            $insert_user = $conn->prepare("INSERT INTO user_table (first_name, last_name, email, username, password, role_id, contact_number, address, department) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $insert_user->bind_param("sssssssss", $first_name, $last_name, $email, $username, $hashed_password, $role_id, $contact_number, $address, $department);
            
            if ($insert_user->execute()) {
                $new_user_id = $insert_user->insert_id;
                $success = "✅ Registration successful! You can now login.";
                
                // ==================== CO-AUTHOR INVITATION PROCESS ====================
                if (!empty($invite_coauthors)) {
                    $coauthors = array_map('trim', explode(',', $invite_coauthors));
                    $invited_count = 0;
                    $invited_list = array();
                    
                    foreach ($coauthors as $coauthor_email) {
                        if (!empty($coauthor_email) && filter_var($coauthor_email, FILTER_VALIDATE_EMAIL) && $coauthor_email != $email) {
                            // Check if co-author exists in system
                            $check_coauthor = $conn->prepare("SELECT user_id, email FROM user_table WHERE email = ?");
                            $check_coauthor->bind_param("s", $coauthor_email);
                            $check_coauthor->execute();
                            $coauthor_result = $check_coauthor->get_result();
                            $coauthor = $coauthor_result->fetch_assoc();
                            $check_coauthor->close();
                            
                            if ($coauthor) {
                                // Co-author exists, create notification
                                $notif_message = "📢 " . $full_name . " invited you to collaborate as co-author!";
                                $notif_query = "INSERT INTO notifications (user_id, message, type, is_read, created_at) VALUES (?, ?, 'coauthor_invitation', 0, NOW())";
                                $notif_stmt = $conn->prepare($notif_query);
                                $notif_stmt->bind_param("is", $coauthor['user_id'], $notif_message);
                                $notif_stmt->execute();
                                $notif_stmt->close();
                                
                                $invited_count++;
                                $invited_list[] = $coauthor_email;
                            } else {
                                // Co-author not yet registered - store pending invitation
                                $pending_query = "INSERT INTO pending_invitations (email, invited_by, invited_by_name) VALUES (?, ?, ?)";
                                $pending_stmt = $conn->prepare($pending_query);
                                $pending_stmt->bind_param("sis", $coauthor_email, $new_user_id, $full_name);
                                $pending_stmt->execute();
                                $pending_stmt->close();
                                
                                $invited_count++;
                                $invited_list[] = $coauthor_email . " (will be invited upon registration)";
                            }
                            
                            // Try to send email invitation (if email is enabled)
                            if ($emailEnabled && isset($emailSender) && method_exists($emailSender, 'sendInvitation')) {
                                try {
                                    $emailSender->sendInvitation(
                                        $coauthor_email,
                                        $full_name,
                                        "Thesis Collaboration",
                                        0
                                    );
                                } catch (Exception $e) {
                                    // Email failed but registration continues
                                }
                            }
                        }
                    }
                    
                    if ($invited_count > 0) {
                        $success .= " 🎉 " . $invited_count . " co-author invitation(s) sent to: " . implode(", ", $invited_list);
                    }
                }
                
            } else {
                $message = "Registration failed: " . $conn->error;
            }
            $insert_user->close();
        }
    }
}

// Get user login status for header
$is_logged_in = isset($_SESSION['user_id']);
$dashboardLink = '#';
if ($is_logged_in) {
    $roleQuery = "SELECT role_id FROM user_table WHERE user_id = ? LIMIT 1";
    $stmt = $conn->prepare($roleQuery);
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $userRole = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($userRole) {
        if ($userRole['role_id'] == 3) $dashboardLink = '../faculty/facultyDashboard.php';
        elseif ($userRole['role_id'] == 2) $dashboardLink = '../student/student_dashboard.php';
        elseif ($userRole['role_id'] == 1) $dashboardLink = '../admin/admindashboard.php';
        elseif ($userRole['role_id'] == 6) $dashboardLink = '../coordinator/coordinatorDashboard.php';
        elseif ($userRole['role_id'] == 4) $dashboardLink = '../departmentDeanDashboard/dean.php';
        elseif ($userRole['role_id'] == 5) $dashboardLink = '../librarian/librarian_dashboard.php';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Thesis Archiving System</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0,1" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="css/register.css">
</head>
<body>
    <div class="theme-toggle" id="themeToggle"><i class="fas fa-moon"></i></div>

    <nav class="navbar">
        <div class="nav-container">
            <a href="homepage.php" class="logo">
                <div class="logo-icon">📚</div>
                <span>Thesis Archive</span>
            </a>
            <ul class="nav-links">
                <li><a href="homepage.php">Home</a></li>
                <li><a href="browse.php">Browse</a></li>
                <li><a href="about.php">About</a></li>
                <?php if ($is_logged_in): ?>
                    <li><a href="<?= $dashboardLink ?>">Dashboard</a></li>
                    <li><a href="../authentication/logout.php">Logout</a></li>
                <?php else: ?>
                    <li><a href="login.php">Login</a></li>
                    <li><a href="register.php" class="active">Register</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <div class="container">
        <div class="card">
            <h1>Student Registration</h1>

            <?php if ($message): ?>
                <div class="alert"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="POST" autocomplete="off" id="registerForm">
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name <span>*</span></label>
                        <input type="text" name="first_name" placeholder="Enter first name" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name <span>*</span></label>
                        <input type="text" name="last_name" placeholder="Enter last name" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Email <span>*</span></label>
                    <input type="email" name="email" placeholder="Enter email" required>
                </div>

                <div class="form-group">
                    <label>Username <span>*</span></label>
                    <input type="text" name="username" placeholder="Enter username" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Password <span>*</span></label>
                        <div class="input-wrapper">
                            <input type="password" name="password" id="password" placeholder="Enter password (min. 6 characters)" required>
                            <span class="material-symbols-outlined password-toggle" id="togglePassword">visibility_off</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Confirm Password <span>*</span></label>
                        <div class="input-wrapper">
                            <input type="password" name="cpassword" id="cpassword" placeholder="Confirm password" required>
                            <span class="material-symbols-outlined password-toggle" id="toggleCPassword">visibility_off</span>
                        </div>
                    </div>
                </div>

                <!-- DEPARTMENT SELECTION (REQUIRED) - Using department name instead of ID -->
                <div class="form-group">
                    <label>Department <span>*</span></label>
                    <select name="department" required>
                        <option value="">Select Department</option>
                        <?php
                        $dept_query = "SELECT department_name FROM department_table";
                        $dept_result = $conn->query($dept_query);
                        if ($dept_result && $dept_result->num_rows > 0) {
                            while ($dept = $dept_result->fetch_assoc()) {
                                echo '<option value="' . htmlspecialchars($dept['department_name']) . '">' . htmlspecialchars($dept['department_name']) . '</option>';
                            }
                        } else {
                            // Fallback departments if department_table is empty
                            echo '<option value="BSIT">BS Information Technology (BSIT)</option>';
                            echo '<option value="BSCRIM">BS Criminology (BSCRIM)</option>';
                            echo '<option value="BSHTM">BS Hospitality Management (BSHTM)</option>';
                            echo '<option value="BSED">BS Education (BSED)</option>';
                            echo '<option value="BSBA">BS Business Administration (BSBA)</option>';
                        }
                        ?>
                    </select>
                </div>

                <!-- CONTACT NUMBER (REQUIRED) -->
                <div class="form-group">
                    <label>Contact Number <span>*</span></label>
                    <input type="text" name="contact_number" placeholder="09xxxxxxxxx" required>
                </div>

                <!-- ADDRESS (OPTIONAL) -->
                <div class="form-group">
                    <label>Address <span class="optional"></span></label>
                    <textarea name="address" placeholder="Enter address"></textarea>
                </div>

                <!-- CO-AUTHOR INVITATION SECTION (OPTIONAL) -->
                <div class="invite-section">
                    <label>👥 Invite Co-Authors <span class="optional">(Optional)</span></label>
                    <input type="text" name="invite_coauthors" placeholder="Enter email addresses separated by commas (e.g., john@example.com, jane@example.com)">
                    <div class="invite-note">
                        📧 Optional: Invite co-authors to collaborate with you. They will receive an invitation after registration.
                    </div>
                </div>

                <button type="submit" class="btn-register">Register as Student</button>

                <div class="or-section">OR</div>

                <div class="login-link">
                    Already have an account? <a href="login.php">Login</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        window.messageData = {
            hasMessage: <?php echo !empty($message) ? 'true' : 'false'; ?>,
            message: '<?php echo addslashes($message); ?>',
            hasSuccess: <?php echo !empty($success) ? 'true' : 'false'; ?>,
            success: '<?php echo addslashes($success); ?>'
        };
        window.isLoggedIn = <?php echo $is_logged_in ? 'true' : 'false'; ?>;
    </script>
    
    <script src="js/register.js"></script>
</body>
</html>