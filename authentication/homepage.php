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
    <style>
        :root {
            --navy: #0f172a;
            --navy-dark: #020617;
            --gold: #fbbf24;
            --gold-dark: #d97706;
            --gray: #64748b;
            --card-bg: #ffffff;
            --radius: 12px;
            --shadow: 0 4px 20px rgba(0,0,0,0.08);
            --shadow-hover: 0 12px 36px rgba(251,191,36,0.18);
            --red: #d32f2f;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
        }

        body {
            font-family: 'Roboto', sans-serif;
            background: #f8fafc;
            color: var(--navy);
            line-height: 1.6;
        }

        /* Navbar */
        .navbar {
            background: linear-gradient(135deg, #FE4853 0%, #732529 100%);
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .nav-container {
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            font-size: 1.9rem;
            font-weight: 700;
            text-decoration: none;
        }

        .logo .material-symbols-outlined {
            font-size: 2.3rem;
            color: white;
        }

        .nav-links {
            display: flex;
            gap: 2.5rem;
            list-style: none;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: 0.3s;
            opacity: 0.9;
        }

        .nav-links a:hover {
            opacity: 1;
            transform: translateY(-2px);
        }

        /* Hero Section */
        .hero {
            position: relative;
            min-height: 100vh;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: white;
            background-image: linear-gradient(
                rgba(15, 23, 42, 0.6), 
                rgba(15, 23, 42, 0.8)
            ), url('https://images.unsplash.com/photo-1524995997946-a1c2e315a42f?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80');
            background-position: center;
            background-size: cover;
            background-repeat: no-repeat;
            background-attachment: fixed;
        }

        .hero-content {
            position: relative;
            z-index: 2;
            max-width: 1000px;
            padding: 0 1.5rem;
        }

        .hero h1 {
            font-size: clamp(3rem, 6vw, 5rem);
            font-weight: 800;
            color: var(--gold);
            line-height: 1.1;
            margin-bottom: 1.2rem;
            text-shadow: 0 3px 12px rgba(0,0,0,0.4);
        }

        .hero p {
            font-size: clamp(1.1rem, 2.5vw, 1.4rem);
            color: #e2e8f0;
            max-width: 720px;
            margin: 0 auto 2.5rem;
            opacity: 0.95;
            text-shadow: 0 2px 8px rgba(0,0,0,0.3);
        }

        .hero-actions {
            display: flex;
            justify-content: center;
            gap: 1.5rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 1rem 2.2rem;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 1.1rem;
            display: inline-block;
        }

        .btn-primary {
            background: var(--gold);
            color: var(--navy);
        }

        .btn-primary:hover {
            background: var(--gold-dark);
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(251,191,36,0.35);
        }

        /* Features Section */
        .features {
            background: #f1f5f9;
            padding: 4rem 2rem;
            text-align: center;
        }

        .features h2 {
            font-size: 2rem;
            color: var(--navy);
            margin-bottom: 2rem;
        }

        .features-grid {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
        }

        .feature-card {
            background: white;
            padding: 2rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            transition: 0.3s;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
        }

        .feature-card .material-symbols-outlined {
            font-size: 3rem;
            color: var(--red);
            margin-bottom: 1rem;
        }

        .feature-card h3 {
            font-size: 1.3rem;
            margin-bottom: 0.8rem;
            color: var(--navy);
        }

        .feature-card p {
            color: var(--gray);
            font-size: 0.95rem;
        }

        /* Footer */
        footer {
            background: var(--navy-dark);
            color: #e2e8f0;
            padding: 2rem;
            text-align: center;
        }

        footer p {
            margin: 0.5rem 0;
        }

        footer a {
            color: var(--gold);
            text-decoration: none;
        }

        footer a:hover {
            text-decoration: underline;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .navbar {
                padding: 0.8rem 1rem;
            }
            
            .nav-container {
                flex-direction: column;
                gap: 0.8rem;
            }

            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
                gap: 1.2rem;
            }

            .hero h1 {
                font-size: clamp(2.4rem, 8vw, 3.5rem);
            }

            .hero p {
                font-size: 1.1rem;
            }

            .btn {
                width: 100%;
                max-width: 320px;
            }

            .features {
                padding: 3rem 1.5rem;
            }

            .features-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 480px) {
            .logo {
                font-size: 1.4rem;
            }
            
            .logo .material-symbols-outlined {
                font-size: 1.8rem;
            }
        }
    </style>
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
                <?php if ($is_logged_in): ?>
                    <li><a href="../student/studentDashboard.php">Dashboard</a></li>
                    <li><a href="logout.php">Logout</a></li>
                <?php else: ?>
                    <li><a href="login.php">Login</a></li>
                    <li><a href="register.php">Register</a></li>
                <?php endif; ?>
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
        <p>© <?= date('Y') ?> Web-Based Thesis Archiving System</p>
        <p>Preserving Knowledge, Empowering Research</p>
        <p><a href="about.php">About Us</a> | <a href="contact.php">Contact</a> | <a href="privacy.php">Privacy Policy</a></p>
    </footer>
</body>
</html>