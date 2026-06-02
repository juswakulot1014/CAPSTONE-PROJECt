<?php
session_start();
include "../config/db.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

$page_title = "Enrollment Reports";

// Check if we are exporting to Word
$export_word = isset($_GET['export_word']) && $_GET['export_word'] == 1;

// Get filter (school year)
$selected_sy = $_GET['school_year'] ?? '';
$sy_filter = "";
$sy_param = "";
if (!empty($selected_sy)) {
    $sy_filter = " AND e.school_year = ?";
    $sy_param = $selected_sy;
}

// ====================== KPI QUERIES ======================
// Total students
$total_students_sql = "SELECT COUNT(DISTINCT student_id) as total FROM enrollment_form WHERE 1=1 $sy_filter";
$stmt = $conn->prepare($total_students_sql);
if (!empty($selected_sy)) $stmt->bind_param("s", $selected_sy);
$stmt->execute();
$total_students = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Total sections (distinct section names)
$total_sections_sql = "SELECT COUNT(DISTINCT section) as total FROM enrollment_form WHERE section IS NOT NULL AND TRIM(section) != '' $sy_filter";
$stmt = $conn->prepare($total_sections_sql);
if (!empty($selected_sy)) $stmt->bind_param("s", $selected_sy);
$stmt->execute();
$total_sections = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Gender breakdown
$gender_sql = "SELECT 
    SUM(CASE WHEN s.sex = 'Male' THEN 1 ELSE 0 END) as boys,
    SUM(CASE WHEN s.sex = 'Female' THEN 1 ELSE 0 END) as girls
    FROM enrollment_form e JOIN students_info s ON e.student_id = s.student_id WHERE 1=1 $sy_filter";
$stmt = $conn->prepare($gender_sql);
if (!empty($selected_sy)) $stmt->bind_param("s", $selected_sy);
$stmt->execute();
$gender = $stmt->get_result()->fetch_assoc();
$stmt->close();
$boys = $gender['boys'] ?? 0;
$girls = $gender['girls'] ?? 0;
$total_gender = $boys + $girls;
$boy_percent = $total_gender > 0 ? round(($boys / $total_gender) * 100, 1) : 0;
$girl_percent = $total_gender > 0 ? round(($girls / $total_gender) * 100, 1) : 0;

// Distinct strands count
$strand_count_sql = "SELECT COUNT(DISTINCT strand) as total FROM enrollment_form WHERE strand IS NOT NULL $sy_filter";
$stmt = $conn->prepare($strand_count_sql);
if (!empty($selected_sy)) $stmt->bind_param("s", $selected_sy);
$stmt->execute();
$total_strands = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// ====================== REPORT 1: By Grade Level ======================
$grade_sql = "
    SELECT 
        e.grade_level,
        COUNT(DISTINCT s.student_id) AS total_students,
        SUM(CASE WHEN s.sex = 'Male' THEN 1 ELSE 0 END) AS boys,
        SUM(CASE WHEN s.sex = 'Female' THEN 1 ELSE 0 END) AS girls
    FROM enrollment_form e 
    JOIN students_info s ON e.student_id = s.student_id 
    WHERE e.grade_level IS NOT NULL $sy_filter
    GROUP BY e.grade_level 
    ORDER BY e.grade_level ASC";
$grade_stmt = $conn->prepare($grade_sql);
if (!empty($selected_sy)) $grade_stmt->bind_param("s", $selected_sy);
$grade_stmt->execute();
$grade_report = $grade_stmt->get_result();
$grade_stmt->close();

// ====================== REPORT 2: By Strand & Program ======================
$strand_sql = "
    SELECT 
        COALESCE(e.strand, 'General') AS strand,
        COALESCE(e.program, 'N/A') AS program,
        COUNT(DISTINCT s.student_id) AS total_students,
        SUM(CASE WHEN s.sex = 'Male' THEN 1 ELSE 0 END) AS boys,
        SUM(CASE WHEN s.sex = 'Female' THEN 1 ELSE 0 END) AS girls
    FROM enrollment_form e 
    JOIN students_info s ON e.student_id = s.student_id 
    WHERE 1=1 $sy_filter
    GROUP BY e.strand, e.program 
    ORDER BY total_students DESC, strand ASC";
$strand_stmt = $conn->prepare($strand_sql);
if (!empty($selected_sy)) $strand_stmt->bind_param("s", $selected_sy);
$strand_stmt->execute();
$strand_report = $strand_stmt->get_result();
$strand_stmt->close();

// ====================== REPORT 3: By Section ======================
$section_sql = "
    SELECT 
        e.grade_level,
        e.section,
        COUNT(DISTINCT s.student_id) AS total_students,
        SUM(CASE WHEN s.sex = 'Male' THEN 1 ELSE 0 END) AS boys,
        SUM(CASE WHEN s.sex = 'Female' THEN 1 ELSE 0 END) AS girls
    FROM enrollment_form e 
    JOIN students_info s ON e.student_id = s.student_id 
    WHERE e.section IS NOT NULL AND TRIM(e.section) != '' $sy_filter
    GROUP BY e.grade_level, e.section
    ORDER BY e.grade_level ASC, e.section ASC";
$section_stmt = $conn->prepare($section_sql);
if (!empty($selected_sy)) $section_stmt->bind_param("s", $selected_sy);
$section_stmt->execute();
$section_report = $section_stmt->get_result();
$section_stmt->close();

// ====================== REPORT 4: By School Year (Trend) ======================
$year_sql = "
    SELECT 
        e.school_year,
        COUNT(DISTINCT s.student_id) AS total_students,
        SUM(CASE WHEN s.sex = 'Male' THEN 1 ELSE 0 END) AS boys,
        SUM(CASE WHEN s.sex = 'Female' THEN 1 ELSE 0 END) AS girls
    FROM enrollment_form e 
    JOIN students_info s ON e.student_id = s.student_id 
    WHERE e.school_year IS NOT NULL $sy_filter
    GROUP BY e.school_year
    ORDER BY e.school_year DESC";
$year_stmt = $conn->prepare($year_sql);
if (!empty($selected_sy)) $year_stmt->bind_param("s", $selected_sy);
$year_stmt->execute();
$year_report = $year_stmt->get_result();
$year_stmt->close();

// Get distinct school years for filter dropdown
$sy_list = $conn->query("SELECT DISTINCT school_year FROM enrollment_form ORDER BY school_year DESC")->fetch_all(MYSQLI_ASSOC);

// ====================== WORD EXPORT ======================
if ($export_word) {
    // Set headers to force download as .doc
    header("Content-Type: application/msword");
    header("Content-Disposition: attachment; filename=enrollment_report_" . date('Y-m-d') . ".doc");
    header("Cache-Control: no-cache, must-revalidate");
    
    // HTML for Word
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Enrollment Report</title>';
    echo '<style>body { font-family: Arial, sans-serif; margin: 2cm; } h1 { color: #1e3c72; } table { border-collapse: collapse; width: 100%; margin-bottom: 20px; } th, td { border: 1px solid #ddd; padding: 8px; text-align: left; } th { background: #f2f2f2; } .header { text-align: center; margin-bottom: 20px; } .kpi { display: flex; justify-content: space-around; margin: 20px 0; } .kpi-box { border: 1px solid #aaa; padding: 10px; width: 22%; text-align: center; }</style>';
    echo '</head><body>';
    echo '<div class="header"><h1>USAT College Enrollment Report</h1><p>Generated: ' . date('F d, Y h:i A') . '</p>';
    if (!empty($selected_sy)) echo '<p><strong>Filtered by School Year:</strong> ' . htmlspecialchars($selected_sy) . '</p>';
    echo '</div>';
    
    // KPI Section
    echo '<div class="kpi">';
    echo '<div class="kpi-box"><strong>Total Students</strong><br>' . number_format($total_students) . '</div>';
    echo '<div class="kpi-box"><strong>Total Sections</strong><br>' . number_format($total_sections) . '</div>';
    echo '<div class="kpi-box"><strong>Boys</strong><br>' . number_format($boys) . ' (' . $boy_percent . '%)</div>';
    echo '<div class="kpi-box"><strong>Girls</strong><br>' . number_format($girls) . ' (' . $girl_percent . '%)</div>';
    echo '</div>';
    
    // Report 1
    echo '<h2>Enrollment by Grade Level</h2>';
    echo '<table><thead><tr><th>Grade Level</th><th>Total Students</th><th>Boys</th><th>Girls</th><th>Ratio (M:F)</th></tr></thead><tbody>';
    $grade_report->data_seek(0);
    while ($row = $grade_report->fetch_assoc()) {
        $ratio = ($row['girls'] > 0) ? round($row['boys'] / $row['girls'], 2) : '—';
        echo '<tr><td>' . htmlspecialchars($row['grade_level']) . '</td><td>' . number_format($row['total_students']) . '</td><td>' . number_format($row['boys']) . '</td><td>' . number_format($row['girls']) . '</td><td>' . $ratio . '</td></tr>';
    }
    echo '</tbody></table>';
    
    // Report 2
    echo '<h2>Enrollment by Strand & Program</h2>';
    echo '<table><thead><tr><th>Strand</th><th>Program</th><th>Total</th><th>Boys</th><th>Girls</th></tr></thead><tbody>';
    $strand_report->data_seek(0);
    while ($row = $strand_report->fetch_assoc()) {
        echo '<tr><td>' . htmlspecialchars($row['strand']) . '</td><td>' . htmlspecialchars($row['program']) . '</td><td>' . number_format($row['total_students']) . '</td><td>' . number_format($row['boys']) . '</td><td>' . number_format($row['girls']) . '</td></tr>';
    }
    echo '</tbody></table>';
    
    // Report 3
    echo '<h2>Enrollment by Section</h2>';
    echo '<table><thead><tr><th>Grade Level</th><th>Section</th><th>Total</th><th>Boys</th><th>Girls</th></tr></thead><tbody>';
    $section_report->data_seek(0);
    while ($row = $section_report->fetch_assoc()) {
        echo '<tr><td>' . htmlspecialchars($row['grade_level']) . '</td><td>' . htmlspecialchars($row['section']) . '</td><td>' . number_format($row['total_students']) . '</td><td>' . number_format($row['boys']) . '</td><td>' . number_format($row['girls']) . '</td></tr>';
    }
    echo '</tbody></table>';
    
    // Report 4
    echo '<h2>Enrollment Trend by School Year</h2>';
    echo '<table><thead><tr><th>School Year</th><th>Total Students</th><th>Boys</th><th>Girls</th></tr></thead><tbody>';
    $year_report->data_seek(0);
    while ($row = $year_report->fetch_assoc()) {
        echo '<tr><td>' . htmlspecialchars($row['school_year']) . '</td><td>' . number_format($row['total_students']) . '</td><td>' . number_format($row['boys']) . '</td><td>' . number_format($row['girls']) . '</td></tr>';
    }
    echo '</tbody></table>';
    
    echo '<p><em>End of Report – USAT College Enrollment System</em></p>';
    echo '</body></html>';
    exit;
}

// ====================== NORMAL PAGE RENDERING ======================
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
        .navbar { background-color: var(--bg-card) !important; box-shadow: 0 4px 15px var(--shadow-color); border-bottom: 1px solid var(--border-color); }
        .main-content { padding: 2rem; min-height: calc(100vh - 76px); padding-top: 110px; }
        .card, .kpi-card { background-color: var(--bg-card); border: none; border-radius: 18px; box-shadow: 0 6px 25px var(--shadow-color); color: var(--text-primary); transition: all 0.3s ease; }
        .kpi-card:hover { transform: translateY(-8px); box-shadow: 0 15px 35px var(--hover-shadow) !important; }
        .text-muted { color: var(--text-muted) !important; }
        .badge { font-weight: 500; }
        .table { color: var(--text-primary); }
        .table-light { background-color: var(--bg-card) !important; color: var(--text-primary); }
        .btn-export { border-radius: 40px; padding: 0.5rem 1.2rem; font-weight: 500; }
        .report-card { height: 100%; }
        .chart-container { max-height: 300px; margin-top: 1rem; }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg fixed-top">
    <div class="container-fluid px-4">
        <a class="navbar-brand d-flex align-items-center gap-2" href="dashboard.php">
            <img src="../assets/img/usat.jpg" width="42" height="42" class="rounded-circle" alt="USAT Logo">
            <span class="fw-bold">USAT Admin</span>
        </a>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto gap-1">
                <li class="nav-item"><a class="nav-link" href="dashboard.php">🎓 Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="student_profile.php">👨‍🎓Manage Student Profiles</a></li>
                <li class="nav-item"><a class="nav-link active" href="reports.php">📊 Reports</a></li>
                <li class="nav-item"><a class="nav-link" href="create_account.php">👤 Accounts</a></li>
            </ul>
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-light rounded-circle" id="themeToggle"><i class="bi bi-sun-fill fs-4" id="themeIcon"></i></button>
                <a href="logout.php" class="btn btn-outline-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
            </div>
        </div>
    </div>
</nav>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h1 class="display-5 fw-bold">📊 Enrollment Reports</h1>
            <p class="text-muted">Comprehensive enrollment statistics and summaries</p>
        </div>
        <div class="d-flex gap-2">
            <a href="?export_word=1<?= !empty($selected_sy) ? '&school_year='.urlencode($selected_sy) : '' ?>" class="btn btn-success">
                <i class="bi bi-file-word"></i> Export as Word
            </a>
            <button onclick="window.print()" class="btn btn-secondary">
                <i class="bi bi-printer"></i> Print / PDF
            </button>
        </div>
    </div>

    <!-- Filter by School Year -->
    <div class="card mb-4 p-3">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold"><i class="bi bi-calendar3"></i> Filter by School Year</label>
                <select name="school_year" class="form-select">
                    <option value="">All Years</option>
                    <?php foreach ($sy_list as $sy): ?>
                        <option value="<?= htmlspecialchars($sy['school_year']) ?>" <?= $selected_sy == $sy['school_year'] ? 'selected' : '' ?>><?= htmlspecialchars($sy['school_year']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel"></i> Apply</button>
            </div>
            <div class="col-md-2">
                <a href="reports.php" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
        </form>
    </div>

    <!-- KPI Cards Row -->
    <div class="row g-4 mb-4">
        <div class="col-md-3"><div class="card kpi-card text-center p-3"><h3><i class="bi bi-people"></i> <?= number_format($total_students) ?></h3><p class="mb-0">Total Students</p></div></div>
        <div class="col-md-3"><div class="card kpi-card text-center p-3"><h3><i class="bi bi-grid"></i> <?= number_format($total_sections) ?></h3><p class="mb-0">Sections</p></div></div>
        <div class="col-md-3"><div class="card kpi-card text-center p-3"><h3><i class="bi bi-gender-male text-primary"></i> <?= number_format($boys) ?> | <i class="bi bi-gender-female text-danger"></i> <?= number_format($girls) ?></h3><p class="mb-0">Boys / Girls (<?= $boy_percent ?>% / <?= $girl_percent ?>%)</p></div></div>
        <div class="col-md-3"><div class="card kpi-card text-center p-3"><h3><i class="bi bi-tags"></i> <?= number_format($total_strands) ?></h3><p class="mb-0">Strands Offered</p></div></div>
    </div>

    <div class="row g-4">
        <!-- Report 1: Grade Level -->
        <div class="col-lg-6">
            <div class="card report-card h-100">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-bar-chart-line"></i> By Grade Level</h5>
                    <button onclick="exportTableToCSV('gradeReportTable')" class="btn btn-sm btn-light">CSV</button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="gradeReportTable">
                            <thead class="table-light"><tr><th>Grade Level</th><th class="text-center">Total</th><th class="text-center">Boys</th><th class="text-center">Girls</th><th class="text-center">M:F Ratio</th></tr></thead>
                            <tbody>
                            <?php if ($grade_report->num_rows > 0): while ($row = $grade_report->fetch_assoc()): $ratio = ($row['girls'] > 0) ? round($row['boys'] / $row['girls'], 2) : '—'; ?>
                                <tr><td><strong><?= htmlspecialchars($row['grade_level']) ?></strong></td><td class="text-center fw-bold"><?= number_format($row['total_students']) ?></td><td class="text-center"><?= number_format($row['boys']) ?></td><td class="text-center"><?= number_format($row['girls']) ?></td><td class="text-center"><?= $ratio ?> : 1</td></tr>
                            <?php endwhile; else: ?><tr><td colspan="5" class="text-center">No data</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Report 2: Strand & Program -->
        <div class="col-lg-6">
            <div class="card report-card h-100">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-tags"></i> By Strand & Program</h5>
                    <button onclick="exportTableToCSV('strandReportTable')" class="btn btn-sm btn-light">CSV</button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="strandReportTable">
                            <thead class="table-light"><tr><th>Strand</th><th>Program</th><th class="text-center">Total</th><th class="text-center">Boys</th><th class="text-center">Girls</th></tr></thead>
                            <tbody>
                            <?php if ($strand_report->num_rows > 0): while ($row = $strand_report->fetch_assoc()): ?>
                                <tr><td><?= htmlspecialchars($row['strand']) ?></td><td><?= htmlspecialchars($row['program']) ?></td><td class="text-center fw-bold"><?= number_format($row['total_students']) ?></td><td class="text-center"><?= number_format($row['boys']) ?></td><td class="text-center"><?= number_format($row['girls']) ?></td></tr>
                            <?php endwhile; else: ?><tr><td colspan="5" class="text-center">No data</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Report 3: By Section -->
        <div class="col-12">
            <div class="card report-card">
                <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-people-fill"></i> Enrollment by Section</h5>
                    <button onclick="exportTableToCSV('sectionReportTable')" class="btn btn-sm btn-light">CSV</button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="sectionReportTable">
                            <thead class="table-light"><tr><th>Grade Level</th><th>Section</th><th class="text-center">Total</th><th class="text-center">Boys</th><th class="text-center">Girls</th></tr></thead>
                            <tbody>
                            <?php if ($section_report->num_rows > 0): while ($row = $section_report->fetch_assoc()): ?>
                                <tr><td><?= htmlspecialchars($row['grade_level']) ?></td><td><?= htmlspecialchars($row['section']) ?></td><td class="text-center fw-bold"><?= number_format($row['total_students']) ?></td><td class="text-center"><?= number_format($row['boys']) ?></td><td class="text-center"><?= number_format($row['girls']) ?></td></tr>
                            <?php endwhile; else: ?><tr><td colspan="5" class="text-center">No data</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Report 4: Trend by School Year + Chart -->
        <div class="col-12">
            <div class="card report-card">
                <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-graph-up"></i> Enrollment Trend by School Year</h5>
                    <button onclick="exportTableToCSV('yearReportTable')" class="btn btn-sm btn-light">CSV</button>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="table-responsive">
                                <table class="table table-hover" id="yearReportTable">
                                    <thead class="table-light"><tr><th>School Year</th><th class="text-center">Total Students</th><th class="text-center">Boys</th><th class="text-center">Girls</th></tr></thead>
                                    <tbody>
                                    <?php 
                                    $years_data = [];
                                    if ($year_report->num_rows > 0): 
                                        while ($row = $year_report->fetch_assoc()): 
                                            $years_data[] = $row;
                                    ?>
                                        <tr><td><?= htmlspecialchars($row['school_year']) ?></td><td class="text-center fw-bold"><?= number_format($row['total_students']) ?></td><td class="text-center"><?= number_format($row['boys']) ?></td><td class="text-center"><?= number_format($row['girls']) ?></td></tr>
                                    <?php endwhile; else: ?><tr><td colspan="4" class="text-center">No data</td></tr><?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <canvas id="trendChart" style="max-height: 300px;"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Theme toggle
const toggleBtn = document.getElementById('themeToggle');
const themeIcon = document.getElementById('themeIcon');
const html = document.documentElement;
function setTheme(theme) {
    html.setAttribute('data-bs-theme', theme);
    if (theme === 'dark') themeIcon.classList.replace('bi-sun-fill', 'bi-moon-fill');
    else themeIcon.classList.replace('bi-moon-fill', 'bi-sun-fill');
    localStorage.setItem('theme', theme);
}
const savedTheme = localStorage.getItem('theme') || 'light';
setTheme(savedTheme);
toggleBtn.addEventListener('click', () => {
    const current = html.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
    setTheme(current);
});

// Export table to CSV
function exportTableToCSV(tableId) {
    let csv = [];
    const rows = document.querySelectorAll(`#${tableId} tr`);
    rows.forEach(row => {
        let rowData = [];
        row.querySelectorAll('th, td').forEach(cell => {
            rowData.push('"' + cell.innerText.replace(/"/g, '""') + '"');
        });
        csv.push(rowData.join(","));
    });
    const csvContent = "data:text/csv;charset=utf-8," + csv.join("\n");
    const link = document.createElement("a");
    link.href = encodeURI(csvContent);
    let filename = tableId === "gradeReportTable" ? "grade_level_report.csv" : (tableId === "strandReportTable" ? "strand_program_report.csv" : (tableId === "sectionReportTable" ? "sections_report.csv" : "yearly_trend_report.csv"));
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Chart.js trend
<?php if (!empty($years_data)): ?>
const ctx = document.getElementById('trendChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: [<?php foreach($years_data as $y) echo '"' . addslashes($y['school_year']) . '",'; ?>],
        datasets: [{
            label: 'Total Students',
            data: [<?php foreach($years_data as $y) echo $y['total_students'] . ','; ?>],
            borderColor: '#1e3c72',
            backgroundColor: 'rgba(30,60,114,0.1)',
            fill: true,
            tension: 0.3
        }]
    },
    options: { responsive: true, maintainAspectRatio: true }
});
<?php endif; ?>
</script>
</body>
</html>