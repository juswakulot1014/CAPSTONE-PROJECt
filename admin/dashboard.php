<?php
session_start();
include "../config/db.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);

// ====================== HANDLE DELETE STUDENT ======================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_student'])) {
    $student_id = (int)$_POST['student_id'];
    $conn->begin_transaction();
    try {
        $tables = ['student_grades','entrance_documents','educational_history','addresses','parents_info','enrollment_form'];
        foreach ($tables as $table) {
            $conn->query("DELETE FROM `$table` WHERE student_id = $student_id");
        }
        $stmt = $conn->prepare("DELETE FROM students_info WHERE student_id = ?");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $stmt->close();
        $conn->commit();
        $_SESSION['success'] = "Student and all related records deleted successfully!";
        header("Location: student_profile.php");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Failed to delete student: " . $e->getMessage();
        header("Location: student_profile.php?id=$student_id");
        exit();
    }
}

// ====================== HANDLE ADD GRADE ======================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_grade'])) {
    $student_id     = (int)$_POST['student_id'];
    $semester       = trim($_POST['semester']);
    $subject_code   = trim($_POST['subject_code']);
    $subject_name   = trim($_POST['subject_name']);
    $grade          = !empty($_POST['grade']) ? (float)$_POST['grade'] : null;
    $remarks        = trim($_POST['remarks'] ?? '');

    $stmt = $conn->prepare("INSERT INTO student_grades (student_id, semester, subject_code, subject_name, grade, remarks) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssds", $student_id, $semester, $subject_code, $subject_name, $grade, $remarks);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Subject/Grade added successfully!";
    } else {
        $_SESSION['error'] = "Failed to add grade: " . $conn->error;
    }
    $stmt->close();
    header("Location: student_profile.php?id=$student_id");
    exit();
}

// ====================== DETERMINE MODE ======================
$is_profile_view = isset($_GET['id']) && is_numeric($_GET['id']);

if ($is_profile_view) {
    // ====================== SINGLE STUDENT PROFILE MODE ======================
    $student_id = (int)$_GET['id'];

    $stmt = $conn->prepare("SELECT * FROM students_info WHERE student_id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$student) {
        $_SESSION['error'] = "Student not found.";
        header("Location: student_profile.php");
        exit();
    }

    $stmt = $conn->prepare("SELECT * FROM enrollment_form WHERE student_id = ? ORDER BY enrollment_id DESC LIMIT 1");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $enrollment = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();

    $stmt = $conn->prepare("SELECT * FROM parents_info WHERE student_id = ? LIMIT 1");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $parents = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();

    $stmt = $conn->prepare("SELECT * FROM addresses WHERE student_id = ? LIMIT 1");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $address = $stmt->get_result()->fetch_assoc() ?: [];
    $stmt->close();

    $edu_stmt = $conn->prepare("SELECT * FROM educational_history WHERE student_id = ? ORDER BY year_completed DESC, edu_id DESC");
    $edu_stmt->bind_param("i", $student_id);
    $edu_stmt->execute();
    $education = $edu_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $edu_stmt->close();

    $grades_stmt = $conn->prepare("SELECT semester, subject_code, subject_name, grade, remarks FROM student_grades WHERE student_id = ? ORDER BY semester DESC, subject_name ASC");
    $grades_stmt->bind_param("i", $student_id);
    $grades_stmt->execute();
    $grades_result = $grades_stmt->get_result();
    $grades = [];
    while ($row = $grades_result->fetch_assoc()) {
        $grades[$row['semester']][] = $row;
    }
    $grades_stmt->close();

    $page_title = "Student Profile • " . htmlspecialchars($student['first_name'] . ' ' . $student['last_name']);
    $is_profile_view = true;

} else {
    // ====================== DASHBOARD / LIST MODE ======================
    $search        = trim($_GET['search'] ?? '');
    $grade_level   = trim($_GET['grade_level'] ?? '');
    $school_year   = trim($_GET['school_year'] ?? '');
    $strand_filter = trim($_GET['strand'] ?? '');

    if (isset($_GET['auto_assign'])) {
        if ($grade_level === '' || $school_year === '') {
            $_SESSION['error'] = "Please select Grade Level and School Year to auto assign sections.";
        } else {
            $sql_groups = "SELECT DISTINCT strand, program FROM enrollment_form WHERE grade_level = ? AND school_year = ?";
            $stmt = $conn->prepare($sql_groups);
            $stmt->bind_param("ss", $grade_level, $school_year);
            $stmt->execute();
            $groups = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            foreach ($groups as $group) {
                $strand = $group['strand'];
                $program = $group['program'];

                $sql_students = "SELECT e.enrollment_id, s.last_name, s.first_name FROM enrollment_form e INNER JOIN students_info s ON e.student_id = s.student_id WHERE e.grade_level = ? AND e.school_year = ?";
                $params = [$grade_level, $school_year];
                $types = "ss";

                if ($strand !== null) { 
                    $sql_students .= " AND e.strand = ?"; $types .= "s"; $params[] = $strand; 
                } else { 
                    $sql_students .= " AND e.strand IS NULL"; 
                }
                if ($program !== null) { 
                    $sql_students .= " AND e.program = ?"; $types .= "s"; $params[] = $program; 
                } else { 
                    $sql_students .= " AND e.program IS NULL"; 
                }
                $sql_students .= " ORDER BY s.last_name ASC, s.first_name ASC";

                $stmt_st = $conn->prepare($sql_students);
                $stmt_st->bind_param($types, ...$params);
                $stmt_st->execute();
                $students_group = $stmt_st->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt_st->close();

                $num_students = count($students_group);
                if ($num_students > 0) {
                    $num_sections = ceil($num_students / 35);
                    $section_letters = range('A', chr(64 + $num_sections));
                    $student_index = 0;
                    for ($sec = 0; $sec < $num_sections; $sec++) {
                        $base_name = trim(($strand ? $strand : '') . ' ' . ($program ? $program : ''));
                        $section_name = ($base_name ? $base_name : 'General') . ' ' . $section_letters[$sec];
                        for ($i = 0; $i < 35 && $student_index < $num_students; $i++, $student_index++) {
                            $enroll_id = $students_group[$student_index]['enrollment_id'];
                            $upd_stmt = $conn->prepare("UPDATE enrollment_form SET section = ? WHERE enrollment_id = ?");
                            $upd_stmt->bind_param("si", $section_name, $enroll_id);
                            $upd_stmt->execute();
                            $upd_stmt->close();
                        }
                    }
                }
            }
            $_SESSION['success'] = "Sections auto assigned successfully for $grade_level in $school_year.";
        }
        header("Location: student_profile.php?grade_level=" . urlencode($grade_level) . "&school_year=" . urlencode($school_year));
        exit();
    }

    $where_parts = [];
    if ($search !== '') {
        $search = $conn->real_escape_string($search);
        $where_parts[] = "(s.first_name LIKE '%$search%' OR s.last_name LIKE '%$search%' OR s.lrn LIKE '%$search%')";
    }
    if ($grade_level !== '') $where_parts[] = "e.grade_level = '" . $conn->real_escape_string($grade_level) . "'";
    if ($school_year !== '') $where_parts[] = "e.school_year = '" . $conn->real_escape_string($school_year) . "'";
    if ($strand_filter !== '') $where_parts[] = "COALESCE(e.strand, 'General') = '" . $conn->real_escape_string($strand_filter) . "'";

    $where = !empty($where_parts) ? "WHERE " . implode(" AND ", $where_parts) : "";

    $sql = "SELECT s.*, e.grade_level, e.school_year, e.semester, e.track, e.strand, e.program, e.section 
            FROM students_info s LEFT JOIN enrollment_form e ON s.student_id = e.student_id 
            $where ORDER BY s.last_name ASC";
    $students = $conn->query($sql);
    $total_students = $students ? $students->num_rows : 0;

    $gender_sql = "SELECT 
        SUM(CASE WHEN s.sex = 'Male' THEN 1 ELSE 0 END) AS boys,
        SUM(CASE WHEN s.sex = 'Female' THEN 1 ELSE 0 END) AS girls
    FROM students_info s LEFT JOIN enrollment_form e ON s.student_id = e.student_id $where";
    $gender_result = $conn->query($gender_sql);
    $stats = $gender_result->fetch_assoc();
    $boys  = (int)($stats['boys'] ?? 0);
    $girls = (int)($stats['girls'] ?? 0);

    $section_sql = "SELECT 
        e.section, COUNT(*) AS total,
        SUM(CASE WHEN s.sex = 'Male' THEN 1 ELSE 0 END) AS boys,
        SUM(CASE WHEN s.sex = 'Female' THEN 1 ELSE 0 END) AS girls
    FROM enrollment_form e INNER JOIN students_info s ON e.student_id = s.student_id
    $where AND e.section IS NOT NULL GROUP BY e.section ORDER BY e.section ASC";
    $section_result = $conn->query($section_sql);

    $strand_sql = "SELECT 
        COALESCE(e.strand, 'General') AS strand, 
        COALESCE(e.program, 'N/A') AS program, 
        COUNT(DISTINCT s.student_id) as enrolled_count,
        SUM(CASE WHEN s.sex = 'Male' THEN 1 ELSE 0 END) as boys,
        SUM(CASE WHEN s.sex = 'Female' THEN 1 ELSE 0 END) as girls
    FROM enrollment_form e INNER JOIN students_info s ON e.student_id = s.student_id 
    $where GROUP BY e.strand, e.program ORDER BY enrolled_count DESC, strand ASC";
    $strand_result = $conn->query($strand_sql);

    $grade_chart_sql = "SELECT e.grade_level, COUNT(DISTINCT s.student_id) as count 
    FROM enrollment_form e INNER JOIN students_info s ON e.student_id = s.student_id 
    $where AND e.grade_level IS NOT NULL GROUP BY e.grade_level ORDER BY e.grade_level ASC";
    $grade_chart_result = $conn->query($grade_chart_sql);

    $attention_sql = "SELECT s.student_id, s.first_name, s.last_name, s.lrn 
    FROM students_info s LEFT JOIN enrollment_form e ON s.student_id = e.student_id 
    WHERE (e.section IS NULL OR e.section = '' OR e.enrollment_id IS NULL) 
    " . (!empty($where_parts) ? "AND " . implode(" AND ", $where_parts) : "") . " 
    LIMIT 15";
    $attention_result = $conn->query($attention_sql);

    $grade_levels = $conn->query("SELECT DISTINCT grade_level FROM enrollment_form WHERE grade_level IS NOT NULL ORDER BY grade_level ASC");
    $school_years = $conn->query("SELECT DISTINCT school_year FROM enrollment_form WHERE school_year IS NOT NULL ORDER BY school_year DESC");

    $page_title = "Student Enrollment Dashboard";
    $is_profile_view = false;
}
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js"></script>

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

<nav class="navbar navbar-expand-lg fixed-top bg-white">
    <div class="container-fluid px-4">
        <a class="navbar-brand d-flex align-items-center gap-2" href="dashboard.php">
            <img src="../assets/img/usat.jpg" width="42" height="42" class="rounded-circle" alt="USAT Logo">
            <span class="fw-bold">USAT Admin</span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto gap-1">
                <li class="nav-item"><a class="nav-link px-4 <?= ($current_page === 'dashboard.php') ? 'active' : '' ?>" href="dashboard.php">🎓 Dashboard</a></li>
                <li class="nav-item"><a class="nav-link px-4 active" href="student_profile.php">👨‍🎓Manage Student Profiles</a></li>
                <li class="nav-item"><a class="nav-link px-4" href="reports.php">📊 Reports</a></li>
                <li class="nav-item"><a class="nav-link px-4" href="create_account.php">👤 Accounts</a></li>
            </ul>

            <div class="d-flex align-items-center gap-3">
                <button class="theme-toggle btn btn-light rounded-circle" id="themeToggle">
                    <i class="bi bi-sun-fill fs-4" id="themeIcon"></i>
                </button>
                <a href="logout.php" class="btn btn-outline-danger">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>
    </div>
</nav>

<div class="main-content">

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <?php if ($is_profile_view): ?>
        <!-- ====================== SINGLE STUDENT PROFILE VIEW ====================== -->
        <div class="profile-header mb-4 text-center">
            <img src="<?= !empty($student['photo']) ? htmlspecialchars($student['photo']) : 'https://via.placeholder.com/140' ?>" 
                 alt="Student Photo" class="rounded-circle mb-3" 
                 style="border:6px solid white; box-shadow:0 10px 30px rgba(0,0,0,0.2); width:140px; height:140px;">
            <h2 class="fw-bold"><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></h2>
            <p class="text-muted fs-5">LRN: <?= htmlspecialchars($student['lrn'] ?? 'Not Assigned') ?></p>
        </div>

        <!-- You can add more profile content here (tabs for grades, documents, etc.) later -->

    <?php else: ?>
        <!-- ====================== DASHBOARD / LIST MODE ====================== -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h1 class="display-5 fw-bold">Student Enrollment Dashboard</h1>
                <p class="text-muted">Manage and monitor student enrollment</p>
            </div>
            <div>
                <button onclick="exportToCSV()" class="btn btn-outline-primary me-2"><i class="bi bi-download"></i> Export CSV</button>
                <button onclick="window.print()" class="btn btn-outline-secondary"><i class="bi bi-printer"></i> Print / PDF</button>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="row g-3 mb-5">
            <div class="col-xl-3 col-md-6"><div class="card kpi-card shadow-sm text-center p-4"><h5 class="text-muted">Total Students</h5><h2 class="fw-bold text-primary"><?= number_format($total_students) ?></h2></div></div>
            <div class="col-xl-3 col-md-6"><div class="card kpi-card shadow-sm text-center p-4"><h5 class="text-muted">Boys</h5><h2 class="fw-bold text-primary"><?= number_format($boys) ?></h2></div></div>
            <div class="col-xl-3 col-md-6"><div class="card kpi-card shadow-sm text-center p-4"><h5 class="text-muted">Girls</h5><h2 class="fw-bold text-pink"><?= number_format($girls) ?></h2></div></div>
            <div class="col-xl-3 col-md-6"><div class="card kpi-card shadow-sm text-center p-4"><h5 class="text-muted">Unassigned</h5><h2 class="fw-bold text-warning"><?= $attention_result ? $attention_result->num_rows : 0 ?></h2></div></div>
        </div>

        <!-- Filter Bar -->
        <form class="bg-white p-4 rounded-4 shadow-sm mb-5" method="GET">
            <div class="row g-3">
                <div class="col-md-4"><input type="text" name="search" class="form-control" placeholder="Search by name or LRN..." value="<?= htmlspecialchars($search) ?>"></div>
                <div class="col-md-3">
                    <select name="grade_level" class="form-select">
                        <option value="">All Grade Levels</option>
                        <?php while($gl = $grade_levels->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($gl['grade_level']) ?>" <?= $gl['grade_level'] === $grade_level ? 'selected' : '' ?>><?= htmlspecialchars($gl['grade_level']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="school_year" class="form-select">
                        <option value="">All School Years</option>
                        <?php while($sy = $school_years->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($sy['school_year']) ?>" <?= $sy['school_year'] === $school_year ? 'selected' : '' ?>><?= htmlspecialchars($sy['school_year']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">Apply</button>
                    <a href="student_profile.php" class="btn btn-outline-secondary flex-grow-1">Reset</a>
                    <button type="submit" name="auto_assign" value="1" class="btn btn-success flex-grow-1">Auto Section</button>
                </div>
            </div>
        </form>

        <!-- Attention Needed -->
        <?php if ($attention_result && $attention_result->num_rows > 0): ?>
        <div class="card shadow-sm mb-5" style="border-left: 6px solid #ffc107;">
            <div class="card-header bg-warning text-dark fw-semibold">⚠️ Attention Needed — Unassigned Students</div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    <?php while($att = $attention_result->fetch_assoc()): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div><strong><?= htmlspecialchars($att['last_name'] . ', ' . $att['first_name']) ?></strong><br><small class="text-muted">LRN: <?= htmlspecialchars($att['lrn'] ?? '—') ?></small></div>
                            <a href="student_profile.php?id=<?= $att['student_id'] ?>" class="btn btn-sm btn-outline-primary">View Profile</a>
                        </li>
                    <?php endwhile; ?>
                </ul>
            </div>
        </div>
        <?php endif; ?>

        <!-- Sections List -->
        <?php if ($section_result && $section_result->num_rows > 0): ?>
        <div class="card shadow-sm mb-5">
            <div class="card-header fw-semibold">Sections List</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="sectionsTable">
                    <thead class="table-light">
                        <tr><th>Section</th><th>Total Students</th><th>Boys</th><th>Girls</th></tr>
                    </thead>
                    <tbody>
                    <?php while($sec = $section_result->fetch_assoc()): ?>
                        <tr>
                            <td class="fw-bold"><?= htmlspecialchars($sec['section']) ?></td>
                            <td><?= $sec['total'] ?></td>
                            <td><?= $sec['boys'] ?></td>
                            <td><?= $sec['girls'] ?></td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Strand Cards -->
        <h4 class="mb-3 fw-semibold">Enrollment by Strand & Program </h4>
        <div class="row g-4 mb-5">
            <?php if ($strand_result && $strand_result->num_rows > 0): ?>
                <?php while($row = $strand_result->fetch_assoc()):
                    $display_name = trim(($row['strand'] ?: 'General') . ' ' . ($row['program'] !== 'N/A' ? $row['program'] : ''));
                ?>
                    <div class="col-lg-4 col-md-6">
                        <div class="strand-card card shadow-sm p-4 text-center" onclick="filterByStrand('<?= htmlspecialchars($row['strand']) ?>')">
                            <div class="strand-icon"><i class="bi bi-mortarboard-fill"></i></div>
                            <h5 class="fw-bold"><?= htmlspecialchars($display_name) ?></h5>
                            <div class="count-number"><?= number_format($row['enrolled_count']) ?></div>
                            <p class="text-muted mb-3">Students Enrolled</p>
                            <div class="d-flex justify-content-center gap-3">
                                <span class="badge bg-primary"><?= $row['boys'] ?> Boys</span>
                                <span class="badge bg-danger"><?= $row['girls'] ?> Girls</span>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12 text-center py-5 text-muted">No strand data found.</div>
            <?php endif; ?>
        </div>

        <!-- Charts -->
        <h4 class="mb-3 fw-semibold">Enrollment Statistics</h4>
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-header">Enrollment by Strand</div>
                    <div class="card-body"><canvas id="strandPieChart" height="160"></canvas></div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-header">Enrollment by Grade Level</div>
                    <div class="card-body"><canvas id="gradeBarChart" height="160"></canvas></div>
                </div>
            </div>
        </div>

    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
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

// Filter by Strand
function filterByStrand(strand) {
    let url = `student_profile.php?strand=${encodeURIComponent(strand)}`;
    if ('<?= $grade_level ?>') url += `&grade_level=<?= urlencode($grade_level) ?>`;
    if ('<?= $school_year ?>') url += `&school_year=<?= urlencode($school_year) ?>`;
    window.location.href = url;
}

// Export to CSV (original)
function exportToCSV() {
    let csv = "data:text/csv;charset=utf-8,";
    document.querySelectorAll("#sectionsTable tr").forEach(row => {
        let rowData = [];
        row.querySelectorAll("th, td").forEach(cell => rowData.push('"' + cell.innerText.replace(/"/g, '""') + '"'));
        csv += rowData.join(",") + "\r\n";
    });
    const link = document.createElement("a");
    link.href = encodeURI(csv);
    link.download = "sections_enrollment.csv";
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Charts (original)
<?php if (!$is_profile_view): 
    $strand_labels = []; $strand_data = [];
    if ($strand_result) {
        $strand_result->data_seek(0);
        while ($r = $strand_result->fetch_assoc()) {
            $name = trim(($r['strand'] ?: 'General') . ' ' . ($r['program'] !== 'N/A' ? $r['program'] : ''));
            $strand_labels[] = $name;
            $strand_data[] = (int)$r['enrolled_count'];
        }
    }
    $grade_labels = []; $grade_data = [];
    if ($grade_chart_result) {
        $grade_chart_result->data_seek(0);
        while ($g = $grade_chart_result->fetch_assoc()) {
            $grade_labels[] = $g['grade_level'];
            $grade_data[] = (int)$g['count'];
        }
    }
?>
new Chart(document.getElementById('strandPieChart'), {
    type: 'pie',
    data: {
        labels: <?= json_encode($strand_labels) ?>,
        datasets: [{ data: <?= json_encode($strand_data) ?>, backgroundColor: ['#3b82f6','#1e40af','#60a5fa','#93c5fd','#2563eb'] }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});

new Chart(document.getElementById('gradeBarChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($grade_labels) ?>,
        datasets: [{ label: 'Students', data: <?= json_encode($grade_data) ?>, backgroundColor: '#3b82f6' }]
    },
    options: { responsive: true, scales: { y: { beginAtZero: true } } }
});
<?php endif; ?>
</script>
</body>
</html>