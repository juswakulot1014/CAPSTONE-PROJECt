<?php
session_start();
include __DIR__ . "/../config/db.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid student ID.";
    header("Location: student_profile.php");
    exit();
}

$student_id = (int)$_GET['id'];

// Fetch student data
$sql = "
    SELECT s.*, a.*, p.*, e.*
    FROM students_info s
    LEFT JOIN addresses a ON s.student_id = a.student_id
    LEFT JOIN parents_info p ON s.student_id = p.student_id
    LEFT JOIN enrollment_form e ON s.student_id = e.student_id
    WHERE s.student_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

if (!$student) {
    $_SESSION['error'] = "Student not found.";
    header("Location: student_profile.php");
    exit();
}

// Fetch grades
$grades_result = $conn->query("
    SELECT semester, subject_code, subject_name, grade, remarks 
    FROM student_grades 
    WHERE student_id = $student_id 
    ORDER BY semester, subject_code ASC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SF10-SHS - <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white; margin: 0; padding: 0; }
            .sf10-container { 
                width: 210mm; 
                min-height: 297mm; 
                margin: 0 auto; 
                padding: 15mm; 
                box-shadow: none; 
            }
        }
        .sf10-container {
            max-width: 1000px;
            margin: 20px auto;
            background: white;
            border: 2px solid #000;
            padding: 25px;
            font-family: 'Times New Roman', serif;
            font-size: 14px;
            color: black;
            line-height: 1.5;
        }
        .official-header {
            text-align: center;
            margin-bottom: 20px;
        }
        .logo {
            max-width: 140px;
            height: auto;
            margin-bottom: 10px;
        }
        .title {
            font-size: 18px;
            font-weight: bold;
            margin: 8px 0;
        }
        .section-title {
            background: #d0d0d0;
            font-weight: bold;
            padding: 6px 10px;
            margin: 15px 0 8px;
            border: 1px solid #000;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        th, td {
            border: 1px solid #000;
            padding: 6px 8px;
            vertical-align: top;
        }
        th {
            background: #e0e0e0;
            font-weight: bold;
            text-align: center;
        }
        .signature {
            margin-top: 50px;
            text-align: center;
        }
        .signature-line {
            border-top: 1px solid #000;
            width: 280px;
            margin: 8px auto 0;
        }
    </style>
</head>
<body>

<div class="sf10-container">
    <!-- Official Header with Logo -->
    <div class="official-header">
        <img src="../assets/img/usat.jpg" alt="USAT College Logo" class="logo"> <!-- ← Change path if needed -->
        <div>
            <strong>Republic of the Philippines</strong><br>
            <strong>Department of Education</strong><br>
            <div class="title">LEARNER'S PERMANENT ACADEMIC RECORD (SF 10 - SHS)</div>
            <small>(Formerly Form 137 - SHS)</small>
        </div>
        <p class="mt-2 mb-0"><strong>USAT College Sagay City, Inc.</strong><br>
        Sagay City, Negros Occidental</p>
    </div>

    <!-- Learner Information -->
    <div class="section-title">LEARNER'S PERSONAL INFORMATION</div>
    <table>
        <tr>
            <td><strong>Learner Reference Number (LRN):</strong></td>
            <td><?= htmlspecialchars($student['lrn'] ?? '—') ?></td>
            <td><strong>Sex:</strong></td>
            <td><?= htmlspecialchars($student['sex'] ?? '—') ?></td>
        </tr>
        <tr>
            <td><strong>Last Name:</strong></td>
            <td><?= htmlspecialchars($student['last_name'] ?? '') ?></td>
            <td><strong>First Name:</strong></td>
            <td><?= htmlspecialchars($student['first_name'] ?? '') ?></td>
        </tr>
        <tr>
            <td><strong>Middle Name:</strong></td>
            <td><?= htmlspecialchars($student['middle_name'] ?? '—') ?></td>
            <td><strong>Extension Name:</strong></td>
            <td><?= htmlspecialchars($student['ext_name'] ?? '—') ?></td>
        </tr>
        <tr>
            <td><strong>Date of Birth:</strong></td>
            <td><?= $student['birth_date'] ? date('F d, Y', strtotime($student['birth_date'])) : '—' ?></td>
            <td><strong>Age:</strong></td>
            <td><?= htmlspecialchars($student['age'] ?? '—') ?></td>
        </tr>
        <tr>
            <td colspan="4"><strong>Address:</strong> 
                <?= htmlspecialchars(trim(($student['purok_street'] ?? '') . ', ' . ($student['barangay'] ?? '') . ', ' . ($student['town_city'] ?? '') . ', ' . ($student['province'] ?? ''))) ?>
            </td>
        </tr>
    </table>

    <!-- Eligibility for COLLEGE -->
    <div class="section-title">ELIGIBILITY FOR COLLEGE ENROLLMENT</div>
    <table>
        <tr>
            <td><strong>Previous School (SHS):</strong></td>
            <td colspan="3"><?= htmlspecialchars($student['previous_school_name'] ?? '—') ?></td>
        </tr>
        <tr>
            <td><strong>Year Completed:</strong></td>
            <td><?= htmlspecialchars($student['previous_year_completed'] ?? '—') ?></td>
            <td><strong>Track / Strand:</strong></td>
            <td><?= htmlspecialchars(trim(($student['track'] ?? '') . ' - ' . ($student['strand'] ?? $student['program'] ?? ''))) ?></td>
        </tr>
    </table>

    <!-- Scholastic Record -->
    <div class="section-title">SCHOLASTIC RECORD</div>
    <?php if ($grades_result && $grades_result->num_rows > 0): 
        $current_sem = '';
        while ($g = $grades_result->fetch_assoc()):
            if ($current_sem !== $g['semester']):
                if ($current_sem !== '') echo '</tbody></table>';
                $current_sem = $g['semester'];
    ?>
        <p class="fw-bold mt-3"><?= htmlspecialchars($current_sem) ?> — <?= htmlspecialchars($student['grade_level'] ?? '') ?> <?= htmlspecialchars($student['strand'] ?? '') ?></p>
        <table>
            <thead>
                <tr>
                    <th>Subject Code</th>
                    <th>Subject Title</th>
                    <th class="text-center">Final Grade</th>
                    <th class="text-center">Remarks</th>
                </tr>
            </thead>
            <tbody>
    <?php endif; ?>
                <tr>
                    <td><?= htmlspecialchars($g['subject_code']) ?></td>
                    <td><?= htmlspecialchars($g['subject_name']) ?></td>
                    <td class="text-center"><?= $g['grade'] ? number_format($g['grade'], 2) : '—' ?></td>
                    <td class="text-center"><?= htmlspecialchars($g['remarks'] ?? 'Passed') ?></td>
                </tr>
    <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="text-center text-muted py-4">No grades recorded yet.</p>
    <?php endif; ?>

    <!-- Certification -->
    <div class="section-title">CERTIFICATION</div>
    <p class="text-center">I hereby certify that the above information is true and correct based on the records of the school.</p>
    
    <div class="row mt-5">
        <div class="col-6">
            <div class="signature">
                _______________________________<br>
                <strong>School Head / Principal</strong><br>
                <small>Date: <?= date('F d, Y') ?></small>
            </div>
        </div>
        <div class="col-6">
            <div class="signature">
                _______________________________<br>
                <strong>Registrar</strong><br>
                <small>USAT College Sagay City, Inc.</small>
            </div>
        </div>
    </div>
</div>

<!-- Print Buttons -->
<div class="no-print text-center my-4">
    <button onclick="window.print()" class="btn btn-success btn-lg px-5">
        <i class="bi bi-printer-fill"></i> Print SF10-SHS
    </button>
    <a href="student_profile.php" class="btn btn-secondary btn-lg px-5 ms-3">Back to Profiles</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>