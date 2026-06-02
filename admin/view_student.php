<?php
session_start();
include __DIR__ . "/../config/db.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

// Check if exporting to Word
$export_word = isset($_GET['export_word']) && $_GET['export_word'] == 1 && isset($_GET['id']);

if ($export_word) {
    $student_id = (int)$_GET['id'];
    // Fetch all data again for Word export
    $stmt = $conn->prepare("SELECT * FROM students_info WHERE student_id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$student) {
        die("Student not found.");
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
    $edu_stmt = $conn->prepare("SELECT level, school_name, school_address, year_completed FROM educational_history WHERE student_id = ? ORDER BY level");
    $edu_stmt->bind_param("i", $student_id);
    $edu_stmt->execute();
    $education = $edu_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $edu_stmt->close();
    $doc_stmt = $conn->prepare("SELECT document_name, submitted, file_path FROM entrance_documents WHERE student_id = ?");
    $doc_stmt->bind_param("i", $student_id);
    $doc_stmt->execute();
    $documents = $doc_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $doc_stmt->close();
    $grades_stmt = $conn->prepare("SELECT semester, subject_code, subject_name, grade, remarks FROM student_grades WHERE student_id = ? ORDER BY semester DESC, subject_name");
    $grades_stmt->bind_param("i", $student_id);
    $grades_stmt->execute();
    $grades = $grades_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $grades_stmt->close();

    // Word document headers
    header("Content-Type: application/msword");
    header("Content-Disposition: attachment; filename=student_" . $student['lrn'] . "_" . date('Y-m-d') . ".doc");
    header("Cache-Control: no-cache, must-revalidate");

    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Student Profile - ' . htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) . '</title>';
    echo '<style>
        body { font-family: Arial, sans-serif; margin: 2cm; }
        h1 { color: #1e3c72; text-align: center; }
        h2 { color: #2b4c8c; margin-top: 25px; border-bottom: 2px solid #2b4c8c; padding-bottom: 5px; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; vertical-align: top; }
        th { background: #f2f2f2; width: 30%; }
        .header { text-align: center; margin-bottom: 20px; }
        .photo { text-align: center; margin-bottom: 20px; }
        .photo img { max-width: 150px; border-radius: 50%; }
        .footer { text-align: center; margin-top: 30px; font-size: 10px; color: #888; }
    </style>';
    echo '</head><body>';
    echo '<div class="header"><h1>USAT College - Student Information</h1><p>Generated: ' . date('F d, Y h:i A') . '</p></div>';
    
    $photo_path = !empty($student['photo']) && file_exists(__DIR__ . "/../uploads/students/" . $student['photo']) ? "../uploads/students/" . $student['photo'] : '';
    if ($photo_path) echo '<div class="photo"><img src="' . $photo_path . '"></div>';
    
    // Personal Information
    echo '<h2>Personal Information</h2>';
    echo '<table>';
    $personal_fields = [
        'Student ID' => $student['student_id'],
        'LRN' => $student['lrn'] ?? '—',
        'Last Name' => $student['last_name'] ?? '—',
        'First Name' => $student['first_name'] ?? '—',
        'Middle Name' => $student['middle_name'] ?? '—',
        'Nickname' => $student['nick_name'] ?? '—',
        'Extension Name' => $student['ext_name'] ?? '—',
        'Sex' => $student['sex'] ?? '—',
        'Birth Date' => $student['birth_date'] ?? '—',
        'Age' => $student['age'] ?? '—',
        'Civil Status' => $student['civil_status'] ?? '—',
        'Nationality' => $student['nationality'] ?? '—',
        'Religion' => $student['religion'] ?? '—',
        'Height/Weight' => ($student['height'] ?? '—') . ' cm / ' . ($student['weight'] ?? '—') . ' kg',
        'Email' => $student['email'] ?? '—',
        'Phone' => $student['phone'] ?? '—',
        'Special Skills' => nl2br(htmlspecialchars($student['special_skills'] ?? 'None'))
    ];
    foreach ($personal_fields as $label => $value) {
        echo "<td><th>$label</th><td>$value</td></tr>";
    }
    echo '</table>';

    // Enrollment Details
    echo '<h2>Enrollment Details</h2>';
    if (!empty($enrollment)) {
        echo '<table>';
        echo '<tr<th>School Year</th><td>' . htmlspecialchars($enrollment['school_year'] ?? '—') . '</td></tr>';
        echo '<tr<th>Grade Level</th><td>' . htmlspecialchars($enrollment['grade_level'] ?? '—') . '</td></tr>';
        echo '<tr<th>Track</th><td>' . htmlspecialchars($enrollment['track'] ?? '—') . '</td></tr>';
        echo '<tr<th>Strand</th><td>' . htmlspecialchars($enrollment['strand'] ?? '—') . '</td></tr>';
        echo '<tr<th>Program</th><td>' . htmlspecialchars($enrollment['program'] ?? '—') . '</td></tr>';
        echo '<tr<th>Section</th><td>' . htmlspecialchars($enrollment['section'] ?? '—') . '</td></tr>';
        echo '<tr<th>Semester</th><td>' . htmlspecialchars($enrollment['semester'] ?? '—') . '</td></tr>';
        echo '<tr<th>Status</th><td>' . htmlspecialchars($enrollment['status'] ?? 'Active') . '</td></tr>';
        echo '<tr<th>Voucher Status</th><td>' . htmlspecialchars($enrollment['voucher_status'] ?? '—') . '</td></tr>';
        echo '<tr<th>Household ID (4Ps)</th><td>' . htmlspecialchars($enrollment['household_id'] ?? '—') . '</td></tr>';
        if (!empty($enrollment['is_transferred'])) {
            echo '<tr<th>Transferred From</th><td>' . htmlspecialchars($enrollment['previous_school_name'] ?? '—') . '</td></tr>';
            echo '<tr<th>Previous School Address</th><td>' . htmlspecialchars($enrollment['previous_school_address'] ?? '—') . '</td></tr>';
            echo '<tr<th>Previous Track/Strand/Program</th><td>' . htmlspecialchars($enrollment['previous_track'] ?? '—') . ' / ' . htmlspecialchars($enrollment['previous_strand'] ?? '—') . ' / ' . htmlspecialchars($enrollment['previous_program'] ?? '—') . '</td></tr>';
        }
        echo '</table>';
    } else {
        echo '<p>No enrollment record.</p>';
    }

    // Parents & Guardian
    echo '<h2>Parents & Guardian Information</h2>';
    if (!empty($parents)) {
        echo '<table>';
        echo '<tr<th>Father\'s Name</th><td>' . htmlspecialchars($parents['father_name'] ?? '—') . '</td></tr>';
        echo '<tr<th>Father\'s Occupation</th><td>' . htmlspecialchars($parents['father_occupation'] ?? '—') . '</td></tr>';
        echo '<tr<th>Father\'s Contact</th><td>' . htmlspecialchars($parents['father_contact'] ?? '—') . '</td></tr>';
        echo '<tr<th>Mother\'s Maiden Name</th><td>' . htmlspecialchars($parents['mother_maiden_name'] ?? '—') . '</td></tr>';
        echo '<tr<th>Mother\'s Occupation</th><td>' . htmlspecialchars($parents['mother_occupation'] ?? '—') . '</td></tr>';
        echo '<tr<th>Mother\'s Contact</th><td>' . htmlspecialchars($parents['mother_contact'] ?? '—') . '</td></tr>';
        echo '<tr<th>Average Family Income</th><td>' . ($parents['ave_family_income'] ? '₱' . number_format($parents['ave_family_income'], 2) : '—') . '</td></tr>';
        echo '<tr<th>CCT/4Ps Recipient</th><td>' . (!empty($parents['is_4ps']) ? 'Yes' : 'No') . '</td></tr>';
        echo '<tr<td colspan="2" style="background:#e9ecef;"><strong>Guardian Information</strong></td></tr>';
        echo '<tr<th>Guardian Full Name</th><td>' . htmlspecialchars($parents['guardian_fullname'] ?? '—') . '</td></tr>';
        echo '<tr<th>Relationship to Student</th><td>' . htmlspecialchars($parents['guardian_relation'] ?? '—') . '</td></tr>';
        echo '<tr<th>Guardian Contact</th><td>' . htmlspecialchars($parents['guardian_contact'] ?? '—') . '</td></tr>';
        echo '</table>';
    } else {
        echo '<p>No parents/guardian information.</p>';
    }

    // Address
    echo '<h2>Complete Address</h2>';
    if (!empty($address)) {
        echo '<table>';
        echo '<tr<th>Purok/Street</th><td>' . htmlspecialchars($address['purok_street'] ?? '—') . '</td></tr>';
        echo '<tr<th>Barangay</th><td>' . htmlspecialchars($address['barangay'] ?? '—') . '</td></tr>';
        echo '<tr<th>Town/City</th><td>' . htmlspecialchars($address['town_city'] ?? '—') . '</td></tr>';
        echo '<tr<th>Province</th><td>' . htmlspecialchars($address['province'] ?? '—') . '</td></tr>';
        echo '<tr<th>Region</th><td>' . htmlspecialchars($address['region'] ?? '—') . '</td></tr>';
        echo '<tr<th>District</th><td>' . htmlspecialchars($address['district'] ?? '—') . '</td></tr>';
        echo '<tr<th>Postal Code</th><td>' . htmlspecialchars($address['postal_code'] ?? '—') . '</td></tr>';
        echo '</table>';
    } else {
        echo '<p>No address information.</p>';
    }

    // Educational History
    echo '<h2>Educational History</h2>';
    if (!empty($education)) {
        echo '<table><thead><tr<th>Level</th><th>School Name</th><th>School Address</th><th>Year Completed</th></tr></thead><tbody>';
        foreach ($education as $edu) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($edu['level']) . '</td>';
            echo '<td>' . htmlspecialchars($edu['school_name'] ?? '—') . '</td>';
            echo '<td>' . htmlspecialchars($edu['school_address'] ?? '—') . '</td>';
            echo '<td>' . htmlspecialchars($edu['year_completed'] ?? '—') . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p>No educational records.</p>';
    }

    // Required Documents
    echo '<h2>Required Documents</h2>';
    if (!empty($documents)) {
        echo '<table><thead><tr<th>Document</th><th>Status</th></tr></thead><tbody>';
        foreach ($documents as $doc) {
            $status = (!empty($doc['submitted']) && !empty($doc['file_path'])) ? 'Submitted' : 'Missing';
            echo '<tr>';
            echo '<td>' . htmlspecialchars($doc['document_name']) . '</td>';
            echo '<td>' . $status . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p>No document requirements.</p>';
    }

    // Grades
    echo '<h2>Grades & Subjects</h2>';
    if (!empty($grades)) {
        echo '<table><thead><tr<th>Semester</th><th>Subject Code</th><th>Subject Name</th><th>Grade</th><th>Remarks</th></tr></thead><tbody>';
        foreach ($grades as $g) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($g['semester']) . '</td>';
            echo '<td>' . htmlspecialchars($g['subject_code']) . '</td>';
            echo '<td>' . htmlspecialchars($g['subject_name']) . '</td>';
            echo '<td>' . ($g['grade'] !== null ? number_format($g['grade'], 2) : '—') . '</td>';
            echo '<td>' . htmlspecialchars($g['remarks'] ?? '—') . '</td>';
            echo '</tr>';
        }
        $total = 0; $count = 0;
        foreach ($grades as $g) {
            if ($g['grade'] !== null && is_numeric($g['grade'])) {
                $total += $g['grade'];
                $count++;
            }
        }
        $gwa = $count > 0 ? number_format($total / $count, 2) : 'N/A';
        echo '<tr style="background:#f2f2f2;"><td colspan="3"><strong>General Weighted Average (GWA)</strong></td><td colspan="2"><strong>' . $gwa . '</strong></td></tr>';
        echo '</tbody></table>';
    } else {
        echo '<p>No grades recorded.</p>';
    }

    echo '<div class="footer"><p>End of Report – USAT College Enrollment System</p></div>';
    echo '</body></html>';
    exit;
}

// ====================== HANDLE DOCUMENT UPLOAD ======================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_document'])) {
    $entrance_id = (int)$_POST['entrance_id'];
    $student_id  = (int)$_POST['student_id'];

    if (isset($_FILES['document_file']) && $_FILES['document_file']['error'] === 0) {
        $allowed = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
        $ext = strtolower(pathinfo($_FILES['document_file']['name'], PATHINFO_EXTENSION));

        if (in_array($ext, $allowed)) {
            $upload_dir = __DIR__ . "/../uploads/documents/";
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

            $new_filename = "doc_" . $student_id . "_" . $entrance_id . "_" . time() . "." . $ext;
            $target_path = $upload_dir . $new_filename;
            $db_path = "uploads/documents/" . $new_filename;

            if (move_uploaded_file($_FILES['document_file']['tmp_name'], $target_path)) {
                $stmt = $conn->prepare("UPDATE entrance_documents SET 
                    submitted = 1, 
                    file_path = ?, 
                    uploaded_by = ?, 
                    uploaded_at = NOW() 
                    WHERE entrance_id = ? AND student_id = ?");
                
                $uploaded_by = $_SESSION['admin_id'];
                $stmt->bind_param("ssii", $db_path, $uploaded_by, $entrance_id, $student_id);
                
                if ($stmt->execute()) {
                    $_SESSION['success'] = "Document uploaded successfully!";
                } else {
                    $_SESSION['error'] = "Database update failed.";
                }
                $stmt->close();
            } else {
                $_SESSION['error'] = "Failed to save file.";
            }
        } else {
            $_SESSION['error'] = "Only PDF, JPG, JPEG, PNG, DOC, DOCX files allowed.";
        }
    } else {
        $_SESSION['error'] = "No file selected.";
    }
    header("Location: view_student.php?id=$student_id");
    exit();
}

// ====================== HANDLE DOCUMENT DELETE ======================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_document'])) {
    $entrance_id = (int)$_POST['entrance_id'];
    $student_id  = (int)$_POST['student_id'];

    $stmt = $conn->prepare("SELECT file_path FROM entrance_documents WHERE entrance_id = ? AND student_id = ?");
    $stmt->bind_param("ii", $entrance_id, $student_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($result && !empty($result['file_path'])) {
        $full_path = __DIR__ . "/../" . $result['file_path'];
        if (file_exists($full_path)) unlink($full_path);
    }

    $stmt = $conn->prepare("UPDATE entrance_documents SET 
        submitted = 0, 
        file_path = NULL, 
        uploaded_by = NULL, 
        uploaded_at = NULL 
        WHERE entrance_id = ? AND student_id = ?");
    
    $stmt->bind_param("ii", $entrance_id, $student_id);
    $stmt->execute();
    $stmt->close();

    $_SESSION['success'] = "Document deleted successfully!";
    header("Location: view_student.php?id=$student_id");
    exit();
}

// ====================== HANDLE STUDENT PHOTO UPLOAD ======================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_photo'])) {
    $student_id = (int)$_POST['student_id'];

    if (isset($_FILES['student_photo']) && $_FILES['student_photo']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $ext = strtolower(pathinfo($_FILES['student_photo']['name'], PATHINFO_EXTENSION));

        if (in_array($ext, $allowed)) {
            $upload_dir = __DIR__ . "/../uploads/students/";
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

            $new_filename = "student_" . $student_id . "_" . time() . "." . $ext;
            $target_path = $upload_dir . $new_filename;

            if (move_uploaded_file($_FILES['student_photo']['tmp_name'], $target_path)) {
                $stmt = $conn->prepare("UPDATE students_info SET photo = ? WHERE student_id = ?");
                $stmt->bind_param("si", $new_filename, $student_id);
                $stmt->execute();
                $stmt->close();
                $_SESSION['success'] = "Photo uploaded successfully!";
            } else {
                $_SESSION['error'] = "Failed to save photo.";
            }
        } else {
            $_SESSION['error'] = "Only JPG, JPEG, PNG files allowed.";
        }
    } else {
        $_SESSION['error'] = "No photo selected.";
    }
    header("Location: view_student.php?id=$student_id");
    exit();
}

// ====================== HANDLE DELETE STUDENT ======================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_student'])) {
    $student_id = (int)$_POST['student_id'];
    if ($student_id <= 0) {
        $_SESSION['error'] = "Invalid student ID.";
        header("Location: student_profile.php");
        exit();
    }

    $conn->begin_transaction();
    try {
        $tables = ['student_grades', 'entrance_documents', 'educational_history', 'addresses', 'parents_info', 'enrollment_form'];
        foreach ($tables as $table) {
            $stmt = $conn->prepare("DELETE FROM `$table` WHERE student_id = ?");
            $stmt->bind_param("i", $student_id);
            $stmt->execute();
            $stmt->close();
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
        $_SESSION['error'] = "Delete failed: " . htmlspecialchars($e->getMessage());
        header("Location: view_student.php?id=$student_id");
        exit();
    }
}

// ====================== HANDLE ADD GRADE ======================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_grade'])) {
    $student_id = (int)$_POST['student_id'];
    $semester = trim($_POST['semester'] ?? '');
    $subject_code = trim($_POST['subject_code'] ?? '');
    $subject_name = trim($_POST['subject_name'] ?? '');
    $grade = !empty($_POST['grade']) ? (float)$_POST['grade'] : null;
    $remarks = trim($_POST['remarks'] ?? '');

    // Get the current enrollment_id for this student
    $enroll_stmt = $conn->prepare("SELECT enrollment_id FROM enrollment_form WHERE student_id = ? ORDER BY enrollment_id DESC LIMIT 1");
    $enroll_stmt->bind_param("i", $student_id);
    $enroll_stmt->execute();
    $enroll_result = $enroll_stmt->get_result();
    $enrollment_row = $enroll_result->fetch_assoc();
    $enroll_stmt->close();

    if (!$enrollment_row) {
        $_SESSION['error'] = "No active enrollment record found for this student. Cannot add grades.";
        header("Location: view_student.php?id=$student_id");
        exit();
    }

    $enrollment_id = $enrollment_row['enrollment_id'];

    if (empty($semester) || empty($subject_code) || empty($subject_name)) {
        $_SESSION['error'] = "Semester, Subject Code, and Subject Name are required.";
    } else {
        $stmt = $conn->prepare("INSERT INTO student_grades (student_id, enrollment_id, semester, subject_code, subject_name, grade, remarks) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iisssds", $student_id, $enrollment_id, $semester, $subject_code, $subject_name, $grade, $remarks);
        if ($stmt->execute()) {
            $_SESSION['success'] = "Grade added successfully!";
        } else {
            $_SESSION['error'] = "Failed to add grade: " . $conn->error;
        }
        $stmt->close();
    }
    header("Location: view_student.php?id=$student_id");
    exit();
}

// ====================== HANDLE EDIT GRADE ======================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_grade'])) {
    $grade_id = (int)$_POST['grade_id'];
    $student_id = (int)$_POST['student_id'];
    $semester = trim($_POST['semester'] ?? '');
    $subject_code = trim($_POST['subject_code'] ?? '');
    $subject_name = trim($_POST['subject_name'] ?? '');
    $grade = !empty($_POST['grade']) ? (float)$_POST['grade'] : null;
    $remarks = trim($_POST['remarks'] ?? '');

    // Fetch current enrollment_id
    $enroll_stmt = $conn->prepare("SELECT enrollment_id FROM enrollment_form WHERE student_id = ? ORDER BY enrollment_id DESC LIMIT 1");
    $enroll_stmt->bind_param("i", $student_id);
    $enroll_stmt->execute();
    $enroll_result = $enroll_stmt->get_result();
    $enrollment_row = $enroll_result->fetch_assoc();
    $enroll_stmt->close();
    $enrollment_id = $enrollment_row ? $enrollment_row['enrollment_id'] : null;

    if ($grade_id <= 0 || empty($semester) || empty($subject_code) || empty($subject_name)) {
        $_SESSION['error'] = "Invalid data for grade update.";
    } else {
        $stmt = $conn->prepare("UPDATE student_grades SET semester=?, subject_code=?, subject_name=?, grade=?, remarks=?, enrollment_id=? WHERE grade_id=? AND student_id=?");
        $stmt->bind_param("sssdsiii", $semester, $subject_code, $subject_name, $grade, $remarks, $enrollment_id, $grade_id, $student_id);
        if ($stmt->execute()) {
            $_SESSION['success'] = "Grade updated successfully!";
        } else {
            $_SESSION['error'] = "Update failed: " . $conn->error;
        }
        $stmt->close();
    }
    header("Location: view_student.php?id=$student_id");
    exit();
}

// ====================== HANDLE DELETE GRADE ======================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_grade'])) {
    $grade_id = (int)$_POST['grade_id'];
    $student_id = (int)$_POST['student_id'];
    $stmt = $conn->prepare("DELETE FROM student_grades WHERE grade_id = ? AND student_id = ?");
    $stmt->bind_param("ii", $grade_id, $student_id);
    $stmt->execute() ? $_SESSION['success'] = "Grade deleted successfully!" : $_SESSION['error'] = "Delete failed.";
    $stmt->close();
    header("Location: view_student.php?id=$student_id");
    exit();
}

// ====================== HANDLE ADD EDUCATION ======================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_education'])) {
    $student_id = (int)$_POST['student_id'];
    $level = trim($_POST['level'] ?? '');
    $school_name = trim($_POST['school_name'] ?? '');
    $school_address = trim($_POST['school_address'] ?? '');
    $year_completed = trim($_POST['year_completed'] ?? '');

    if (empty($level) || empty($school_name)) {
        $_SESSION['error'] = "Level and School Name are required.";
    } else {
        $stmt = $conn->prepare("INSERT INTO educational_history (student_id, level, school_name, school_address, year_completed) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $student_id, $level, $school_name, $school_address, $year_completed);
        $stmt->execute() ? $_SESSION['success'] = "Educational record added!" : $_SESSION['error'] = "Failed to add record.";
        $stmt->close();
    }
    header("Location: view_student.php?id=$student_id");
    exit();
}

// ====================== HANDLE EDIT EDUCATION ======================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_education'])) {
    $edu_id = (int)$_POST['edu_id'];
    $student_id = (int)$_POST['student_id'];
    $level = trim($_POST['level'] ?? '');
    $school_name = trim($_POST['school_name'] ?? '');
    $school_address = trim($_POST['school_address'] ?? '');
    $year_completed = trim($_POST['year_completed'] ?? '');

    if ($edu_id <= 0 || empty($level) || empty($school_name)) {
        $_SESSION['error'] = "Invalid data.";
    } else {
        $stmt = $conn->prepare("UPDATE educational_history SET level=?, school_name=?, school_address=?, year_completed=? WHERE edu_id=? AND student_id=?");
        $stmt->bind_param("ssssii", $level, $school_name, $school_address, $year_completed, $edu_id, $student_id);
        $stmt->execute() ? $_SESSION['success'] = "Record updated!" : $_SESSION['error'] = "Update failed.";
        $stmt->close();
    }
    header("Location: view_student.php?id=$student_id");
    exit();
}

// ====================== HANDLE DELETE EDUCATION ======================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_education'])) {
    $edu_id = (int)$_POST['edu_id'];
    $student_id = (int)$_POST['student_id'];
    $stmt = $conn->prepare("DELETE FROM educational_history WHERE edu_id = ? AND student_id = ?");
    $stmt->bind_param("ii", $edu_id, $student_id);
    $stmt->execute() ? $_SESSION['success'] = "Record deleted!" : $_SESSION['error'] = "Delete failed.";
    $stmt->close();
    header("Location: view_student.php?id=$student_id");
    exit();
}

// ====================== FETCH STUDENT DATA ======================
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "No student ID provided.";
    header("Location: student_profile.php");
    exit();
}

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

$edu_stmt = $conn->prepare("
    SELECT edu_id, level, school_name, school_address, year_completed 
    FROM educational_history 
    WHERE student_id = ? 
    ORDER BY CASE level 
        WHEN 'Elementary' THEN 1 
        WHEN 'JHS' THEN 2 
        WHEN 'Senior High School' THEN 3 
        ELSE 4 END, year_completed DESC
");
$edu_stmt->bind_param("i", $student_id);
$edu_stmt->execute();
$education = $edu_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$edu_stmt->close();

$doc_stmt = $conn->prepare("
    SELECT entrance_id, document_name, submitted, file_path 
    FROM entrance_documents 
    WHERE student_id = ? 
    ORDER BY document_name
");
$doc_stmt->bind_param("i", $student_id);
$doc_stmt->execute();
$documents = $doc_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$doc_stmt->close();

$grades_stmt = $conn->prepare("
    SELECT grade_id, semester, subject_code, subject_name, grade, remarks 
    FROM student_grades 
    WHERE student_id = ? 
    ORDER BY semester DESC, subject_name ASC
");
$grades_stmt->bind_param("i", $student_id);
$grades_stmt->execute();
$grades_result = $grades_stmt->get_result();
$grades = [];
while ($row = $grades_result->fetch_assoc()) {
    $grades[$row['semester']][] = $row;
}
$grades_stmt->close();

// Calculate GWA
$total_subjects = 0; 
$total_grade_points = 0.0; 
$grade_count = 0;
foreach ($grades as $sem_grades) {
    foreach ($sem_grades as $g) {
        if ($g['grade'] !== null && is_numeric($g['grade'])) {
            $total_subjects++;
            $total_grade_points += (float)$g['grade'];
            $grade_count++;
        }
    }
}
$general_average = $grade_count > 0 ? round($total_grade_points / $grade_count, 2) : null;

// Determine theme from cookie or default to light
$theme = 'light';
if (isset($_COOKIE['admin_theme'])) {
    $theme = $_COOKIE['admin_theme'] === 'dark' ? 'dark' : 'light';
}
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>USAT Admin • <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-gradient-start: #1e3c72;
            --primary-gradient-end: #2b4c8c;
            --surface-ground: #f8fafc;
            --surface-card: #ffffff;
            --text-primary: #1e293b;
            --text-secondary: #475569;
            --border-light: #e2e8f0;
            --hover-shadow: 0 20px 35px -10px rgba(0,0,0,0.1);
        }

        [data-bs-theme="dark"] {
            --surface-ground: #0f172a;
            --surface-card: #1e293b;
            --text-primary: #f1f5f9;
            --text-secondary: #94a3b8;
            --border-light: #334155;
            --hover-shadow: 0 20px 35px -10px rgba(0,0,0,0.4);
        }

        * { font-family: 'Inter', sans-serif; }

        body {
            background: var(--surface-ground);
            color: var(--text-primary);
            transition: background 0.2s ease, color 0.2s ease;
        }

        /* Modern Card Design */
        .card-modern {
            background: var(--surface-card);
            border-radius: 24px;
            border: 1px solid var(--border-light);
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
            transition: all 0.25s ease;
            overflow: hidden;
            margin-bottom: 1.5rem;
        }

        .card-modern:hover {
            transform: translateY(-2px);
            box-shadow: var(--hover-shadow);
        }

        .card-header-custom {
            background: linear-gradient(135deg, var(--primary-gradient-start), var(--primary-gradient-end));
            padding: 1rem 1.5rem;
            border-bottom: none;
            color: white;
        }

        .card-header-custom h5 {
            margin: 0;
            font-weight: 600;
            font-size: 1.1rem;
            letter-spacing: -0.2px;
        }

        .card-header-custom i {
            margin-right: 8px;
            font-size: 1.2rem;
        }

        /* Profile Section */
        .profile-header {
            background: var(--surface-card);
            border-radius: 24px;
            border: 1px solid var(--border-light);
            padding: 2rem;
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .profile-avatar {
            width: 130px;
            height: 130px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid white;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.15);
            transition: transform 0.3s ease;
        }

        .profile-avatar:hover {
            transform: scale(1.02);
        }

        .badge-gwa {
            background: linear-gradient(135deg, #059669, #10b981);
            color: white;
            padding: 0.4rem 1rem;
            border-radius: 40px;
            font-weight: 500;
            font-size: 0.85rem;
            box-shadow: 0 2px 8px rgba(5,150,105,0.3);
        }

        /* Info Grid Layout */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 0.75rem 1.5rem;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            padding: 0.6rem 0;
            border-bottom: 1px solid var(--border-light);
            flex-wrap: wrap;
        }

        .info-label {
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-value {
            font-weight: 500;
            color: var(--text-primary);
            text-align: right;
            word-break: break-word;
        }

        /* Responsive Info Items */
        @media (max-width: 640px) {
            .info-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.25rem;
            }
            .info-value {
                text-align: left;
            }
        }

        /* Table Styling */
        .table-custom {
            margin-bottom: 0;
        }

        .table-custom th {
            background: var(--surface-ground);
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            border-bottom: 1px solid var(--border-light);
        }

        .table-custom td {
            vertical-align: middle;
            color: var(--text-primary);
            border-color: var(--border-light);
        }

        /* Accordion Styling */
        .accordion-modern {
            --bs-accordion-bg: transparent;
            --bs-accordion-border-color: var(--border-light);
            --bs-accordion-btn-bg: transparent;
            --bs-accordion-active-bg: transparent;
            --bs-accordion-btn-focus-box-shadow: none;
        }

        .accordion-modern .accordion-button {
            background: var(--surface-card);
            color: var(--text-primary);
            font-weight: 600;
            padding: 1rem 1.25rem;
        }

        .accordion-modern .accordion-button:not(.collapsed) {
            background: var(--surface-ground);
            color: var(--primary-gradient-start);
            box-shadow: none;
        }

        [data-bs-theme="dark"] .accordion-modern .accordion-button:not(.collapsed) {
            color: #90a4cf;
        }

        /* Buttons */
        .btn-icon {
            padding: 0.4rem 0.8rem;
            border-radius: 40px;
            font-size: 0.8rem;
            transition: all 0.2s;
        }

        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary-gradient-start), var(--primary-gradient-end));
            border: none;
            color: white;
        }

        /* Floating Action Bar */
        .action-bar {
            position: sticky;
            bottom: 20px;
            background: var(--surface-card);
            backdrop-filter: blur(12px);
            border: 1px solid var(--border-light);
            border-radius: 60px;
            padding: 0.5rem 1rem;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            display: inline-flex;
            gap: 8px;
            z-index: 100;
        }

        /* Theme Toggle */
        .theme-toggle-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: var(--surface-ground);
            border: 1px solid var(--border-light);
            color: var(--text-primary);
            transition: all 0.2s;
        }

        .theme-toggle-btn:hover {
            transform: scale(1.05);
            background: var(--primary-gradient-start);
            color: white;
        }

        /* Alert Custom */
        .alert-custom {
            border-radius: 20px;
            border: none;
            backdrop-filter: blur(8px);
        }

        /* Badge */
        .badge-status {
            padding: 0.35rem 0.9rem;
            border-radius: 30px;
            font-weight: 500;
        }

        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: var(--surface-ground);
        }
        ::-webkit-scrollbar-thumb {
            background: var(--primary-gradient-end);
            border-radius: 4px;
        }
    </style>
</head>
<body>

<div class="container py-4 py-lg-5">

    <!-- Alert Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show alert-custom shadow-sm d-flex align-items-center gap-2 mb-4">
            <i class="bi bi-check-circle-fill fs-5"></i> <?= htmlspecialchars($_SESSION['success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show alert-custom shadow-sm d-flex align-items-center gap-2 mb-4">
            <i class="bi bi-exclamation-triangle-fill fs-5"></i> <?= htmlspecialchars($_SESSION['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Profile Header Section -->
    <div class="profile-header">
        <?php 
        $photo_file = !empty($student['photo']) ? "../uploads/students/" . htmlspecialchars($student['photo']) : '';
        $photo_path = (!empty($student['photo']) && file_exists(__DIR__ . "/../uploads/students/" . $student['photo'])) 
            ? $photo_file 
            : "https://ui-avatars.com/api/?name=" . urlencode($student['first_name'] . '+' . $student['last_name']) . "&background=1e3c72&color=fff&size=130&rounded=true&bold=true"; 
        ?>
        <div class="position-relative d-inline-block mb-3">
            <img src="<?= $photo_path ?>" class="profile-avatar" alt="Student Photo">
            <button class="btn btn-sm btn-light rounded-circle position-absolute bottom-0 end-0 p-1 shadow-sm border-0" style="transform: translate(15%, 15%);" data-bs-toggle="modal" data-bs-target="#uploadPhotoModal">
                <i class="bi bi-camera-fill"></i>
            </button>
        </div>
        <h2 class="fw-bold mb-2"><?= htmlspecialchars($student['first_name'] ?? '') ?> <?= $student['middle_name'] ? htmlspecialchars(substr($student['middle_name'], 0, 1)) . '. ' : '' ?><?= htmlspecialchars($student['last_name'] ?? '') ?></h2>
        <div class="d-flex justify-content-center gap-3 flex-wrap align-items-center">
            <span class="badge bg-secondary bg-opacity-10 text-secondary px-3 py-2 rounded-pill">
                <i class="bi bi-upc-scan me-1"></i> LRN: <?= htmlspecialchars($student['lrn'] ?? 'Not Assigned') ?>
            </span>
            <?php if ($general_average !== null): ?>
                <span class="badge-gwa"><i class="bi bi-star-fill me-1"></i> GWA: <?= number_format($general_average, 2) ?></span>
            <?php endif; ?>
            <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill">
                <i class="bi bi-person-badge me-1"></i> ID: <?= $student['student_id'] ?>
            </span>
        </div>
    </div>

    <!-- Personal Information Card -->
    <div class="card-modern">
        <div class="card-header-custom">
            <h5><i class="bi bi-person-badge"></i> Personal Information</h5>
        </div>
        <div class="p-4">
            <div class="info-grid">
                <div class="info-item"><span class="info-label">Student ID</span><span class="info-value"><?= $student['student_id'] ?></span></div>
                <div class="info-item"><span class="info-label">LRN</span><span class="info-value"><?= htmlspecialchars($student['lrn'] ?? '—') ?></span></div>
                <div class="info-item"><span class="info-label">Full Name</span><span class="info-value"><?= htmlspecialchars($student['last_name'] ?? '') ?>, <?= htmlspecialchars($student['first_name'] ?? '') ?> <?= htmlspecialchars($student['middle_name'] ?? '') ?></span></div>
                <div class="info-item"><span class="info-label">Nickname</span><span class="info-value"><?= htmlspecialchars($student['nick_name'] ?? '—') ?></span></div>
                <div class="info-item"><span class="info-label">Extension</span><span class="info-value"><?= htmlspecialchars($student['ext_name'] ?? '—') ?></span></div>
                <div class="info-item"><span class="info-label">Sex</span><span class="info-value"><?= htmlspecialchars($student['sex'] ?? '—') ?></span></div>
                <div class="info-item"><span class="info-label">Birth Date</span><span class="info-value"><?= htmlspecialchars($student['birth_date'] ?? '—') ?></span></div>
                <div class="info-item"><span class="info-label">Age</span><span class="info-value"><?= htmlspecialchars($student['age'] ?? '—') ?></span></div>
                <div class="info-item"><span class="info-label">Civil Status</span><span class="info-value"><?= htmlspecialchars($student['civil_status'] ?? '—') ?></span></div>
                <div class="info-item"><span class="info-label">Nationality</span><span class="info-value"><?= htmlspecialchars($student['nationality'] ?? '—') ?></span></div>
                <div class="info-item"><span class="info-label">Religion</span><span class="info-value"><?= htmlspecialchars($student['religion'] ?? '—') ?></span></div>
                <div class="info-item"><span class="info-label">Height / Weight</span><span class="info-value"><?= htmlspecialchars($student['height'] ?? '—') ?> cm / <?= htmlspecialchars($student['weight'] ?? '—') ?> kg</span></div>
                <div class="info-item"><span class="info-label">Email</span><span class="info-value"><?= htmlspecialchars($student['email'] ?? '—') ?></span></div>
                <div class="info-item"><span class="info-label">Phone</span><span class="info-value"><?= htmlspecialchars($student['phone'] ?? '—') ?></span></div>
                <div class="info-item"><span class="info-label">Special Skills</span><span class="info-value"><?= nl2br(htmlspecialchars($student['special_skills'] ?? 'None')) ?></span></div>
            </div>
        </div>
    </div>

    <!-- Enrollment Details Card -->
    <div class="card-modern">
        <div class="card-header-custom">
            <h5><i class="bi bi-mortarboard"></i> Enrollment Details</h5>
        </div>
        <div class="p-4">
            <?php if (!empty($enrollment)): ?>
                <div class="info-grid">
                    <div class="info-item"><span class="info-label">School Year</span><span class="info-value"><?= htmlspecialchars($enrollment['school_year'] ?? '—') ?></span></div>
                    <div class="info-item"><span class="info-label">Grade Level</span><span class="info-value"><?= htmlspecialchars($enrollment['grade_level'] ?? '—') ?></span></div>
                    <div class="info-item"><span class="info-label">Track</span><span class="info-value"><?= htmlspecialchars($enrollment['track'] ?? '—') ?></span></div>
                    <div class="info-item"><span class="info-label">Strand</span><span class="info-value"><?= htmlspecialchars($enrollment['strand'] ?? '—') ?></span></div>
                    <div class="info-item"><span class="info-label">Program</span><span class="info-value"><?= htmlspecialchars($enrollment['program'] ?? '—') ?></span></div>
                    <div class="info-item"><span class="info-label">Section</span><span class="info-value"><?= htmlspecialchars($enrollment['section'] ?? '—') ?></span></div>
                    <div class="info-item"><span class="info-label">Semester</span><span class="info-value"><?= htmlspecialchars($enrollment['semester'] ?? '—') ?></span></div>
                    <div class="info-item"><span class="info-label">Status</span><span class="info-value"><span class="badge-status <?= ($enrollment['status'] ?? 'Active') == 'Active' ? 'bg-success' : 'bg-warning text-dark' ?>"><?= htmlspecialchars($enrollment['status'] ?? 'Active') ?></span></span></div>
                    <div class="info-item"><span class="info-label">Voucher</span><span class="info-value"><?= htmlspecialchars($enrollment['voucher_status'] ?? '—') ?></span></div>
                    <div class="info-item"><span class="info-label">4Ps ID</span><span class="info-value"><?= htmlspecialchars($enrollment['household_id'] ?? '—') ?></span></div>
                </div>
                <?php if (!empty($enrollment['is_transferred'])): ?>
                    <hr class="my-3 opacity-25">
                    <div class="info-grid">
                        <div class="info-item"><span class="info-label">Transferred From</span><span class="info-value"><?= htmlspecialchars($enrollment['previous_school_name'] ?? '—') ?></span></div>
                        <div class="info-item"><span class="info-label">Previous Address</span><span class="info-value"><?= htmlspecialchars($enrollment['previous_school_address'] ?? '—') ?></span></div>
                        <div class="info-item"><span class="info-label">Previous Program</span><span class="info-value"><?= htmlspecialchars($enrollment['previous_track'] ?? '—') ?> / <?= htmlspecialchars($enrollment['previous_strand'] ?? '—') ?> / <?= htmlspecialchars($enrollment['previous_program'] ?? '—') ?></span></div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="alert alert-warning mb-0">No enrollment record found.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-4">
        <!-- Parents Card -->
        <div class="col-md-6">
            <div class="card-modern h-100">
                <div class="card-header-custom">
                    <h5><i class="bi bi-people"></i> Parents Information</h5>
                </div>
                <div class="p-4">
                    <?php if (!empty($parents)): ?>
                        <div class="info-grid">
                            <div class="info-item"><span class="info-label">Father</span><span class="info-value"><?= htmlspecialchars($parents['father_name'] ?? '—') ?></span></div>
                            <div class="info-item"><span class="info-label">Father's Occupation</span><span class="info-value"><?= htmlspecialchars($parents['father_occupation'] ?? '—') ?></span></div>
                            <div class="info-item"><span class="info-label">Father's Contact</span><span class="info-value"><?= htmlspecialchars($parents['father_contact'] ?? '—') ?></span></div>
                            <div class="info-item"><span class="info-label">Mother (Maiden)</span><span class="info-value"><?= htmlspecialchars($parents['mother_maiden_name'] ?? '—') ?></span></div>
                            <div class="info-item"><span class="info-label">Mother's Occupation</span><span class="info-value"><?= htmlspecialchars($parents['mother_occupation'] ?? '—') ?></span></div>
                            <div class="info-item"><span class="info-label">Mother's Contact</span><span class="info-value"><?= htmlspecialchars($parents['mother_contact'] ?? '—') ?></span></div>
                            <div class="info-item"><span class="info-label">Family Income</span><span class="info-value"><?= $parents['ave_family_income'] ? '₱' . number_format($parents['ave_family_income'], 2) : '—' ?></span></div>
                            <div class="info-item"><span class="info-label">4Ps Recipient</span><span class="info-value"><?= !empty($parents['is_4ps']) ? 'Yes' : 'No' ?></span></div>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No parents information available.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Guardian Card -->
        <div class="col-md-6">
            <div class="card-modern h-100">
                <div class="card-header-custom">
                    <h5><i class="bi bi-shield-plus"></i> Guardian Information</h5>
                </div>
                <div class="p-4">
                    <?php if (!empty($parents)): ?>
                        <div class="info-grid">
                            <div class="info-item"><span class="info-label">Full Name</span><span class="info-value"><?= htmlspecialchars($parents['guardian_fullname'] ?? '—') ?></span></div>
                            <div class="info-item"><span class="info-label">Relationship</span><span class="info-value"><?= htmlspecialchars($parents['guardian_relation'] ?? '—') ?></span></div>
                            <div class="info-item"><span class="info-label">Contact Number</span><span class="info-value"><?= htmlspecialchars($parents['guardian_contact'] ?? '—') ?></span></div>
                        </div>
                    <?php else: ?>
                        <p class="text-muted">No guardian information available.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Address Card -->
    <div class="card-modern mt-4">
        <div class="card-header-custom">
            <h5><i class="bi bi-geo-alt"></i> Complete Address</h5>
        </div>
        <div class="p-4">
            <?php if (!empty($address)): ?>
                <div class="row">
                    <div class="col-md-6">
                        <div class="info-item"><span class="info-label">Purok/Street</span><span class="info-value"><?= htmlspecialchars($address['purok_street'] ?? '—') ?></span></div>
                        <div class="info-item"><span class="info-label">Barangay</span><span class="info-value"><?= htmlspecialchars($address['barangay'] ?? '—') ?></span></div>
                        <div class="info-item"><span class="info-label">Town/City</span><span class="info-value"><?= htmlspecialchars($address['town_city'] ?? '—') ?></span></div>
                    </div>
                    <div class="col-md-6">
                        <div class="info-item"><span class="info-label">Province</span><span class="info-value"><?= htmlspecialchars($address['province'] ?? '—') ?></span></div>
                        <div class="info-item"><span class="info-label">Region</span><span class="info-value"><?= htmlspecialchars($address['region'] ?? '—') ?></span></div>
                        <div class="info-item"><span class="info-label">District</span><span class="info-value"><?= htmlspecialchars($address['district'] ?? '—') ?></span></div>
                        <div class="info-item"><span class="info-label">Postal Code</span><span class="info-value"><?= htmlspecialchars($address['postal_code'] ?? '—') ?></span></div>
                    </div>
                </div>
            <?php else: ?>
                <p class="text-muted">No address information available.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Required Documents Card -->
    <div class="card-modern mt-4">
        <div class="card-header-custom d-flex justify-content-between align-items-center">
            <h5><i class="bi bi-files"></i> Required Documents</h5>
        </div>
        <div class="p-0">
            <?php if (!empty($documents)): ?>
                <div class="table-responsive">
                    <table class="table table-custom mb-0">
                        <thead>
                            <tr><th>Document Name</th><th>Status</th><th class="text-end">Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documents as $doc): $isSubmitted = !empty($doc['submitted']) && !empty($doc['file_path']); ?>
                                <tr>
                                    <td><i class="bi bi-file-earmark-text me-2 text-secondary"></i> <?= htmlspecialchars($doc['document_name']) ?></td>
                                    <td><span class="badge-status <?= $isSubmitted ? 'bg-success' : 'bg-secondary' ?>"><?= $isSubmitted ? 'Submitted' : 'Missing' ?></span></td>
                                    <td class="text-end">
                                        <?php if ($isSubmitted && !empty($doc['file_path'])): ?>
                                            <a href="../<?= htmlspecialchars($doc['file_path']) ?>" class="btn btn-sm btn-outline-primary btn-icon me-1" target="_blank"><i class="bi bi-eye"></i> View</a>
                                            <button class="btn btn-sm btn-outline-danger btn-icon delete-doc-btn" data-entrance-id="<?= $doc['entrance_id'] ?>" data-doc-name="<?= htmlspecialchars($doc['document_name']) ?>"><i class="bi bi-trash3"></i> Delete</button>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-primary btn-icon upload-doc-btn" data-entrance-id="<?= $doc['entrance_id'] ?>" data-doc-name="<?= htmlspecialchars($doc['document_name']) ?>"><i class="bi bi-upload"></i> Upload</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="p-4 text-center text-muted">No document requirements configured.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Educational History Card -->
    <div class="card-modern mt-4">
        <div class="card-header-custom d-flex justify-content-between align-items-center">
            <h5><i class="bi bi-book"></i> Educational History</h5>
            <button class="btn btn-sm btn-light rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#addEducationModal"><i class="bi bi-plus-lg"></i> Add Record</button>
        </div>
        <div class="p-0">
            <?php if (!empty($education)): ?>
                <div class="table-responsive">
                    <table class="table table-custom mb-0">
                        <thead>
                            <tr><th>Level</th><th>School Name</th><th>Address</th><th>Year Completed</th><th class="text-end">Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($education as $edu): ?>
                                <tr>
                                    <td class="fw-medium"><?= htmlspecialchars($edu['level']) ?></td>
                                    <td><?= htmlspecialchars($edu['school_name']) ?></td>
                                    <td><?= htmlspecialchars($edu['school_address']) ?></td>
                                    <td><?= htmlspecialchars($edu['year_completed']) ?></td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-warning btn-icon edit-education-btn" data-bs-toggle="modal" data-bs-target="#editEducationModal"
                                            data-edu-id="<?= $edu['edu_id'] ?>" data-level="<?= htmlspecialchars($edu['level']) ?>"
                                            data-school="<?= htmlspecialchars($edu['school_name']) ?>" data-address="<?= htmlspecialchars($edu['school_address']) ?>"
                                            data-year="<?= htmlspecialchars($edu['year_completed']) ?>"><i class="bi bi-pencil"></i> Edit</button>
                                        <button class="btn btn-sm btn-outline-danger btn-icon delete-education-btn" data-bs-toggle="modal" data-bs-target="#deleteEducationModal"
                                            data-edu-id="<?= $edu['edu_id'] ?>" data-school="<?= htmlspecialchars($edu['school_name']) ?>"><i class="bi bi-trash3"></i> Delete</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="p-4 text-center text-muted">No educational records yet.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Grades Card -->
    <div class="card-modern mt-4">
        <div class="card-header-custom d-flex justify-content-between align-items-center">
            <h5><i class="bi bi-bar-chart-steps"></i> Grades & Subjects</h5>
            <button class="btn btn-sm btn-light rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#addGradeModal"><i class="bi bi-plus-lg"></i> Add Grade</button>
        </div>
        <div class="p-0">
            <?php if (!empty($grades)): ?>
                <div class="accordion accordion-modern" id="gradesAccordion">
                    <?php foreach ($grades as $sem => $sem_grades): ?>
                        <div class="accordion-item border-0 border-bottom">
                            <h2 class="accordion-header" id="heading<?= md5($sem) ?>">
                                <button class="accordion-button <?= $sem !== array_key_first($grades) ? 'collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= md5($sem) ?>" aria-expanded="<?= $sem === array_key_first($grades) ? 'true' : 'false' ?>" aria-controls="collapse<?= md5($sem) ?>">
                                    <i class="bi bi-calendar-week me-2"></i> <?= htmlspecialchars($sem) ?>
                                </button>
                            </h2>
                            <div id="collapse<?= md5($sem) ?>" class="accordion-collapse collapse <?= $sem === array_key_first($grades) ? 'show' : '' ?>" data-bs-parent="#gradesAccordion">
                                <div class="accordion-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-custom mb-0">
                                            <thead>
                                                <tr><th>Code</th><th>Subject</th><th>Grade</th><th>Remarks</th><th class="text-end">Actions</th></tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($sem_grades as $g): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($g['subject_code']) ?></td>
                                                        <td><?= htmlspecialchars($g['subject_name']) ?></td>
                                                        <td><?= $g['grade'] !== null ? number_format($g['grade'], 2) : '—' ?></td>
                                                        <td><?= htmlspecialchars($g['remarks'] ?? '—') ?></td>
                                                        <td class="text-end">
                                                            <button class="btn btn-sm btn-outline-warning btn-icon edit-grade-btn" data-bs-toggle="modal" data-bs-target="#editGradeModal"
                                                                data-grade-id="<?= $g['grade_id'] ?>" data-semester="<?= htmlspecialchars($g['semester']) ?>"
                                                                data-code="<?= htmlspecialchars($g['subject_code']) ?>" data-name="<?= htmlspecialchars($g['subject_name']) ?>"
                                                                data-grade="<?= $g['grade'] ?? '' ?>" data-remarks="<?= htmlspecialchars($g['remarks'] ?? '') ?>"><i class="bi bi-pencil"></i> Edit</button>
                                                            <button class="btn btn-sm btn-outline-danger btn-icon delete-grade-btn" data-bs-toggle="modal" data-bs-target="#deleteGradeModal"
                                                                data-grade-id="<?= $g['grade_id'] ?>" data-subject="<?= htmlspecialchars($g['subject_name']) ?>"><i class="bi bi-trash3"></i> Delete</button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="p-4 text-center text-muted">No grades recorded yet.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Floating Action Bar -->
    <div class="d-flex justify-content-center mt-5">
        <div class="action-bar">
            <a href="edit_student.php?id=<?= $student_id ?>" class="btn btn-warning btn-icon px-3"><i class="bi bi-pencil-square"></i> Edit</a>
            <a href="student_profile.php" class="btn btn-secondary btn-icon px-3"><i class="bi bi-arrow-left"></i> Back</a>
            <button class="btn btn-danger btn-icon px-3" data-bs-toggle="modal" data-bs-target="#deleteStudentModal"><i class="bi bi-trash3"></i> Delete</button>
            <a href="?id=<?= $student_id ?>&export_word=1" class="btn btn-outline-success btn-icon px-3"><i class="bi bi-file-word"></i> Word</a>
            <button class="btn btn-outline-primary btn-icon px-3" id="printBtn"><i class="bi bi-printer"></i> Print</button>
            <button class="theme-toggle-btn" id="themeToggleBtn"><i class="bi bi-moon-stars-fill" id="themeIcon"></i></button>
        </div>
    </div>
</div>

<!-- Modals (same as before, omitted for brevity, but they remain functional) -->

<!-- Photo Upload Modal -->
<div class="modal fade" id="uploadPhotoModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-0"><h5 class="modal-title"><i class="bi bi-camera"></i> Change Student Photo</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <form method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="upload_photo" value="1">
                    <input type="hidden" name="student_id" value="<?= $student_id ?>">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Select new photo (JPG, JPEG, PNG)</label>
                        <input type="file" name="student_photo" class="form-control" accept="image/jpeg,image/png" required>
                        <div class="form-text">Recommended size: 500x500px, max 2MB</div>
                    </div>
                </div>
                <div class="modal-footer border-0"><button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary rounded-pill px-4">Upload Photo</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Upload Document Modal -->
<div class="modal fade" id="uploadDocumentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-0"><h5 class="modal-title"><i class="bi bi-cloud-upload"></i> Upload Document</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <form id="documentUploadForm" enctype="multipart/form-data">
                    <input type="hidden" name="upload_document" value="1"><input type="hidden" name="student_id" value="<?= $student_id ?>"><input type="hidden" name="entrance_id" id="modal_entrance_id">
                    <div class="mb-3"><label class="form-label fw-semibold">Document Name</label><input type="text" class="form-control bg-light" id="modal_document_name" readonly></div>
                    <div class="mb-3"><label class="form-label fw-semibold">Select File</label><input type="file" class="form-control" name="document_file" id="document_file" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required></div>
                    <div id="uploadMessage"></div>
                </form>
            </div>
            <div class="modal-footer border-0"><button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-primary rounded-pill px-4" id="btnUploadDocument">Upload Now</button></div>
        </div>
    </div>
</div>

<!-- Delete Document Modal -->
<div class="modal fade" id="deleteDocModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg"><div class="modal-header bg-danger text-white border-0"><h5 class="modal-title"><i class="bi bi-exclamation-triangle"></i> Delete Document</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
        <div class="modal-body"><p>Permanently delete <strong id="delete_doc_name"></strong>? This action cannot be undone.</p></div>
        <div class="modal-footer border-0"><button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Cancel</button>
            <form method="POST"><input type="hidden" name="delete_document" value="1"><input type="hidden" name="student_id" value="<?= $student_id ?>"><input type="hidden" name="entrance_id" id="delete_entrance_id"><button type="submit" class="btn btn-danger rounded-pill px-4">Yes, Delete</button></form>
        </div></div>
    </div>
</div>

<!-- Delete Student Modal -->
<div class="modal fade" id="deleteStudentModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content rounded-4 border-0 shadow-lg"><div class="modal-header bg-danger text-white border-0"><h5 class="modal-title"><i class="bi bi-exclamation-triangle-fill"></i> Delete Student</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body"><p class="fw-bold">Are you sure you want to permanently delete this student?</p><p><strong><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></strong> (LRN: <?= htmlspecialchars($student['lrn'] ?? '—') ?>)</p><p class="text-danger small">This action cannot be undone. All related records will be removed.</p></div><div class="modal-footer border-0"><button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Cancel</button><form method="POST"><input type="hidden" name="delete_student" value="1"><input type="hidden" name="student_id" value="<?= $student_id ?>"><button type="submit" class="btn btn-danger rounded-pill px-4">Yes, Delete Student</button></form></div></div></div></div>

<!-- Add Grade Modal -->
<div class="modal fade" id="addGradeModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content rounded-4 border-0 shadow-lg"><div class="modal-header bg-primary text-white border-0"><h5 class="modal-title"><i class="bi bi-plus-circle"></i> Add Grade</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><form method="POST"><div class="modal-body"><input type="hidden" name="student_id" value="<?= $student_id ?>"><div class="mb-3"><label class="form-label fw-semibold">Semester</label><input type="text" name="semester" class="form-control" placeholder="e.g., 1st Semester, 2nd Semester" required></div><div class="mb-3"><label class="form-label fw-semibold">Subject Code</label><input type="text" name="subject_code" class="form-control" placeholder="e.g., MATH101" required></div><div class="mb-3"><label class="form-label fw-semibold">Subject Name</label><input type="text" name="subject_name" class="form-control" placeholder="e.g., General Mathematics" required></div><div class="mb-3"><label class="form-label fw-semibold">Grade</label><input type="number" step="0.01" name="grade" class="form-control" placeholder="0.00 - 100.00"></div><div class="mb-3"><label class="form-label fw-semibold">Remarks</label><textarea name="remarks" class="form-control" rows="2" placeholder="Optional remarks"></textarea></div></div><div class="modal-footer border-0"><button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Cancel</button><button type="submit" name="add_grade" class="btn btn-primary rounded-pill px-4">Save Grade</button></div></form></div></div></div>

<!-- Edit Grade Modal -->
<div class="modal fade" id="editGradeModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content rounded-4 border-0 shadow-lg"><div class="modal-header bg-primary text-white border-0"><h5 class="modal-title"><i class="bi bi-pencil-square"></i> Edit Grade</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><form method="POST"><div class="modal-body"><input type="hidden" name="grade_id" id="edit_grade_id"><input type="hidden" name="student_id" value="<?= $student_id ?>"><div class="mb-3"><label class="form-label fw-semibold">Semester</label><input type="text" name="semester" id="edit_semester" class="form-control" required></div><div class="mb-3"><label class="form-label fw-semibold">Subject Code</label><input type="text" name="subject_code" id="edit_subject_code" class="form-control" required></div><div class="mb-3"><label class="form-label fw-semibold">Subject Name</label><input type="text" name="subject_name" id="edit_subject_name" class="form-control" required></div><div class="mb-3"><label class="form-label fw-semibold">Grade</label><input type="number" step="0.01" name="grade" id="edit_grade" class="form-control"></div><div class="mb-3"><label class="form-label fw-semibold">Remarks</label><textarea name="remarks" id="edit_remarks" class="form-control" rows="2"></textarea></div></div><div class="modal-footer border-0"><button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Cancel</button><button type="submit" name="edit_grade" class="btn btn-primary rounded-pill px-4">Update Grade</button></div></form></div></div></div>

<!-- Delete Grade Modal -->
<div class="modal fade" id="deleteGradeModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content rounded-4 border-0 shadow-lg"><div class="modal-header bg-danger text-white border-0"><h5 class="modal-title"><i class="bi bi-trash3"></i> Delete Grade</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body"><p>Remove grade for <strong id="delete_subject_name"></strong>? This action cannot be undone.</p></div><div class="modal-footer border-0"><button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Cancel</button><form method="POST"><input type="hidden" name="grade_id" id="delete_grade_id"><input type="hidden" name="student_id" value="<?= $student_id ?>"><button type="submit" name="delete_grade" class="btn btn-danger rounded-pill px-4">Delete Permanently</button></form></div></div></div></div>

<!-- Add Education Modal -->
<div class="modal fade" id="addEducationModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content rounded-4 border-0 shadow-lg"><div class="modal-header bg-primary text-white border-0"><h5 class="modal-title"><i class="bi bi-plus-circle"></i> Add Educational Record</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><form method="POST"><div class="modal-body"><input type="hidden" name="student_id" value="<?= $student_id ?>"><div class="mb-3"><label class="form-label fw-semibold">Level</label><input type="text" name="level" class="form-control" placeholder="e.g., Elementary, JHS, Senior High School" required></div><div class="mb-3"><label class="form-label fw-semibold">School Name</label><input type="text" name="school_name" class="form-control" required></div><div class="mb-3"><label class="form-label fw-semibold">School Address</label><input type="text" name="school_address" class="form-control"></div><div class="mb-3"><label class="form-label fw-semibold">Year Completed</label><input type="text" name="year_completed" class="form-control" placeholder="e.g., 2020"></div></div><div class="modal-footer border-0"><button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Cancel</button><button type="submit" name="add_education" class="btn btn-primary rounded-pill px-4">Add Record</button></div></form></div></div></div>

<!-- Edit Education Modal -->
<div class="modal fade" id="editEducationModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content rounded-4 border-0 shadow-lg"><div class="modal-header bg-primary text-white border-0"><h5 class="modal-title"><i class="bi bi-pencil-square"></i> Edit Educational Record</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><form method="POST"><div class="modal-body"><input type="hidden" name="edu_id" id="edit_edu_id"><input type="hidden" name="student_id" value="<?= $student_id ?>"><div class="mb-3"><label class="form-label fw-semibold">Level</label><input type="text" name="level" id="edit_level" class="form-control" required></div><div class="mb-3"><label class="form-label fw-semibold">School Name</label><input type="text" name="school_name" id="edit_school_name" class="form-control" required></div><div class="mb-3"><label class="form-label fw-semibold">School Address</label><input type="text" name="school_address" id="edit_school_address" class="form-control"></div><div class="mb-3"><label class="form-label fw-semibold">Year Completed</label><input type="text" name="year_completed" id="edit_year_completed" class="form-control"></div></div><div class="modal-footer border-0"><button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Cancel</button><button type="submit" name="edit_education" class="btn btn-primary rounded-pill px-4">Update Record</button></div></form></div></div></div>

<!-- Delete Education Modal -->
<div class="modal fade" id="deleteEducationModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content rounded-4 border-0 shadow-lg"><div class="modal-header bg-danger text-white border-0"><h5 class="modal-title"><i class="bi bi-trash3"></i> Delete Educational Record</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body"><p>Delete record for <strong id="delete_education_school"></strong>? This action cannot be undone.</p></div><div class="modal-footer border-0"><button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">Cancel</button><form method="POST"><input type="hidden" name="edu_id" id="delete_edu_id_input"><input type="hidden" name="student_id" value="<?= $student_id ?>"><button type="submit" name="delete_education" class="btn btn-danger rounded-pill px-4">Delete Permanently</button></form></div></div></div></div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
// Persistent Theme Toggle with Cookie
const themeToggleBtn = document.getElementById('themeToggleBtn');
const themeIcon = document.getElementById('themeIcon');
const htmlElement = document.documentElement;

function setTheme(theme) {
    htmlElement.setAttribute('data-bs-theme', theme);
    if (theme === 'dark') {
        themeIcon.classList.remove('bi-moon-stars-fill');
        themeIcon.classList.add('bi-sun-fill');
    } else {
        themeIcon.classList.remove('bi-sun-fill');
        themeIcon.classList.add('bi-moon-stars-fill');
    }
    // Save to cookie for 1 year
    document.cookie = `admin_theme=${theme}; path=/; max-age=${60*60*24*365}`;
}

// Load saved theme from cookie
function loadTheme() {
    const match = document.cookie.match(/admin_theme=([^;]+)/);
    const savedTheme = match ? match[1] : 'light';
    setTheme(savedTheme);
}

loadTheme();

if (themeToggleBtn) {
    themeToggleBtn.addEventListener('click', () => {
        const currentTheme = htmlElement.getAttribute('data-bs-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        setTheme(newTheme);
    });
}

// Print functionality
document.getElementById('printBtn')?.addEventListener('click', function() {
    const originalTitle = document.title;
    document.title = "Student Profile - <?= addslashes($student['first_name'] . ' ' . $student['last_name']) ?>";
    window.print();
    document.title = originalTitle;
});

// Document upload handlers
$(document).ready(function() {
    $('.upload-doc-btn').on('click', function() {
        $('#modal_entrance_id').val($(this).data('entrance-id'));
        $('#modal_document_name').val($(this).data('doc-name'));
        $('#uploadMessage').html('');
        $('#document_file').val('');
        $('#uploadDocumentModal').modal('show');
    });
    $('.delete-doc-btn').on('click', function() {
        $('#delete_entrance_id').val($(this).data('entrance-id'));
        $('#delete_doc_name').text($(this).data('doc-name'));
        $('#deleteDocModal').modal('show');
    });
    $('#btnUploadDocument').on('click', function() {
        const formData = new FormData($('#documentUploadForm')[0]);
        $.ajax({
            url: 'view_student.php',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            beforeSend: function() { $('#btnUploadDocument').prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Uploading...'); },
            success: function() { $('#uploadMessage').html('<div class="alert alert-success">Upload successful! Refreshing...</div>'); setTimeout(() => location.reload(), 1500); },
            error: function() { $('#uploadMessage').html('<div class="alert alert-danger">Upload failed.</div>'); $('#btnUploadDocument').prop('disabled', false).html('Upload Now'); }
        });
    });
});

// Grade and Education modal data population
document.querySelectorAll('.edit-grade-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('edit_grade_id').value = this.dataset.gradeId;
        document.getElementById('edit_semester').value = this.dataset.semester;
        document.getElementById('edit_subject_code').value = this.dataset.code;
        document.getElementById('edit_subject_name').value = this.dataset.name;
        document.getElementById('edit_grade').value = this.dataset.grade || '';
        document.getElementById('edit_remarks').value = this.dataset.remarks || '';
    });
});
document.querySelectorAll('.delete-grade-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('delete_grade_id').value = this.dataset.gradeId;
        document.getElementById('delete_subject_name').textContent = this.dataset.subject;
    });
});
document.querySelectorAll('.edit-education-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('edit_edu_id').value = this.dataset.eduId;
        document.getElementById('edit_level').value = this.dataset.level;
        document.getElementById('edit_school_name').value = this.dataset.school;
        document.getElementById('edit_school_address').value = this.dataset.address || '';
        document.getElementById('edit_year_completed').value = this.dataset.year || '';
    });
});
document.querySelectorAll('.delete-education-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('delete_edu_id_input').value = this.dataset.eduId;
        document.getElementById('delete_education_school').textContent = this.dataset.school;
    });
});
</script>
</body>
</html>