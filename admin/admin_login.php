<?php
session_start();
include "../config/db.php";

$error = "";

$remember_cookie_name = 'admin_remember_token';
$remember_duration_days = 30;
$remember_duration_seconds = 86400 * $remember_duration_days;

// 1. Auto-login from Remember Me cookie
if (!isset($_SESSION['admin_id']) && isset($_COOKIE[$remember_cookie_name])) {
    $token = $_COOKIE[$remember_cookie_name];

    $stmt = $conn->prepare("
        SELECT id, fullname, username, role 
        FROM admins 
        WHERE remember_token = ? 
          AND remember_expires > NOW() 
        LIMIT 1
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $admin = $result->fetch_assoc();
        session_regenerate_id(true);

        $_SESSION['admin_id']   = $admin['id'];
        $_SESSION['admin_name'] = $admin['fullname'];
        $_SESSION['admin_role'] = $admin['role'];

        // Refresh token
        $new_token = bin2hex(random_bytes(32));
        $new_expires = date('Y-m-d H:i:s', time() + $remember_duration_seconds);

        $upd = $conn->prepare("UPDATE admins SET remember_token = ?, remember_expires = ? WHERE id = ?");
        $upd->bind_param("ssi", $new_token, $new_expires, $admin['id']);
        $upd->execute();
        $upd->close();

        setcookie($remember_cookie_name, $new_token, time() + $remember_duration_seconds, "/", "", false, true);

        header("Location: dashboard.php");
        exit();
    }
    $stmt->close();
}

// 2. If already logged in → redirect
if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit();
}

// 3. Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $remember = isset($_POST['remember']);

    if (empty($username) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        $stmt = $conn->prepare("SELECT id, fullname, username, password, role FROM admins WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $admin = $result->fetch_assoc();

            if (password_verify($password, $admin['password'])) {
                session_regenerate_id(true);

                $_SESSION['admin_id']   = $admin['id'];
                $_SESSION['admin_name'] = $admin['fullname'];
                $_SESSION['admin_role'] = $admin['role'];

                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    $expires = date('Y-m-d H:i:s', time() + $remember_duration_seconds);

                    $upd = $conn->prepare("UPDATE admins SET remember_token = ?, remember_expires = ? WHERE id = ?");
                    $upd->bind_param("ssi", $token, $expires, $admin['id']);
                    $upd->execute();
                    $upd->close();

                    setcookie($remember_cookie_name, $token, time() + $remember_duration_seconds, "/", "", false, true);
                } else if (isset($_COOKIE[$remember_cookie_name])) {
                    setcookie($remember_cookie_name, '', time() - 3600, "/");
                }

                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Invalid password.";
            }
        } else {
            $error = "No account found with that username.";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login • USAT College Sagay City</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;family=Playfair+Display:wght@700;800&amp;display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

    <style>
        :root {
            --primary: #1e40af;
            --primary-light: #3b82f6;
            --accent: #d4af37;
        }

        [data-theme="dark"] {
            --primary: #60a5fa;
            --primary-light: #93c5fd;
            --accent: #fcd34d;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 40%, #1e40af 100%);
            background-size: 400% 400%;
            animation: gradientShift 35s ease infinite;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            position: relative;
            overflow-y: auto;
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        body::before {
            content: '';
            position: absolute;
            inset: 0;
            background: 
                radial-gradient(circle at 20% 30%, rgba(255,255,255,0.22) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(212,175,55,0.18) 0%, transparent 50%),
                radial-gradient(circle at 40% 80%, rgba(147,197,253,0.15) 0%, transparent 60%);
            pointer-events: none;
            z-index: 1;
        }

        .login-container {
            width: 100%;
            max-width: 920px;           /* ← Wide rectangle */
            position: relative;
            z-index: 10;
        }

        .login-card {
            display: flex;
            background: rgba(255, 255, 255, 0.97);
            backdrop-filter: blur(32px);
            border-radius: 28px;
            overflow: hidden;
            box-shadow: 0 40px 100px rgba(30, 58, 138, 0.55);
            min-height: 520px;          /* ← Good height for rectangle */
            border: 1px solid rgba(255,255,255,0.6);
        }

        /* Left Side - Logo & Branding (Decorative) */
        .left-side {
            flex: 1;
            background: linear-gradient(135deg, var(--primary), #1e40af);
            padding: 4rem 3rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: white;
            text-align: center;
            position: relative;
        }

        .logo {
            width: 160px;
            height: 160px;
            border-radius: 50%;
            object-fit: cover;
            border: 12px solid rgba(255,255,255,0.9);
            box-shadow: 0 20px 60px rgba(0,0,0,0.4);
            margin-bottom: 2.5rem;
        }

        .left-side h1 {
            font-family: 'Playfair Display', serif;
            font-size: 2.8rem;
            font-weight: 800;
            margin-bottom: 0.8rem;
            letter-spacing: -1.5px;
        }

        .left-side .school-name {
            font-size: 1.65rem;
            font-weight: 700;
            letter-spacing: 3px;
            color: var(--accent);
            margin-bottom: 1rem;
        }

        .left-side .subtitle {
            font-size: 1.25rem;
            opacity: 0.95;
        }

        /* Right Side - Login Form */
        .right-side {
            flex: 1.15;
            padding: 4.5rem 4.5rem;
            display: flex;
            flex-direction: column;
        }

        .form-header {
            margin-bottom: 2.5rem;
        }

        .form-header h2 {
            font-size: 2.1rem;
            color: #1e2937;
            margin-bottom: 0.4rem;
        }

        [data-theme="dark"] .form-header h2 {
            color: white;
        }

        /* Form */
        .form-group {
            position: relative;
            margin-bottom: 2.4rem;
        }

        .form-group i {
            position: absolute;
            left: 1.7rem;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            font-size: 1.55rem;
        }

        input {
            width: 100%;
            padding: 1.65rem 1.65rem 1.65rem 5.2rem;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            font-size: 1.18rem;
            background: white;
        }

        [data-theme="dark"] input {
            background: #1e2937;
            border-color: #475569;
            color: white;
        }

        input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 6px rgba(59, 130, 246, 0.25);
        }

        label {
            position: absolute;
            left: 5.3rem;
            top: 1.65rem;
            color: #64748b;
            font-size: 1.1rem;
            pointer-events: none;
            transition: all 0.3s ease;
            background: white;
            padding: 0 8px;
        }

        [data-theme="dark"] label {
            background: #1e2937;
        }

        input:focus + label,
        input:not(:placeholder-shown) + label {
            top: -0.9rem;
            left: 1.7rem;
            font-size: 0.95rem;
            color: var(--primary);
            font-weight: 600;
        }

        .password-wrapper {
            position: relative;
        }

        .toggle-password {
            position: absolute;
            right: 1.7rem;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            cursor: pointer;
            font-size: 1.65rem;
        }

        .options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 1.8rem 0 2.8rem;
            font-size: 1.08rem;
        }

        .btn-login {
            width: 100%;
            padding: 1.7rem;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
            color: white;
            border: none;
            border-radius: 16px;
            font-size: 1.28rem;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 12px 35px rgba(30,58,138,0.45);
            transition: all 0.4s ease;
        }

        .btn-login:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 50px rgba(30,58,138,0.6);
        }

        .error {
            background: #fee2e2;
            color: #b91c1c;
            padding: 1.4rem 1.8rem;
            border-radius: 14px;
            margin-bottom: 2rem;
            text-align: center;
            font-size: 1.08rem;
            border-left: 6px solid #ef4444;
        }

        .student-link {
            margin-top: 2.5rem;
            text-align: center;
        }

        .student-link a {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .footer-note {
            text-align: center;
            margin-top: 3rem;
            color: #64748b;
            font-size: 0.98rem;
        }

        /* Responsive */
        @media (max-width: 860px) {
            .login-card {
                flex-direction: column;
                min-height: auto;
            }
            .left-side {
                padding: 3rem 2rem;
            }
            .right-side {
                padding: 3.5rem 3rem;
            }
        }
    </style>
</head>
<body>

<div class="login-container">
    <div class="login-card">

        <!-- Left Side: Branding -->
        <div class="left-side">
            <img src="../assets/img/usat.jpg" alt="USAT College Seal" class="logo">
            <h1>Admin Portal</h1>
            <div class="school-name">USAT COLLEGE Sagay City INC.</div>
            <p class="subtitle"> Enrollment Profiling System</p>
        </div>

        <!-- Right Side: Login Form -->
        <div class="right-side">
            <?php if ($error): ?>
                <div class="error">
                    <i class="fas fa-exclamation-triangle me-2"></i> 
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="form-header">
                <h2>Welcome Back</h2>
                <p style="color:#64748b;">Sign in to access the admin dashboard</p>
            </div>

            <form method="POST" id="loginForm" autocomplete="off">
                <div class="form-group">
                    <i class="fas fa-user"></i>
                    <input type="text" id="username" name="username" placeholder=" " required autofocus>
                    <label for="username">Username or Email</label>
                </div>

                <div class="form-group password-wrapper">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="password" name="password" placeholder=" " required>
                    <label for="password">Password</label>
                    <i class="fas fa-eye toggle-password" id="togglePassword"></i>
                </div>

                <div class="options">
                    <div class="remember" style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                        <input type="checkbox" id="remember" name="remember">
                        <label for="remember" style="cursor:pointer; margin:0;">Remember me</label>
                    </div>
                    <a href=" change_user_password.php" style="color: var(--primary); font-weight: 600; text-decoration: none;">Forgot Password?</a>
                </div>

                <button type="submit" class="btn-login" id="submitBtn">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>
            </form>

            <div class="student-link">
                <a href="../enrollment/enroll_form.php">
                    <i class="fas fa-user-graduate"></i>
                    Go to Student Enrollment Portal
                </a>
            </div>

            <div class="footer-note">
                © <?= date('Y') ?> USAT College Sagay City Inc. • All Rights Reserved
            </div>
        </div>
    </div>
</div>

<script>
// Theme Toggle (kept simple)
const html = document.documentElement;
const toggleBtn = document.createElement('button');
toggleBtn.innerHTML = '<i class="fas fa-moon"></i>';
toggleBtn.style.position = 'fixed';
toggleBtn.style.top = '2rem';
toggleBtn.style.right = '2rem';
toggleBtn.style.background = 'rgba(255,255,255,0.25)';
toggleBtn.style.border = 'none';
toggleBtn.style.width = '52px';
toggleBtn.style.height = '52px';
toggleBtn.style.borderRadius = '50%';
toggleBtn.style.color = 'white';
toggleBtn.style.fontSize = '1.6rem';
toggleBtn.style.cursor = 'pointer';
toggleBtn.style.zIndex = '100';
document.body.appendChild(toggleBtn);

toggleBtn.addEventListener('click', () => {
    const current = html.getAttribute('data-theme') || 'light';
    const newTheme = current === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    toggleBtn.innerHTML = newTheme === 'dark' ? '<i class="fas fa-sun"></i>' : '<i class="fas fa-moon"></i>';
});

// Password Toggle
const passwordInput = document.getElementById('password');
const togglePassword = document.getElementById('togglePassword');

togglePassword.addEventListener('click', () => {
    const isPassword = passwordInput.type === 'password';
    passwordInput.type = isPassword ? 'text' : 'password';
    togglePassword.classList.toggle('fa-eye');
    togglePassword.classList.toggle('fa-eye-slash');
});

// Form Submit
document.getElementById('loginForm').addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Authenticating...`;
});
</script>

</body>
</html>