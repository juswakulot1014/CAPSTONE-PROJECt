<?php
session_start();
include __DIR__ . "/../config/db.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: enroll_form.php');
    exit;
}

// ====================== CSRF VALIDATION ======================
if (!isset($_SESSION['csrf_token']) || empty($_POST['csrf_token']) || 
    $_SESSION['csrf_token'] !== $_POST['csrf_token']) {
    
    $_SESSION['error'] = "Security validation failed. Please try again.";
    header('Location: enroll_form.php');
    exit;
}

$data = $_POST;

// ====================== EXTRACT & SANITIZE DATA ======================
$lrn                    = trim($data['lrn'] ?? '');
$last_name              = trim($data['last_name'] ?? '');
$first_name             = trim($data['first_name'] ?? '');
$middle_name            = trim($data['middle_name'] ?? '');
$nick_name              = trim($data['nick_name'] ?? '');
$ext_name               = trim($data['ext_name'] ?? '');
$sex                    = trim($data['sex'] ?? '');
$birth_date             = trim($data['birth_date'] ?? '');
$age                    = (int)($data['age'] ?? 0);
$civil_status           = trim($data['civil_status'] ?? '');
$nationality            = trim($data['nationality'] ?? '');
$religion               = trim($data['religion'] ?? '');
$height                 = !empty($data['height']) ? (float)$data['height'] : null;
$weight                 = !empty($data['weight']) ? (float)$data['weight'] : null;
$email                  = trim($data['email'] ?? '');
$phone                  = trim($data['phone'] ?? '');
$special_skills         = trim($data['special_skills'] ?? '');

// Parents & Guardian
$father_name            = trim($data['father_name'] ?? '');
$father_occupation      = trim($data['father_occupation'] ?? '');
$father_contact         = trim($data['father_contact'] ?? '');
$mother_maiden_name     = trim($data['mother_maiden_name'] ?? '');
$mother_occupation      = trim($data['mother_occupation'] ?? '');
$mother_contact         = trim($data['mother_contact'] ?? '');
$guardian_fullname      = trim($data['guardian_fullname'] ?? '');
$guardian_relation      = trim($data['guardian_relation'] ?? '');
$guardian_contact       = trim($data['guardian_contact'] ?? '');
$ave_family_income      = !empty($data['ave_family_income']) ? (float)$data['ave_family_income'] : null;
$is_4ps                 = isset($data['is_4ps']) && $data['is_4ps'] !== '' ? (int)$data['is_4ps'] : 0;
$household_id           = trim($data['household_id'] ?? '');

// Address
$purok_street   = trim($data['purok_street'] ?? '');
$barangay       = trim($data['barangay'] ?? '');
$town_city      = trim($data['town_city'] ?? '');
$province       = trim($data['province'] ?? '');
$region         = trim($data['region'] ?? '');
$district       = trim($data['district'] ?? '');
$postal_code    = trim($data['postal_code'] ?? '');

// Enrollment Details
$school_year    = trim($data['school_year'] ?? '');
$grade_level    = trim($data['grade_level'] ?? '');
$semester       = trim($data['semester'] ?? '');
$strand         = trim($data['strand'] ?? '');
$track          = trim($data['track'] ?? 'TECHPRO ELECTIVES');
$program        = trim($data['program'] ?? '');

// Educational History
$edu_level       = $data['edu_level'] ?? [];
$school_name     = $data['school_name'] ?? [];
$school_address  = $data['school_address'] ?? [];
$year_completed  = $data['year_completed'] ?? [];

// Transferee Information
$is_transferred          = !empty($data['is_transferred']) ? 1 : 0;
$previous_school_name    = trim($data['previous_school_name'] ?? '');
$previous_school_address = trim($data['previous_school_address'] ?? '');
$previous_track          = trim($data['previous_track'] ?? '');
$previous_strand         = trim($data['previous_strand'] ?? '');
$previous_program        = trim($data['previous_program'] ?? '');
$previous_year_completed = !empty($data['previous_year_completed']) ? (int)$data['previous_year_completed'] : null;
$voucher_status          = isset($data['voucher_qualified']) && $data['voucher_qualified'] !== '' 
                           ? ($data['voucher_qualified'] == 1 ? 'Qualified' : 'Not Qualified') 
                           : null;

$entrance_documents = isset($data['entrance_data']) && is_array($data['entrance_data']) 
                      ? $data['entrance_data'] : [];

// ====================== AUTO SECTION ASSIGNMENT ======================
function getAvailableSection($conn, $school_year, $grade_level, $strand, $program, $max_students = 35) {
    $group_field = !empty($strand) ? 'strand' : 'program';
    $group_value = !empty($strand) ? $strand : $program;
    $letters = range('A', 'Z');

    foreach ($letters as $letter) {
        $sql = "SELECT COUNT(*) FROM enrollment_form 
                WHERE school_year = ? AND grade_level = ? AND $group_field = ? AND section = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssss", $school_year, $grade_level, $group_value, $letter);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();

        if ($count < $max_students) {
            return $letter;
        }
    }
    return 'A'; // Fallback
}

$section = trim($data['section'] ?? '');
if (empty($section)) {
    $section = getAvailableSection($conn, $school_year, $grade_level, $strand, $program);
}

// ====================== DATABASE TRANSACTION ======================
$conn->begin_transaction();

try {
    $student_id = null;
    $is_existing = false;

    // Check if student already exists by LRN
    if (!empty($lrn)) {
        $stmt = $conn->prepare("SELECT student_id FROM students_info WHERE lrn = ?");
        $stmt->bind_param("s", $lrn);
        $stmt->execute();
        $stmt->bind_result($existing_id);
        if ($stmt->fetch()) {
            $student_id = $existing_id;
            $is_existing = true;
        }
        $stmt->close();
    }

    if ($is_existing) {
        // === UPDATE EXISTING STUDENT ===
        $sql = "UPDATE students_info SET 
                last_name=?, first_name=?, middle_name=?, nick_name=?, ext_name=?, sex=?, 
                birth_date=?, age=?, civil_status=?, nationality=?, religion=?, height=?, 
                weight=?, email=?, phone=?, special_skills=? 
                WHERE student_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssssisssssssi", 
            $last_name, $first_name, $middle_name, $nick_name, $ext_name, $sex, 
            $birth_date, $age, $civil_status, $nationality, $religion, $height, 
            $weight, $email, $phone, $special_skills, $student_id);
        $stmt->execute();
        $stmt->close();

        // Update Address
        $sql = "UPDATE addresses SET purok_street=?, barangay=?, town_city=?, province=?, 
                region=?, district=?, postal_code=? WHERE student_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssssi", 
            $purok_street, $barangay, $town_city, $province, $region, $district, 
            $postal_code, $student_id);
        $stmt->execute();
        $stmt->close();

        // Update Parents Info
        $sql = "UPDATE parents_info SET 
                father_name=?, father_occupation=?, father_contact=?, 
                mother_maiden_name=?, mother_occupation=?, mother_contact=?, 
                guardian_fullname=?, guardian_relation=?, guardian_contact=?, 
                ave_family_income=?, is_4ps=?, household_id=? 
                WHERE student_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssssssdisi", 
            $father_name, $father_occupation, $father_contact, 
            $mother_maiden_name, $mother_occupation, $mother_contact, 
            $guardian_fullname, $guardian_relation, $guardian_contact, 
            $ave_family_income, $is_4ps, $household_id, $student_id);
        $stmt->execute();
        $stmt->close();

    } else {
        // === INSERT NEW STUDENT ===
        $sql = "INSERT INTO students_info 
                (lrn, last_name, first_name, middle_name, nick_name, ext_name, sex, birth_date, age, 
                 civil_status, nationality, religion, height, weight, email, phone, special_skills) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssssissssssss", 
            $lrn, $last_name, $first_name, $middle_name, $nick_name, $ext_name, $sex, 
            $birth_date, $age, $civil_status, $nationality, $religion, $height, $weight, 
            $email, $phone, $special_skills);
        $stmt->execute();
        $student_id = $conn->insert_id;
        $stmt->close();

        // Insert Address
        $sql = "INSERT INTO addresses (student_id, purok_street, barangay, town_city, province, 
                region, district, postal_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isssssss", $student_id, $purok_street, $barangay, $town_city, 
                          $province, $region, $district, $postal_code);
        $stmt->execute();
        $stmt->close();

        // Insert Parents Info
        $sql = "INSERT INTO parents_info (student_id, father_name, father_occupation, father_contact, 
                mother_maiden_name, mother_occupation, mother_contact, guardian_fullname, 
                guardian_relation, guardian_contact, ave_family_income, is_4ps, household_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issssssssssdi", 
            $student_id, $father_name, $father_occupation, $father_contact, 
            $mother_maiden_name, $mother_occupation, $mother_contact, 
            $guardian_fullname, $guardian_relation, $guardian_contact, 
            $ave_family_income, $is_4ps, $household_id);
        $stmt->execute();
        $stmt->close();
    }

    // ====================== EDUCATIONAL HISTORY ======================
    if (!empty($edu_level)) {
        $sql = "INSERT INTO educational_history 
                (student_id, level, school_name, school_address, year_completed) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        for ($i = 0; $i < count($edu_level); $i++) {
            if (!empty($school_name[$i] ?? '')) {
                $level = trim($edu_level[$i] ?? '');
                $stmt->bind_param("issss", $student_id, $level, 
                    trim($school_name[$i] ?? ''), 
                    trim($school_address[$i] ?? ''), 
                    trim($year_completed[$i] ?? ''));
                $stmt->execute();
            }
        }
        $stmt->close();
    }

    // ====================== ENROLLMENT RECORD ======================
    $sql = "INSERT INTO enrollment_form 
            (student_id, school_year, grade_level, semester, strand, section, track, program, 
             is_transferred, previous_school_name, previous_school_address, previous_track, 
             previous_strand, previous_program, previous_year_completed, voucher_status, 
             cct_4ps, household_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isssssssissssssisi", 
        $student_id, $school_year, $grade_level, $semester, $strand, $section, $track, $program,
        $is_transferred, $previous_school_name, $previous_school_address,
        $previous_track, $previous_strand, $previous_program, $previous_year_completed,
        $voucher_status, $is_4ps, $household_id);
    $stmt->execute();
    $stmt->close();

    // ====================== ENTRANCE DOCUMENTS ======================
    if (!empty($entrance_documents)) {
        $sql = "INSERT INTO entrance_documents (student_id, document_name, submitted) 
                VALUES (?, ?, 1)";
        $stmt = $conn->prepare($sql);
        foreach ($entrance_documents as $doc) {
            $stmt->bind_param("is", $student_id, trim($doc));
            $stmt->execute();
        }
        $stmt->close();
    }

    $conn->commit();

    // ====================== SUCCESS RESPONSE ======================
    unset($_SESSION['form_data']);
    $_SESSION['success'] = true;
    
    $status = $is_existing ? "Re-enrollment Successful!" : "Enrollment Successful!";
    $_SESSION['success_message'] = "
        {$status}<br><br>
        <strong>Student:</strong> " . htmlspecialchars($first_name . ' ' . $last_name) . "<br>
        <strong>LRN:</strong> " . htmlspecialchars($lrn) . "<br>
        <strong>Student ID:</strong> #{$student_id}
    ";

    header('Location: enroll_form.php');
    exit;

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = "Enrollment failed: " . $e->getMessage();
    header('Location: enroll_form.php');
    exit;
} finally {
    $conn->close();
}