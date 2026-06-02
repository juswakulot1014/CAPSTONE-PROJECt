<?php
session_start();
include "../config/db.php";

// ====================== SECURITY: Only Super Admin Allowed ======================
if (!isset($_SESSION['admin_id']) || $_SESSION['admin_role'] !== 'superadmin') {
    $_SESSION['error'] = "Access Denied! Only The Principal can manage accounts.";
    header("Location: dashboard.php");
    exit();
}

// ====================== HANDLE ADD ACCOUNT ======================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_account'])) {
    $fullname = trim($_POST['fullname']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $role     = trim($_POST['role']);

    if (empty($fullname) || empty($username) || empty($password) || empty($role)) {
        $_SESSION['error'] = "All fields are required!";
    } else {
        $check = $conn->prepare("SELECT id FROM admins WHERE username = ?");
        $check->bind_param("s", $username);
        $check->execute();
        
        if ($check->get_result()->num_rows > 0) {
            $_SESSION['error'] = "Username already exists!";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("INSERT INTO admins (fullname, username, password, role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $fullname, $username, $hashed_password, $role);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "New account created successfully for " . htmlspecialchars($fullname) . "!";
            } else {
                $_SESSION['error'] = "Failed to create account.";
            }
            $stmt->close();
        }
        $check->close();
    }
    header("Location: create_account.php");
    exit();
}

// ====================== HANDLE DELETE ACCOUNT ======================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {
    $account_id = (int)$_POST['account_id'];

    if ($account_id == $_SESSION['admin_id']) {
        $_SESSION['error'] = "You cannot delete your own account!";
    } else {
        $stmt = $conn->prepare("DELETE FROM admins WHERE id = ?");
        $stmt->bind_param("i", $account_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Account has been deleted successfully!";
        } else {
            $_SESSION['error'] = "Failed to delete account.";
        }
        $stmt->close();
    }
    header("Location: create_account.php");
    exit();
}

// ====================== FETCH ALL ACCOUNTS ======================
$result = $conn->query("SELECT id, fullname, username, role, created_at 
                        FROM admins 
                        ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Accounts - USAT Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
<style>
    :root {
        --bg-body: #f8f9fc;
        --bg-card: #ffffff;
        --text-primary: #212529;
        --text-muted: #6c757d;
        --border-color: #dee2e6;
        --shadow-color: rgba(0, 0, 0, 0.08);
        --hover-shadow: rgba(0, 0, 0, 0.15);
    }

    [data-bs-theme="dark"] {
        --bg-body: #1a1d23;
        --bg-card: #2b2f38;
        --text-primary: #e9ecef;
        --text-muted: #adb5bd;
        --border-color: #495057;
        --shadow-color: rgba(0, 0, 0, 0.4);
        --hover-shadow: rgba(0, 0, 0, 0.6);
    }

    body {
        font-family: 'Segoe UI', system-ui, sans-serif;
        background: var(--bg-body);
        color: var(--text-primary);
        min-height: 100vh;
        transition: background 0.3s ease;
    }

    .navbar {
        background-color: var(--bg-card) !important;
        box-shadow: 0 4px 15px var(--shadow-color);
        border-bottom: 1px solid var(--border-color);
    }

    .main-content {
        padding: 2rem;
        min-height: calc(100vh - 76px);
        padding-top: 110px;
    }

    .card, .kpi-card, .strand-card {
        background-color: var(--bg-card);
        border: none;
        border-radius: 18px;
        box-shadow: 0 6px 25px var(--shadow-color);
        color: var(--text-primary);
        transition: all 0.3s ease;
    }

    /* KPI Cards */
    .kpi-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 15px 35px var(--hover-shadow) !important;
    }

    /* Strand Cards */
    .strand-card {
        cursor: pointer;
        height: 100%;
    }
    .strand-card:hover {
        transform: translateY(-12px);
        box-shadow: 0 20px 30px var(--hover-shadow) !important;
    }

    /* Text colors */
    .text-muted {
        color: var(--text-muted) !important;
    }

    .badge {
        font-weight: 500;
    }

    /* Table improvements */
    .table {
        color: var(--text-primary);
    }
    .table-light {
        background-color: var(--bg-card) !important;
        color: var(--text-primary);
    }

    /* Alert colors adapt automatically with Bootstrap */
</style>
</head>
<body>

<nav class="navbar navbar-expand-lg fixed-top top-navbar">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">
            <img src="../assets/img/usat.jpg" width="42" height="42" class="rounded-circle" alt="USAT Logo">
            USAT Admin
        </a>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto gap-1">
                <li class="nav-item"><a class="nav-link px-4" href="dashboard.php">🎓 Dashboard</a></li>
                <li class="nav-item"><a class="nav-link px-4" href="student_profile.php">👨‍🎓Manage Student Profiles</a></li>
                 <li class="nav-item"><a class="nav-link px-4 active" href="reports.php">📊 Reports</a></li>
                <li class="nav-item"><a class="nav-link px-4 active" href="create_account.php">👤 Accounts</a></li>
            </ul>
            <div class="d-flex align-items-center gap-3">
                <span class="badge bg-danger">Super Admin Only</span>
                <a href="logout.php" class="btn btn-outline-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
            </div>
        </div>
    </div>
</nav>

<div class="container mt-5 pt-5">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="display-6 fw-bold">Manage Admin Accounts</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAccountModal">
            <i class="bi bi-plus-circle"></i> Add New Account
        </button>
    </div>

    <!-- Accounts Table -->
    <div class="card shadow-sm">
        <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
            All Admin Accounts
            <span class="text-muted small">Only Principal can manage accounts</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Full Name</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Created At</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['id']) ?></td>
                                <td><?= htmlspecialchars($row['fullname']) ?></td>
                                <td><?= htmlspecialchars($row['username']) ?></td>
                                <td>
                                    <span class="badge <?= $row['role'] === 'superadmin' ? 'bg-danger' : 'bg-primary' ?>">
                                        <?= ucfirst(htmlspecialchars($row['role'])) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($row['created_at']) ?></td>
                                <td class="text-center">
                                    <?php if ($row['id'] != $_SESSION['admin_id']): ?>
                                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this account? This action cannot be undone.');" style="display:inline;">
                                            <input type="hidden" name="account_id" value="<?= $row['id'] ?>">
                                            <button type="submit" name="delete_account" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i> Delete
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted small">Current User</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">No accounts found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add New Account Modal -->
<div class="modal fade" id="addAccountModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Admin Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="fullname" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-select" required>
                            <option value="superadmin">Super Admin</option>
                            <option value="registrar">Registrar</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_account" class="btn btn-primary">Create Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Success / Error Popup Modal -->
<div class="modal fade" id="resultModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered result-modal">
        <div class="modal-content">
            <div class="modal-body text-center py-5">
                <div id="modalIcon" style="font-size: 4rem; margin-bottom: 1rem;"></div>
                <h4 id="modalTitle" class="mb-3"></h4>
                <p id="modalMessage" class="text-muted"></p>
                <button type="button" class="btn btn-primary px-5 mt-3" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Show popup message after page loads
document.addEventListener('DOMContentLoaded', function() {
    <?php if (isset($_SESSION['success'])): ?>
        showPopup("success", "<?= addslashes($_SESSION['success']) ?>");
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        showPopup("error", "<?= addslashes($_SESSION['error']) ?>");
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
});

function showPopup(type, message) {
    const modal = new bootstrap.Modal(document.getElementById('resultModal'));
    const icon = document.getElementById('modalIcon');
    const title = document.getElementById('modalTitle');
    const msg = document.getElementById('modalMessage');

    if (type === "success") {
        icon.innerHTML = '✅';
        icon.style.color = '#198754';
        title.textContent = "Success!";
        title.style.color = '#198754';
    } else {
        icon.innerHTML = '⚠️';
        icon.style.color = '#dc3545';
        title.textContent = "Error";
        title.style.color = '#dc3545';
    }

    msg.textContent = message;
    modal.show();
}

// Theme Toggle (Improved)
const toggleBtn = document.getElementById('themeToggle');
const themeIcon = document.getElementById('themeIcon');
const html = document.documentElement;

function setTheme(theme) {
    html.setAttribute('data-bs-theme', theme);
    if (theme === 'dark') {
        themeIcon.classList.replace('bi-sun-fill', 'bi-moon-fill');
    } else {
        themeIcon.classList.replace('bi-moon-fill', 'bi-sun-fill');
    }
    localStorage.setItem('theme', theme);
}

// Load saved theme
const savedTheme = localStorage.getItem('theme') || 'light';
setTheme(savedTheme);

toggleBtn.addEventListener('click', () => {
    const currentTheme = html.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
    setTheme(currentTheme);
});
</script>

</body>
</html>