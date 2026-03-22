<?php
session_start();
include("../config/db.php");
include("includes/register_functions.php");

$message = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $result = handleRegistration($conn, $_POST);
    
    if ($result['success']) {
        $success = $result['message'];
    } else {
        $message = $result['message'];
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
    <link rel="stylesheet" href="css/register.css">
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
            <h1>Create Account</h1>

            <?php if ($message): ?>
                <div class="alert"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="POST" autocomplete="off">
                <!-- Role -->
                <div class="form-group">
                    <label>Role <span>*</span></label>
                    <select name="role_id" required>
                        <option value="" disabled selected>Select role</option>
                        <option value="1">Admin</option>
                        <option value="2">Student</option>
                        <option value="3">Researcher Adviser</option>
                        <option value="4">Researcher Coordinator</option>
                        <option value="5">Department Dean</option>
                        <option value="6">Librarian</option>
                    </select>
                </div>

                <!-- First Name & Last Name row -->
                <div class="form-row">
                    <div>
                        <label>First Name <span>*</span></label>
                        <input type="text" name="first_name" placeholder="Enter first name" required>
                    </div>
                    <div>
                        <label>Last Name <span>*</span></label>
                        <input type="text" name="last_name" placeholder="Enter last name" required>
                    </div>
                </div>

                <!-- Email -->
                <div class="form-group">
                    <label>Email <span>*</span></label>
                    <input type="email" name="email" placeholder="Enter email" required>
                </div>

                <!-- Username -->
                <div class="form-group">
                    <label>Username <span>*</span></label>
                    <input type="text" name="username" placeholder="Enter username" required>
                </div>

                <!-- Password & Confirm Password row -->
                <div class="form-row">
                    <div>
                        <label>Password <span>*</span></label>
                        <input type="password" name="password" placeholder="Enter password" required>
                    </div>
                    <div>
                        <label>Confirm Password <span>*</span></label>
                        <input type="password" name="cpassword" placeholder="Confirm password" required>
                    </div>
                </div>

                <!-- Department & Birth Date row -->
                <div class="form-row">
                    <div>
                        <label>Department</label>
                        <input type="text" name="department" placeholder="Department">
                    </div>
                    <div>
                        <label>Birth Date</label>
                        <input type="date" name="birth_date">
                    </div>
                </div>

                <!-- Contact Number -->
                <div class="form-group">
                    <label>Contact Number <span>*</span></label>
                    <input type="text" name="contact_number" placeholder="09xxxxxxxxx" required>
                </div>

                <!-- Address -->
                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" placeholder="Enter address"></textarea>
                </div>

                <!-- Register Button -->
                <button type="submit" class="btn-register">Register</button>

                <!-- OR -->
                <div class="or-section">OR</div>

                <!-- Login Link -->
                <div class="login-link">
                    Already have an account? <a href="login.php">Login</a>
                </div>
            </form>
        </div>
    </div>

    <script src="js/register.js"></script>
</body>
</html>