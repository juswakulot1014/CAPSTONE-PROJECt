<?php
session_start();
include __DIR__ . "/../config/db.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}

$student_id = $_GET['id'];

// List all child tables in the correct order
$child_tables = [
    'addresses',
    'parents_info',
    'educational_history',
    'enrollment_form',
    // add any other tables that reference students_info.student_id
];

foreach ($child_tables as $table) {
    $stmt = $conn->prepare("DELETE FROM $table WHERE student_id=?");
    if ($stmt) {
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $stmt->close();
    }
}

// Now delete the student from students_info
$stmt = $conn->prepare("DELETE FROM students_info WHERE student_id=?");
if ($stmt) {
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $stmt->close();
}

$conn->close();
header("Location: dashboard.php");
exit();
?>