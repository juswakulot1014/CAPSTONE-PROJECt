<?php
session_start();
include __DIR__ . "/../config/db.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);

// ────────────────────────────────────────────────
// FILTER INPUTS
// ────────────────────────────────────────────────
$search       = trim($_GET['search'] ?? '');
$grade_level  = trim($_GET['grade_level'] ?? '');
$school_year  = trim($_GET['school_year'] ?? '');
$status       = trim($_GET['status'] ?? '');   // New: Active, Transferred, Stopped, Dropped

// ────────────────────────────────────────────────
// AUTO SECTION ASSIGNMENT
// ────────────────────────────────────────────────
if (isset($_GET['auto_assign'])) {
    if (empty($grade_level) || empty($school_year)) {
        $_SESSION['error'] = "Please select Grade Level and School Year to auto-assign sections.";
    } else {
        $stmt = $conn->prepare("
            SELECT DISTINCT strand, program 
            FROM enrollment_form 
            WHERE grade_level = ? AND school_year = ?
        ");
        $stmt->bind_param("ss", $grade_level, $school_year);
        $stmt->execute();
        $groups = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($groups as $group) {
            $strand  = $group['strand'];
            $program = $group['program'];

            $sql = "
                SELECT e.enrollment_id, s.last_name, s.first_name 
                FROM enrollment_form e 
                INNER JOIN students_info s ON e.student_id = s.student_id 
                WHERE e.grade_level = ? AND e.school_year = ?";

            $params = [$grade_level, $school_year];
            $types  = "ss";

            if ($strand !== null) {
                $sql .= " AND e.strand = ?";
                $types .= "s";
                $params[] = $strand;
            } else {
                $sql .= " AND e.strand IS NULL";
            }
            if ($program !== null) {
                $sql .= " AND e.program = ?";
                $types .= "s";
                $params[] = $program;
            } else {
                $sql .= " AND e.program IS NULL";
            }

            $sql .= " ORDER BY s.last_name ASC, s.first_name ASC";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $students_group = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $num_students = count($students_group);
            if ($num_students > 0) {
                $num_sections = ceil($num_students / 35);
                $section_letters = range('A', chr(64 + $num_sections));
                $student_index = 0;

                for ($sec = 0; $sec < $num_sections; $sec++) {
                    $base_name = trim(($strand ? $strand : '') . ' ' . ($program ? $program : ''));
                    $section_name = $base_name ? $base_name . ' ' . $section_letters[$sec] : 'General ' . $section_letters[$sec];

                    for ($i = 0; $i < 35 && $student_index < $num_students; $i++, $student_index++) {
                        $enroll_id = $students_group[$student_index]['enrollment_id'];
                        $upd = $conn->prepare("UPDATE enrollment_form SET section = ? WHERE enrollment_id = ?");
                        $upd->bind_param("si", $section_name, $enroll_id);
                        $upd->execute();
                        $upd->close();
                    }
                }
            }
        }
        $_SESSION['success'] = "Sections auto-assigned successfully for Grade $grade_level - School Year $school_year.";
    }
    header("Location: student_profile.php?grade_level=" . urlencode($grade_level) . "&school_year=" . urlencode($school_year));
    exit();
}

// ────────────────────────────────────────────────
// MAIN STUDENTS QUERY WITH STATUS
// ────────────────────────────────────────────────
$sql = "
    SELECT 
        s.student_id, s.lrn, s.last_name, s.first_name, s.middle_name, s.sex,
        e.grade_level, e.school_year, e.section, e.strand, e.track, e.program,
        COALESCE(e.status, 'Active') AS status
    FROM students_info s
    LEFT JOIN enrollment_form e ON s.student_id = e.student_id
    WHERE 1=1";

$params = [];
$types  = "";

if ($search !== '') {
    $sql .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.lrn LIKE ?)";
    $like = "%$search%";
    $params = [$like, $like, $like];
    $types = "sss";
}
if ($grade_level !== '') {
    $sql .= " AND e.grade_level = ?";
    $params[] = $grade_level;
    $types .= "s";
}
if ($school_year !== '') {
    $sql .= " AND e.school_year = ?";
    $params[] = $school_year;
    $types .= "s";
}
if ($status !== '' && $status !== 'All') {
    $sql .= " AND COALESCE(e.status, 'Active') = ?";
    $params[] = $status;
    $types .= "s";
}

$sql .= " ORDER BY s.last_name ASC, s.first_name ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$students = $stmt->get_result();
$total_students = $students->num_rows;
$stmt->close();

// Gender Statistics (with filters)
$gender_sql = "
    SELECT 
        SUM(CASE WHEN UPPER(TRIM(s.sex)) IN ('MALE','M') THEN 1 ELSE 0 END) AS boys,
        SUM(CASE WHEN UPPER(TRIM(s.sex)) IN ('FEMALE','F') THEN 1 ELSE 0 END) AS girls
    FROM students_info s
    LEFT JOIN enrollment_form e ON s.student_id = e.student_id
    WHERE 1=1";

$g_params = [];
$g_types = "";

if ($search !== '') {
    $gender_sql .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.lrn LIKE ?)";
    $g_params = [$like, $like, $like];
    $g_types = "sss";
}
if ($grade_level !== '') {
    $gender_sql .= " AND e.grade_level = ?";
    $g_params[] = $grade_level;
    $g_types .= "s";
}
if ($school_year !== '') {
    $gender_sql .= " AND e.school_year = ?";
    $g_params[] = $school_year;
    $g_types .= "s";
}
if ($status !== '' && $status !== 'All') {
    $gender_sql .= " AND COALESCE(e.status, 'Active') = ?";
    $g_params[] = $status;
    $g_types .= "s";
}

$g_stmt = $conn->prepare($gender_sql);
if (!empty($g_params)) $g_stmt->bind_param($g_types, ...$g_params);
$g_stmt->execute();
$stats = $g_stmt->get_result()->fetch_assoc();
$boys  = (int)($stats['boys'] ?? 0);
$girls = (int)($stats['girls'] ?? 0);
$g_stmt->close();

// Section-wise Statistics (with filters)
$section_sql = "
    SELECT 
        e.section,
        COUNT(*) AS total,
        SUM(CASE WHEN UPPER(TRIM(s.sex)) IN ('MALE','M') THEN 1 ELSE 0 END) AS boys,
        SUM(CASE WHEN UPPER(TRIM(s.sex)) IN ('FEMALE','F') THEN 1 ELSE 0 END) AS girls
    FROM enrollment_form e
    INNER JOIN students_info s ON e.student_id = s.student_id
    WHERE e.section IS NOT NULL";

$s_params = [];
$s_types = "";

if ($search !== '') {
    $section_sql .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.lrn LIKE ?)";
    $s_params = [$like, $like, $like];
    $s_types = "sss";
}
if ($grade_level !== '') {
    $section_sql .= " AND e.grade_level = ?";
    $s_params[] = $grade_level;
    $s_types .= "s";
}
if ($school_year !== '') {
    $section_sql .= " AND e.school_year = ?";
    $s_params[] = $school_year;
    $s_types .= "s";
}
if ($status !== '' && $status !== 'All') {
    $section_sql .= " AND COALESCE(e.status, 'Active') = ?";
    $s_params[] = $status;
    $s_types .= "s";
}

$section_sql .= " GROUP BY e.section ORDER BY e.section ASC";

$sec_stmt = $conn->prepare($section_sql);
if (!empty($s_params)) $sec_stmt->bind_param($s_types, ...$s_params);
$sec_stmt->execute();
$section_result = $sec_stmt->get_result();
$sec_stmt->close();

// Dropdowns
$grade_levels = $conn->query("SELECT DISTINCT grade_level FROM enrollment_form WHERE grade_level IS NOT NULL ORDER BY grade_level ASC");
$school_years = $conn->query("SELECT DISTINCT school_year FROM enrollment_form WHERE school_year IS NOT NULL ORDER BY school_year DESC");

$possible_statuses = ['Active', 'Transferred', 'Stopped', 'Dropped'];

$page_title = "Student Profiles";
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>USAT Admin • <?= htmlspecialchars($page_title) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #f8f9fc; --bg-secondary: #ffffff; --text-primary: #1f2937;
            --text-muted: #6b7280; --accent-primary: #3b82f6; --accent-primary-dark: #2563eb;
            --accent-light: #dbeafe; --border: #e2e8f0; --shadow-sm: 0 1px 3px rgba(0,0,0,0.1);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
            --radius: 12px;
        }
        [data-bs-theme="dark"] {
            --bg-primary: #0f172a; --bg-secondary: #1e293b; --text-primary: #e2e8f0;
            --text-muted: #94a3b8; --accent-primary: #60a5fa; --accent-primary-dark: #3b82f6;
            --accent-light: #1e40af; --border: #334155;
        }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--bg-primary); color: var(--text-primary); }
        .top-navbar { background: var(--bg-secondary); border-bottom: 1px solid var(--border); box-shadow: var(--shadow-md); z-index: 1050; }
        .navbar-brand { font-weight: 700; color: var(--accent-primary); }
        .nav-link { font-weight: 500; color: var(--text-primary); transition: all 0.3s ease; }
        .nav-link:hover, .nav-link.active { color: white; background: linear-gradient(90deg, var(--accent-primary) 0%, var(--accent-primary-dark) 100%); border-radius: 8px; }
        .theme-toggle { width: 42px; height: 42px; display: flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.9); border: none; border-radius: 50%; box-shadow: var(--shadow-sm); }
        [data-bs-theme="dark"] .theme-toggle { background: rgba(30,41,59,0.9); }
        .main-content { padding: 2rem; min-height: 100vh; padding-top: 100px; }
        .students-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 2rem; }
        .student-card { background: var(--bg-secondary); border-radius: var(--radius); box-shadow: var(--shadow-md); border: 1px solid var(--border); overflow: hidden; transition: all 0.35s ease; }
        .student-card:hover { transform: translateY(-10px); box-shadow: 0 20px 25px -5px rgba(0,0,0,0.15); border-color: var(--accent-primary); }
        .card-header { background: linear-gradient(135deg,var(--accent-light) 0%,#e0f2fe 100%); padding:1.5rem; position:relative; }
        .student-avatar { width:62px;height:62px;min-width:62px;border-radius:9999px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.45rem;color:white; background:linear-gradient(135deg,var(--accent-primary),var(--accent-primary-dark)); border:4px solid var(--bg-secondary); }
        .card-body { padding:1.75rem; }
        .card-title { font-size:1.35rem; font-weight:700;color:var(--accent-primary); margin-bottom:0.25rem; }
        .info-grid { display:grid; grid-template-columns:1fr 1fr; gap:1rem 1.5rem; margin-bottom:1.5rem; }
        .info-label { font-size:0.75rem; font-weight:500;color:var(--text-muted); text-transform:uppercase; letter-spacing:0.5px; }
        .info-value { font-weight:600; color:var(--text-primary); }
        .filter-bar { background:var(--bg-secondary); border-radius:var(--radius); padding:1.5rem; box-shadow:var(--shadow-md); margin-bottom:2.5rem; border:1px solid var(--border); }
        .filter-bar .form-control, .filter-bar .form-select { background:var(--bg-primary); border-color:var(--border); border-radius:12px; height:48px; }
        .badge.bg-boy { background-color: #0d6efd !important; }
        .badge.bg-girl { background-color: #d63384 !important; }
        .status-active { background-color: #198754 !important; color: white; }
        .status-transferred { background-color: #0dcaf0 !important; color: #000; }
        .status-stopped { background-color: #fd7e14 !important; color: white; }
        .status-dropped { background-color: #dc3545 !important; color: white; }
        .status-badge { font-size: 0.82rem; padding: 0.4em 0.9em; font-weight: 600; }
        @media (max-width: 992px) {
            .main-content { padding-top: 90px; padding-left: 1rem; padding-right: 1rem; }
            .students-grid { grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); }
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg fixed-top top-navbar">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php">
            <img src="../assets/img/usat.jpg" width="42" height="42" class="rounded-circle" alt="USAT Logo">
            USAT Admin
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto gap-1">
                <li class="nav-item"><a class="nav-link px-4 <?= ($current_page === 'dashboard.php') ? 'active' : '' ?>" href="dashboard.php">🎓 Dashboard</a></li>
                <li class="nav-item"><a class="nav-link px-4 active" href="student_profile.php">👨‍🎓 Manage Student Profiles</a></li>
                <li class="nav-item"><a class="nav-link px-4" href="reports.php">📊 Reports</a></li>
                <li class="nav-item"><a class="nav-link px-4" href="create_account.php">👤 Accounts</a></li>
            </ul>
            <div class="d-flex align-items-center gap-3">
                <button class="theme-toggle" id="themeToggle" title="Toggle Dark Mode">
                    <i class="bi bi-sun-fill fs-4" id="themeIcon"></i>
                </button>
                <a href="logout.php" class="btn btn-outline-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
            </div>
        </div>
    </div>
</nav>

<div class="main-content">
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($_SESSION['success']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($_SESSION['error']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="d-flex flex-wrap justify-content-between align-items-end mb-4 gap-3">
        <div>
            <h1 class="display-6 fw-bold">Student Profiles</h1>
            <p class="text-muted mb-0">View and manage enrolled students with status tracking</p>
        </div>
        <?php if ($total_students > 0): ?>
            <div class="d-flex gap-2 flex-wrap">
                <div class="badge bg-primary fs-5 px-4 py-2 rounded-3 shadow-sm"><?= number_format($total_students) ?> student<?= $total_students > 1 ? 's' : '' ?></div>
                <div class="badge bg-boy fs-5 px-4 py-2 rounded-3 shadow-sm"><?= number_format($boys) ?> Boys</div>
                <div class="badge bg-girl fs-5 px-4 py-2 rounded-3 shadow-sm"><?= number_format($girls) ?> Girls</div>
            </div>
        <?php endif; ?>
    </div>

    <!-- FILTER BAR -->
    <form class="filter-bar" method="GET">
        <div class="row g-3">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control" placeholder="Search by name or LRN..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-2">
                <select name="grade_level" class="form-select">
                    <option value="">All Grade Levels</option>
                    <?php while($gl = $grade_levels->fetch_assoc()): ?>
                        <option value="<?= htmlspecialchars($gl['grade_level']) ?>" <?= $gl['grade_level'] === $grade_level ? 'selected' : '' ?>><?= htmlspecialchars($gl['grade_level']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="school_year" class="form-select">
                    <option value="">All School Years</option>
                    <?php while($sy = $school_years->fetch_assoc()): ?>
                        <option value="<?= htmlspecialchars($sy['school_year']) ?>" <?= $sy['school_year'] === $school_year ? 'selected' : '' ?>><?= htmlspecialchars($sy['school_year']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <?php foreach($possible_statuses as $st): ?>
                        <option value="<?= $st ?>" <?= $st === $status ? 'selected' : '' ?>><?= $st ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">Apply Filter</button>
                <a href="student_profile.php" class="btn btn-outline-secondary flex-grow-1">Reset</a>
                <button type="submit" name="auto_assign" value="1" class="btn btn-success flex-grow-1">Auto Section</button>
            </div>
        </div>
    </form>

    <!-- Sections List -->
    <?php if ($section_result && $section_result->num_rows > 0): ?>
    <div class="card mt-4 shadow-sm">
        <div class="card-header bg-light fw-semibold">Sections Overview</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Section Name</th>
                            <th>Total</th>
                            <th>Boys</th>
                            <th>Girls</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while($sec = $section_result->fetch_assoc()): ?>
                        <tr>
                            <td class="fw-bold"><?= htmlspecialchars($sec['section']) ?></td>
                            <td class="fw-semibold"><?= $sec['total'] ?></td>
                            <td class="text-primary fw-semibold"><?= $sec['boys'] ?? 0 ?></td>
                            <td class="text-danger fw-semibold"><?= $sec['girls'] ?? 0 ?></td>
                            <td class="text-center">
                                <a href="sections_list.php?section=<?= urlencode($sec['section']) ?>" 
                                   class="btn btn-sm btn-primary">
                                    <i class="bi bi-eye me-1"></i> View List
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Students Grid -->
    <?php if ($total_students > 0): ?>
        <div class="students-grid mt-4">
            <?php while($s = $students->fetch_assoc()):
                $full_name = trim($s['last_name'] . ', ' . $s['first_name'] . ($s['middle_name'] ? ' ' . $s['middle_name'] : ''));
                $initials = strtoupper(substr($s['first_name']??'',0,1) . substr($s['last_name']??'',0,1)) ?: 'ST';
                $status_class = 'status-' . strtolower(str_replace(' ', '-', $s['status']));
            ?>
            <div class="student-card">
                <div class="card-header">
                    <div class="d-flex align-items-start gap-3">
                        <div class="student-avatar"><?= $initials ?></div>
                        <div class="flex-grow-1 pt-1">
                            <h5 class="card-title"><?= htmlspecialchars($full_name) ?></h5>
                            <small class="text-muted">LRN: <?= htmlspecialchars($s['lrn'] ?? '—') ?></small>
                        </div>
                    </div>
                    <span class="badge <?= $status_class ?> position-absolute top-0 end-0 mt-3 me-3 status-badge">
                        <?= htmlspecialchars($s['status']) ?>
                    </span>
                </div>
                <div class="card-body">
                    <div class="info-grid">
                        <div class="info-item"><div class="info-label">Grade Level</div><div class="info-value"><?= htmlspecialchars($s['grade_level'] ?? '—') ?></div></div>
                        <div class="info-item"><div class="info-label">School Year</div><div class="info-value"><?= htmlspecialchars($s['school_year'] ?? '—') ?></div></div>
                        <div class="info-item"><div class="info-label">Section</div><div class="info-value"><?= htmlspecialchars($s['section'] ?? '—') ?></div></div>
                        <div class="info-item"><div class="info-label">Strand / Program</div><div class="info-value"><?= htmlspecialchars(trim(($s['strand']??'') . ($s['program'] ? ' • '.$s['program'] : '')) ?: '—') ?></div></div>
                    </div>

                    <div class="d-grid gap-2 mt-3">
                        <a href="view_student.php?id=<?= $s['student_id'] ?>" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-eye-fill"></i> View Profile
                        </a>
                        <?php if (!empty($s['grade_level'])): ?>
                        <a href="generate_form137.php?id=<?= $s['student_id'] ?>" class="btn btn-success btn-sm" target="_blank">
                            <i class="bi bi-printer-fill"></i> View SF10
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="no-data-card mt-4 text-center p-5 bg-white rounded-3 shadow-sm">
            <i class="bi bi-people-fill fs-1 text-muted mb-4 d-block"></i>
            <h4>No students found</h4>
            <p class="text-muted">Try adjusting your search or filters.</p>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Theme Toggle
const toggleBtn = document.getElementById('themeToggle');
const themeIcon = document.getElementById('themeIcon');
const html = document.documentElement;

function setTheme(theme) {
    html.setAttribute('data-bs-theme', theme);
    themeIcon.classList.toggle('bi-sun-fill', theme === 'light');
    themeIcon.classList.toggle('bi-moon-fill', theme === 'dark');
    localStorage.setItem('theme', theme);
}

const savedTheme = localStorage.getItem('theme') || 'light';
setTheme(savedTheme);

toggleBtn.addEventListener('click', () => {
    const current = html.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
    setTheme(current);
});
</script>
</body>
</html>