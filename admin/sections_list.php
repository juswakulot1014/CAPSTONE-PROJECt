<?php
session_start();
include "../config/db.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Check if we are exporting to Word
$export_word = isset($_GET['export_word']) && $_GET['export_word'] == 1;

// Get filters
$school_year = trim($_GET['school_year'] ?? '');
$grade_level = trim($_GET['grade_level'] ?? '');
$program     = trim($_GET['program'] ?? '');
$section     = trim($_GET['section'] ?? '');

$show_student_list = !empty($section);

// ====================== SUMMARY QUERY (Sections List) ======================
if (!$show_student_list) {
    $where = "WHERE 1=1";
    $params = [];
    $types = "";

    if ($school_year) { $where .= " AND e.school_year = ?"; $params[] = $school_year; $types .= "s"; }
    if ($grade_level) { $where .= " AND e.grade_level = ?"; $params[] = $grade_level; $types .= "s"; }
    if ($program)     { $where .= " AND e.program = ?";     $params[] = $program;     $types .= "s"; }

    $sql = "
        SELECT 
            e.section, e.program, e.grade_level, e.school_year,
            COUNT(*) as total,
            SUM(CASE WHEN s.sex = 'Male' THEN 1 ELSE 0 END) as boys,
            SUM(CASE WHEN s.sex = 'Female' THEN 1 ELSE 0 END) as girls
        FROM enrollment_form e
        JOIN students_info s ON e.student_id = s.student_id
        $where
        GROUP BY e.section, e.program, e.grade_level, e.school_year
        ORDER BY e.grade_level ASC, e.program ASC, e.section ASC
    ";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $section_result = $stmt->get_result();
}

// ====================== STUDENT LIST QUERY ======================
else {
    $where = "WHERE TRIM(e.section) LIKE ?";
    $params = ["%" . $section . "%"];
    $types = "s";

    if ($school_year) { $where .= " AND e.school_year = ?"; $params[] = $school_year; $types .= "s"; }
    if ($grade_level) { $where .= " AND e.grade_level = ?"; $params[] = $grade_level; $types .= "s"; }
    if ($program)     { $where .= " AND e.program = ?";     $params[] = $program;     $types .= "s"; }

    $sql = "
        SELECT 
            TRIM(e.section) as section, e.program, e.grade_level, e.school_year,
            s.student_id, s.lrn, s.last_name, s.first_name, s.middle_name, 
            s.ext_name, s.sex, s.photo
        FROM enrollment_form e
        JOIN students_info s ON e.student_id = s.student_id
        $where
        ORDER BY s.last_name ASC, s.first_name ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $students_result = $stmt->get_result();
}

// Filter options
$years    = $conn->query("SELECT DISTINCT school_year FROM enrollment_form ORDER BY school_year DESC")->fetch_all(MYSQLI_ASSOC);
$grades   = $conn->query("SELECT DISTINCT grade_level FROM enrollment_form ORDER BY grade_level ASC")->fetch_all(MYSQLI_ASSOC);
$programs = $conn->query("SELECT DISTINCT program FROM enrollment_form WHERE program IS NOT NULL ORDER BY program ASC")->fetch_all(MYSQLI_ASSOC);

// ====================== WORD EXPORT ======================
if ($export_word) {
    header("Content-Type: application/msword");
    header("Content-Disposition: attachment; filename=sections_report_" . date('Y-m-d') . ".doc");
    header("Cache-Control: no-cache, must-revalidate");
    
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Sections Report</title>';
    echo '<style>body { font-family: Arial, sans-serif; margin: 2cm; } h1 { color: #1e3c72; } table { border-collapse: collapse; width: 100%; margin-bottom: 20px; } th, td { border: 1px solid #ddd; padding: 8px; text-align: left; } th { background: #f2f2f2; } .header { text-align: center; margin-bottom: 20px; } .meta { margin: 20px 0; }</style>';
    echo '</head><body>';
    echo '<div class="header"><h1>USAT College - Sections & Students Report</h1><p>Generated: ' . date('F d, Y h:i A') . '</p>';
    if ($school_year) echo '<p><strong>School Year:</strong> ' . htmlspecialchars($school_year) . '</p>';
    if ($grade_level) echo '<p><strong>Grade Level:</strong> ' . htmlspecialchars($grade_level) . '</p>';
    if ($program) echo '<p><strong>Program:</strong> ' . htmlspecialchars($program) . '</p>';
    if ($section) echo '<p><strong>Section:</strong> ' . htmlspecialchars($section) . '</p>';
    echo '</div>';
    
    if ($show_student_list && $students_result && $students_result->num_rows > 0) {
        $first = $students_result->fetch_assoc();
        $students_result->data_seek(0);
        echo '<h2>Student List: ' . htmlspecialchars($first['grade_level'] . ' - ' . $first['section']) . '</h2>';
        echo '<p><strong>Program:</strong> ' . htmlspecialchars($first['program'] ?? 'N/A') . ' | <strong>School Year:</strong> ' . htmlspecialchars($first['school_year']) . '</p>';
        echo '<table><thead><tr><th>LRN</th><th>Full Name</th><th>Sex</th></tr></thead><tbody>';
        while ($row = $students_result->fetch_assoc()) {
            $full_name = trim($row['last_name'] . ', ' . $row['first_name'] . ' ' . ($row['middle_name'] ?? '') . ' ' . ($row['ext_name'] ?? ''));
            echo '<tr><td>' . htmlspecialchars($row['lrn'] ?? '—') . '</td><td>' . htmlspecialchars($full_name) . '</td><td>' . htmlspecialchars($row['sex']) . '</td></tr>';
        }
        echo '</tbody></table>';
    } elseif ($section_result && $section_result->num_rows > 0) {
        echo '<h2>Sections Summary</h2>';
        echo '<table><thead><tr><th>Section</th><th>Grade Level</th><th>Program</th><th>Total</th><th>Boys</th><th>Girls</th></tr></thead><tbody>';
        while ($sec = $section_result->fetch_assoc()) {
            echo '<tr><td>' . htmlspecialchars($sec['section']) . '</td><td>' . htmlspecialchars($sec['grade_level']) . '</td><td>' . htmlspecialchars($sec['program']) . '</td><td>' . $sec['total'] . '</td><td>' . $sec['boys'] . '</td><td>' . $sec['girls'] . '</td></tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p>No data found for the selected filters.</p>';
    }
    echo '<p><em>End of Report – USAT College Enrollment System</em></p>';
    echo '</body></html>';
    exit;
}

?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sections & Students • USAT Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: linear-gradient(135deg, #f5f7fc 0%, #eef2f9 100%); min-height: 100vh; padding-bottom: 2rem; transition: background 0.3s ease; }
        [data-bs-theme="dark"] body { background: #1a1d23; }
        .card { border: none; border-radius: 24px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05), 0 8px 10px -6px rgba(0,0,0,0.02); transition: all 0.2s; overflow: hidden; }
        [data-bs-theme="dark"] .card { background-color: #2b2f38; color: #e9ecef; box-shadow: 0 6px 25px rgba(0,0,0,0.4); }
        .card-header { border: none; padding: 1.2rem 1.5rem; font-weight: 600; letter-spacing: -0.3px; }
        .bg-gradient-primary { background: linear-gradient(135deg, #1e3c72 0%, #2b4c8c 100%); }
        .bg-gradient-success { background: linear-gradient(135deg, #059669 0%, #047857 100%); }
        .btn-export { border-radius: 40px; padding: 0.5rem 1.2rem; font-weight: 500; transition: all 0.2s; }
        .filter-card .form-control, .filter-card .form-select { border-radius: 14px; padding: 0.6rem 1rem; border: 1px solid #e2e8f0; background-color: #fff; }
        [data-bs-theme="dark"] .filter-card .form-control, 
        [data-bs-theme="dark"] .filter-card .form-select { background-color: #3b3f48; border-color: #495057; color: #e9ecef; }
        .table thead th { background: #f8fafc; border-bottom: 2px solid #e2e8f0; color: #1e293b; font-weight: 600; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; }
        [data-bs-theme="dark"] .table thead th { background: #2b2f38; color: #e9ecef; border-bottom-color: #495057; }
        .table tbody tr { transition: background 0.2s; cursor: pointer; }
        .table tbody tr:hover { background-color: #f1f5ff !important; }
        [data-bs-theme="dark"] .table tbody tr:hover { background-color: #3a3f4a !important; }
        .student-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .clickable { cursor: pointer; }
        .theme-toggle { border-radius: 50%; width: 42px; height: 42px; display: inline-flex; align-items: center; justify-content: center; background: #f1f5f9; border: none; transition: 0.2s; }
        [data-bs-theme="dark"] .theme-toggle { background: #3b3f48; color: #ffc107; }
        @media (max-width: 768px) {
            .card-header { font-size: 1rem; padding: 1rem; }
            .btn-export { padding: 0.3rem 0.8rem; font-size: 0.8rem; }
        }
    </style>
</head>
<body>

<div class="container py-4 py-lg-5">
    <!-- Page Header with Dark Mode Toggle -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h2 class="fw-bold text-dark mb-1"><i class="bi bi-people-fill text-primary me-2"></i>Sections & Students</h2>
            <p class="text-muted mb-0">Manage class sections and view student lists</p>
        </div>
        <div class="d-flex gap-2">
            <?php
            // Build current URL parameters for Word export
            $word_params = [];
            if ($school_year) $word_params['school_year'] = $school_year;
            if ($grade_level) $word_params['grade_level'] = $grade_level;
            if ($program) $word_params['program'] = $program;
            if ($section) $word_params['section'] = $section;
            $word_url = 'sections_list.php?export_word=1' . (count($word_params) > 0 ? '&' . http_build_query($word_params) : '');
            ?>
            <a href="<?= $word_url ?>" class="btn btn-success rounded-pill px-3">
                <i class="bi bi-file-word"></i> Export Word
            </a>
            <button class="theme-toggle" id="themeToggle">
                <i class="bi bi-sun-fill fs-5" id="themeIcon"></i>
            </button>
            <a href="student_profile.php" class="btn btn-outline-secondary rounded-pill px-4">
                <i class="bi bi-arrow-left me-2"></i> Back
            </a>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card filter-card mb-4 shadow-sm">
        <div class="card-body p-4">
            <form method="GET" id="filterForm" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-semibold"><i class="bi bi-calendar3 me-1"></i> School Year</label>
                    <select name="school_year" class="form-select">
                        <option value="">All Years</option>
                        <?php foreach ($years as $y): ?>
                            <option value="<?= htmlspecialchars($y['school_year']) ?>" <?= $school_year === $y['school_year'] ? 'selected' : '' ?>><?= htmlspecialchars($y['school_year']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold"><i class="bi bi-book me-1"></i> Grade Level</label>
                    <select name="grade_level" class="form-select">
                        <option value="">All Grades</option>
                        <?php foreach ($grades as $g): ?>
                            <option value="<?= htmlspecialchars($g['grade_level']) ?>" <?= $grade_level === $g['grade_level'] ? 'selected' : '' ?>><?= htmlspecialchars($g['grade_level']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold"><i class="bi bi-mortarboard me-1"></i> Program</label>
                    <select name="program" class="form-select">
                        <option value="">All Programs</option>
                        <?php foreach ($programs as $p): ?>
                            <option value="<?= htmlspecialchars($p['program']) ?>" <?= $program === $p['program'] ? 'selected' : '' ?>><?= htmlspecialchars($p['program']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold"><i class="bi bi-tag me-1"></i> Section Name</label>
                    <input type="text" name="section" class="form-control" value="<?= htmlspecialchars($section) ?>" placeholder="e.g., A, CSS A, 12-STEM">
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary w-100 rounded-pill" id="filterBtn"><i class="bi bi-search"></i> Filter</button>
                </div>
                <div class="col-md-1">
                    <a href="sections_list.php" class="btn btn-outline-secondary w-100 rounded-pill">Reset</a>
                </div>
            </form>
            <div class="mt-2 text-muted small"><i class="bi bi-info-circle"></i> Tip: Type part of a section name (e.g., "A", "STEM") to find matching classes.</div>
        </div>
    </div>

    <?php if ($show_student_list): ?>
        <?php if ($students_result && $students_result->num_rows > 0): 
            $first = $students_result->fetch_assoc();
            $students_result->data_seek(0);
            $section_title = htmlspecialchars(trim(($first['grade_level'] ?? '') . ' - ' . $first['section']));
            $section_details = [
                'section' => htmlspecialchars($first['section'] ?? ''),
                'grade_level' => htmlspecialchars($first['grade_level'] ?? ''),
                'program' => htmlspecialchars($first['program'] ?? ''),
                'school_year' => htmlspecialchars($first['school_year'] ?? '')
            ];
        ?>
            <div class="card">
                <div class="card-header bg-gradient-primary text-white d-flex flex-wrap justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-0"><i class="bi bi-people me-2"></i><?= $section_title ?></h4>
                        <small><i class="bi bi-tag"></i> <?= htmlspecialchars($first['program'] ?? 'No Program') ?> • <?= htmlspecialchars($first['school_year']) ?></small>
                    </div>
                    <div class="mt-2 mt-sm-0">
                        <a href="sections_list.php?school_year=<?= urlencode($school_year) ?>&grade_level=<?= urlencode($grade_level) ?>&program=<?= urlencode($program) ?>" class="btn btn-light btn-sm rounded-pill me-2"><i class="bi bi-arrow-left"></i> Back to Sections</a>
                        <button class="btn btn-light btn-sm rounded-pill" id="exportExcelBtn" data-section='<?= json_encode($section_details) ?>'><i class="bi bi-file-earmark-excel"></i> Excel</button>
                        <button class="btn btn-light btn-sm rounded-pill" id="printBtn"><i class="bi bi-printer"></i> Print</button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table id="studentTable" class="table table-hover mb-0">
                            <thead class="table-light"><tr><th>LRN</th><th>Full Name</th><th>Sex</th><th>Photo</th></tr></thead>
                            <tbody>
                            <?php while($row = $students_result->fetch_assoc()): 
                                $full_name = trim($row['last_name'] . ', ' . $row['first_name'] . ' ' . ($row['middle_name'] ?? '') . ' ' . ($row['ext_name'] ?? ''));
                                $photo_path = !empty($row['photo']) ? "../uploads/students/" . htmlspecialchars($row['photo']) : '';
                            ?>
                                <tr class="clickable" onclick="window.location='view_student.php?id=<?= $row['student_id'] ?>'">
                                    <td><?= htmlspecialchars($row['lrn'] ?? '—') ?></td>
                                    <td class="fw-medium"><?= htmlspecialchars($full_name) ?></td>
                                    <td><span class="badge <?= $row['sex'] === 'Male' ? 'bg-primary' : 'bg-danger' ?> rounded-pill px-3"><?= htmlspecialchars($row['sex'] ?? '—') ?></span></td>
                                    <td><?php if (!empty($photo_path) && file_exists($photo_path)): ?><img src="<?= $photo_path ?>" class="student-avatar" alt="Photo"><?php else: ?><i class="bi bi-person-circle fs-3 text-secondary"></i><?php endif; ?></td>
                                </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-transparent text-muted small py-2 px-3"><i class="bi bi-database"></i> Total students: <?= $students_result->num_rows ?></div>
            </div>
        <?php else: ?>
            <div class="card"><div class="card-body text-center py-5"><i class="bi bi-search fs-1 text-muted"></i><h5 class="mt-3">No students found</h5><p class="text-muted">Section "<strong><?= htmlspecialchars($section) ?></strong>" has no enrolled students with the current filters.</p><a href="sections_list.php" class="btn btn-primary rounded-pill px-4">Reset Filters</a></div></div>
        <?php endif; ?>
    <?php else: ?>
        <?php if ($section_result && $section_result->num_rows > 0): ?>
            <div class="card">
                <div class="card-header bg-gradient-success text-white d-flex flex-wrap justify-content-between align-items-center">
                    <div><i class="bi bi-grid-3x3-gap-fill me-2"></i> Available Sections (<?= $section_result->num_rows ?>)</div>
                    <div>
                        <button class="btn btn-light btn-sm rounded-pill" id="exportSectionsExcel"><i class="bi bi-file-earmark-excel"></i> Excel</button>
                        <button class="btn btn-light btn-sm rounded-pill" id="printSectionsBtn"><i class="bi bi-printer"></i> Print</button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table id="sectionsTable" class="table table-hover mb-0">
                            <thead class="table-light"><tr><th>Section</th><th>Grade Level</th><th>Program</th><th class="text-center">Total</th><th class="text-center"><i class="bi bi-gender-male text-primary"></i> Boys</th><th class="text-center"><i class="bi bi-gender-female text-danger"></i> Girls</th><th class="text-center">Action</th></tr></thead>
                            <tbody>
                            <?php while($sec = $section_result->fetch_assoc()): ?>
                                <tr>
                                    <td class="fw-bold"><?= htmlspecialchars($sec['section']) ?></td>
                                    <td class="clickable" onclick="filterBy('grade_level', '<?= htmlspecialchars($sec['grade_level'] ?? '') ?>')"><?= htmlspecialchars($sec['grade_level'] ?? '—') ?> <i class="bi bi-funnel text-muted small"></i></td>
                                    <td class="clickable" onclick="filterBy('program', '<?= htmlspecialchars($sec['program'] ?? '') ?>')"><?= htmlspecialchars($sec['program'] ?? 'N/A') ?> <i class="bi bi-funnel text-muted small"></i></td>
                                    <td class="text-center fw-semibold"><span class="badge bg-secondary rounded-pill px-3"><?= number_format($sec['total']) ?></span></td>
                                    <td class="text-center text-primary"><?= $sec['boys'] ?? 0 ?></td>
                                    <td class="text-center text-danger"><?= $sec['girls'] ?? 0 ?></td>
                                    <td class="text-center"><a href="sections_list.php?section=<?= urlencode($sec['section']) ?>&school_year=<?= urlencode($school_year) ?>&grade_level=<?= urlencode($grade_level) ?>&program=<?= urlencode($program) ?>" class="btn btn-primary btn-sm rounded-pill px-3"><i class="bi bi-eye"></i> View</a></td>
                                </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="card"><div class="card-body text-center py-5"><i class="bi bi-table fs-1 text-muted"></i><h5 class="mt-3">No sections found</h5><p class="text-muted">Try adjusting your filters or resetting them.</p><a href="sections_list.php" class="btn btn-primary rounded-pill px-4">Reset Filters</a></div></div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Dark Mode Toggle
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
if (toggleBtn) toggleBtn.addEventListener('click', () => {
    const current = html.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
    setTheme(current);
});

// Helper: filter redirect
function filterBy(type, value) {
    if (!value) return;
    let url = new URL(window.location.href);
    url.searchParams.set(type, value);
    if (type !== 'section') url.searchParams.delete('section');
    window.location.href = url.toString();
}

// Show loading spinner on form submit
document.getElementById('filterForm')?.addEventListener('submit', function(e) {
    const btn = document.getElementById('filterBtn');
    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Searching...'; }
});

// Export sections table to CSV
function exportSectionsToCSV() {
    const table = document.getElementById('sectionsTable');
    if (!table) return;
    let csv = [];
    const headers = [];
    table.querySelectorAll('thead th').forEach(th => {
        let text = th.innerText.trim().replace(/[▼▲]/g, '').replace(/[^\w\s\/-]/g, '').trim();
        if (text) headers.push(text);
    });
    csv.push(headers.join(','));
    table.querySelectorAll('tbody tr').forEach(tr => {
        const row = [];
        tr.querySelectorAll('td').forEach(td => {
            let data = td.innerText.trim();
            if (td.querySelector('.btn')) data = '';
            data = data.replace(/"/g, '""');
            row.push(`"${data}"`);
        });
        if (row.length) csv.push(row.join(','));
    });
    downloadCSV(csv.join('\n'), 'sections_list.csv');
}

// Export student list with section details
document.getElementById('exportExcelBtn')?.addEventListener('click', function() {
    const sectionData = this.getAttribute('data-section');
    let sectionInfo = { section: '', grade_level: '', program: '', school_year: '' };
    if (sectionData) { try { sectionInfo = JSON.parse(sectionData); } catch(e) {} }
    const table = document.getElementById('studentTable');
    if (!table) return;
    let csvRows = [];
    csvRows.push(`"Section:","${sectionInfo.section}"`);
    csvRows.push(`"Grade Level:","${sectionInfo.grade_level}"`);
    csvRows.push(`"Program:","${sectionInfo.program}"`);
    csvRows.push(`"School Year:","${sectionInfo.school_year}"`);
    csvRows.push([]);
    const headers = ['LRN', 'Full Name', 'Sex'];
    csvRows.push(headers.map(h => `"${h.replace(/"/g, '""')}"`).join(','));
    table.querySelectorAll('tbody tr').forEach(tr => {
        const cols = tr.querySelectorAll('td');
        if (cols.length >= 3) {
            const lrn = cols[0].innerText.trim().replace(/"/g, '""');
            const name = cols[1].innerText.trim().replace(/"/g, '""');
            const sex = cols[2].innerText.trim().replace(/"/g, '""');
            csvRows.push(`"${lrn}","${name}","${sex}"`);
        }
    });
    downloadCSV(csvRows.join('\n'), 'student_list.csv');
});

function downloadCSV(content, filename) {
    const blob = new Blob(["\uFEFF" + content], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.href = url;
    link.setAttribute('download', filename);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}

document.getElementById('exportSectionsExcel')?.addEventListener('click', exportSectionsToCSV);

// Print functions
document.getElementById('printBtn')?.addEventListener('click', function() {
    const printContent = document.querySelector('.card').cloneNode(true);
    printContent.querySelectorAll('.btn, .card-footer').forEach(el => el.remove());
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`<html><head><title>Student List</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><style>body { padding: 2rem; } .btn, .card-footer { display: none; }</style></head><body>${printContent.outerHTML}</body></html>`);
    printWindow.document.close();
    printWindow.print();
});
document.getElementById('printSectionsBtn')?.addEventListener('click', function() {
    const printContent = document.querySelector('.card').cloneNode(true);
    printContent.querySelectorAll('.btn, .card-footer').forEach(el => el.remove());
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`<html><head><title>Sections List</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><style>body { padding: 2rem; } .btn, .card-footer { display: none; }</style></head><body>${printContent.outerHTML}</body></html>`);
    printWindow.document.close();
    printWindow.print();
});
</script>
</body>
</html>