<?php
session_start();
include "../config/db.php";

$error = "";
$success = "";

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $new_password = trim($_POST['new_password'] ?? '');

    if (empty($username) || empty($new_password)) {
        $error = "Please fill in both username and new password.";
    } elseif (strlen($new_password) < 8) {
        $error = "New password must be at least 8 characters long.";
    } else {
        // Check if user exists
        $stmt = $conn->prepare("SELECT id, fullname FROM admins WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            // Update password and clear any reset token
            $upd = $conn->prepare("UPDATE admins SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
            $upd->bind_param("si", $hashed_password, $user['id']);
            $upd->execute();

            if ($upd->affected_rows > 0) {
                $success = "✅ Password for <strong>" . htmlspecialchars($user['fullname']) . 
                           " (" . htmlspecialchars($username) . ")</strong> has been successfully changed!";
            } else {
                $error = "Failed to update password. Please try again.";
            }
        } else {
            $error = "No user found with username: " . htmlspecialchars($username);
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
    <title>Change User Password • USAT Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;family=Playfair+Display:wght@700;800&amp;display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

    <style>
        :root { --primary: #1e40af; --primary-light: #3b82f6; }
        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 40%, #1e40af 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        .card {
            display: flex;
            background: rgba(255,255,255,0.97);
            backdrop-filter: blur(32px);
            border-radius: 28px;
            overflow: hidden;
            box-shadow: 0 40px 100px rgba(30,58,138,0.55);
            max-width: 920px;
            width: 100%;
            min-height: 520px;
        }
        .left {
            flex: 1;
            background: linear-gradient(135deg, var(--primary), #1e40af);
            padding: 4rem 3rem;
            color: white;
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .logo { 
            width: 150px; 
            height: 150px; 
            border-radius: 50%; 
            border: 12px solid rgba(255,255,255,0.9); 
            margin-bottom: 2rem; 
        }
        .right {
            flex: 1.15;
            padding: 4.5rem 4.5rem;
        }
        input {
            width: 100%;
            padding: 1.6rem;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            font-size: 1.18rem;
            margin-bottom: 1.8rem;
        }
        input:focus { 
            border-color: var(--primary); 
            outline: none; 
        }
        .btn {
            width: 100%;
            padding: 1.7rem;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
            color: white;
            border: none;
            border-radius: 16px;
            font-size: 1.28rem;
            font-weight: 700;
            cursor: pointer;
        }
        .error { 
            background:#fee2e2; 
            color:#b91c1c; 
            padding:1.4rem; 
            border-radius:14px; 
            text-align:center; 
            margin-bottom:2rem; 
        }
        .success { 
            background:#d1fae5; 
            color:#166534; 
            padding:1.6rem; 
            border-radius:14px; 
            text-align:center; 
            margin-bottom:2rem; 
            font-size: 1.1rem;
        }
    </style>
</head>
<body>

<div class="card">
    <!-- Left Side -->
    <div class="left">
        <img src="../assets/img/usat.jpg" alt="USAT" class="logo">
        <h1>Change Password</h1>
        <p style="margin-top:1rem; opacity:0.9;">Admin Tool</p>
    </div>

    <!-- Right Side -->
    <div class="right">
        <h2 style="margin-bottom:2rem;">Update User Password</h2>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success"><?= $success ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="text" 
                   name="username" 
                   placeholder="Username" 
                   required 
                   autofocus 
                   value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">

            <input type="password" 
                   name="new_password" 
                   placeholder="New Password (min 8 chars)" 
                   required 
                   minlength="8">

            <button type="submit" class="btn">
                <i class="fas fa-key"></i> Change Password
            </button>
        </form>

        <div style="text-align:center; margin-top:3rem;">
            <a href="admin_login.php" style="color:var(--primary); font-weight:600; text-decoration:none;">
                ← Back to Login
            </a>
        </div>
    </div>
</div>

</body>
</html>