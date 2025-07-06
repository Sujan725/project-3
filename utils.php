<?php
date_default_timezone_set('Asia/Kolkata');
require_once("config.php");

function githubApiRequest($method, $url, $data = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, GITHUB_API_URL . $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, "ProjectCreatorBot");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: token " . GITHUB_TOKEN,
        "Accept: application/vnd.github+json"
    ]);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    return [
        "status" => $info['http_code'],
        "body" => json_decode($response, true)
    ];
}

function logMessage($message) {
    $logDir = dirname(LOG_FILE);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    file_put_contents(LOG_FILE, "[" . date('Y-m-d H:i:s') . "] $message\n", FILE_APPEND);
}

function saveSelectedRepositories($selectedRepos) {
    $selectedFile = __DIR__ . "/logs/selected_repos.txt";
    $data = json_encode($selectedRepos);
    file_put_contents($selectedFile, $data);
    logMessage("Selected repositories saved: " . implode(', ', $selectedRepos));
}

function loadSelectedRepositories() {
    $selectedFile = __DIR__ . "/logs/selected_repos.txt";
    if (file_exists($selectedFile)) {
        $data = file_get_contents($selectedFile);
        $selectedRepos = json_decode($data, true);
        if (is_array($selectedRepos)) {
            return $selectedRepos;
        }
    }
    return [];
}

// User authentication and management helpers
function loadUsers() {
    $file = USER_DATA_FILE;
    if (!file_exists($file)) return [];
    $data = file_get_contents($file);
    $users = json_decode($data, true);
    return is_array($users) ? $users : [];
}

function saveUsers($users) {
    $file = USER_DATA_FILE;
    file_put_contents($file, json_encode($users, JSON_PRETTY_PRINT));
}

function registerUser($username, $password, $github_username = '', $github_token = '') {
    $users = loadUsers();
    if (isset($users[$username])) return false; // User exists
    $users[$username] = [
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'github_username' => $github_username,
        'github_token' => $github_token
    ];
    saveUsers($users);
    return true;
}

function authenticateUser($username, $password) {
    $users = loadUsers();
    if (!isset($users[$username])) return false;
    return password_verify($password, $users[$username]['password']);
}

function getUser($username) {
    $users = loadUsers();
    return $users[$username] ?? null;
}

function updateUser($username, $data) {
    $users = loadUsers();
    if (!isset($users[$username])) return false;
    $users[$username] = array_merge($users[$username], $data);
    saveUsers($users);
    return true;
}

function loginUser($username) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['username'] = $username;
}

function logoutUser() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    session_destroy();
}

function isLoggedIn() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return isset($_SESSION['username']);
}

function getCurrentUser() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return $_SESSION['username'] ?? null;
}
?>
