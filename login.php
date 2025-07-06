<?php
require_once("utils.php");
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if (authenticateUser($username, $password)) {
        loginUser($username);
        header("Location: index.php");
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root {
            --peach-bg: #fdf6f3;
            --peach-accent: #ffe5d0;
            --peach-btn: #f7bfa0;
            --peach-btn-hover: #f5a97f;
            --input-border: #e6dcd2;
            --input-bg: #fff;
            --input-focus: #f7bfa0;
            --text-main: #2d2d2d;
            --text-muted: #888;
            --card-radius: 2.5rem;
        }
        body {
            margin: 0;
            font-family: 'Segoe UI', Arial, sans-serif;
            background: var(--peach-bg);
            min-height: 100vh;
        }
        .main-container {
            display: flex;
            min-height: 100vh;
            align-items: center;
            justify-content: center;
            background: var(--peach-bg);
        }
        .login-card {
            display: flex;
            width: 100vw;
            max-width: 100vw;
            min-height: 100vh;
            background: none;
            border-radius: 0;
            box-shadow: none;
            overflow: visible;
            animation: none;
        }
        .login-form-section {
            flex: 1;
            padding: 0;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            background: none;
            min-height: 100vh;
            text-align: center;
        }
        .login-title {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 18px;
            text-align: center;
            opacity: 0;
            animation: fadeInUp 0.7s 0.1s forwards;
        }
        .input-label {
            font-size: 1.05rem;
            color: var(--text-main);
            margin-bottom: 4px;
            font-weight: 500;
            margin-top: 18px;
            text-align: left;
            display: block;
            opacity: 0;
            animation: fadeInUp 0.7s 0.2s forwards;
        }
        .input-group {
            max-width: 400px;
            width: 100%;
            margin-bottom: 8px;
            margin-left: 0;
            display: flex;
            align-items: center;
            border: 1.5px solid var(--input-border);
            border-radius: 1.5rem;
            background: var(--input-bg);
            padding: 0 18px;
            transition: border 0.2s;
            opacity: 0;
            animation: fadeInUp 0.7s 0.3s forwards;
        }
        .input-group:nth-of-type(2) {
            animation-delay: 0.4s;
        }
        .input-group:focus-within {
            border: 1.5px solid var(--input-focus);
        }
        .input-group svg {
            margin-right: 10px;
            color: #bdbdbd;
        }
        .input-group input {
            border: none;
            outline: none;
            background: transparent;
            padding: 16px 0;
            flex: 1;
            font-size: 1.08rem;
            color: #333;
        }
        .forgot {
            text-align: right;
            margin-bottom: 18px;
            font-size: 0.98rem;
            color: var(--text-main);
            font-weight: 500;
        }
        .forgot a {
            color: var(--text-main);
            text-decoration: none;
            font-weight: 500;
        }
        .login-btn {
            width: 100%;
            background: var(--peach-btn);
            color: #fff;
            border: none;
            border-radius: 1.5rem;
            padding: 16px 0;
            font-size: 1.15rem;
            font-weight: 600;
            margin-top: 18px;
            cursor: pointer;
            transition: background 0.2s;
            box-shadow: 0 2px 8px rgba(247,191,160,0.08);
            opacity: 0;
            animation: fadeInUp 0.7s 0.5s forwards;
        }
        .login-btn:hover {
            background: var(--peach-btn-hover);
        }
        .or {
            text-align: center;
            color: #bbb;
            margin: 22px auto 0 auto;
            font-size: 1rem;
            max-width: 400px;
            width: 100%;
            display: block;
            opacity: 0;
            animation: fadeInUp 0.7s 0.7s forwards;
        }
        .social-row {
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 18px auto 0 auto;
            max-width: 400px;
            width: 100%;
            opacity: 0;
            animation: fadeInUp 0.7s 0.8s forwards;
        }
        .social-row .social {
            margin: 0 12px;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: #f5f5f5;
            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
            cursor: pointer;
            transition: box-shadow 0.2s;
        }
        .social-row .social:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.10);
        }
        .signup-link {
            text-align: center;
            margin-top: 18px;
            color: #444;
            font-size: 1rem;
        }
        .signup-link a {
            color: #f7bfa0;
            text-decoration: none;
            font-weight: 600;
        }
        .error {
            color: #f72585;
            text-align: center;
            margin-bottom: 12px;
        }
        .illustration-section {
            flex: 1;
            background: none;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            min-height: 100vh;
            overflow: visible;
        }
        .arch-bg {
            position: absolute;
            left: 50%;
            top: 8vw;
            transform: translateX(-50%);
            width: 32vw;
            max-width: 480px;
            min-width: 220px;
            height: 40vw;
            max-height: 600px;
            min-height: 220px;
            background: #ffe5d0;
            border-top-left-radius: 50vw;
            border-top-right-radius: 50vw;
            border-bottom-left-radius: 0;
            border-bottom-right-radius: 0;
            z-index: 0;
        }
        .illustration {
            position: absolute;
            left: 28%;
            bottom: 5vw;
            transform: translateX(-50%);
            z-index: 1;
            display: flex;
            align-items: flex-end;
            justify-content: center;
            width: 22vw;
            max-width: 320px;
            min-width: 120px;
            height: auto;
        }
        .illustration img {
            width: 100%;
            height: auto;
            display: block;
            margin: 0;
            border-radius: 0;
            box-shadow: none;
            background: transparent;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: none; }
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: none; }
        }
        @keyframes slideInBg {
            from { transform: translateX(100%); }
            to { transform: none; }
        }
        @media (max-width: 900px) {
            .main-container { flex-direction: column; }
            .login-card { flex-direction: column; min-height: unset; }
            .illustration-section { display: none; }
            .login-form-section { padding: 32px 16px; min-height: unset; }
        }
        @media (max-width: 600px) {
            .login-title { font-size: 1.5rem; }
            .login-card { border-radius: 1.2rem; }
            .arch-bg, .illustration { display: none; }
        }
        .login-form-section form {
            max-width: 400px;
            width: 100%;
            margin: 0 auto;
        }
        .spark {
            position: absolute;
            pointer-events: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: radial-gradient(circle, #f7bfa0 0%, #fff0 80%);
            opacity: 0.7;
            transform: scale(0);
            animation: spark-anim 0.5s cubic-bezier(.4,2,.6,1) forwards;
            z-index: 10;
        }
        @keyframes spark-anim {
            to {
                opacity: 0;
                transform: scale(2.5);
            }
        }
    </style>
</head>
<body>
<div class="main-container">
    <div class="login-card">
        <div class="login-form-section">
            <div class="login-title">Welcome Back!!</div>
            <?php if ($error) echo "<div class='error'>$error</div>"; ?>
            <form method="post" autocomplete="off">
                <div class="input-label">Email</div>
                <div class="input-group">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M16 12a4 4 0 1 1-8 0 4 4 0 0 1 8 0z"/></svg>
                    <input type="text" name="username" placeholder="email@gmail.com" required autofocus>
                </div>
                <div class="input-label">Password</div>
                <div class="input-group">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    <input type="password" name="password" placeholder="Enter your password" required>
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="cursor:pointer;opacity:0.5;" onclick="togglePassword()"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                </div>
                <div class="forgot"><a href="#">Forgot Password?</a></div>
                <button class="login-btn" type="submit">Login</button>
            </form>
            <div class="or">- or -</div>
            <div class="social-row">
                <div class="social" title="Google">
                    <img src="photos/google.png" alt="Google" width="36" height="36" style="display:block;" />
                </div>
                <div class="social" title="Facebook"><img src="https://upload.wikimedia.org/wikipedia/commons/5/51/Facebook_f_logo_%282019%29.svg" alt="Facebook" width="20"></div>
                <div class="social" title="Apple"><img src="https://upload.wikimedia.org/wikipedia/commons/f/fa/Apple_logo_black.svg" alt="Apple" width="20"></div>
            </div>
            <div class="signup-link">Don't have an account? <a href="signup.php">Sign up</a></div>
        </div>
        <div class="illustration-section">
            <div class="arch-bg"></div>
            <div class="illustration">
                <img src="photos/287de5cc1c825597a50d56520555ee32-removebg-preview.png" alt="Login Illustration" />
            </div>
        </div>
    </div>
</div>
<script>
function togglePassword() {
    var pwd = document.querySelector('input[name="password"]');
    if (pwd.type === 'password') {
        pwd.type = 'text';
    } else {
        pwd.type = 'password';
    }
}
// Click spark effect
function addSpark(e) {
    const btn = e.currentTarget;
    const rect = btn.getBoundingClientRect();
    const spark = document.createElement('span');
    spark.className = 'spark';
    spark.style.left = (e.clientX - rect.left - 20) + 'px';
    spark.style.top = (e.clientY - rect.top - 20) + 'px';
    btn.appendChild(spark);
    setTimeout(() => spark.remove(), 500);
}
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.login-btn, .social').forEach(el => {
        el.addEventListener('click', addSpark);
    });
});
</script>
</body>
</html> 