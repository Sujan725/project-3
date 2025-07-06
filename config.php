<?php
// GitHub personal access token
define("GITHUB_TOKEN", "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");

// Your GitHub username
define("GITHUB_USERNAME", "Prohunter-bnb");

// Owner of the repo (usually same as username)
define("GITHUB_OWNER", "Prohunter-bnb");

// GitHub API URL
define("GITHUB_API_URL", "https://api.github.com");

// Default branch (GitHub usually uses main)
define("DEFAULT_BRANCH", "main");

// Log file path
define("LOG_FILE", __DIR__ . "/logs/run.log");

// User data file path for authentication and settings
define("USER_DATA_FILE", __DIR__ . "/logs/users.json");

// SQLite database file path for user data
define("USER_DB_FILE", __DIR__ . "/logs/users.db");

// Encryption key for API tokens (change this to a secure, random value in production)
define("ENCRYPTION_KEY", "your-very-secret-key-1234567890");
?>
