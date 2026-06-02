<?php
session_start();
include __DIR__ . "/../config/db.php";

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isset($_SESSION['lang'])) {
    $browserLang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '', 0, 2);
    $_SESSION['lang'] = in_array($browserLang, ['tl', 'fil']) ? 'fil' : 'en';
}
if (isset($_GET['lang'])) $_SESSION['lang'] = $_GET['lang'];
$lang = $_SESSION['lang'];

$t = [
    'en' => [
        'school' => 'USAT COLLEGE SAGAY CITY INC.',
        'form' => 'Student Enrollment Form',
        'ay' => 'Academic Year 2026–2027',
        'student' => 'Student Information',
        'parents' => 'Parents & Guardian Information',
        'address' => 'Complete Home Address',
        'education' => 'Educational History',
        'enrollment' => 'Enrollment Details',
        'voucher_question' => 'Are you a CCT/4Ps recipient? *',
        'yes' => 'YES', 'no' => 'NO',
        'entrance_data' => 'Entrance Data Presented (select at least one) *',
        'good_moral' => 'Good Moral Certificate',
        'jhs_certificate' => 'Junior High School Certificate (Original)',
        'birth_certificate' => 'NSO/PSA Birth Certificate (Original)',
        'pictures_2x2' => '2 pcs. 2×2 picture',
        'lrn' => 'LRN *', 'lname' => 'Last Name *', 'fname' => 'First Name *', 'mname' => 'Middle Name',
        'nick' => 'Nickname', 'ext' => 'Extension Name', 'sex' => 'Sex *',
        'male' => 'Male', 'female' => 'Female',
        'bdate' => 'Birth Date *', 'age' => 'Age *',
        'civil' => 'Civil Status', 'nation' => 'Nationality', 'religion' => 'Religion',
        'height' => 'Height (cm)', 'weight' => 'Weight (kg)',
        'email' => 'Email', 'phone' => 'Phone',
        'skills' => 'Special Skills',
        'father' => 'Father Name',
        'fjob' => 'Father Occupation',
        'fcontact' => 'Father Contact Number',
        'mother' => 'Mother Maiden Name',
        'mjob' => 'Mother Occupation',
        'mcontact' => 'Mother Contact Number',
        'income' => 'Family Income',
        'guardian' => 'Guardian Information',
        'guardian_name' => 'Guardian Full Name',
        'guardian_relation' => 'Relationship to Student',
        'guardian_contact' => 'Guardian Contact Number',
        'street' => 'Purok / Street', 'barangay' => 'Barangay', 'city' => 'Town / City',
        'province' => 'Province', 'region' => 'Region', 'district' => 'District', 'postal' => 'Postal Code',
        'elem' => 'Elementary', 'jhs' => 'Junior High School', 'trans' => 'Transferred',
        'school_name' => 'School Name', 'school_addr' => 'School Address', 'year_comp' => 'Year Completed',
        'add_school' => 'Add School', 'sy' => 'School Year *', 'grade' => 'Grade Level *',
        'semester' => 'Semester *', 'track' => 'Track', 'strand' => 'Strand *', 'program' => 'Program *',
        'household' => 'Household ID',
        'submit' => 'Submit Enrollment', 'back' => 'Back',
        'required' => 'This field is required',
        'invalid_lrn' => 'LRN must be 12 digits',
        'invalid_name' => 'Invalid name format',
        'invalid_age' => 'Age must be between 14 and 25',
        'invalid_email' => 'Invalid email format',
        'invalid_phone' => 'Phone must be 11 digits (09XXXXXXXXX)',
        'select_one_entrance' => 'Please select at least one entrance document',

        'transferred_in' => 'Transferred IN from another school',
        'prev_school_name' => 'Name of Previous School',
        'prev_school_address' => 'Address of Previous School',
        'prev_track' => 'Previous TRACK',
        'prev_strand' => 'Previous STRAND',
        'prev_program' => 'Previous PROGRAM',
        'prev_year_completed' => 'Year Completed (Previous School)',
        'voucher_qualified' => 'Voucher Status (for transferee)',
        'qualified_voucher' => 'Qualified Voucher Recipient',
        'not_qualified_voucher' => 'Not Qualified Voucher Recipient',
    ],
    'fil' => [
        'school' => 'USAT Senior High School',
        'form' => 'Porma ng Pagpaparehistro ng Mag-aaral',
        'ay' => 'Taong Panuruan 2025–2026',
        'student' => 'Impormasyon ng Mag-aaral',
        'parents' => 'Impormasyon ng mga Magulang at Guardian',
        'address' => 'Tirahan',
        'education' => 'Kasaysayang Pang-edukasyon',
        'enrollment' => 'Detalye ng Pag-enroll',
        'voucher_question' => 'Ikaw ba ay tumatanggap ng CCT/4Ps? *',
        'yes' => 'OO', 'no' => 'HINDI',
        'entrance_data' => 'Datos na Iniharap sa Pagpasok (pumili ng kahit isa) *',
        'good_moral' => 'Sertipiko ng Mabuting Asal',
        'jhs_certificate' => 'Sertipiko mula sa Junior High School (Orihinal)',
        'birth_certificate' => 'NSO/PSA Sertipiko ng Kapanganakan (Orihinal)',
        'pictures_2x2' => '2 piraso na 2×2 larawan',
        'lrn' => 'LRN *', 'lname' => 'Apelyido *', 'fname' => 'Pangalan *', 'mname' => 'Gitnang Pangalan',
        'nick' => 'Palayaw', 'ext' => 'Karugtong ng Pangalan', 'sex' => 'Kasarian *',
        'male' => 'Lalaki', 'female' => 'Babae',
        'bdate' => 'Araw ng Kapanganakan *', 'age' => 'Edad *',
        'civil' => 'Katayuang Sibil', 'nation' => 'Nasyonalidad', 'religion' => 'Relihiyon',
        'height' => 'Taas (cm)', 'weight' => 'Timbang (kg)',
        'email' => 'Email', 'phone' => 'Telepono',
        'skills' => 'Espesyal na Kasanayan',
        'father' => 'Pangalan ng Ama',
        'fjob' => 'Hanapbuhay ng Ama',
        'fcontact' => 'Numero ng Contact ng Ama',
        'mother' => 'Apelyido ng Ina sa Dalaga',
        'mjob' => 'Hanapbuhay ng Ina',
        'mcontact' => 'Numero ng Contact ng Ina',
        'income' => 'Kita ng Pamilya',
        'guardian' => 'Impormasyon ng Guardian',
        'guardian_name' => 'Buong Pangalan ng Guardian',
        'guardian_relation' => 'Relasyon sa Mag-aaral',
        'guardian_contact' => 'Numero ng Contact ng Guardian',
        'street' => 'Purok / Kalye', 'barangay' => 'Barangay', 'city' => 'Bayan / Lungsod',
        'province' => 'Lalawigan', 'region' => 'Rehiyon', 'district' => 'Distrito', 'postal' => 'Postal Code',
        'elem' => 'Elementarya', 'jhs' => 'Junior High School', 'trans' => 'Lumipat',
        'school_name' => 'Pangalan ng Paaralan', 'school_addr' => 'Address ng Paaralan', 'year_comp' => 'Taong Natapos',
        'add_school' => 'Magdagdag ng Paaralan', 'sy' => 'Taon ng Paaralan *', 'grade' => 'Antas ng Baitang *',
        'semester' => 'Semestre *', 'track' => 'Track', 'strand' => 'Strand *', 'program' => 'Programa *',
        'household' => 'Household ID',
        'submit' => 'Ipasa ang Pagpaparehistro', 'back' => 'Bumalik',
        'required' => 'Kailangan ang patlang na ito',
        'invalid_lrn' => 'Ang LRN ay dapat 12 digits',
        'invalid_name' => 'Hindi wasto ang format ng pangalan',
        'invalid_age' => 'Ang edad ay dapat nasa pagitan ng 14 at 25',
        'invalid_email' => 'Hindi wasto ang format ng email',
        'invalid_phone' => 'Ang telepono ay dapat 11 digits (09XXXXXXXXX)',
        'select_one_entrance' => 'Pumili ng kahit isang dokumento sa pagpasok',

        'transferred_in' => 'Lumipat mula sa ibang paaralan (Transferred IN)',
        'prev_school_name' => 'Pangalan ng Dating Paaralan',
        'prev_school_address' => 'Address ng Dating Paaralan',
        'prev_track' => 'Dating TRACK',
        'prev_strand' => 'Dating STRAND',
        'prev_program' => 'Dating PROGRAMA',
        'prev_year_completed' => 'Taong Natapos sa Dating Paaralan',
        'voucher_qualified' => 'Katayuan ng Voucher (para sa lumipat)',
        'qualified_voucher' => 'Qualified Voucher Recipient',
        'not_qualified_voucher' => 'Hindi Qualified Voucher Recipient',
    ]
];

$errors = [];
$fields = [
    'lrn' => ['required' => true, 'pattern' => '/^\d{12}$/', 'msg' => 'invalid_lrn'],
    'last_name' => ['required' => true, 'pattern' => '/^[A-Za-z\s\-\']{2,}$/', 'msg' => 'invalid_name'],
    'first_name' => ['required' => true, 'pattern' => '/^[A-Za-z\s\-\']{2,}$/', 'msg' => 'invalid_name'],
    'sex' => ['required' => true],
    'birth_date' => ['required' => true],
    'school_year' => ['required' => true],
    'grade_level' => ['required' => true],
    'semester' => ['required' => true],
    'strand' => ['required' => true],
    'program' => ['required' => true],
    'is_4ps' => ['required' => true]  // This will now accept 0 as valid
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($fields as $f => $rules) {
        $val = $_POST[$f] ?? '';
        // Special handling for is_4ps - accept 0 as valid value
        if ($f === 'is_4ps') {
            if (($val === '' || $val === null) && $rules['required']) {
                $errors[$f] = $t[$lang]['required'];
            }
        } else {
            $val = trim($val);
            if ($rules['required'] && empty($val)) {
                $errors[$f] = $t[$lang]['required'];
            } elseif (!empty($val) && isset($rules['pattern']) && !preg_match($rules['pattern'], $val)) {
                $errors[$f] = $t[$lang][$rules['msg']];
            }
        }
    }

    if (!empty($_POST['birth_date'])) {
        $birth = new DateTime($_POST['birth_date']);
        $today = new DateTime();
        $age = $today->diff($birth)->y;
        if ($age < 14 || $age > 25) {
            $errors['birth_date'] = $t[$lang]['invalid_age'];
        }
        $_POST['age'] = $age;
    }

    foreach (['phone', 'father_contact', 'mother_contact', 'guardian_contact'] as $field) {
        if (!empty($_POST[$field]) && !preg_match('/^09\d{9}$/', $_POST[$field])) {
            $errors[$field] = $t[$lang]['invalid_phone'];
        }
    }

    if (empty($_POST['entrance_data'] ?? [])) {
        $errors['entrance_data'] = $t[$lang]['select_one_entrance'];
    }

    if (!empty($_POST['email']) && !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = $t[$lang]['invalid_email'];
    }

    if (empty($errors)) {
        $_SESSION['form_data'] = $_POST;
        header('Location: ' . $_SERVER['PHP_SELF'] . '?review=1');
        exit;
    } else {
        $_SESSION['errors'] = $errors;
        $_SESSION['old'] = $_POST;
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }
}

// Handle edit and review modes
if (isset($_GET['edit'])) {
    $mode = 'form';
    if (isset($_SESSION['form_data'])) {
        $_POST = $_SESSION['form_data'];
    }
} elseif (isset($_GET['review']) && isset($_SESSION['form_data'])) {
    $mode = 'review';
    $_POST = $_SESSION['form_data'];
} else {
    $mode = 'form';
    if (isset($_SESSION['old'])) {
        $_POST = $_SESSION['old'];
        unset($_SESSION['old']);
    }
    if (isset($_SESSION['errors'])) {
        $errors = $_SESSION['errors'];
        unset($_SESSION['errors']);
    }
}
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $t[$lang]['form'] ?> | USAT College</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #1a202c;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('https://images.unsplash.com/photo-1523050854058-8df90110c9f1?q=80&w=2070') center/cover no-repeat;
            opacity: 0.05;
            pointer-events: none;
            z-index: -1;
        }

        .hero-header {
            background: linear-gradient(135deg, rgba(30, 136, 229, 0.95), rgba(21, 101, 192, 0.95));
            backdrop-filter: blur(10px);
            padding: 2rem 1rem;
            text-align: center;
            color: white;
            border-bottom: 4px solid #ffd700;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .hero-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            animation: pulse 4s ease-in-out infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                transform: scale(1);
                opacity: 0.3;
            }

            50% {
                transform: scale(1.1);
                opacity: 0.5;
            }
        }

        .hero-header img {
            max-width: 100px;
            border-radius: 50%;
            border: 4px solid #ffd700;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            transition: transform 0.3s ease;
        }

        .hero-header img:hover {
            transform: scale(1.05);
        }

        .hero-header h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-top: 1rem;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
        }

        .hero-header p {
            font-size: 1.1rem;
            opacity: 0.95;
        }

        .form-container {
            max-width: 1300px;
            margin: 0 auto;
            padding: 0 1.5rem 3rem;
        }

        .section-card {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 20px;
            padding: 1.8rem;
            margin-bottom: 1.8rem;
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.15);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e2e8f0;
        }

        .section-header i {
            font-size: 1.8rem;
            color: #1e88e5;
        }

        .section-header h2 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1a202c;
        }

        .form-group {
            position: relative;
            margin-bottom: 1rem;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.85rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: white;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: #1e88e5;
            box-shadow: 0 0 0 3px rgba(30, 136, 229, 0.1);
            outline: none;
        }

        .form-group label {
            position: absolute;
            left: 1rem;
            top: 0.85rem;
            color: #718096;
            font-size: 0.95rem;
            pointer-events: none;
            transition: all 0.3s ease;
            background: white;
            padding: 0 0.4rem;
            font-weight: 500;
            z-index: 1;
        }

        .form-group.filled label,
        .form-group input:focus+label,
        .form-group select:focus+label,
        .form-group textarea:focus+label {
            top: -0.6rem;
            font-size: 0.75rem;
            color: #1e88e5;
            font-weight: 600;
        }

        .error-msg {
            color: #dc3545;
            font-size: 0.75rem;
            margin-top: 0.3rem;
            display: block;
            font-weight: 500;
        }

        .checkbox-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 0.8rem;
            margin: 1rem 0;
        }

        .btn-custom {
            padding: 0.9rem 2rem;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-back {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
            color: white;
        }

        .btn-add {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }

        .transferee-section {
            margin-top: 1.5rem;
            padding: 1.5rem;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 16px;
            border-left: 4px solid #1e88e5;
        }

        .review-container {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.1);
        }

        .review-section {
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: #f8f9fa;
            border-radius: 12px;
            border-left: 4px solid #1e88e5;
        }

        .review-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 0.8rem;
        }

        .review-item {
            padding: 0.5rem;
            border-bottom: 1px solid #dee2e6;
        }

        .review-item strong {
            color: #2d3748;
            font-weight: 600;
        }

        .lang-switch {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            background: white;
            border-radius: 50px;
            padding: 0.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .lang-btn {
            padding: 0.5rem 1rem;
            border: none;
            background: none;
            font-weight: 600;
            transition: all 0.3s ease;
            border-radius: 50px;
        }

        .lang-btn.active {
            background: #1e88e5;
            color: white;
        }

        .progress-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            padding: 1rem;
            background: white;
            border-radius: 50px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .progress-step .step-number {
            width: 35px;
            height: 35px;
            background: #e2e8f0;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #718096;
        }

        .progress-step.active .step-number {
            background: #1e88e5;
            color: white;
            box-shadow: 0 0 0 3px rgba(30, 136, 229, 0.3);
        }
    </style>
</head>

<body>

    <div class="lang-switch">
        <button class="lang-btn <?= $lang == 'en' ? 'active' : '' ?>" onclick="switchLang('en')">EN</button>
        <button class="lang-btn <?= $lang == 'fil' ? 'active' : '' ?>" onclick="switchLang('fil')">FIL</button>
    </div>

    <div class="hero-header">
        <img src="../assets/img/usat.jpg" alt="USAT College Logo" onerror="this.src='https://via.placeholder.com/100'">
        <h1><?= $t[$lang]['school'] ?></h1>
        <p><?= $t[$lang]['form'] ?> | <?= $t[$lang]['ay'] ?></p>
    </div>

    <div class="form-container">

        <?php if ($mode === 'form'): ?>

            <div class="progress-indicator">
                <div class="progress-step active" data-step="1">
                    <div class="step-number">1</div>
                    <div class="step-label">Student Info</div>
                </div>
                <div class="progress-step" data-step="2">
                    <div class="step-number">2</div>
                    <div class="step-label">Parents & Guardian</div>
                </div>
                <div class="progress-step" data-step="3">
                    <div class="step-number">3</div>
                    <div class="step-label">Address</div>
                </div>
                <div class="progress-step" data-step="4">
                    <div class="step-number">4</div>
                    <div class="step-label">Education</div>
                </div>
                <div class="progress-step" data-step="5">
                    <div class="step-number">5</div>
                    <div class="step-label">Enrollment</div>
                </div>
            </div>

            <form method="POST" action="" id="enrollForm" novalidate>

                <!-- STUDENT INFORMATION -->
                <div class="section-card" data-section="1">
                    <div class="section-header">
                        <i class="fas fa-user-graduate"></i>
                        <h2><?= $t[$lang]['student'] ?></h2>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6 form-group <?= isset($errors['lrn']) ? 'error' : '' ?>">
                            <input type="text" name="lrn" id="lrn" placeholder=" " value="<?= htmlspecialchars($_POST['lrn'] ?? '') ?>" required pattern="\d{12}" maxlength="12">
                            <label><i class="fas fa-id-card me-1"></i> <?= $t[$lang]['lrn'] ?></label>
                            <?php if (isset($errors['lrn'])): ?><span class="error-msg"><?= $errors['lrn'] ?></span><?php endif; ?>
                        </div>
                        <div class="col-md-6 form-group <?= isset($errors['last_name']) ? 'error' : '' ?>">
                            <input type="text" name="last_name" id="last_name" placeholder=" " value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" required pattern="[A-Za-z\s\-']{2,}">
                            <label><i class="fas fa-user me-1"></i> <?= $t[$lang]['lname'] ?></label>
                            <?php if (isset($errors['last_name'])): ?><span class="error-msg"><?= $errors['last_name'] ?></span><?php endif; ?>
                        </div>
                        <div class="col-md-6 form-group <?= isset($errors['first_name']) ? 'error' : '' ?>">
                            <input type="text" name="first_name" id="first_name" placeholder=" " value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" required pattern="[A-Za-z\s\-']{2,}">
                            <label><i class="fas fa-user me-1"></i> <?= $t[$lang]['fname'] ?></label>
                            <?php if (isset($errors['first_name'])): ?><span class="error-msg"><?= $errors['first_name'] ?></span><?php endif; ?>
                        </div>
                        <div class="col-md-6 form-group">
                            <input type="text" name="middle_name" id="middle_name" placeholder=" " value="<?= htmlspecialchars($_POST['middle_name'] ?? '') ?>">
                            <label><i class="fas fa-user me-1"></i> <?= $t[$lang]['mname'] ?></label>
                        </div>
                        <div class="col-md-4 form-group">
                            <input type="text" name="nick_name" id="nick_name" placeholder=" " value="<?= htmlspecialchars($_POST['nick_name'] ?? '') ?>">
                            <label><i class="fas fa-smile me-1"></i> <?= $t[$lang]['nick'] ?></label>
                        </div>
                        <div class="col-md-4 form-group">
                            <input type="text" name="ext_name" id="ext_name" placeholder=" " value="<?= htmlspecialchars($_POST['ext_name'] ?? '') ?>">
                            <label><i class="fas fa-tag me-1"></i> <?= $t[$lang]['ext'] ?></label>
                        </div>
                        <div class="col-md-4 form-group <?= isset($errors['sex']) ? 'error' : '' ?>">
                            <select name="sex" id="sex" required>
                                <option value="" disabled selected></option>
                                <option value="Male" <?= ($_POST['sex'] ?? '') == 'Male' ? 'selected' : '' ?>><?= $t[$lang]['male'] ?></option>
                                <option value="Female" <?= ($_POST['sex'] ?? '') == 'Female' ? 'selected' : '' ?>><?= $t[$lang]['female'] ?></option>
                            </select>
                            <label><i class="fas fa-venus-mars me-1"></i> <?= $t[$lang]['sex'] ?></label>
                        </div>
                        <div class="col-md-4 form-group <?= isset($errors['birth_date']) ? 'error' : '' ?>">
                            <input type="date" id="birth_date" name="birth_date" value="<?= htmlspecialchars($_POST['birth_date'] ?? '') ?>" required>
                            <label><i class="fas fa-calendar-alt me-1"></i> <?= $t[$lang]['bdate'] ?></label>
                            <?php if (isset($errors['birth_date'])): ?><span class="error-msg"><?= $errors['birth_date'] ?></span><?php endif; ?>
                        </div>
                        <div class="col-md-4 form-group">
                            <input type="number" id="age" name="age" placeholder=" " value="<?= htmlspecialchars($_POST['age'] ?? '') ?>" min="14" max="25" readonly style="background:#f8f9fa;">
                            <label><i class="fas fa-birthday-cake me-1"></i> <?= $t[$lang]['age'] ?></label>
                        </div>
                        <div class="col-md-3 form-group">
                            <input type="text" name="civil_status" id="civil_status" placeholder=" " value="<?= htmlspecialchars($_POST['civil_status'] ?? '') ?>">
                            <label><i class="fas fa-ring me-1"></i> <?= $t[$lang]['civil'] ?></label>
                        </div>
                        <div class="col-md-3 form-group">
                            <input type="text" name="nationality" id="nationality" placeholder=" " value="<?= htmlspecialchars($_POST['nationality'] ?? '') ?>">
                            <label><i class="fas fa-flag me-1"></i> <?= $t[$lang]['nation'] ?></label>
                        </div>
                        <div class="col-md-3 form-group">
                            <input type="text" name="religion" id="religion" placeholder=" " value="<?= htmlspecialchars($_POST['religion'] ?? '') ?>">
                            <label><i class="fas fa-church me-1"></i> <?= $t[$lang]['religion'] ?></label>
                        </div>
                        <div class="col-md-3 form-group">
                            <input type="number" step="0.01" name="height" id="height" placeholder=" " value="<?= htmlspecialchars($_POST['height'] ?? '') ?>">
                            <label><i class="fas fa-arrow-up me-1"></i> <?= $t[$lang]['height'] ?></label>
                        </div>
                        <div class="col-md-3 form-group">
                            <input type="number" step="0.01" name="weight" id="weight" placeholder=" " value="<?= htmlspecialchars($_POST['weight'] ?? '') ?>">
                            <label><i class="fas fa-weight-hanging me-1"></i> <?= $t[$lang]['weight'] ?></label>
                        </div>
                        <div class="col-md-5 form-group <?= isset($errors['email']) ? 'error' : '' ?>">
                            <input type="email" name="email" id="email" placeholder=" " value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                            <label><i class="fas fa-envelope me-1"></i> <?= $t[$lang]['email'] ?></label>
                            <?php if (isset($errors['email'])): ?><span class="error-msg"><?= $errors['email'] ?></span><?php endif; ?>
                        </div>
                        <div class="col-md-4 form-group <?= isset($errors['phone']) ? 'error' : '' ?>">
                            <input type="tel" name="phone" id="phone" placeholder=" " value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" pattern="09\d{9}" maxlength="11">
                            <label><i class="fas fa-phone-alt me-1"></i> <?= $t[$lang]['phone'] ?></label>
                            <?php if (isset($errors['phone'])): ?><span class="error-msg"><?= $errors['phone'] ?></span><?php endif; ?>
                        </div>
                        <div class="col-12 form-group">
                            <textarea name="special_skills" id="special_skills" rows="2" placeholder=" "><?= htmlspecialchars($_POST['special_skills'] ?? '') ?></textarea>
                            <label><i class="fas fa-lightbulb me-1"></i> <?= $t[$lang]['skills'] ?></label>
                        </div>
                    </div>
                </div>

                <!-- PARENTS & GUARDIAN -->
                <div class="section-card" data-section="2">
                    <div class="section-header">
                        <i class="fas fa-users"></i>
                        <h2><?= $t[$lang]['parents'] ?></h2>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6 form-group">
                            <input type="text" name="father_name" id="father_name" placeholder=" " value="<?= htmlspecialchars($_POST['father_name'] ?? '') ?>">
                            <label><i class="fas fa-male me-1"></i> <?= $t[$lang]['father'] ?></label>
                        </div>
                        <div class="col-md-6 form-group <?= isset($errors['father_contact']) ? 'error' : '' ?>">
                            <input type="tel" name="father_contact" id="father_contact" placeholder="09XXXXXXXXX" value="<?= htmlspecialchars($_POST['father_contact'] ?? '') ?>" pattern="09\d{9}" maxlength="11">
                            <label><i class="fas fa-phone me-1"></i> <?= $t[$lang]['fcontact'] ?></label>
                            <?php if (isset($errors['father_contact'])): ?><span class="error-msg"><?= $errors['father_contact'] ?></span><?php endif; ?>
                        </div>
                        <div class="col-md-6 form-group">
                            <input type="text" name="father_occupation" id="father_occupation" placeholder=" " value="<?= htmlspecialchars($_POST['father_occupation'] ?? '') ?>">
                            <label><i class="fas fa-briefcase me-1"></i> <?= $t[$lang]['fjob'] ?></label>
                        </div>
                        <div class="col-md-6 form-group">
                            <input type="text" name="mother_maiden_name" id="mother_maiden_name" placeholder=" " value="<?= htmlspecialchars($_POST['mother_maiden_name'] ?? '') ?>">
                            <label><i class="fas fa-female me-1"></i> <?= $t[$lang]['mother'] ?></label>
                        </div>
                        <div class="col-md-6 form-group <?= isset($errors['mother_contact']) ? 'error' : '' ?>">
                            <input type="tel" name="mother_contact" id="mother_contact" placeholder="09XXXXXXXXX" value="<?= htmlspecialchars($_POST['mother_contact'] ?? '') ?>" pattern="09\d{9}" maxlength="11">
                            <label><i class="fas fa-phone me-1"></i> <?= $t[$lang]['mcontact'] ?></label>
                            <?php if (isset($errors['mother_contact'])): ?><span class="error-msg"><?= $errors['mother_contact'] ?></span><?php endif; ?>
                        </div>
                        <div class="col-md-6 form-group">
                            <input type="text" name="mother_occupation" id="mother_occupation" placeholder=" " value="<?= htmlspecialchars($_POST['mother_occupation'] ?? '') ?>">
                            <label><i class="fas fa-briefcase me-1"></i> <?= $t[$lang]['mjob'] ?></label>
                        </div>
                        <div class="col-md-6 form-group">
                            <input type="text" name="ave_family_income" id="ave_family_income" placeholder=" " value="<?= htmlspecialchars($_POST['ave_family_income'] ?? '') ?>">
                            <label><i class="fas fa-money-bill-wave me-1"></i> <?= $t[$lang]['income'] ?></label>
                        </div>
                        <!-- FIXED: CCT/4Ps Recipient Field - Now properly handles NO selection -->
                        <div class="col-md-6 form-group <?= isset($errors['is_4ps']) ? 'error' : '' ?>">
                            <select name="is_4ps" id="is_4ps" required>
                                <option value="" disabled <?= !isset($_POST['is_4ps']) || $_POST['is_4ps'] === '' ? 'selected' : '' ?>></option>
                                <option value="1" <?= (isset($_POST['is_4ps']) && $_POST['is_4ps'] == '1') ? 'selected' : '' ?>><?= $t[$lang]['yes'] ?></option>
                                <option value="0" <?= (isset($_POST['is_4ps']) && $_POST['is_4ps'] == '0') ? 'selected' : '' ?>><?= $t[$lang]['no'] ?></option>
                            </select>
                            <label><i class="fas fa-hand-holding-usd me-1"></i> <?= $t[$lang]['voucher_question'] ?></label>
                            <?php if (isset($errors['is_4ps'])): ?><span class="error-msg"><?= $errors['is_4ps'] ?></span><?php endif; ?>
                        </div>
                        <div class="col-12 mt-4">
                            <div class="section-header" style="border-bottom: none; padding-bottom: 0;">
                                <i class="fas fa-shield-alt"></i>
                                <h5 class="text-primary mb-0"><?= $t[$lang]['guardian'] ?></h5>
                            </div>
                        </div>
                        <div class="col-md-6 form-group">
                            <input type="text" name="guardian_fullname" id="guardian_fullname" placeholder=" " value="<?= htmlspecialchars($_POST['guardian_fullname'] ?? '') ?>">
                            <label><i class="fas fa-user-shield me-1"></i> <?= $t[$lang]['guardian_name'] ?></label>
                        </div>
                        <div class="col-md-6 form-group">
                            <input type="text" name="guardian_relation" id="guardian_relation" placeholder=" " value="<?= htmlspecialchars($_POST['guardian_relation'] ?? '') ?>">
                            <label><i class="fas fa-handshake me-1"></i> <?= $t[$lang]['guardian_relation'] ?></label>
                        </div>
                        <div class="col-md-6 form-group <?= isset($errors['guardian_contact']) ? 'error' : '' ?>">
                            <input type="tel" name="guardian_contact" id="guardian_contact" placeholder="09XXXXXXXXX" value="<?= htmlspecialchars($_POST['guardian_contact'] ?? '') ?>" pattern="09\d{9}" maxlength="11">
                            <label><i class="fas fa-phone-alt me-1"></i> <?= $t[$lang]['guardian_contact'] ?></label>
                            <?php if (isset($errors['guardian_contact'])): ?><span class="error-msg"><?= $errors['guardian_contact'] ?></span><?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- ADDRESS SECTION -->
                <div class="section-card" data-section="3">
                    <div class="section-header">
                        <i class="fas fa-map-marker-alt"></i>
                        <h2><?= $t[$lang]['address'] ?></h2>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6 form-group">
                            <input type="text" name="purok_street" id="purok_street" placeholder=" " value="<?= htmlspecialchars($_POST['purok_street'] ?? '') ?>">
                            <label><i class="fas fa-road me-1"></i> <?= $t[$lang]['street'] ?></label>
                        </div>
                        <div class="col-md-6 form-group">
                            <input type="text" name="barangay" id="barangay" placeholder=" " value="<?= htmlspecialchars($_POST['barangay'] ?? '') ?>">
                            <label><i class="fas fa-home me-1"></i> <?= $t[$lang]['barangay'] ?></label>
                        </div>
                        <div class="col-md-6 form-group">
                            <input type="text" name="town_city" id="town_city" placeholder=" " value="<?= htmlspecialchars($_POST['town_city'] ?? '') ?>">
                            <label><i class="fas fa-city me-1"></i> <?= $t[$lang]['city'] ?></label>
                        </div>
                        <div class="col-md-6 form-group">
                            <input type="text" name="province" id="province" placeholder=" " value="<?= htmlspecialchars($_POST['province'] ?? '') ?>">
                            <label><i class="fas fa-globe-asia me-1"></i> <?= $t[$lang]['province'] ?></label>
                        </div>
                        <div class="col-md-6 form-group">
                            <input type="text" name="region" id="region" placeholder=" " value="<?= htmlspecialchars($_POST['region'] ?? '') ?>">
                            <label><i class="fas fa-map me-1"></i> <?= $t[$lang]['region'] ?></label>
                        </div>
                        <div class="col-md-6 form-group">
                            <input type="text" name="district" id="district" placeholder=" " value="<?= htmlspecialchars($_POST['district'] ?? '') ?>">
                            <label><i class="fas fa-chart-line me-1"></i> <?= $t[$lang]['district'] ?></label>
                        </div>
                        <div class="col-md-6 form-group">
                            <input type="text" name="postal_code" id="postal_code" placeholder=" " value="<?= htmlspecialchars($_POST['postal_code'] ?? '') ?>">
                            <label><i class="fas fa-mail-bulk me-1"></i> <?= $t[$lang]['postal'] ?></label>
                        </div>
                    </div>
                </div>

                <!-- EDUCATIONAL HISTORY -->
                <div class="section-card" data-section="4">
                    <div class="section-header">
                        <i class="fas fa-graduation-cap"></i>
                        <h2><?= $t[$lang]['education'] ?></h2>
                    </div>
                    <div id="edu-container">
                        <div class="edu-entry row g-3 align-items-end">
                            <div class="col-md-3 form-group filled">
                                <select name="edu_level[]" class="edu-level" style="background: white;">
                                    <option value="Elementary" selected><?= $t[$lang]['elem'] ?></option>
                                    <option value="Junior High School"><?= $t[$lang]['jhs'] ?></option>
                                    <option value="Transferred"><?= $t[$lang]['trans'] ?></option>
                                </select>
                                <label>Level</label>
                            </div>
                            <div class="col-md-3 form-group">
                                <input type="text" name="school_name[]" placeholder=" " value="">
                                <label><?= $t[$lang]['school_name'] ?></label>
                            </div>
                            <div class="col-md-3 form-group">
                                <input type="text" name="school_address[]" placeholder=" " value="">
                                <label><?= $t[$lang]['school_addr'] ?></label>
                            </div>
                            <div class="col-md-3 form-group">
                                <input type="text" name="year_completed[]" placeholder=" " value="">
                                <label><?= $t[$lang]['year_comp'] ?></label>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-add mt-3" id="addEduBtn">
                        <i class="fas fa-plus me-2"></i><?= $t[$lang]['add_school'] ?>
                    </button>
                </div>

                <!-- ENROLLMENT DETAILS -->
                <div class="section-card" data-section="5">
                    <div class="section-header">
                        <i class="fas fa-clipboard-list"></i>
                        <h2><?= $t[$lang]['enrollment'] ?></h2>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-3 form-group <?= isset($errors['school_year']) ? 'error' : '' ?>">
                            <input type="text" name="school_year" id="school_year" placeholder=" " value="<?= htmlspecialchars($_POST['school_year'] ?? '2026-2027') ?>" required>
                            <label><i class="fas fa-calendar me-1"></i> <?= $t[$lang]['sy'] ?></label>
                            <?php if (isset($errors['school_year'])): ?><span class="error-msg"><?= $errors['school_year'] ?></span><?php endif; ?>
                        </div>
                        <div class="col-md-3 form-group <?= isset($errors['grade_level']) ? 'error' : '' ?>">
                            <select name="grade_level" id="grade_level" required>
                                <option value="" disabled selected></option>
                                <option value="Grade 11" <?= ($_POST['grade_level'] ?? '') == 'Grade 11' ? 'selected' : '' ?>>Grade 11</option>
                                <option value="Grade 12" <?= ($_POST['grade_level'] ?? '') == 'Grade 12' ? 'selected' : '' ?>>Grade 12</option>
                            </select>
                            <label><i class="fas fa-layer-group me-1"></i> <?= $t[$lang]['grade'] ?></label>
                            <?php if (isset($errors['grade_level'])): ?><span class="error-msg"><?= $errors['grade_level'] ?></span><?php endif; ?>
                        </div>
                        <div class="col-md-3 form-group <?= isset($errors['semester']) ? 'error' : '' ?>">
                            <select name="semester" id="semester" required>
                                <option value="" disabled selected></option>
                                <option value="1st Semester" <?= ($_POST['semester'] ?? '') == '1st Semester' ? 'selected' : '' ?>>1st Semester</option>
                                <option value="2nd Semester" <?= ($_POST['semester'] ?? '') == '2nd Semester' ? 'selected' : '' ?>>2nd Semester</option>
                            </select>
                            <label><i class="fas fa-chart-simple me-1"></i> <?= $t[$lang]['semester'] ?></label>
                            <?php if (isset($errors['semester'])): ?><span class="error-msg"><?= $errors['semester'] ?></span><?php endif; ?>
                        </div>
                        <div class="col-md-3 form-group">
                            <input type="text" name="track" value="TECHPRO ELECTIVES" readonly required style="background:#f8f9fa; cursor:not-allowed;">
                            <label><i class="fas fa-road me-1"></i> <?= $t[$lang]['track'] ?></label>
                        </div>
                    </div>

                    <div class="row g-3 mt-2">
                        <div class="col-md-6 form-group <?= isset($errors['strand']) ? 'error' : '' ?>">
                            <select name="strand" id="strand" required>
                                <option value="" disabled selected></option>
                            </select>
                            <label><i class="fas fa-tree me-1"></i> <?= $t[$lang]['strand'] ?></label>
                            <?php if (isset($errors['strand'])): ?><span class="error-msg"><?= $errors['strand'] ?></span><?php endif; ?>
                        </div>
                        <div class="col-md-6 form-group <?= isset($errors['program']) ? 'error' : '' ?>">
                            <select name="program" id="program" required>
                                <option value="" disabled selected></option>
                            </select>
                            <label><i class="fas fa-code-branch me-1"></i> <?= $t[$lang]['program'] ?></label>
                            <?php if (isset($errors['program'])): ?><span class="error-msg"><?= $errors['program'] ?></span><?php endif; ?>
                        </div>
                    </div>

                    <div class="form-group mt-3">
                        <input type="text" name="household_id" id="household_id" placeholder=" " value="<?= htmlspecialchars($_POST['household_id'] ?? '') ?>">
                        <label><i class="fas fa-home me-1"></i> <?= $t[$lang]['household'] ?></label>
                    </div>

                    <div class="mt-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_transferred" id="is_transferred" value="1" style="width: 18px; height: 18px;" <?= !empty($_POST['is_transferred']) ? 'checked' : '' ?>>
                            <label class="form-check-label fw-bold ms-2" for="is_transferred">
                                <i class="fas fa-exchange-alt me-1 text-primary"></i> <?= $t[$lang]['transferred_in'] ?>
                            </label>
                        </div>

                        <div id="transferee-fields" class="transferee-section" style="display: <?= !empty($_POST['is_transferred']) ? 'block' : 'none' ?>;">
                            <div class="row g-3">
                                <div class="col-md-6 form-group">
                                    <input type="text" name="previous_school_name" placeholder=" " value="<?= htmlspecialchars($_POST['previous_school_name'] ?? '') ?>">
                                    <label><?= $t[$lang]['prev_school_name'] ?></label>
                                </div>
                                <div class="col-md-6 form-group">
                                    <textarea name="previous_school_address" rows="2" placeholder=" "><?= htmlspecialchars($_POST['previous_school_address'] ?? '') ?></textarea>
                                    <label><?= $t[$lang]['prev_school_address'] ?></label>
                                </div>
                                <div class="col-md-4 form-group">
                                    <input type="text" name="previous_track" placeholder=" " value="<?= htmlspecialchars($_POST['previous_track'] ?? '') ?>">
                                    <label><?= $t[$lang]['prev_track'] ?></label>
                                </div>
                                <div class="col-md-4 form-group">
                                    <input type="text" name="previous_strand" placeholder=" " value="<?= htmlspecialchars($_POST['previous_strand'] ?? '') ?>">
                                    <label><?= $t[$lang]['prev_strand'] ?></label>
                                </div>
                                <div class="col-md-4 form-group">
                                    <input type="text" name="previous_program" placeholder=" " value="<?= htmlspecialchars($_POST['previous_program'] ?? '') ?>">
                                    <label><?= $t[$lang]['prev_program'] ?></label>
                                </div>
                                <div class="col-md-4 form-group">
                                    <input type="number" name="previous_year_completed" placeholder=" " value="<?= htmlspecialchars($_POST['previous_year_completed'] ?? '') ?>" min="2000" max="<?= date('Y') ?>">
                                    <label><?= $t[$lang]['prev_year_completed'] ?></label>
                                </div>
                            </div>

                            <div class="mt-4">
                                <label class="form-label fw-bold mb-2"><?= $t[$lang]['voucher_qualified'] ?></label>
                                <div class="d-flex gap-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="voucher_qualified" id="vq_yes" value="1" <?= ($_POST['voucher_qualified'] ?? '') == '1' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="vq_yes"><?= $t[$lang]['qualified_voucher'] ?></label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="voucher_qualified" id="vq_no" value="0" <?= ($_POST['voucher_qualified'] ?? '') == '0' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="vq_no"><?= $t[$lang]['not_qualified_voucher'] ?></label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ENTRANCE DOCUMENTS -->
                <div class="section-card">
                    <div class="section-header">
                        <i class="fas fa-folder-open"></i>
                        <h2><?= $t[$lang]['entrance_data'] ?></h2>
                    </div>
                    <div class="checkbox-container">
                        <div class="checkbox-item">
                            <input type="checkbox" name="entrance_data[]" value="Good Moral Certificate" id="gm" <?= in_array('Good Moral Certificate', $_POST['entrance_data'] ?? []) ? 'checked' : '' ?>>
                            <label for="gm"><i class="fas fa-scroll me-2 text-primary"></i><?= $t[$lang]['good_moral'] ?></label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" name="entrance_data[]" value="Junior High School Certificate (Original)" id="jhs" <?= in_array('Junior High School Certificate (Original)', $_POST['entrance_data'] ?? []) ? 'checked' : '' ?>>
                            <label for="jhs"><i class="fas fa-certificate me-2 text-primary"></i><?= $t[$lang]['jhs_certificate'] ?></label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" name="entrance_data[]" value="NSO/PSA Birth Certificate (Original)" id="bc" <?= in_array('NSO/PSA Birth Certificate (Original)', $_POST['entrance_data'] ?? []) ? 'checked' : '' ?>>
                            <label for="bc"><i class="fas fa-baby-carriage me-2 text-primary"></i><?= $t[$lang]['birth_certificate'] ?></label>
                        </div>
                        <div class="checkbox-item">
                            <input type="checkbox" name="entrance_data[]" value="2 pcs 2×2 picture" id="pic" <?= in_array('2 pcs 2×2 picture', $_POST['entrance_data'] ?? []) ? 'checked' : '' ?>>
                            <label for="pic"><i class="fas fa-camera me-2 text-primary"></i><?= $t[$lang]['pictures_2x2'] ?></label>
                        </div>
                    </div>
                    <?php if (isset($errors['entrance_data'])): ?>
                        <span class="error-msg"><i class="fas fa-exclamation-triangle me-1"></i><?= $errors['entrance_data'] ?></span>
                    <?php endif; ?>
                </div>

                <div class="text-center mt-5 mb-4">
                    <button type="submit" class="btn btn-submit btn-custom px-5">
                        <i class="fas fa-paper-plane me-2"></i><?= $t[$lang]['submit'] ?>
                    </button>
                    <a href="../admin/admin_login.php" class="btn btn-back btn-custom px-5 ms-3">
                        <i class="fas fa-arrow-left me-2"></i><?= $t[$lang]['back'] ?>
                    </a>
                </div>

            </form>

        <?php else: ?>

            <div class="review-container">
                <div class="text-center mb-4">
                    <i class="fas fa-check-circle" style="font-size: 4rem; color: #28a745;"></i>
                    <h1 class="mt-3" style="color: #1e88e5;"><?= $t[$lang]['form'] ?> - Review & Confirm</h1>
                    <p class="text-muted">Please review your information before submitting</p>
                </div>

                <div class="review-section">
                    <h3><i class="fas fa-user-graduate me-2"></i><?= $t[$lang]['student'] ?></h3>
                    <div class="review-grid">
                        <div class="review-item"><strong>LRN:</strong> <?= htmlspecialchars($_POST['lrn'] ?? '—') ?></div>
                        <div class="review-item"><strong>Last Name:</strong> <?= htmlspecialchars($_POST['last_name'] ?? '—') ?></div>
                        <div class="review-item"><strong>First Name:</strong> <?= htmlspecialchars($_POST['first_name'] ?? '—') ?></div>
                        <div class="review-item"><strong>Middle Name:</strong> <?= htmlspecialchars($_POST['middle_name'] ?? '—') ?></div>
                        <div class="review-item"><strong>Nickname:</strong> <?= htmlspecialchars($_POST['nick_name'] ?? '—') ?></div>
                        <div class="review-item"><strong>Extension Name:</strong> <?= htmlspecialchars($_POST['ext_name'] ?? '—') ?></div>
                        <div class="review-item"><strong>Sex:</strong> <?= htmlspecialchars($_POST['sex'] ?? '—') ?></div>
                        <div class="review-item"><strong>Birth Date:</strong> <?= htmlspecialchars($_POST['birth_date'] ?? '—') ?></div>
                        <div class="review-item"><strong>Age:</strong> <?= htmlspecialchars($_POST['age'] ?? '—') ?></div>
                        <div class="review-item"><strong>Civil Status:</strong> <?= htmlspecialchars($_POST['civil_status'] ?? '—') ?></div>
                        <div class="review-item"><strong>Nationality:</strong> <?= htmlspecialchars($_POST['nationality'] ?? '—') ?></div>
                        <div class="review-item"><strong>Religion:</strong> <?= htmlspecialchars($_POST['religion'] ?? '—') ?></div>
                        <div class="review-item"><strong>Height:</strong> <?= htmlspecialchars($_POST['height'] ?? '—') ?> cm</div>
                        <div class="review-item"><strong>Weight:</strong> <?= htmlspecialchars($_POST['weight'] ?? '—') ?> kg</div>
                        <div class="review-item"><strong>Email:</strong> <?= htmlspecialchars($_POST['email'] ?? '—') ?></div>
                        <div class="review-item"><strong>Phone:</strong> <?= htmlspecialchars($_POST['phone'] ?? '—') ?></div>
                        <div class="review-item"><strong>Special Skills:</strong> <?= htmlspecialchars($_POST['special_skills'] ?? '—') ?></div>
                    </div>
                </div>

                <div class="review-section">
                    <h3><i class="fas fa-users me-2"></i><?= $t[$lang]['parents'] ?></h3>
                    <div class="review-grid">
                        <div class="review-item"><strong>Father Name:</strong> <?= htmlspecialchars($_POST['father_name'] ?? '—') ?></div>
                        <div class="review-item"><strong>Father Occupation:</strong> <?= htmlspecialchars($_POST['father_occupation'] ?? '—') ?></div>
                        <div class="review-item"><strong>Father Contact:</strong> <?= htmlspecialchars($_POST['father_contact'] ?? '—') ?></div>
                        <div class="review-item"><strong>Mother Maiden Name:</strong> <?= htmlspecialchars($_POST['mother_maiden_name'] ?? '—') ?></div>
                        <div class="review-item"><strong>Mother Occupation:</strong> <?= htmlspecialchars($_POST['mother_occupation'] ?? '—') ?></div>
                        <div class="review-item"><strong>Mother Contact:</strong> <?= htmlspecialchars($_POST['mother_contact'] ?? '—') ?></div>
                        <div class="review-item"><strong>Average Family Income:</strong> <?= htmlspecialchars($_POST['ave_family_income'] ?? '—') ?></div>
                        <div class="review-item"><strong>CCT/4Ps Recipient:</strong> <?= (isset($_POST['is_4ps']) && $_POST['is_4ps'] == '1') ? $t[$lang]['yes'] : $t[$lang]['no'] ?></div>
                        <div class="review-item"><strong>Guardian Full Name:</strong> <?= htmlspecialchars($_POST['guardian_fullname'] ?? '—') ?></div>
                        <div class="review-item"><strong>Relationship:</strong> <?= htmlspecialchars($_POST['guardian_relation'] ?? '—') ?></div>
                        <div class="review-item"><strong>Guardian Contact:</strong> <?= htmlspecialchars($_POST['guardian_contact'] ?? '—') ?></div>
                    </div>
                </div>

                <div class="review-section">
                    <h3><i class="fas fa-map-marker-alt me-2"></i><?= $t[$lang]['address'] ?></h3>
                    <div class="review-grid">
                        <div class="review-item"><strong>Purok/Street:</strong> <?= htmlspecialchars($_POST['purok_street'] ?? '—') ?></div>
                        <div class="review-item"><strong>Barangay:</strong> <?= htmlspecialchars($_POST['barangay'] ?? '—') ?></div>
                        <div class="review-item"><strong>Town/City:</strong> <?= htmlspecialchars($_POST['town_city'] ?? '—') ?></div>
                        <div class="review-item"><strong>Province:</strong> <?= htmlspecialchars($_POST['province'] ?? '—') ?></div>
                        <div class="review-item"><strong>Region:</strong> <?= htmlspecialchars($_POST['region'] ?? '—') ?></div>
                        <div class="review-item"><strong>District:</strong> <?= htmlspecialchars($_POST['district'] ?? '—') ?></div>
                        <div class="review-item"><strong>Postal Code:</strong> <?= htmlspecialchars($_POST['postal_code'] ?? '—') ?></div>
                    </div>
                </div>

                <div class="review-section">
                    <h3><i class="fas fa-graduation-cap me-2"></i><?= $t[$lang]['education'] ?></h3>
                    <?php if (isset($_POST['edu_level']) && is_array($_POST['edu_level'])): ?>
                        <?php foreach ($_POST['edu_level'] as $i => $level): ?>
                            <div style="margin-bottom: 1rem; padding: 1rem; background: white; border-radius: 8px;">
                                <strong><?= htmlspecialchars($level) ?></strong><br>
                                School: <?= htmlspecialchars($_POST['school_name'][$i] ?? '—') ?><br>
                                Address: <?= htmlspecialchars($_POST['school_address'][$i] ?? '—') ?><br>
                                Year Completed: <?= htmlspecialchars($_POST['year_completed'][$i] ?? '—') ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-muted">No educational history provided.</p>
                    <?php endif; ?>
                </div>

                <div class="review-section">
                    <h3><i class="fas fa-clipboard-list me-2"></i><?= $t[$lang]['enrollment'] ?></h3>
                    <div class="review-grid">
                        <div class="review-item"><strong>School Year:</strong> <?= htmlspecialchars($_POST['school_year'] ?? '—') ?></div>
                        <div class="review-item"><strong>Grade Level:</strong> <?= htmlspecialchars($_POST['grade_level'] ?? '—') ?></div>
                        <div class="review-item"><strong>Semester:</strong> <?= htmlspecialchars($_POST['semester'] ?? '—') ?></div>
                        <div class="review-item"><strong>Track:</strong> <?= htmlspecialchars($_POST['track'] ?? '—') ?></div>
                        <div class="review-item"><strong>Strand:</strong> <?= htmlspecialchars($_POST['strand'] ?? '—') ?></div>
                        <div class="review-item"><strong>Program:</strong> <?= htmlspecialchars($_POST['program'] ?? '—') ?></div>
                        <div class="review-item"><strong>Household ID:</strong> <?= htmlspecialchars($_POST['household_id'] ?? '—') ?></div>
                    </div>

                    <?php if (!empty($_POST['is_transferred'])): ?>
                        <hr class="my-3">
                        <h4 class="text-primary"><?= $t[$lang]['transferred_in'] ?></h4>
                        <div class="review-grid">
                            <div class="review-item"><strong>Previous School:</strong> <?= htmlspecialchars($_POST['previous_school_name'] ?? '—') ?></div>
                            <div class="review-item"><strong>Previous Address:</strong> <?= nl2br(htmlspecialchars($_POST['previous_school_address'] ?? '—')) ?></div>
                            <div class="review-item"><strong>Previous TRACK:</strong> <?= htmlspecialchars($_POST['previous_track'] ?? '—') ?></div>
                            <div class="review-item"><strong>Previous STRAND:</strong> <?= htmlspecialchars($_POST['previous_strand'] ?? '—') ?></div>
                            <div class="review-item"><strong>Previous PROGRAM:</strong> <?= htmlspecialchars($_POST['previous_program'] ?? '—') ?></div>
                            <div class="review-item"><strong>Year Completed:</strong> <?= htmlspecialchars($_POST['previous_year_completed'] ?? '—') ?></div>
                            <div class="review-item"><strong>Voucher Status:</strong> <?php $vq = $_POST['voucher_qualified'] ?? '';
                                                                                    echo $vq === '1' ? $t[$lang]['qualified_voucher'] : ($vq === '0' ? $t[$lang]['not_qualified_voucher'] : '—'); ?></div>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="review-section">
                    <h3><i class="fas fa-folder-open me-2"></i><?= $t[$lang]['entrance_data'] ?></h3>
                    <ul class="list-unstyled">
                        <?php
                        if (!empty($_POST['entrance_data'] ?? [])) {
                            foreach ($_POST['entrance_data'] as $d) {
                                echo "<li class='mb-2'><i class='fas fa-check-circle text-success me-2'></i>" . htmlspecialchars($d) . "</li>";
                            }
                        } else {
                            echo "<li class='text-muted'>None selected</li>";
                        }
                        ?>
                    </ul>
                </div>

                <!-- Review Form with CSRF Token -->
                <form method="POST" action="process_enroll.php" class="text-center mt-5">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                    <?php foreach ($_POST as $k => $v): ?>
                        <?php if (is_array($v)): ?>
                            <?php foreach ($v as $val): ?>
                                <input type="hidden" name="<?= htmlspecialchars($k) ?>[]" value="<?= htmlspecialchars($val) ?>">
                            <?php endforeach; ?>
                        <?php else: ?>
                            <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars($v) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <button type="submit" class="btn btn-submit btn-custom px-5">
                        <i class="fas fa-save me-2"></i> Confirm & Save Enrollment
                    </button>
                </form>

                <div class="text-center mt-3">
                    <a href="?edit=1" class="btn btn-back btn-custom px-4">
                        <i class="fas fa-edit me-2"></i> Edit
                    </a>
                </div>
            </div>

        <?php endif; ?>

    </div>

    <script>
        function switchLang(lang) {
            const url = new URL(window.location.href);
            url.searchParams.set('lang', lang);
            window.location.href = url.toString();
        }

        function capitalize(firstName, lastName, middleName) {
            // Capitalization logic here (keep your existing implementation)
            return str.charAt(0).toUpperCase() + str.slice(1).toLowerCase();
        }

        const nameFields = [
            'last_name', 'first_name', 'middle_name', 'nick_name', 'ext_name',
            'father_name', 'mother_maiden_name', 'guardian_fullname'
        ];

        // Floating Label Handler
        function initFloatingLabels(container = document) {
            const groups = container.querySelectorAll('.form-group');
            groups.forEach(group => {
                const input = group.querySelector('input, select, textarea');
                if (!input) return;

                const updateFilled = () => {
                    if (input.value && input.value.trim() !== '') {
                        group.classList.add('filled');
                    } else {
                        group.classList.remove('filled');
                    }
                };

                input.addEventListener('focus', () => group.classList.add('filled'));
                input.addEventListener('blur', updateFilled);
                input.addEventListener('input', updateFilled);
                input.addEventListener('change', updateFilled);

                updateFilled();
            });
        }

        // Age calculation
        const birthDateInput = document.getElementById('birth_date');
        const ageInput = document.getElementById('age');

        function calculateAge() {
            if (!birthDateInput || !birthDateInput.value) return;
            const birth = new Date(birthDateInput.value);
            const today = new Date();
            let age = today.getFullYear() - birth.getFullYear();
            if (today.getMonth() < birth.getMonth() || (today.getMonth() === birth.getMonth() && today.getDate() < birth.getDate())) age--;
            if (ageInput) ageInput.value = age;
        }
        if (birthDateInput) {
            birthDateInput.addEventListener('change', calculateAge);
            window.addEventListener('load', calculateAge);
        }

        // Capitalization for name fields
        document.addEventListener('DOMContentLoaded', () => {
            nameFields.forEach(field => {
                const input = document.querySelector(`input[name="${field}"]`);
                if (input) {
                    input.addEventListener('input', () => {
                        if (input.value.length > 0) input.value = capitalize(input.value);
                    });
                    input.addEventListener('blur', () => {
                        if (input.value.trim() !== '') input.value = capitalize(input.value.trim());
                    });
                }
            });

            initFloatingLabels();
        });

        // Dynamic Strand & Program
        const strandSelect = document.getElementById('strand');
        const progSelect = document.getElementById('program');
        const programs = {
            "Automotive and Small Engine Technologies": ["Driving and Automotive Servicing", "Automotive Servicing (Electrical Repair)", "Automotive Servicing (Engine and Chassis Repairs)"],
            "Construction and Building Technologies": ["Carpentry", "Manual Metal Arc Welding"],
            "ICT Support and Computer Programming Technologies": ["Computer Programming (Java)", "Computer Programming (.NET)", "Computer System Servicing"],
            "Industrial Technologies": ["Electronics Product Assembly and Servicing"],
            "Agri-Fishery Business and Food Innovation": ["Agricultural Crops Production"],
            "Hospitality and Tourism": ["Food and Beverage Operation", "Hotel Operation (Housekeeping)"]
        };

        if (strandSelect) {
            strandSelect.innerHTML = '<option value="" disabled selected></option>';
            Object.keys(programs).forEach(k => {
                let o = document.createElement("option");
                o.value = o.textContent = k;
                strandSelect.appendChild(o);
            });
            strandSelect.addEventListener("change", () => {
                progSelect.innerHTML = '<option value="" disabled selected></option>';
                if (programs[strandSelect.value]) {
                    programs[strandSelect.value].forEach(p => {
                        let o = document.createElement("option");
                        o.value = o.textContent = p;
                        progSelect.appendChild(o);
                    });
                }
                initFloatingLabels(progSelect.parentElement);
            });
            const currentStrand = "<?= addslashes($_POST['strand'] ?? '') ?>";
            if (currentStrand) {
                strandSelect.value = currentStrand;
                strandSelect.dispatchEvent(new Event('change'));
                setTimeout(() => {
                    progSelect.value = "<?= addslashes($_POST['program'] ?? '') ?>";
                    if (progSelect.value) progSelect.parentElement.classList.add('filled');
                }, 50);
            }
        }

        // Add educational history row
        function addEdu() {
            const container = document.getElementById('edu-container');
            const original = container.querySelector('.edu-entry');
            const clone = original.cloneNode(true);
            clone.querySelectorAll('input, select, textarea').forEach(el => {
                if (el.type !== 'select-one') el.value = '';
                else if (el.classList.contains('edu-level')) el.selectedIndex = 0;
            });
            container.appendChild(clone);
            initFloatingLabels(clone);
        }
        document.getElementById('addEduBtn')?.addEventListener('click', addEdu);

        // Transferee fields toggle
        const transfereeCheck = document.getElementById('is_transferred');
        const transfereeSection = document.getElementById('transferee-fields');
        if (transfereeCheck && transfereeSection) {
            const toggleTransferee = () => {
                transfereeSection.style.display = transfereeCheck.checked ? 'block' : 'none';
                if (transfereeCheck.checked) initFloatingLabels(transfereeSection);
            };
            transfereeCheck.addEventListener('change', toggleTransferee);
            window.addEventListener('load', toggleTransferee);
        }

        // Form validation with SweetAlert
        const form = document.getElementById('enrollForm');
        if (form) {
            form.addEventListener('submit', function(e) {
                let valid = true;

                // Check required fields
                form.querySelectorAll('[required]').forEach(field => {
                    if (!field.checkValidity()) {
                        valid = false;
                    }
                });

                // Check entrance documents
                if (form.querySelectorAll('input[name="entrance_data[]"]:checked').length === 0) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Required',
                        text: "<?= addslashes($t[$lang]['select_one_entrance']) ?>",
                        confirmButtonColor: '#1e88e5'
                    });
                    valid = false;
                }

                // Check LRN format
                const lrn = document.getElementById('lrn');
                if (lrn && lrn.value && !/^\d{12}$/.test(lrn.value)) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Invalid LRN',
                        text: 'LRN must be exactly 12 digits',
                        confirmButtonColor: '#1e88e5'
                    });
                    valid = false;
                }

                // Check CCT/4Ps selection
                const is4psSelect = document.getElementById('is_4ps');
                if (is4psSelect && (!is4psSelect.value || is4psSelect.value === '')) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Required',
                        text: 'Please select YES or NO for CCT/4Ps recipient',
                        confirmButtonColor: '#1e88e5'
                    });
                    valid = false;
                }

                if (!valid) e.preventDefault();
            });
        }

       document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($_SESSION['success']) && $_SESSION['success'] === true): ?>
                Swal.fire({
                    title: 'Success!',
                    html: '<?= addslashes($_SESSION['success_message'] ?? 'Student successfully enrolled!') ?>',
                    icon: 'success',
                    confirmButtonColor: '#1e88e5',
                    confirmButtonText: 'OK',
                    timer: 5000,
                    timerProgressBar: true,
                    allowOutsideClick: false
                }).then(() => {
                    window.location.href = 'enroll_form.php';
                });
                <?php 
                    unset($_SESSION['success']);
                    unset($_SESSION['success_message']); 
                ?>
            <?php elseif (isset($_SESSION['error'])): ?>
                Swal.fire({
                    title: 'Error!',
                    text: '<?= addslashes($_SESSION['error']) ?>',
                    icon: 'error',
                    confirmButtonColor: '#dc3545',
                    confirmButtonText: 'OK'
                });
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
        });
    </script>

</body>
</html>