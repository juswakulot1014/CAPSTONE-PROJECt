<?php
session_start();
include "../config/db.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

$student_id = (int)($_GET['id'] ?? 0);
if ($student_id <= 0) {
    die("Invalid student ID");
}

// Fetch complete student data including status
$stmt = $conn->prepare("
    SELECT 
        s.*,
        p.father_name, p.father_occupation, p.father_contact,
        p.mother_maiden_name, p.mother_occupation, p.mother_contact, p.ave_family_income,
        p.guardian_fullname, p.guardian_relation, p.guardian_contact,
        a.purok_street, a.barangay, a.town_city, a.province, a.region, a.district, a.postal_code,
        e.grade_level, e.track, e.strand, e.program, e.section, e.school_year, e.semester,
        e.voucher_status, e.household_id,
        COALESCE(e.status, 'Active') AS status
    FROM students_info s
    LEFT JOIN parents_info p ON s.student_id = p.student_id
    LEFT JOIN addresses a ON s.student_id = a.student_id
    LEFT JOIN enrollment_form e ON s.student_id = e.student_id
    WHERE s.student_id = ?
    ORDER BY e.enrollment_id DESC 
    LIMIT 1
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc() ?: [];
$stmt->close();

if (empty($student)) {
    die("Student not found.");
}

// Fetch educational history
$edu_stmt = $conn->prepare("
    SELECT * FROM educational_history 
    WHERE student_id = ? 
    ORDER BY 
        CASE level 
            WHEN 'Elementary' THEN 1 
            WHEN 'JHS' THEN 2 
            WHEN 'Senior High School' THEN 3 
            WHEN 'College' THEN 4
            WHEN 'Transferred' THEN 5
            ELSE 6 
        END, year_completed DESC
");
$edu_stmt->bind_param("i", $student_id);
$edu_stmt->execute();
$education_rows = $edu_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$edu_stmt->close();

$edu_data = [];
foreach ($education_rows as $edu) {
    $edu_data[$edu['level']] = $edu;
}

$errors = [];
$old = $student;   // For form repopulation

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = array_merge($old, $_POST);

    // Merge educational history for repopulation on error
    if (isset($_POST['school_name']) && is_array($_POST['school_name'])) {
        foreach ($_POST['school_name'] as $level => $value) {
            $old['school_name'][$level]    = trim($value ?? '');
            $old['school_address'][$level] = trim($_POST['school_address'][$level] ?? '');
            $old['year_completed'][$level] = trim($_POST['year_completed'][$level] ?? '');
        }
    }

    $conn->begin_transaction();

    try {
        // Collect Form Data
        $lrn              = trim($_POST['lrn'] ?? '');
        $first_name       = trim($_POST['first_name'] ?? '');
        $middle_name      = trim($_POST['middle_name'] ?? '');
        $last_name        = trim($_POST['last_name'] ?? '');
        $nick_name        = trim($_POST['nick_name'] ?? '');
        $ext_name         = trim($_POST['ext_name'] ?? '');
        $birth_date       = $_POST['birth_date'] ?? null;
        $sex              = $_POST['sex'] ?? '';
        $civil_status     = trim($_POST['civil_status'] ?? '');
        $nationality      = trim($_POST['nationality'] ?? 'Filipino');
        $religion         = trim($_POST['religion'] ?? '');
        $height           = !empty($_POST['height']) ? (float)$_POST['height'] : null;
        $weight           = !empty($_POST['weight']) ? (float)$_POST['weight'] : null;
        $email            = trim($_POST['email'] ?? '');
        $phone            = trim($_POST['phone'] ?? '');
        $special_skills   = trim($_POST['special_skills'] ?? '');

        // Parents
        $father_name        = trim($_POST['father_name'] ?? '');
        $father_occupation  = trim($_POST['father_occupation'] ?? '');
        $father_contact     = trim($_POST['father_contact'] ?? '');
        $mother_maiden_name = trim($_POST['mother_maiden_name'] ?? '');
        $mother_occupation  = trim($_POST['mother_occupation'] ?? '');
        $mother_contact     = trim($_POST['mother_contact'] ?? '');
        $ave_family_income  = !empty($_POST['ave_family_income']) ? (float)$_POST['ave_family_income'] : null;
        
        // Guardian
        $guardian_fullname  = trim($_POST['guardian_fullname'] ?? '');
        $guardian_relation  = trim($_POST['guardian_relation'] ?? '');
        $guardian_contact   = trim($_POST['guardian_contact'] ?? '');

        // Address
        $purok_street = trim($_POST['purok_street'] ?? '');
        $barangay     = trim($_POST['barangay'] ?? '');
        $town_city    = trim($_POST['town_city'] ?? '');
        $province     = trim($_POST['province'] ?? '');
        $region       = trim($_POST['region'] ?? '');
        $district     = trim($_POST['district'] ?? '');
        $postal_code  = trim($_POST['postal_code'] ?? '');

        // Enrollment + Status + Voucher & Household
        $grade_level    = trim($_POST['grade_level'] ?? '');
        $track          = trim($_POST['track'] ?? 'TECHPRO');
        $strand         = trim($_POST['strand'] ?? '');
        $program        = trim($_POST['program'] ?? '');
        $section        = trim($_POST['section'] ?? '');
        $school_year    = trim($_POST['school_year'] ?? '');
        $semester       = trim($_POST['semester'] ?? '');
        $student_status = trim($_POST['status'] ?? 'Active');
        $voucher_status = $_POST['voucher_status'] ?? null;
        $household_id   = trim($_POST['household_id'] ?? '');

        // ========== BIRTH DATE & AGE VALIDATION (FIXED) ==========
        $age = null;
        if (!empty($birth_date)) {
            $date_obj = DateTime::createFromFormat('Y-m-d', $birth_date);
            if (!$date_obj || $date_obj->format('Y-m-d') !== $birth_date) {
                throw new Exception("Invalid birth date format.");
            }
            if ($date_obj > new DateTime()) {
                throw new Exception("Birth date cannot be in the future.");
            }
            $today = new DateTime();
            $age = $today->diff($date_obj)->y;
        }

        // Basic validation
        if (empty($first_name)) $errors['first_name'] = "First name is required";
        if (empty($last_name))  $errors['last_name']  = "Last name is required";
        if (empty($birth_date)) $errors['birth_date'] = "Birth date is required";
        if (empty($sex))        $errors['sex']        = "Sex is required";

        if (!empty($errors)) {
            throw new Exception("Please correct the errors below.");
        }

        // ==================== Update students_info ====================
        $stmt = $conn->prepare("
            UPDATE students_info SET
                lrn = ?, first_name = ?, middle_name = ?, last_name = ?, nick_name = ?, ext_name = ?,
                sex = ?, birth_date = ?, age = ?, civil_status = ?, nationality = ?, religion = ?,
                height = ?, weight = ?, email = ?, phone = ?, special_skills = ?
            WHERE student_id = ?
        ");
        $stmt->bind_param("ssssssssissddssssi",
            $lrn, $first_name, $middle_name, $last_name, $nick_name, $ext_name,
            $sex, $birth_date, $age, $civil_status, $nationality, $religion,
            $height, $weight, $email, $phone, $special_skills,
            $student_id
        );
        $stmt->execute();
        if ($stmt->error) throw new Exception("Error updating student info: " . $stmt->error);

        // ==================== Parents Info (Upsert with Guardian) ====================
        $stmt = $conn->prepare("SELECT COUNT(*) FROM parents_info WHERE student_id = ?");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $stmt->bind_result($parent_exists);
        $stmt->fetch();
        $stmt->close();

        if ($parent_exists) {
            $stmt = $conn->prepare("UPDATE parents_info SET 
                father_name = ?, father_occupation = ?, father_contact = ?, 
                mother_maiden_name = ?, mother_occupation = ?, mother_contact = ?, 
                ave_family_income = ?, guardian_fullname = ?, guardian_relation = ?, guardian_contact = ?
                WHERE student_id = ?");
            $stmt->bind_param("ssssssdsssi", 
                $father_name, $father_occupation, $father_contact, 
                $mother_maiden_name, $mother_occupation, $mother_contact, 
                $ave_family_income, $guardian_fullname, $guardian_relation, $guardian_contact,
                $student_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO parents_info 
                (student_id, father_name, father_occupation, father_contact, 
                 mother_maiden_name, mother_occupation, mother_contact, ave_family_income,
                 guardian_fullname, guardian_relation, guardian_contact)
                VALUES (?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param("issssssdsss", 
                $student_id, $father_name, $father_occupation, $father_contact, 
                $mother_maiden_name, $mother_occupation, $mother_contact, $ave_family_income,
                $guardian_fullname, $guardian_relation, $guardian_contact);
        }
        $stmt->execute();
        if ($stmt->error) throw new Exception("Error updating parents info: " . $stmt->error);

        // ==================== Address (Upsert) ====================
        $stmt = $conn->prepare("SELECT COUNT(*) FROM addresses WHERE student_id = ?");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $stmt->bind_result($addr_exists);
        $stmt->fetch();
        $stmt->close();

        if ($addr_exists) {
            $stmt = $conn->prepare("UPDATE addresses SET 
                purok_street = ?, barangay = ?, town_city = ?, province = ?, 
                region = ?, district = ?, postal_code = ? WHERE student_id = ?");
            $stmt->bind_param("sssssssi", 
                $purok_street, $barangay, $town_city, $province, 
                $region, $district, $postal_code, $student_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO addresses 
                (student_id, purok_street, barangay, town_city, province, region, district, postal_code) 
                VALUES (?,?,?,?,?,?,?,?)");
            $stmt->bind_param("isssssss", 
                $student_id, $purok_street, $barangay, $town_city, 
                $province, $region, $district, $postal_code);
        }
        $stmt->execute();
        if ($stmt->error) throw new Exception("Error updating address: " . $stmt->error);

        // ==================== Enrollment Form (Upsert) ====================
        $stmt = $conn->prepare("SELECT COUNT(*) FROM enrollment_form WHERE student_id = ?");
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $stmt->bind_result($enroll_exists);
        $stmt->fetch();
        $stmt->close();

        if ($enroll_exists) {
            $stmt = $conn->prepare("UPDATE enrollment_form SET 
                grade_level = ?, track = ?, strand = ?, program = ?, 
                section = ?, school_year = ?, semester = ?, status = ?,
                voucher_status = ?, household_id = ?
                WHERE student_id = ?");
            $stmt->bind_param("ssssssssssi", 
                $grade_level, $track, $strand, $program, $section, $school_year, $semester, 
                $student_status, $voucher_status, $household_id, $student_id);
        } elseif (!empty($grade_level) || !empty($school_year)) {
            $stmt = $conn->prepare("INSERT INTO enrollment_form 
                (student_id, grade_level, track, strand, program, section, school_year, semester, status, voucher_status, household_id) 
                VALUES (?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param("issssssssss", 
                $student_id, $grade_level, $track, $strand, $program, $section, $school_year, $semester, 
                $student_status, $voucher_status, $household_id);
        }
        if (isset($stmt)) {
            $stmt->execute();
            if ($stmt->error) throw new Exception("Error updating enrollment: " . $stmt->error);
        }

        // ==================== Educational History ====================
        // First, get all existing levels from the database
        $existing_levels = array_keys($edu_data);
        $submitted_levels = isset($_POST['school_name']) ? array_keys($_POST['school_name']) : [];

        // Delete levels that are no longer present in the form
        foreach ($existing_levels as $level) {
            if (!in_array($level, $submitted_levels)) {
                $del = $conn->prepare("DELETE FROM educational_history WHERE student_id = ? AND level = ?");
                $del->bind_param("is", $student_id, $level);
                $del->execute();
                $del->close();
            }
        }

        // Insert or update submitted levels
        if (isset($_POST['school_name']) && is_array($_POST['school_name'])) {
            foreach ($_POST['school_name'] as $level => $school_name) {
                $level          = trim($level);
                $school_name    = trim($school_name ?? '');
                $school_address = trim($_POST['school_address'][$level] ?? '');
                $year_completed = trim($_POST['year_completed'][$level] ?? '');

                // Skip completely empty entries
                if (empty($school_name) && empty($school_address) && empty($year_completed)) {
                    $del = $conn->prepare("DELETE FROM educational_history WHERE student_id = ? AND level = ?");
                    $del->bind_param("is", $student_id, $level);
                    $del->execute();
                    $del->close();
                    continue;
                }

                // Check if already exists
                $check = $conn->prepare("SELECT COUNT(*) FROM educational_history WHERE student_id = ? AND level = ?");
                $check->bind_param("is", $student_id, $level);
                $check->execute();
                $check->bind_result($exists);
                $check->fetch();
                $check->close();

                if ($exists) {
                    $stmt = $conn->prepare("UPDATE educational_history SET 
                        school_name = ?, school_address = ?, year_completed = ? 
                        WHERE student_id = ? AND level = ?");
                    $stmt->bind_param("sssis", $school_name, $school_address, $year_completed, $student_id, $level);
                } else {
                    $stmt = $conn->prepare("INSERT INTO educational_history 
                        (student_id, level, school_name, school_address, year_completed) 
                        VALUES (?,?,?,?,?)");
                    $stmt->bind_param("issss", $student_id, $level, $school_name, $school_address, $year_completed);
                }
                $stmt->execute();
                if ($stmt->error) throw new Exception("Error updating education: " . $stmt->error);
            }
        }

        $conn->commit();
        $_SESSION['success'] = "Student record updated successfully!";
        header("Location: view_student.php?id=$student_id");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $errors['general'] = $e->getMessage();
    }
}

$status_options = ['Active', 'Transferred', 'Stopped', 'Dropped'];
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Student • USAT Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        body { background: linear-gradient(135deg, #f5f7fc 0%, #eef2f9 100%); min-height: 100vh; padding-bottom: 3rem; }
        .card { border: none; border-radius: 24px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); transition: transform 0.2s ease, box-shadow 0.2s ease; overflow: hidden; }
        .card:hover { transform: translateY(-3px); box-shadow: 0 20px 35px rgba(0,0,0,0.1); }
        .card-header { border: none; padding: 1.2rem 1.5rem; font-weight: 600; letter-spacing: -0.3px; font-size: 1.2rem; }
        .form-label { font-weight: 500; margin-bottom: 0.5rem; color: #1f2937; font-size: 0.9rem; }
        .form-control, .form-select { border-radius: 14px; padding: 0.7rem 1rem; border: 1px solid #e2e8f0; transition: all 0.2s; background-color: #fff; }
        .form-control:focus, .form-select:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.15); outline: none; }
        .btn-primary { background: linear-gradient(95deg, #2563eb, #1e40af); border: none; border-radius: 40px; padding: 0.7rem 1.8rem; font-weight: 500; transition: all 0.2s; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(37,99,235,0.3); background: linear-gradient(95deg, #3b82f6, #2563eb); }
        .btn-outline-secondary { border-radius: 40px; padding: 0.7rem 1.8rem; }
        .status-badge { font-size: 0.9rem; padding: 0.5rem 1.2rem; border-radius: 40px; font-weight: 500; }
        .required::after { content: " *"; color: #ef4444; font-weight: bold; }
        .edu-block { background: #ffffff; border: 1px solid #eef2ff; border-radius: 20px; padding: 1.5rem; margin-bottom: 1.5rem; transition: all 0.2s; box-shadow: 0 2px 6px rgba(0,0,0,0.02); }
        .edu-block:hover { border-color: #cbd5e1; background: #fafcff; }
        .alert { border-radius: 20px; border: none; }
        .section-icon { font-size: 1.3rem; margin-right: 8px; vertical-align: middle; }
        .bg-gradient-blue { background: linear-gradient(120deg, #1e3c72, #2b4c8c); }
        .bg-gradient-green { background: linear-gradient(120deg, #059669, #047857); }
        .bg-gradient-amber { background: linear-gradient(120deg, #d97706, #b45309); }
        .bg-gradient-purple { background: linear-gradient(120deg, #7c3aed, #6d28d9); }
        .bg-gradient-pink { background: linear-gradient(120deg, #db2777, #be185d); }
        .btn-add-level { border-radius: 40px; padding: 0.4rem 1.2rem; font-size: 0.85rem; }
        @media (max-width: 768px) {
            .card-header { font-size: 1rem; padding: 1rem; }
            .btn-lg { font-size: 0.9rem; padding: 0.6rem 1.2rem; }
        }
    </style>
</head>
<body>

<div class="container py-4 py-lg-5">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h2 class="fw-bold text-dark mb-1"><i class="bi bi-pencil-square text-primary me-2"></i>Edit Student Profile</h2>
            <div class="d-flex align-items-center gap-3 mt-2">
                <span class="badge status-badge 
                    <?= strtolower($student['status']) === 'active' ? 'bg-success' : 
                        (strtolower($student['status']) === 'transferred' ? 'bg-info text-dark' : 
                        (strtolower($student['status']) === 'stopped' ? 'bg-warning text-dark' : 'bg-danger')) ?>">
                    <i class="bi bi-info-circle me-1"></i> Current: <?= htmlspecialchars($student['status']) ?>
                </span>
                <small class="text-muted"><i class="bi bi-person-vcard"></i> ID: <?= $student_id ?></small>
            </div>
        </div>
        <a href="view_student.php?id=<?= $student_id ?>" class="btn btn-outline-secondary rounded-pill px-4">
            <i class="bi bi-arrow-left me-2"></i> Back to Profile
        </a>
    </div>

    <?php if (!empty($errors['general'])): ?>
        <div class="alert alert-danger shadow-sm d-flex align-items-center gap-2"><i class="bi bi-exclamation-triangle-fill fs-5"></i> <?= htmlspecialchars($errors['general']) ?></div>
    <?php endif; ?>

    <form method="POST" novalidate id="editStudentForm">

        <!-- 1. BASIC INFORMATION -->
        <div class="card mb-4">
            <div class="card-header bg-gradient-blue text-white">
                <i class="bi bi-person-badge section-icon"></i> Student Basic Information
            </div>
            <div class="card-body p-4">
                <div class="row g-4">
                    <div class="col-md-3">
                        <label class="form-label">LRN</label>
                        <input type="text" name="lrn" class="form-control" value="<?= htmlspecialchars($old['lrn'] ?? '') ?>" placeholder="12-digit number">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label required">First Name</label>
                        <input type="text" name="first_name" class="form-control <?= isset($errors['first_name']) ? 'is-invalid' : '' ?>" 
                               value="<?= htmlspecialchars($old['first_name'] ?? '') ?>" required>
                        <?php if (isset($errors['first_name'])): ?><div class="invalid-feedback"><?= $errors['first_name'] ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Middle Name</label>
                        <input type="text" name="middle_name" class="form-control" value="<?= htmlspecialchars($old['middle_name'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label required">Last Name</label>
                        <input type="text" name="last_name" class="form-control <?= isset($errors['last_name']) ? 'is-invalid' : '' ?>" 
                               value="<?= htmlspecialchars($old['last_name'] ?? '') ?>" required>
                        <?php if (isset($errors['last_name'])): ?><div class="invalid-feedback"><?= $errors['last_name'] ?></div><?php endif; ?>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Nickname</label>
                        <input type="text" name="nick_name" class="form-control" value="<?= htmlspecialchars($old['nick_name'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Extension Name</label>
                        <input type="text" name="ext_name" class="form-control" placeholder="Jr., III, etc." value="<?= htmlspecialchars($old['ext_name'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label required">Birth Date</label>
                        <input type="date" name="birth_date" id="birth_date" class="form-control <?= isset($errors['birth_date']) ? 'is-invalid' : '' ?>" 
                               value="<?= htmlspecialchars($old['birth_date'] ?? '') ?>" required>
                        <?php if (isset($errors['birth_date'])): ?><div class="invalid-feedback"><?= $errors['birth_date'] ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Age</label>
                        <input type="number" name="age" id="age" class="form-control bg-light" readonly value="<?= htmlspecialchars($old['age'] ?? '') ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label required">Sex</label>
                        <select name="sex" class="form-select <?= isset($errors['sex']) ? 'is-invalid' : '' ?>" required>
                            <option value="">-- Select --</option>
                            <option value="Male"   <?= ($old['sex'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                            <option value="Female" <?= ($old['sex'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                        </select>
                        <?php if (isset($errors['sex'])): ?><div class="invalid-feedback"><?= $errors['sex'] ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Civil Status</label>
                        <select name="civil_status" class="form-select">
                            <option value="">-- Select --</option>
                            <option value="Single"     <?= ($old['civil_status'] ?? '') === 'Single' ? 'selected' : '' ?>>Single</option>
                            <option value="Married"    <?= ($old['civil_status'] ?? '') === 'Married' ? 'selected' : '' ?>>Married</option>
                            <option value="Widowed"    <?= ($old['civil_status'] ?? '') === 'Widowed' ? 'selected' : '' ?>>Widowed</option>
                            <option value="Separated"  <?= ($old['civil_status'] ?? '') === 'Separated' ? 'selected' : '' ?>>Separated</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Nationality</label>
                        <input type="text" name="nationality" class="form-control" value="<?= htmlspecialchars($old['nationality'] ?? 'Filipino') ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Religion</label>
                        <input type="text" name="religion" class="form-control" value="<?= htmlspecialchars($old['religion'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Height (cm)</label>
                        <input type="number" step="0.01" name="height" class="form-control" value="<?= htmlspecialchars($old['height'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Weight (kg)</label>
                        <input type="number" step="0.01" name="weight" class="form-control" value="<?= htmlspecialchars($old['weight'] ?? '') ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($old['email'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone / Mobile</label>
                        <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($old['phone'] ?? '') ?>">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Special Skills / Talents</label>
                        <textarea name="special_skills" class="form-control" rows="2" placeholder="e.g., Painting, Coding, Sports"><?= htmlspecialchars($old['special_skills'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. PARENTS / GUARDIAN -->
        <div class="card mb-4">
            <div class="card-header bg-gradient-green text-white">
                <i class="bi bi-people section-icon"></i> Parents / Guardian Information
            </div>
            <div class="card-body p-4">
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label">Father's Full Name</label>
                        <input type="text" name="father_name" class="form-control" value="<?= htmlspecialchars($old['father_name'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Father's Occupation</label>
                        <input type="text" name="father_occupation" class="form-control" value="<?= htmlspecialchars($old['father_occupation'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Father's Contact</label>
                        <input type="tel" name="father_contact" class="form-control" value="<?= htmlspecialchars($old['father_contact'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Mother's Maiden Name</label>
                        <input type="text" name="mother_maiden_name" class="form-control" value="<?= htmlspecialchars($old['mother_maiden_name'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Mother's Occupation</label>
                        <input type="text" name="mother_occupation" class="form-control" value="<?= htmlspecialchars($old['mother_occupation'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Mother's Contact</label>
                        <input type="tel" name="mother_contact" class="form-control" value="<?= htmlspecialchars($old['mother_contact'] ?? '') ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Average Monthly Family Income (₱)</label>
                        <input type="number" step="0.01" name="ave_family_income" class="form-control" 
                               value="<?= htmlspecialchars($old['ave_family_income'] ?? '') ?>" placeholder="e.g., 25000.00">
                    </div>
                    
                    <div class="col-12 mt-3">
                        <hr>
                        <h6 class="fw-bold mb-3"><i class="bi bi-shield-plus me-2"></i>Guardian Information (if different from parents)</h6>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Guardian Full Name</label>
                        <input type="text" name="guardian_fullname" class="form-control" value="<?= htmlspecialchars($old['guardian_fullname'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Relationship to Student</label>
                        <input type="text" name="guardian_relation" class="form-control" value="<?= htmlspecialchars($old['guardian_relation'] ?? '') ?>" placeholder="e.g., Aunt, Grandparent">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Guardian Contact</label>
                        <input type="tel" name="guardian_contact" class="form-control" value="<?= htmlspecialchars($old['guardian_contact'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. ADDRESS -->
        <div class="card mb-4">
            <div class="card-header bg-gradient-amber text-white">
                <i class="bi bi-geo-alt section-icon"></i> Residential Address
            </div>
            <div class="card-body p-4">
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label">Purok / Street</label>
                        <input type="text" name="purok_street" class="form-control" value="<?= htmlspecialchars($old['purok_street'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Barangay</label>
                        <input type="text" name="barangay" class="form-control" value="<?= htmlspecialchars($old['barangay'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Town / City</label>
                        <input type="text" name="town_city" class="form-control" value="<?= htmlspecialchars($old['town_city'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Province</label>
                        <input type="text" name="province" class="form-control" value="<?= htmlspecialchars($old['province'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Region</label>
                        <input type="text" name="region" class="form-control" value="<?= htmlspecialchars($old['region'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">District</label>
                        <input type="text" name="district" class="form-control" value="<?= htmlspecialchars($old['district'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Postal Code</label>
                        <input type="text" name="postal_code" class="form-control" value="<?= htmlspecialchars($old['postal_code'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. ENROLLMENT DETAILS & STATUS -->
        <div class="card mb-4">
            <div class="card-header bg-gradient-purple text-white">
                <i class="bi bi-mortarboard section-icon"></i> Enrollment Details & Student Status
            </div>
            <div class="card-body p-4">
                <div class="row g-4">
                    <div class="col-md-3">
                        <label class="form-label">Grade Level</label>
                        <input type="text" name="grade_level" class="form-control" value="<?= htmlspecialchars($old['grade_level'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">School Year</label>
                        <input type="text" name="school_year" class="form-control" placeholder="2025-2026" value="<?= htmlspecialchars($old['school_year'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Section</label>
                        <input type="text" name="section" class="form-control" value="<?= htmlspecialchars($old['section'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Semester</label>
                        <input type="text" name="semester" class="form-control" placeholder="1st Semester" value="<?= htmlspecialchars($old['semester'] ?? '') ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Track</label>
                        <input type="text" name="track" class="form-control bg-light" value="<?= htmlspecialchars($old['track'] ?? 'TECHPRO') ?>" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Strand</label>
                        <select name="strand" id="strand" class="form-select">
                            <option value="">-- Select Strand --</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Program</label>
                        <select name="program" id="program" class="form-select">
                            <option value="">-- Select Program --</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Voucher Status</label>
                        <select name="voucher_status" class="form-select">
                            <option value="">-- Select --</option>
                            <option value="Qualified"     <?= ($old['voucher_status'] ?? '') === 'Qualified' ? 'selected' : '' ?>>Qualified</option>
                            <option value="Not Qualified" <?= ($old['voucher_status'] ?? '') === 'Not Qualified' ? 'selected' : '' ?>>Not Qualified</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Household ID (4Ps)</label>
                        <input type="text" name="household_id" class="form-control" value="<?= htmlspecialchars($old['household_id'] ?? '') ?>">
                    </div>

                    <div class="col-12 mt-3 pt-2 border-top">
                        <label class="form-label required fw-bold">Student Status</label>
                        <select name="status" class="form-select form-select-lg" required>
                            <?php foreach($status_options as $st): ?>
                                <option value="<?= $st ?>" 
                                    <?= ($old['status'] ?? $student['status'] ?? 'Active') === $st ? 'selected' : '' ?>>
                                    <?= $st ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted"><i class="bi bi-info-circle"></i> Changing the status affects reports and enrollment lists.</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- 5. EDUCATIONAL HISTORY -->
        <div class="card mb-5">
            <div class="card-header bg-gradient-pink text-white d-flex justify-content-between align-items-center">
                <span><i class="bi bi-book section-icon"></i> Educational History</span>
                <button type="button" class="btn btn-light btn-add-level" id="addLevelBtn">
                    <i class="bi bi-plus-lg"></i> Add New Level
                </button>
            </div>
            <div class="card-body p-4" id="educationalContainer">
                <?php 
                $display_levels = [];
                foreach ($education_rows as $edu) {
                    $display_levels[$edu['level']] = $edu;
                }
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['school_name']) && is_array($_POST['school_name'])) {
                    foreach (array_keys($_POST['school_name']) as $lvl) {
                        if (!isset($display_levels[$lvl])) {
                            $display_levels[$lvl] = ['level' => $lvl];
                        }
                    }
                }
                ?>

                <?php foreach ($display_levels as $level => $edu): ?>
                    <div class="edu-block" data-level="<?= htmlspecialchars($level) ?>">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <h6 class="fw-bold text-primary"><i class="bi bi-journal-bookmark-fill me-2"></i><?= htmlspecialchars($level) ?></h6>
                            <button type="button" class="btn btn-sm btn-outline-danger rounded-pill remove-level-btn" data-level="<?= htmlspecialchars($level) ?>">
                                <i class="bi bi-trash"></i> Remove
                            </button>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-5">
                                <label class="form-label">School Name</label>
                                <input type="text" name="school_name[<?= htmlspecialchars($level) ?>]" class="form-control"
                                       value="<?= htmlspecialchars($old['school_name'][$level] ?? $edu['school_name'] ?? '') ?>"
                                       placeholder="School name">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">School Address</label>
                                <input type="text" name="school_address[<?= htmlspecialchars($level) ?>]" class="form-control"
                                       value="<?= htmlspecialchars($old['school_address'][$level] ?? $edu['school_address'] ?? '') ?>"
                                       placeholder="City, Province">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Year Completed</label>
                                <input type="text" name="year_completed[<?= htmlspecialchars($level) ?>]" class="form-control"
                                       value="<?= htmlspecialchars($old['year_completed'][$level] ?? $edu['year_completed'] ?? '') ?>"
                                       placeholder="YYYY">
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="card-footer bg-transparent text-muted small">
                <i class="bi bi-info-circle"></i> You can add any educational level (e.g., "Senior High School", "College"). Empty fields will be ignored.
            </div>
        </div>

        <div class="d-flex flex-wrap justify-content-end gap-3 mt-4">
            <a href="view_student.php?id=<?= $student_id ?>" class="btn btn-outline-secondary btn-lg rounded-pill px-5">
                <i class="bi bi-x-circle me-2"></i> Cancel
            </a>
            <button type="submit" class="btn btn-primary btn-lg rounded-pill px-5" id="submitBtn">
                <i class="bi bi-check-lg me-2"></i> Save All Changes
            </button>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Age calculation (client-side, server also recalculates)
const birthInput = document.getElementById('birth_date');
const ageInput = document.getElementById('age');

function calculateAge() {
    if (!birthInput.value) { ageInput.value = ''; return; }
    const birth = new Date(birthInput.value);
    const today = new Date();
    let age = today.getFullYear() - birth.getFullYear();
    const m = today.getMonth() - birth.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
    ageInput.value = age >= 0 ? age : '';
}
birthInput.addEventListener('change', calculateAge);
birthInput.addEventListener('input', calculateAge);
window.addEventListener('load', calculateAge);

// Dynamic Strand & Program (TECHPRO only)
const strandSelect = document.getElementById('strand');
const programSelect = document.getElementById('program');

const techproStrands = {
    "Automotive and Small Engine Technologies": ["Driving and Automotive Servicing","Automotive Servicing (Electrical Repair)","Automotive Servicing (Engine and Chassis Repairs)"],
    "Construction and Building Technologies": ["Carpentry","Manual Metal Arc Welding"],
    "ICT Support and Computer Programming Technologies": ["Computer Programming (Java)","Computer Programming (.NET)","Computer System Servicing"],
    "Industrial Technologies": ["Electronics Product Assembly and Servicing"],
    "Agri-Fishery Business and Food Innovation": ["Agricultural Crops Production"],
    "Hospitality and Tourism": ["Food and Beverage Operation","Hotel Operation (Housekeeping)"]
};

function populateStrands() {
    Object.keys(techproStrands).forEach(strand => {
        const opt = document.createElement('option');
        opt.value = strand;
        opt.textContent = strand;
        strandSelect.appendChild(opt);
    });
}

function populatePrograms(strand) {
    programSelect.innerHTML = '<option value="">-- Select Program --</option>';
    if (strand && techproStrands[strand]) {
        techproStrands[strand].forEach(prog => {
            const opt = document.createElement('option');
            opt.value = prog;
            opt.textContent = prog;
            programSelect.appendChild(opt);
        });
    }
}

window.addEventListener('load', () => {
    populateStrands();
    
    const savedStrand = "<?= addslashes($old['strand'] ?? $student['strand'] ?? '') ?>";
    const savedProgram = "<?= addslashes($old['program'] ?? $student['program'] ?? '') ?>";
    
    if (savedStrand && techproStrands[savedStrand]) {
        strandSelect.value = savedStrand;
        populatePrograms(savedStrand);
        if (savedProgram) programSelect.value = savedProgram;
    }
    
    strandSelect.addEventListener('change', function() {
        populatePrograms(this.value);
    });
});

// Add new educational level
document.getElementById('addLevelBtn')?.addEventListener('click', function() {
    const newLevel = prompt("Enter new educational level (e.g., Senior High School, College, Elementary):");
    if (!newLevel) return;
    // Check if already exists
    const existingBlocks = document.querySelectorAll('.edu-block');
    let exists = false;
    existingBlocks.forEach(block => {
        if (block.dataset.level === newLevel) exists = true;
    });
    if (exists) {
        alert("This level already exists. Use the form fields to edit it.");
        return;
    }
    // Create new block
    const container = document.getElementById('educationalContainer');
    const template = `
        <div class="edu-block" data-level="${escapeHtml(newLevel)}">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <h6 class="fw-bold text-primary"><i class="bi bi-journal-bookmark-fill me-2"></i>${escapeHtml(newLevel)}</h6>
                <button type="button" class="btn btn-sm btn-outline-danger rounded-pill remove-level-btn" data-level="${escapeHtml(newLevel)}">
                    <i class="bi bi-trash"></i> Remove
                </button>
            </div>
            <div class="row g-3">
                <div class="col-md-5">
                    <label class="form-label">School Name</label>
                    <input type="text" name="school_name[${escapeHtml(newLevel)}]" class="form-control" placeholder="School name">
                </div>
                <div class="col-md-5">
                    <label class="form-label">School Address</label>
                    <input type="text" name="school_address[${escapeHtml(newLevel)}]" class="form-control" placeholder="City, Province">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Year Completed</label>
                    <input type="text" name="year_completed[${escapeHtml(newLevel)}]" class="form-control" placeholder="YYYY">
                </div>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', template);
    attachRemoveEvents();
});

function escapeHtml(str) {
    return str.replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

function attachRemoveEvents() {
    document.querySelectorAll('.remove-level-btn').forEach(btn => {
        btn.removeEventListener('click', removeLevelHandler);
        btn.addEventListener('click', removeLevelHandler);
    });
}
function removeLevelHandler(e) {
    const block = this.closest('.edu-block');
    if (block) block.remove();
}
attachRemoveEvents();

// Loading spinner on submit
document.getElementById('editStudentForm').addEventListener('submit', function(e) {
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Saving...';
});
</script>
</body>
</html>