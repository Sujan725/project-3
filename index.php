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

// --- Automatic README Update Logic ---
function shouldTriggerAutomaticReadmeUpdate() {
    if (!file_exists(LAST_README_UPDATE_FILE)) {
        return true; // Run if file doesn't exist
    }
    $lastRunTime = (int)file_get_contents(LAST_README_UPDATE_FILE);
    return (time() - $lastRunTime) >= AUTO_UPDATE_INTERVAL_SECONDS;
}

function updateLastReadmeUpdateTime() {
    file_put_contents(LAST_README_UPDATE_FILE, time());
}

if (shouldTriggerAutomaticReadmeUpdate()) {
    logMessage("Automatic README update triggered - " . (AUTO_UPDATE_INTERVAL_SECONDS / 60) . " minutes have passed.");
    define('AUTO_INCLUDED', true); // Flag for update_readme.php
    include_once("update_readme.php"); // Assuming this file contains updateSelectedProjectReadmes()
    $result = updateSelectedProjectReadmes(true); // Pass true for automatic run
    updateLastReadmeUpdateTime();
    if ($result) {
        logMessage("Automatic README update completed successfully. ✅");
    } else {
        logMessage("Automatic README update failed. ❌");
    }
}

// --- GitHub API Data Fetching ---
$isConnected = false;
$repos = [];
$githubUser = null; // To store GitHub user data for profile picture

try {
    $userResponse = githubApiRequest("GET", "/user");
    if ($userResponse['status'] === 200) {
        $isConnected = true;
        $githubUser = $userResponse['body'];
        $repoResponse = githubApiRequest("GET", "/user/repos?per_page=100&sort=updated&direction=desc");
        if ($repoResponse['status'] === 200) {
            $repos = $repoResponse['body'];
        } else {
            logMessage("Failed to fetch repositories: " . ($repoResponse['message'] ?? 'Unknown error') . " ❌");
        }
    } else {
        logMessage("GitHub connection failed: " . ($userResponse['message'] ?? 'Unknown error') . " ❌");
    }
} catch (Exception $e) {
    logMessage("GitHub API request error: " . $e->getMessage() . " ❌");
}

logMessage("Dashboard accessed - GitHub connection status: " . ($isConnected ? "Connected ✅" : "Failed ❌"));

// --- Log File Handling ---
$logs = "No logs found.";
if (file_exists(LOG_FILE)) {
    $logContent = file_get_contents(LOG_FILE);
    if ($logContent !== false && !empty(trim($logContent))) {
        $logs = $logContent;
    } else {
        $logs = "Log file exists but is empty. No activity recorded yet.";
    }
} else {
    $logs = "Log file not found at: " . LOG_FILE;
}

// --- Handle Quick Actions (e.g., 'Create New Project') ---
if (isset($_GET['run']) && $_GET['run'] === 'yes') {
    header("Location: create_project.php");
    exit;
}

// --- System Message Handling (from redirects) ---
$clearStatus = filter_input(INPUT_GET, 'clear', FILTER_SANITIZE_STRING);
$clearMessage = filter_input(INPUT_GET, 'message', FILTER_SANITIZE_STRING);
if ($clearMessage) {
    $clearMessage = urldecode($clearMessage);
}

// --- Helper Function for Next Auto Run Time ---
function getNextAutoRunTime() {
    if (!file_exists(LAST_README_UPDATE_FILE)) {
        return "Ready to run now";
    }
    $lastRunTime = (int)file_get_contents(LAST_README_UPDATE_FILE);
    $nextRunTime = $lastRunTime + AUTO_UPDATE_INTERVAL_SECONDS;
    $currentTime = time();

    if ($currentTime >= $nextRunTime) {
        return "Ready to run now";
    }

    $timeRemaining = $nextRunTime - $currentTime;
    $hours = floor($timeRemaining / 3600);
    $minutes = floor(($timeRemaining % 3600) / 60);

    $timeParts = [];
    if ($hours > 0) {
        $timeParts[] = "$hours hour" . ($hours > 1 ? "s" : "");
    }
    if ($minutes > 0) {
        $timeParts[] = "$minutes minute" . ($minutes > 1 ? "s" : "");
    }

    return "in " . implode(", ", $timeParts);
}

// --- Fetch Selected Repositories ---
$selectedRepos = [];
if (file_exists(SELECTED_REPOS_FILE)) {
    $data = file_get_contents(SELECTED_REPOS_FILE);
    $selectedRepos = json_decode($data, true) ?: [];
}

// Get username from session for display
$username = htmlspecialchars($_SESSION['username'] ?? 'Guest');
$profilePicUrl = $githubUser['avatar_url'] ?? 'photos/default-profile.png'; // Default profile pic
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bitzenix - GitHub Project Manager Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Base Styles */
        :root {
            --primary-color: #667eea; /* Blue */
            --secondary-color: #764ba2; /* Purple */
            --accent-color: #ffe082; /* Yellow */
            --text-dark: #222;
            --text-medium: #4a5568;
            --text-light: #888;
            --bg-light: #f6f7f9; /* Very light grey */
            --bg-lighter: #fdf6e3; /* Very light yellow */
            --card-bg: #fff;
            --border-color: #e2e8f0;
            --shadow-light: rgba(0,0,0,0.04);
            --shadow-medium: rgba(0,0,0,0.07);
            --shadow-strong: rgba(0,0,0,0.1);
            --success-color: #48bb78;
            --error-color: #f56565;
            --info-color: #4299e1;
            --warning-color: #ed8936;

            /* New Yellow Palette */
            --yellow-header-bg: #FFF9DB; /* Lightest yellow */
            --yellow-header-border: #F7E9A0; /* Slightly darker yellow */
            --yellow-sidebar-bg: #fffbe7; /* Light yellow for sidebar */
            --yellow-active-link: #ffe082; /* Active link yellow */
            --yellow-footer-bg: #FFF9DB; /* Same as header for consistency */
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

        .header-center {
            flex: 1 1 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-width: 0;
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

        .header-subtitle {
            font-size: 1.1rem;
            font-weight: 500;
            color: var(--text-medium);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
            white-space: nowrap;
        }

        .header-subtitle i {
            color: var(--accent-color);
        }

        .header-user-block {
            display: flex;
            align-items: center;
            gap: 18px;
            margin-left: 24px;
        }

        .global-header .welcome-message {
            font-size: 1rem;
            font-weight: 400;
            color: var(--text-medium);
        }

        .global-header .profile-menu {
            position: relative;
            display: flex;
            align-items: center;
            margin-left: auto;
        }

        .global-header .profile-pic-top {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--accent-color);
            cursor: pointer;
            transition: box-shadow 0.2s;
            box-shadow: 0 2px 8px var(--shadow-light);
        }

        .global-header .profile-pic-top:focus {
            outline: 2px solid var(--primary-color);
        }

        .global-header .profile-dropdown {
            display: none;
            position: absolute;
            right: 0;
            top: 54px;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.12);
            min-width: 200px;
            z-index: 2000;
            padding: 12px 0;
            animation: fadeIn 0.2s;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px);}
            to { opacity: 1; transform: translateY(0);}
        }

        .global-header .profile-dropdown.show {
            display: block;
        }

        .global-header .profile-dropdown .profile-info {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 20px 12px 20px;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 8px;
        }

        .global-header .profile-pic-dropdown {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--accent-color);
        }

        .global-header .profile-username {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 1rem;
            word-break: break-all;
        }

        /* Layout */
        .layout {
            display: flex;
            flex: 1; /* Allow layout to grow and fill space */
        }

        /* Sidebar */
        .sidebar {
            width: 250px;
            background: var(--yellow-sidebar-bg);
            border-right: 1px solid var(--yellow-header-border);
            box-shadow: 2px 0 16px var(--shadow-light);
            display: flex;
            flex-direction: column;
            padding: 16px 0;
            position: sticky;
            top: 0;
            height: calc(100vh - 60px);
            overflow-y: auto;
            z-index: 999;
            justify-content: flex-start;
        }

        .sidebar-header {
            padding: 0 32px;
            margin-bottom: 40px;
            display: flex;
            align-items: center;
            gap: 10px;
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
            gap: 8px;
            flex-grow: 1;
        }

        .sidebar-link {
            text-decoration: none;
            color: var(--text-medium);
            font-size: 1.05rem;
            font-weight: 500;
            padding: 14px 32px;
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
            background: var(--yellow-active-link); /* Yellow active link */
            color: var(--text-dark); /* Dark text for active link */
            border-left-color: var(--primary-color); /* Primary color for active border */
            font-weight: 600;
        }

        .sidebar-link i {
            font-size: 1.2rem;
            width: 20px;
            text-align: center;
        }

        .sidebar-profile {
            padding: 20px 32px;
            margin-top: auto;
            display: flex;
            align-items: center;
            gap: 15px;
            border-top: 1px solid var(--yellow-header-border); /* Yellow border */
            margin-top: 30px;
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
            padding: 40px;
            display: flex;
            flex-direction: column;
            gap: 40px;
        }

        /* Dashboard Header */
        .dashboard-header {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .dashboard-header h1 {
            font-size: 2.8rem;
            font-weight: 700;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .dashboard-header h1 i {
            font-size: 3rem;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .dashboard-header .subtitle {
            font-size: 1.2rem;
            font-weight: 500;
            color: var(--text-medium);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .dashboard-header .subtitle i {
            color: var(--accent-color);
        }

        /* System Message */
        .system-message {
            width: 100%;
            margin-bottom: 32px;
            border-radius: 12px;
            padding: 20px 25px;
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 1rem;
            font-weight: 500;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }

        .system-message.success {
            background: #e6ffed;
            color: #2d764e;
            border: 1px solid #b2f5ea;
        }

        .system-message.error {
            background: #ffe6e6;
            color: #a02b2b;
            border: 1px solid #fbd3d3;
        }

        .system-message.info {
            background: #e0f2f7;
            color: #2c5282;
            border: 1px solid #b3e0f2;
        }

        .system-message i {
            font-size: 1.8rem;
            flex-shrink: 0;
        }

        .system-message.success i { color: var(--success-color); }
        .system-message.error i { color: var(--error-color); }
        .system-message.info i { color: var(--info-color); }

        /* Status Grid */
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
        }

        .status-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 10px 30px var(--shadow-medium);
            border: 1px solid var(--border-color);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        .status-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px var(--shadow-strong);
        }

        .status-card.success { border-left: 5px solid var(--success-color); }
        .status-card.error { border-left: 5px solid var(--error-color); }
        .status-card.info { border-left: 5px solid var(--info-color); }
        .status-card.warning { border-left: 5px solid var(--warning-color); }

        .card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-color);
        }

        .card-header h3 {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--text-dark);
        }

        .card-header i {
            font-size: 1.6rem;
            width: 28px;
            text-align: center;
        }

        .status-card.success .card-header i { color: var(--success-color); }
        .status-card.error .card-header i { color: var(--error-color); }
        .status-card.info .card-header i { color: var(--info-color); }
        .status-card.warning .card-header i { color: var(--warning-color); }

        .status-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px dashed var(--border-color);
            font-size: 0.95rem;
        }

        .status-item:last-child {
            border-bottom: none;
        }

        .status-label {
            font-weight: 500;
            color: var(--text-medium);
        }

        .status-value {
            font-weight: 600;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .status-value.ready, .status-value.connected { color: var(--success-color); }
        .status-value.failed { color: var(--error-color); }

        .status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        .status-indicator.connected { background: var(--success-color); }
        .status-indicator.failed { background: var(--error-color); }
        .status-indicator.info { background: var(--info-color); }

        .stats-highlight {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            min-width: 50px;
            text-align: center;
        }

        /* Actions Section */
        .actions-section, .repositories-section, .logs-section {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 10px 30px var(--shadow-medium);
            border: 1px solid var(--border-color);
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 14px 20px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(var(--primary-color-rgb, 102, 126, 234), 0.3);
        }

        .btn.secondary {
            background: linear-gradient(135deg, #718096, #4a5568);
        }
        .btn.secondary:hover {
            box-shadow: 0 10px 25px rgba(113, 128, 150, 0.3);
        }

        .btn.danger {
            background: linear-gradient(135deg, var(--error-color), #e53e3e);
        }
        .btn.danger:hover {
            box-shadow: 0 10px 25px rgba(245, 101, 101, 0.3);
        }

        .btn.success {
            background: linear-gradient(135deg, var(--success-color), #38a169);
        }
        .btn.success:hover {
            box-shadow: 0 10px 25px rgba(72, 187, 120, 0.3);
        }

        /* Repositories Section */
        .repo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .repo-card {
            background: #f7fafc;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        .repo-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            border-color: var(--primary-color);
        }

        .repo-name {
            font-size: 1.15rem;
            font-weight: 600;
            color: #2b6cb0;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .repo-name a {
            color: inherit;
            text-decoration: none;
        }

        .repo-name a:hover {
            text-decoration: underline;
        }

        .repo-desc {
            color: var(--text-medium);
            font-size: 0.9rem;
            margin-bottom: 12px;
            line-height: 1.5;
            flex-grow: 1;
        }

        .repo-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 15px;
            font-size: 0.85rem;
            color: var(--text-light);
        }

        .repo-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .repo-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: auto;
        }

        .repo-actions .btn {
            padding: 8px 15px;
            font-size: 0.85rem;
            box-shadow: none;
        }
        .repo-actions .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 8px rgba(0,0,0,0.1);
        }

        /* Logs Section */
        .logs-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .logs-content {
            background: linear-gradient(135deg, #1a202c 0%, #2d3748 100%);
            color: #e2e8f0;
            padding: 25px;
            border-radius: 12px;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', 'Consolas', monospace;
            font-size: 0.9rem;
            line-height: 1.7;
            max-height: 450px;
            overflow-y: auto;
            border: 1px solid #4a5568;
            position: relative;
            box-shadow: inset 0 2px 8px rgba(0, 0, 0, 0.3);
        }

        .logs-content::-webkit-scrollbar {
            width: 8px;
        }
        .logs-content::-webkit-scrollbar-track {
            background: #2d3748;
            border-radius: 4px;
        }
        .logs-content::-webkit-scrollbar-thumb {
            background: #4a5568;
            border-radius: 4px;
        }
        .logs-content::-webkit-scrollbar-thumb:hover {
            background: #718096;
        }

        .logs-content .log-entry {
            margin-bottom: 8px;
            padding: 4px 0;
            border-left: 3px solid transparent;
            padding-left: 12px;
            transition: all 0.2s ease;
            word-break: break-word;
        }

        .logs-content .log-entry:hover {
            background: rgba(255, 255, 255, 0.05);
            border-left-color: var(--primary-color);
        }

        .logs-content .log-entry.success { border-left-color: var(--success-color); color: #9ae6b4; }
        .logs-content .log-entry.error { border-left-color: var(--error-color); color: #feb2b2; }
        .logs-content .log-entry.warning { border-left-color: var(--warning-color); color: #fbd38d; }
        .logs-content .log-entry.info { border-left-color: var(--info-color); color: #90cdf4; }

        .logs-content .log-timestamp {
            color: #a0aec0;
            font-weight: 600;
            margin-right: 8px;
        }

        .logs-content .log-message {
            color: #e2e8f0;
        }

        .logs-content .log-status-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 8px;
            vertical-align: middle;
        }

        .logs-content .log-status-dot.success { background: var(--success-color); }
        .logs-content .log-status-dot.error { background: var(--error-color); }
        .logs-content .log-status-dot.warning { background: var(--warning-color); }
        .logs-content .log-status-dot.info { background: var(--info-color); }

        .logs-empty {
            text-align: center;
            padding: 40px 20px;
            color: #a0aec0;
            font-style: italic;
        }

        .logs-empty i {
            font-size: 2.5rem;
            margin-bottom: 15px;
            opacity: 0.6;
            color: var(--text-light);
        }

        /* Global Footer */
        .global-footer {
            background: var(--yellow-footer-bg); /* Light yellow background */
            text-align: center;
            padding: 18px 0;
            font-size: 0.9rem;
            color: var(--text-medium);
            border-top: 1px solid var(--yellow-header-border); /* Yellow border */
            box-shadow: 0 -2px 8px rgba(0,0,0,0.05);
            margin-top: auto; /* Push to bottom */
        }

        /* Animations */
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .main-content {
                padding: 30px;
            }
            .sidebar {
                width: 220px;
            }
        }

        @media (max-width: 992px) {
            .main-content {
                padding: 25px;
            }
            .sidebar {
                width: 80px;
                align-items: center;
                padding: 20px 0;
                height: calc(100vh - 60px); /* Adjust height */
            }
            .sidebar-header {
                padding: 0;
                margin-bottom: 30px;
            }
            .sidebar-header .logo-text {
                display: none;
            }
            .sidebar-header .logo-icon {
                font-size: 2.5rem;
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
            .sidebar-link i {
                font-size: 1.5rem;
                width: auto;
            }
            .sidebar-link:hover, .sidebar-link.active {
                border-left-color: transparent;
                border-bottom-color: var(--primary-color);
                background: rgba(var(--primary-color-rgb, 102, 126, 234), 0.08);
            }
            .sidebar-profile {
                padding: 15px 0;
                justify-content: center;
                flex-direction: column;
                gap: 10px;
            }
            .sidebar-profile .username {
                display: none;
            }
            .dashboard-header h1 {
                font-size: 2.2rem;
            }
            .dashboard-header h1 i {
                font-size: 2.5rem;
            }
            .dashboard-header .subtitle {
                font-size: 1rem;
            }
            .status-grid {
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            }
            .repo-grid {
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .global-header {
                padding: 15px 20px;
                font-size: 1.1rem;
            }
            .global-header .app-name {
                font-size: 1.3rem;
            }
            .global-header .welcome-message {
                font-size: 0.9rem;
            }

            .layout {
                flex-direction: column;
            }
            .sidebar {
                width: 100%;
                height: auto;
                position: static;
                border-right: none;
                border-bottom: 1px solid var(--yellow-header-border);
                box-shadow: 0 2px 10px var(--shadow-light);
                flex-direction: row;
                justify-content: space-around;
                padding: 15px 0;
            }
            .sidebar-header {
                display: none;
            }
            .sidebar-nav {
                flex-direction: row;
                flex-grow: 0;
                gap: 5px;
            }
            .sidebar-link {
                padding: 10px 15px;
                border-bottom: none;
                border-left: 4px solid transparent;
            }
            .sidebar-link:hover, .sidebar-link.active {
                border-bottom-color: transparent;
                border-left-color: var(--primary-color);
            }
            .sidebar-profile {
                display: none;
            }
            .main-content {
                padding: 20px;
                gap: 30px;
            }
            .dashboard-header h1 {
                font-size: 1.8rem;
                gap: 10px;
            }
            .dashboard-header h1 i {
                font-size: 2rem;
            }
            .dashboard-header .subtitle {
                font-size: 0.9rem;
            }
            .status-grid, .actions-grid, .repo-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            .btn {
                padding: 12px 15px;
                font-size: 0.95rem;
            }
            .repo-actions .btn {
                width: 100%;
            }
            .logs-content {
                max-height: 300px;
                padding: 15px;
                font-size: 0.85rem;
            }
            .system-message {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
                padding: 15px;
            }
            .system-message i {
                font-size: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .global-header {
                padding: 12px 15px;
                font-size: 1rem;
            }
            .global-header .app-name {
                font-size: 1.2rem;
            }
            .global-header .welcome-message {
                font-size: 0.8rem;
            }

            .main-content {
                padding: 15px;
            }
            .dashboard-header h1 {
                font-size: 1.5rem;
            }
            .dashboard-header h1 i {
                font-size: 1.8rem;
            }
            .status-card, .actions-section, .repositories-section, .logs-section {
                padding: 18px;
            }
            .card-header h3 {
                font-size: 1.1rem;
            }
            .card-header i {
                font-size: 1.4rem;
            }
            .status-item {
                font-size: 0.9rem;
            }
            .btn {
                font-size: 0.9rem;
                padding: 10px 12px;
            }
            .repo-card {
                padding: 15px;
            }
            .repo-name {
                font-size: 1rem;
            }
            .repo-desc {
                font-size: 0.85rem;
            }
            .repo-actions .btn {
                font-size: 0.8rem;
                padding: 6px 10px;
            }
            .logs-content {
                font-size: 0.8rem;
                padding: 12px;
            }
            .global-footer {
                padding: 15px 0;
                font-size: 0.8rem;
            }
        }

        .profile-menu {
            position: relative;
            display: flex;
            align-items: center;
            margin-left: auto;
        }

        .profile-pic-top {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--accent-color);
            cursor: pointer;
            transition: box-shadow 0.2s;
            box-shadow: 0 2px 8px var(--shadow-light);
        }

        .profile-pic-top:focus {
            outline: 2px solid var(--primary-color);
        }

        .profile-dropdown {
            display: none;
            position: absolute;
            right: 0;
            top: 54px;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.12);
            min-width: 200px;
            z-index: 2000;
            padding: 12px 0;
            animation: fadeIn 0.2s;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px);}
            to { opacity: 1; transform: translateY(0);}
        }

        .profile-dropdown.show {
            display: block;
        }

        .profile-dropdown .profile-info {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 20px 12px 20px;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 8px;
        }

        .profile-pic-dropdown {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--accent-color);
        }

        .profile-username {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 1rem;
            word-break: break-all;
        }

        .profile-dropdown a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 20px;
            color: var(--text-medium);
            text-decoration: none;
            font-size: 1rem;
            transition: background 0.15s, color 0.15s;
        }

        .profile-dropdown a:hover {
            background: var(--yellow-active-link);
            color: var(--primary-color);
        }

        .profile-dropdown i {
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
        }

        .welcome-message {
            font-size: 1rem;
            font-weight: 400;
            color: var(--text-medium);
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <!-- Global Header -->
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
            <span class="welcome-message">Welcome, <?php echo $username; ?></span>
            <div class="profile-menu">
                <img src="<?php echo $profilePicUrl; ?>" class="profile-pic-top" alt="User Profile Picture" onclick="toggleProfileDropdown()" tabindex="0">
                <div class="profile-dropdown" id="profileDropdown">
                    <div class="profile-info">
                        <img src="<?php echo $profilePicUrl; ?>" class="profile-pic-dropdown" alt="User Profile Picture">
                        <div class="profile-username"><?php echo $username; ?></div>
                    </div>
                    <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
    </header>

    <div class="layout">
        <!-- Sidebar -->
        <aside class="sidebar" aria-label="Main Navigation">
            <div class="sidebar-header">
                <i class="fas fa-rocket logo-icon" aria-hidden="true"></i>
                <span class="logo-text">Bitzenix</span>
            </div>
            <nav class="sidebar-nav">
                <a href="index.php" class="sidebar-link active" aria-current="page">
                    <i class="fas fa-tachometer-alt" aria-hidden="true"></i>
                    <span>Dashboard</span>
                </a>
                <a href="select_repos.php" class="sidebar-link">
                    <i class="fas fa-code-branch" aria-hidden="true"></i>
                    <span>Select Repos</span>
                </a>
                <button class="sidebar-link" onclick="window.location.href='create_project.php';" type="button" style="background:none;border:none;width:100%;text-align:left;">
                    <i class="fas fa-plus-circle" aria-hidden="true"></i>
                    <span>Create Project</span>
                </button>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <?php if (!empty($clearStatus) && !empty($clearMessage)): ?>
            <div class="system-message <?php echo htmlspecialchars($clearStatus); ?>" role="alert">
                <i class="fas fa-<?php echo $clearStatus === 'success' ? 'check-circle' : ($clearStatus === 'error' ? 'exclamation-triangle' : 'info-circle'); ?>" aria-hidden="true"></i>
                <div>
                    <strong>System Message:</strong><br>
                    <?php echo htmlspecialchars($clearMessage); ?>
                </div>
            </div>
            <?php endif; ?>

            <section class="status-grid">
                <div class="status-card <?php echo $isConnected ? 'success' : 'error'; ?>">
                    <div class="card-header">
                        <i class="fas fa-<?php echo $isConnected ? 'check-circle' : 'times-circle'; ?>" aria-hidden="true"></i>
                        <h3>GitHub Connection</h3>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Status</span>
                        <span class="status-value <?php echo $isConnected ? 'connected' : 'failed'; ?>">
                            <span class="status-indicator <?php echo $isConnected ? 'connected' : 'failed'; ?>" aria-hidden="true"></span>
                            <?php echo $isConnected ? 'Connected' : 'Failed'; ?>
                        </span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">API Response Code</span>
                        <span class="status-value"><?php echo $userResponse['status'] ?? 'N/A'; ?></span>
                    </div>
                </div>

                <div class="status-card info">
                    <div class="card-header">
                        <i class="fas fa-sync-alt" aria-hidden="true"></i>
                        <h3>Automatic Updates</h3>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Status</span>
                        <span class="status-value ready">
                            <span class="status-indicator connected" aria-hidden="true"></span>
                            Active
                        </span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Schedule</span>
                        <span class="status-value">Every <?php echo AUTO_UPDATE_INTERVAL_SECONDS / 60; ?> minutes</span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Next Update</span>
                        <span class="status-value"><?php echo getNextAutoRunTime(); ?></span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Last Update</span>
                        <span class="status-value">
                            <?php echo file_exists(LAST_README_UPDATE_FILE) ? date('Y-m-d H:i:s', (int)file_get_contents(LAST_README_UPDATE_FILE)) : 'Never'; ?>
                        </span>
                    </div>
                </div>

                <div class="status-card warning">
                    <div class="card-header">
                        <i class="fas fa-repository" aria-hidden="true"></i>
                        <h3>Repository Status</h3>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Total Repositories</span>
                        <span class="status-value">
                            <span class="stats-highlight"><?php echo count($repos); ?></span>
                        </span>
                    </div>
                    <div class="status-item">
                        <span class="status-label">Selected for Updates</span>
                        <span class="status-value">
                            <span class="stats-highlight"><?php echo count($selectedRepos); ?></span>
                        </span>
                    </div>
                    <?php if (!empty($selectedRepos)): ?>
                    <div class="status-item">
                        <span class="status-label">Selected Repos (Preview)</span>
                        <span class="status-value">
                            <?php echo htmlspecialchars(implode(', ', array_slice($selectedRepos, 0, 2))); ?>
                            <?php echo count($selectedRepos) > 2 ? ' +' . (count($selectedRepos) - 2) . ' more' : ''; ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="actions-section">
                <div class="card-header">
                    <i class="fas fa-tools" aria-hidden="true"></i>
                    <h3>Quick Actions</h3>
                </div>
                <div class="actions-grid">
                    <a href="create_project.php" class="btn" role="button">
                        <i class="fas fa-plus" aria-hidden="true"></i>
                        Create New Project
                    </a>
                    <a href="update_readme.php" class="btn success" role="button">
                        <i class="fas fa-sync" aria-hidden="true"></i>
                        Update Selected READMEs
                    </a>
                    <a href="select_repos.php" class="btn secondary" role="button">
                        <i class="fas fa-cog" aria-hidden="true"></i>
                        Manage Repository Selection
                    </a>
                    <a href="clear_logs.php" class="btn danger" role="button">
                        <i class="fas fa-trash" aria-hidden="true"></i>
                        Clear Logs
                    </a>
                </div>
            </section>

            <?php if (!empty($repos)): ?>
            <section class="repositories-section">
                <div class="card-header">
                    <i class="fas fa-folder" aria-hidden="true"></i>
                    <h3>Your Repositories (Recent)</h3>
                </div>
                <div class="repo-grid">
                    <?php foreach (array_slice($repos, 0, 12) as $repo): ?>
                    <div class="repo-card">
                        <div class="repo-name">
                            <i class="fas fa-<?php echo $repo['private'] ? 'lock' : 'globe'; ?>" aria-hidden="true"></i>
                            <a href="https://github.com/<?php echo htmlspecialchars(GITHUB_OWNER); ?>/<?php echo rawurlencode($repo['name']); ?>" target="_blank" rel="noopener noreferrer">
                                <?php echo htmlspecialchars($repo['name']); ?>
                            </a>
                        </div>
                        <p class="repo-desc">
                            <?php echo htmlspecialchars($repo['description'] ?: 'No description available.'); ?>
                        </p>
                        <div class="repo-meta">
                            <span><i class="fas fa-calendar" aria-hidden="true"></i> Created: <?php echo date('M Y', strtotime($repo['created_at'])); ?></span>
                            <span><i class="fas fa-clock" aria-hidden="true"></i> Updated: <?php echo date('M d, Y', strtotime($repo['updated_at'])); ?></span>
                            <span><i class="fas fa-<?php echo $repo['private'] ? 'lock' : 'unlock'; ?>" aria-hidden="true"></i> <?php echo $repo['private'] ? 'Private' : 'Public'; ?></span>
                        </div>
                        <div class="repo-actions">
                            <a href="update_single_readme.php?repo=<?php echo urlencode($repo['name']); ?>" class="btn success" role="button">
                                <i class="fas fa-edit" aria-hidden="true"></i>
                                Update README
                            </a>
                            <a href="https://github.com/<?php echo htmlspecialchars(GITHUB_OWNER); ?>/<?php echo rawurlencode($repo['name']); ?>" target="_blank" rel="noopener noreferrer" class="btn secondary" role="button">
                                <i class="fas fa-external-link-alt" aria-hidden="true"></i>
                                View on GitHub
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if (count($repos) > 12): ?>
                <div style="text-align: center; margin-top: 25px;">
                    <p style="color: var(--text-medium); font-size: 0.95rem;">Showing 12 of <?php echo count($repos); ?> repositories. <a href="select_repos.php" style="color: var(--primary-color); text-decoration: none;">View all</a></p>
                </div>
                <?php endif; ?>
            </section>
            <?php else: ?>
            <section class="repositories-section">
                <div class="card-header">
                    <i class="fas fa-folder" aria-hidden="true"></i>
                    <h3>Your Repositories</h3>
                </div>
                <div class="logs-empty" style="padding: 60px 20px;">
                    <i class="fas fa-box-open" aria-hidden="true"></i>
                    <p>No repositories found or connected.</p>
                    <p style="font-size: 0.9rem; margin-top: 10px;">Please ensure your GitHub connection is active and you have repositories.</p>
                </div>
            </section>
            <?php endif; ?>

            <section class="logs-section">
                <div class="logs-header">
                    <div class="card-header">
                        <i class="fas fa-terminal" aria-hidden="true"></i>
                        <h3>System Logs</h3>
                    </div>
                    <div class="repo-actions">
                        <a href="clear_logs.php" class="btn danger" role="button">
                            <i class="fas fa-trash" aria-hidden="true"></i>
                            Clear Logs
                        </a>
                    </div>
                </div>
                <div class="logs-content" tabindex="0" aria-live="polite">
                    <?php
                    if (trim($logs) === "No logs found." || trim($logs) === "Log file exists but is empty. No activity recorded yet." || strpos($logs, "Log file not found") !== false) {
                        echo '<div class="logs-empty">';
                        echo '<i class="fas fa-file-alt" aria-hidden="true"></i>';
                        echo '<p>No logs available yet</p>';
                        echo '<p style="font-size: 0.8rem; margin-top: 10px;">System activity will appear here once operations are performed.</p>';
                        echo '</div>';
                    } else {
                        $logLines = explode("\n", trim($logs));
                        $logLines = array_reverse($logLines); // Show most recent logs first
                        foreach ($logLines as $line) {
                            if (trim($line) === '') continue;
                            $timestamp = '';
                            $message = htmlspecialchars($line);
                            $logClass = 'info';
                            $statusClass = 'info';

                            if (preg_match('/^\[(.*?)\]\s*(.*)$/', $line, $matches)) {
                                $timestamp = htmlspecialchars($matches[1]);
                                $message = htmlspecialchars($matches[2]);

                                if (strpos($message, '✅') !== false || strpos($message, 'successfully') !== false || strpos($message, 'Success') !== false) {
                                    $logClass = 'success';
                                    $statusClass = 'success';
                                } elseif (strpos($message, '❌') !== false || strpos($message, 'Failed') !== false || strpos($message, 'ERROR') !== false) {
                                    $logClass = 'error';
                                    $statusClass = 'error';
                                } elseif (strpos($message, '⚠️') !== false || strpos($message, 'Warning') !== false) {
                                    $logClass = 'warning';
                                    $statusClass = 'warning';
                                }
                            }
                            echo '<div class="log-entry ' . $logClass . '">';
                            echo '<span class="log-status-dot ' . $statusClass . '" aria-hidden="true"></span>';
                            if ($timestamp) {
                                echo '<span class="log-timestamp">' . $timestamp . '</span>';
                            }
                            echo '<span class="log-message">' . $message . '</span>';
                            echo '</div>';
                        }
                    }
                    ?>
                </div>
            </section>
        </main>
    </div>

    <!-- Global Footer -->
    <footer class="global-footer">
        <p>&copy; <?php echo date('Y'); ?> Bitzenix. All rights reserved.</p>
    </footer>

    <script>
    function toggleProfileDropdown() {
        var dropdown = document.getElementById('profileDropdown');
        dropdown.classList.toggle('show');
    }

    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        var dropdown = document.getElementById('profileDropdown');
        var profilePic = document.querySelector('.profile-pic-top');
        if (!dropdown.contains(event.target) && event.target !== profilePic) {
            dropdown.classList.remove('show');
        }
    });
    </script>
</body>
</html>

