<?php
session_start();
$is_logged_in = isset($_SESSION['user_id']) || isset($_SESSION['username']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Web-Based Thesis Archiving System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Symbols+Outlined" rel="stylesheet">
    <!-- External CSS -->
    <link rel="stylesheet" href="css/homepage.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="homepage.php" class="logo">
                <span class="material-symbols-outlined">book</span>
                Thesis Archiving
            </a>
            <ul class="nav-links">
                <li><a href="homepage.php">Home</a></li>
                <li><a href="browse.php">Browse</a></li>
                <li><a href="about.php">About</a></li>
                <li><a href="login.php">Login</a></li>
                <li><a href="register.php">Register</a></li>
            </ul>
        </div>
    </nav>
    
    <section class="hero">
        <div class="hero-content">
            <h1>Web-Based Thesis<br>Archiving System</h1>
            <p>Discover, browse, and preserve academic research. Your gateway to scholarly knowledge.</p>
            <div class="hero-actions">
                <a href="browse.php" class="btn btn-primary">Browse Theses</a>
            </div>
        </div>
    </section>

    <section class="features">
        <h2>Why Choose Our Platform?</h2>
        <div class="features-grid">
            <div class="feature-card">
                <span class="material-symbols-outlined">search</span>
                <h3>Easy Search</h3>
                <p>Find theses quickly with our advanced search and filter system.</p>
            </div>
            <div class="feature-card">
                <span class="material-symbols-outlined">download</span>
                <h3>Download Access</h3>
                <p>Download full thesis documents for your research.</p>
            </div>
            <div class="feature-card">
                <span class="material-symbols-outlined">archive</span>
                <h3>Secure Archive</h3>
                <p>All theses are securely stored and preserved for future reference.</p>
            </div>
            <div class="feature-card">
                <span class="material-symbols-outlined">category</span>
                <h3>Organized by Category</h3>
                <p>Browse by department, year, or research topic.</p>
            </div>
        </div>
    </section>

    <footer>
        <p>&copy; 2024 Web-Based Thesis Archiving System. All rights reserved.</p>
        <p>Empowering academic research through digital preservation</p>
    </footer>

    <!-- External JavaScript -->
    <script src="js/homepage.js"></script>
</body>
</html>