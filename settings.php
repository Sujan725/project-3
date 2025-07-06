<?php
// Ensure session is started and secure
session_start();
session_regenerate_id(true); // Regenerate session ID to prevent session fixation

require_once("utils.php"); // Assuming utils.php contains isLoggedIn(), githubApiRequest(), and logMessage()

// Configuration constants (could be in a separate config.php)
if (!defined('LOG_FILE')) {
    define('LOG_FILE', __DIR__ . "/logs/app_log.txt");
}
if (!defined('LAST_README_UPDATE_FILE')) {
    define('LAST_README_UPDATE_FILE', __DIR__ . "/logs/last_readme_update.txt");
}
if (!defined('SELECTED_REPOS_FILE')) {
    define('SELECTED_REPOS_FILE', __DIR__ . "/logs/selected_repos.txt");
}
if (!defined('AUTO_UPDATE_INTERVAL_SECONDS')) {
    define('AUTO_UPDATE_INTERVAL_SECONDS', 60 * 60); // 1 hour
}

// Redirect if not logged in
if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

// Get user data
$username = htmlspecialchars($_SESSION['username'] ?? 'Guest');
$user = getUser($username);
$profilePicUrl = 'photos/default-profile.png'; // Default profile picture
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $github_username = trim($_POST['github_username'] ?? '');
    $github_token = trim($_POST['github_token'] ?? '');
    updateUser($username, [
        'github_username' => $github_username,
        'github_token' => $github_token
    ]);
    $success = 'Settings updated successfully!';
    $user = getUser($username);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bitzenix - Settings</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --accent-color: #ffe082;
            --text-dark: #222;
            --text-medium: #4a5568;
            --text-light: #718096;
            --bg-light: #f6f7f9;
            --bg-lighter: #fdf6e3;
            --card-bg: #fff;
            --border-color: #e2e8f0;
            --shadow-light: rgba(0,0,0,0.04);
            --shadow-medium: rgba(0,0,0,0.07);
            --shadow-strong: rgba(0,0,0,0.1);
            --success-color: #48bb78;
            --error-color: #f56565;
            --info-color: #4299e1;
            --warning-color: #ed8936;
            --yellow-header-bg: #FFF9DB;
            --yellow-header-border: #F7E9A0;
            --yellow-sidebar-bg: #fffbe7;
            --yellow-active-link: #ffe082;
            --yellow-footer-bg: #FFF9DB;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(120deg, var(--bg-light) 0%, var(--bg-lighter) 100%);
            font-family: 'Inter', 'Segoe UI', Arial, sans-serif;
            color: var(--text-dark);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column; /* Changed to column for header/footer */
        }

        /* Layout */
        .app-container {
            display: flex;
            min-height: 100vh;
        }

        /* Global Header */
        .global-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--yellow-header-bg);
            padding: 32px 40px 28px 32px;
            border-bottom: 1px solid var(--yellow-header-border);
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 1000;
            min-height: 110px;
        }

        .header-left {
            flex: 0 0 auto;
            display: flex;
            align-items: center;
        }

        .header-title {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--text-dark);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 15px;
            line-height: 1.1;
            white-space: nowrap;
        }

        .header-title i {
            font-size: 2.3rem;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .header-user-block {
            display: flex;
            align-items: center;
            gap: 18px;
            margin-left: auto;
        }

        .welcome-message {
            font-size: 1rem;
            font-weight: 400;
            color: var(--text-medium);
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: var(--yellow-sidebar-bg);
            border-right: 1px solid var(--yellow-header-border);
            box-shadow: 2px 0 16px var(--shadow-light);
            display: flex;
            flex-direction: column;
            padding: 20px 0;
            position: sticky;
            top: 0;
            height: calc(100vh - 60px);
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 0 25px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sidebar-header .logo-icon {
            font-size: 2.2rem;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .sidebar-header .logo-text {
            font-size: 1.8rem;
            font-weight: 700;
            letter-spacing: -0.5px;
            color: var(--text-dark);
        }

        .sidebar-nav {
            display: flex;
            flex-direction: column;
            width: 100%;
            gap: 5px;
            flex-grow: 1;
        }

        .sidebar-link {
            text-decoration: none;
            color: var(--text-medium);
            font-size: 1.05rem;
            font-weight: 500;
            padding: 12px 25px;
            border-left: 4px solid transparent;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sidebar-link:hover {
            background: rgba(var(--primary-color-rgb, 102, 126, 234), 0.08);
            color: var(--primary-color);
            border-left-color: var(--primary-color);
        }

        .sidebar-link.active {
            background: var(--yellow-active-link);
            color: var(--text-dark);
            border-left-color: var(--primary-color);
            font-weight: 600;
        }

        .sidebar-link i {
            font-size: 1.2rem;
            width: 24px;
            text-align: center;
        }

        .sidebar-profile {
            padding: 20px 25px;
            margin-top: auto;
            display: flex;
            align-items: center;
            gap: 15px;
            border-top: 1px solid var(--yellow-header-border);
        }

        .sidebar-profile .profile-pic {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            box-shadow: 0 2px 8px var(--shadow-light);
            border: 2px solid var(--accent-color);
        }

        .sidebar-profile .username {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 1.1rem;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 30px;
            display: flex;
            flex-direction: column;
            gap: 30px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header h2 {
            font-size: 1.8rem;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-header h2 i {
            color: var(--accent-color);
        }

        /* Settings Card */
        .settings-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 10px 30px var(--shadow-medium);
            border: 1px solid var(--border-color);
            max-width: 700px;
            margin: 0 auto;
            width: 100%;
        }

        /* Form Elements */
        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 8px;
            color: var(--text-medium);
        }

        .form-group .input-wrapper {
            position: relative;
        }

        .form-group .input-wrapper i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
        }

        /* Buttons */
        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            border: none;
            font-size: 1rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(102, 126, 234, 0.2);
        }

        .btn-secondary {
            background: white;
            color: var(--text-medium);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background: #f8f9fa;
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        /* Messages */
        .success-message {
            background: rgba(72, 187, 120, 0.1);
            color: var(--success-color);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .success-message i {
            font-size: 1.2rem;
        }

        /* Global Footer */
        .global-footer {
            background: var(--yellow-footer-bg);
            text-align: center;
            padding: 18px 0;
            font-size: 0.9rem;
            color: var(--text-medium);
            border-top: 1px solid var(--yellow-header-border);
            box-shadow: 0 -2px 8px rgba(0,0,0,0.05);
            margin-top: auto; /* Push to bottom */
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .sidebar {
                width: 80px;
                padding: 20px 0;
            }
            
            .sidebar-header {
                padding: 0;
                justify-content: center;
                margin-bottom: 30px;
            }
            
            .sidebar-header .logo-text {
                display: none;
            }
            
            .sidebar-link {
                padding: 15px 0;
                justify-content: center;
                border-left: none;
                border-bottom: 4px solid transparent;
            }
            
            .sidebar-link span {
                display: none;
            }
            
            .sidebar-link.active {
                border-left: none;
                border-bottom-color: var(--primary-color);
            }
            
            .sidebar-profile {
                justify-content: center;
                padding: 15px 0;
                flex-direction: column;
            }
            
            .sidebar-profile .username {
                display: none;
            }
        }

        @media (max-width: 768px) {
            .app-container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                height: auto;
                flex-direction: row;
                justify-content: space-around;
                padding: 15px 0;
                border-right: none;
                border-bottom: 1px solid var(--yellow-header-border);
            }
            
            .sidebar-header {
                display: none;
            }
            
            .sidebar-nav {
                flex-direction: row;
                gap: 5px;
            }
            
            .sidebar-profile {
                display: none;
            }
            
            .main-content {
                padding: 20px;
            }
            
            .page-header h2 {
                font-size: 1.5rem;
            }
            
            .settings-card {
                padding: 20px;
            }
            
            .button-group {
                flex-direction: column;
                gap: 10px;
            }
            
            .btn {
                width: 100%;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 15px;
            }
            
            .form-group input {
                padding: 10px 15px 10px 40px;
            }
        }
    </style>
</head>
<body>
    <header class="global-header">
        <div class="header-left">
            <span class="app-name">Bitzenix</span>
        </div>
        <div class="header-center">
            <h1 class="header-title">
                <i class="fas fa-rocket" aria-hidden="true"></i>
                GitHub Project Manager
            </h1>
            <p class="header-subtitle">
                <i class="fas fa-crown" aria-hidden="true"></i>
                Enterprise-grade repository management with intelligent automation
            </p>
        </div>
        <div class="header-user-block">
            <span class="welcome-message">Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Guest'); ?></span>
            <div class="profile-menu">
                <img src="<?php echo $profilePicUrl ?? 'photos/default-profile.png'; ?>" class="profile-pic-top" alt="User Profile Picture" onclick="toggleProfileDropdown()" tabindex="0">
                <div class="profile-dropdown" id="profileDropdown">
                    <div class="profile-info">
                        <img src="<?php echo $profilePicUrl ?? 'photos/default-profile.png'; ?>" class="profile-pic-dropdown" alt="User Profile Picture">
                        <div class="profile-username"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Guest'); ?></div>
                    </div>
                    <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
    </header>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar" aria-label="Main Navigation">
            <div class="sidebar-header">
                <i class="fas fa-rocket logo-icon" aria-hidden="true"></i>
                <span class="logo-text">Bitzenix</span>
            </div>
            <nav class="sidebar-nav">
                <a href="index.php" class="sidebar-link">
                    <i class="fas fa-tachometer-alt" aria-hidden="true"></i>
                    <span>Dashboard</span>
                </a>
                <a href="settings.php" class="sidebar-link active" aria-current="page">
                    <i class="fas fa-cog" aria-hidden="true"></i>
                    <span>Settings</span>
                </a>
                <a href="select_repos.php" class="sidebar-link">
                    <i class="fas fa-code-branch" aria-hidden="true"></i>
                    <span>Select Repos</span>
                </a>
                <a href="create_project.php" class="sidebar-link">
                    <i class="fas fa-plus-circle" aria-hidden="true"></i>
                    <span>Create Project</span>
                </a>
                <a href="logout.php" class="sidebar-link">
                    <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
                    <span>Logout</span>
                </a>
            </nav>
            <div class="sidebar-profile">
                <img src="<?php echo $profilePicUrl; ?>" class="profile-pic" alt="User profile picture">
                <span class="username">Welcome, <?php echo $username; ?></span>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="main-content">
            <div class="page-header">
                <h2><i class="fas fa-cog"></i> Account Settings</h2>
            </div>

            <?php if ($success): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                <span><?php echo $success; ?></span>
            </div>
            <?php endif; ?>

            <div class="settings-card">
                <form method="post">
                    <div class="form-group">
                        <label for="github_username">GitHub Username</label>
                        <div class="input-wrapper">
                            <i class="fas fa-user"></i>
                            <input type="text" id="github_username" name="github_username" 
                                   value="<?php echo htmlspecialchars($user['github_username'] ?? ''); ?>" 
                                   placeholder="Enter your GitHub username" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="github_token">GitHub API Token</label>
                        <div class="input-wrapper">
                            <i class="fas fa-key"></i>
                            <input type="text" id="github_token" name="github_token" 
                                   value="<?php echo htmlspecialchars($user['github_token'] ?? ''); ?>" 
                                   placeholder="Enter your GitHub API token" required>
                        </div>
                        <small style