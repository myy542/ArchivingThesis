<?php
session_start();
include("../config/db.php");

error_reporting(E_ALL);
ini_set('display_errors', 1);

$message = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $cpassword = $_POST['cpassword'] ?? '';
    $role_id = 2;
    $contact_number = trim($_POST['contact_number'] ?? '');
    $birth_date = $_POST['birth_date'] ?? '';
    $address = trim($_POST['address'] ?? '');
    $department_id = $_POST['department_id'] ?? '';
    
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
    } else {
        $check_email = $conn->prepare("SELECT user_id FROM user_table WHERE email = ?");
        $check_email->bind_param("s", $email);
        $check_email->execute();
        $email_exists = $check_email->get_result()->num_rows > 0;
        $check_email->close();
        
        $check_user = $conn->prepare("SELECT user_id FROM user_table WHERE username = ?");
        $check_user->bind_param("s", $username);
        $check_user->execute();
        $username_exists = $check_user->get_result()->num_rows > 0;
        $check_user->close();
        
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
            
            $insert_user = $conn->prepare("INSERT INTO user_table (first_name, last_name, email, username, password, role_id, contact_number, birth_date, address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $insert_user->bind_param("sssssssss", $first_name, $last_name, $email, $username, $hashed_password, $role_id, $contact_number, $birth_date, $address);
            
            if ($insert_user->execute()) {
                $success = "Registration successful! You can now login.";
            } else {
                $message = "Registration failed: " . $conn->error;
            }
            $insert_user->close();
        }
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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif;
        }

        body {
            background: #f0f2f5;
            min-height: 100vh;
            padding: 20px;
            padding-top: 90px;
        }

        /* NAVBAR */
        .navbar {
            background: linear-gradient(135deg, #FE4853 0%, #732529 100%);
            padding: 15px 0;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            width: 100%;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .nav-container {
            max-width: 1300px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 40px;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: white;
            font-size: 1.2rem;
            font-weight: 700;
        }

        .logo .material-symbols-outlined {
            font-size: 28px;
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 30px;
        }

        .nav-links li a {
            text-decoration: none;
            color: white;
            font-size: 1rem;
            font-weight: 500;
            padding: 8px 0;
            transition: all 0.2s;
        }

        .nav-links a:hover {
            border-bottom: 2px solid white;
        }

        .nav-links a.active {
            font-weight: 600;
        }

        /* CONTAINER & CARD */
        .container {
            max-width: 650px;
            margin: 0 auto;
        }

        .card {
            background: white;
            border-radius: 20px;
            padding: 40px 45px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            border: 1px solid #eee;
        }

        /* HEADER */
        h1 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #732529;
            margin-bottom: 25px;
            border-left: 5px solid #FE4853;
            padding-left: 15px;
        }

        /* ALERTS */
        .alert, .success {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.85rem;
        }

        .alert {
            background: #ffe0e0;
            border: 1px solid #ffb3b3;
            color: #732529;
        }

        .success {
            background: #e0ffe0;
            border: 1px solid #b3ffb3;
            color: #2d7a2d;
        }

        /* FORM - GIDUGANGAN OG SPACING */
        .form-group {
            margin-bottom: 20px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 5px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #732529;
            font-size: 0.85rem;
        }

        label span {
            color: #FE4853;
        }

        input, select, textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 10px;
            font-size: 0.95rem;
            background: white;
            transition: all 0.2s;
        }

        textarea {
            resize: none;
            height: 48px;
            overflow-y: auto;
        }

        input:focus, select:focus, textarea:focus {
            border-color: #FE4853;
            outline: none;
            box-shadow: 0 0 0 3px rgba(254, 72, 83, 0.1);
        }

        /* BUTTON */
        .btn-register {
            width: 100%;
            background: #FE4853;
            color: white;
            border: none;
            padding: 14px;
            border-radius: 40px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            margin: 25px 0 15px;
            transition: all 0.2s;
        }

        .btn-register:hover {
            background: #732529;
        }

        /* OR SECTION */
        .or-section {
            text-align: center;
            margin: 15px 0;
            color: #999;
            font-size: 0.8rem;
        }

        .or-section::before,
        .or-section::after {
            content: "";
            display: inline-block;
            width: 42%;
            height: 1px;
            background-color: #eee;
            vertical-align: middle;
            margin: 0 10px;
        }

        /* LOGIN LINK */
        .login-link {
            text-align: center;
            font-size: 0.85rem;
            color: #888;
        }

        .login-link a {
            color: #FE4853;
            font-weight: 600;
            text-decoration: none;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        /* THEME TOGGLE */
        .theme-toggle {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 45px;
            height: 45px;
            background: #FE4853;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            z-index: 1000;
        }

        .theme-toggle .material-symbols-outlined {
            font-size: 22px;
        }

        /* DARK MODE */
        body.dark-mode {
            background: #1a1a1a;
        }

        body.dark-mode .card {
            background: #2d2d2d;
            border-color: #732529;
        }

        body.dark-mode h1 {
            color: #FE4853;
        }

        body.dark-mode label {
            color: #FE4853;
        }

        body.dark-mode input,
        body.dark-mode select,
        body.dark-mode textarea {
            background: #3d3d3d;
            border-color: #555;
            color: #e0e0e0;
        }

        body.dark-mode .alert {
            background: #3a1a1a;
            border-color: #732529;
            color: #fca5a5;
        }

        body.dark-mode .success {
            background: #1a3a2a;
            border-color: #2d7a2d;
            color: #86efac;
        }

        body.dark-mode .or-section::before,
        body.dark-mode .or-section::after {
            background-color: #732529;
        }

        body.dark-mode .theme-toggle {
            background: #732529;
        }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .nav-container {
                flex-direction: column;
                gap: 10px;
                padding: 0 20px;
            }
            
            .nav-links {
                gap: 20px;
                flex-wrap: wrap;
                justify-content: center;
            }
            
            body {
                padding-top: 120px;
            }
            
            .card {
                padding: 25px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
            
            h1 {
                font-size: 1.5rem;
            }
            
            .container {
                padding: 0 20px;
            }
        }

        @media (max-width: 550px) {
            .card {
                padding: 20px;
            }
            
            h1 {
                font-size: 1.3rem;
            }
            
            .nav-links {
                gap: 12px;
            }
            
            .nav-links li a {
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>

    <!-- NAVBAR -->
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
                <li><a href="login.php">Login</a></li>
                <li><a href="register.php" class="active">Register</a></li>
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

            <form method="POST" autocomplete="off">
                <input type="hidden" name="role_id" value="2">

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
                        <input type="password" name="password" placeholder="Enter password (min. 6 characters)" required>
                    </div>
                    <div class="form-group">
                        <label>Confirm Password <span>*</span></label>
                        <input type="password" name="cpassword" placeholder="Confirm password" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Department <span>*</span></label>
                        <select name="department_id" required>
                            <option value="">Select Department</option>
                            <?php
                            $dept_query = "SELECT department_id, department_name FROM department_table";
                            $dept_result = $conn->query($dept_query);
                            if ($dept_result && $dept_result->num_rows > 0) {
                                while ($dept = $dept_result->fetch_assoc()) {
                                    echo '<option value="' . $dept['department_id'] . '">' . htmlspecialchars($dept['department_name']) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Birth Date</label>
                        <input type="date" name="birth_date">
                    </div>
                </div>

                <div class="form-group">
                    <label>Contact Number <span>*</span></label>
                    <input type="text" name="contact_number" placeholder="09xxxxxxxxx" required>
                </div>

                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" placeholder="Enter address"></textarea>
                </div>

                <button type="submit" class="btn-register">Register as Student</button>

                <div class="or-section">OR</div>

                <div class="login-link">
                    Already have an account? <a href="login.php">Login</a>
                </div>
            </form>
        </div>
    </div>

    <div class="theme-toggle" id="themeToggle">
        <span class="material-symbols-outlined">dark_mode</span>
    </div>

    <script>
        // Dark mode toggle
        const themeToggle = document.getElementById('themeToggle');
        const savedMode = localStorage.getItem('darkMode');
        
        if (savedMode === 'true') {
            document.body.classList.add('dark-mode');
            themeToggle.innerHTML = '<span class="material-symbols-outlined">light_mode</span>';
        }
        
        themeToggle.addEventListener('click', function() {
            document.body.classList.toggle('dark-mode');
            const isDark = document.body.classList.contains('dark-mode');
            localStorage.setItem('darkMode', isDark);
            themeToggle.innerHTML = isDark ? '<span class="material-symbols-outlined">light_mode</span>' : '<span class="material-symbols-outlined">dark_mode</span>';
        });
        
        // Form validation
        const form = document.querySelector('form');
        const password = document.querySelector('input[name="password"]');
        const cpassword = document.querySelector('input[name="cpassword"]');
        const contact = document.querySelector('input[name="contact_number"]');
        const email = document.querySelector('input[name="email"]');
        
        if (form) {
            form.addEventListener('submit', function(e) {
                let errors = [];
                
                if (password && cpassword && password.value !== cpassword.value) {
                    errors.push('Passwords do not match.');
                }
                if (password && password.value.length < 6 && password.value.length > 0) {
                    errors.push('Password must be at least 6 characters.');
                }
                if (contact && contact.value) {
                    const phoneRegex = /^[0-9]{10,11}$/;
                    if (!phoneRegex.test(contact.value)) {
                        errors.push('Contact number must be 10-11 digits.');
                    }
                }
                if (email && email.value) {
                    const emailRegex = /^[^\s@]+@([^\s@]+\.)+[^\s@]+$/;
                    if (!emailRegex.test(email.value)) {
                        errors.push('Please enter a valid email address.');
                    }
                }
                
                if (errors.length > 0) {
                    e.preventDefault();
                    alert(errors.join('\n'));
                }
            });
        }
        
        // Auto-hide alerts
        const alerts = document.querySelectorAll('.alert, .success');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 300);
            }, 5000);
        });
    </script>
</body>
</html>