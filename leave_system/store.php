<?php

session_start();

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: user_students, leave_types, leave_requests
// โครงสร้างตารางแบบเต็มดูได้ที่ database/leave_system.sql

// =====================================================
// ตรวจสอบสิทธิ์: เฉพาะนักเรียนเท่านั้น
// =====================================================

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/index.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];

function fail($message)
{
    echo "<script>
        alert(" . json_encode($message, JSON_UNESCAPED_UNICODE) . ");
        history.back();
    </script>";
    exit;
}

$sql = "SELECT student_id, classroom_id FROM user_students WHERE user_id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    http_response_code(403);
    fail('หน้านี้สำหรับนักเรียนเท่านั้น');
}

if (empty($student['classroom_id'])) {
    fail('บัญชีนี้ยังไม่มีห้องเรียน กรุณาติดต่อเจ้าหน้าที่');
}

// =====================================================
// รับข้อมูล
// =====================================================

$leave_type_id = (int)($_POST['leave_type_id'] ?? 0);
$start_date     = $_POST['start_date'] ?? "";
$end_date       = $_POST['end_date'] ?? "";
$reason         = trim($_POST['reason'] ?? "");

if ($leave_type_id <= 0 || $start_date === "" || $end_date === "" || $reason === "") {
    fail('กรุณากรอกข้อมูลให้ครบถ้วน');
}

$sql = "SELECT leave_type_id FROM leave_types WHERE leave_type_id = ? AND is_active = 1 LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $leave_type_id);
$stmt->execute();
$leave_type = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$leave_type) {
    fail('ประเภทการลาไม่ถูกต้องหรือถูกปิดใช้งาน');
}

$start_ts = strtotime($start_date);
$end_ts   = strtotime($end_date);

if ($start_ts === false || $end_ts === false) {
    fail('รูปแบบวันที่ไม่ถูกต้อง');
}

if ($end_ts < $start_ts) {
    fail('วันที่สิ้นสุดต้องไม่ก่อนวันที่เริ่ม');
}

// =====================================================
// เอกสารประกอบ (ไม่บังคับ)
// =====================================================

$evidence_image = null;

if (isset($_FILES['evidence_image']) && $_FILES['evidence_image']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['evidence_image'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        fail('ไม่สามารถอัปโหลดเอกสารประกอบได้');
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        fail('ไฟล์ต้องมีขนาดไม่เกิน 5 MB');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);

    $allowed_mime = ["image/jpeg" => "jpg", "image/png" => "png"];

    if (!isset($allowed_mime[$mime])) {
        fail('รองรับเฉพาะไฟล์ JPG และ PNG เท่านั้น');
    }

    $upload_dir = __DIR__ . "/uploads/";

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $extension = $allowed_mime[$mime];
    $filename  = "leave_" . date("YmdHis") . "_" . bin2hex(random_bytes(5)) . "." . $extension;
    $target    = $upload_dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        fail('ไม่สามารถบันทึกไฟล์เอกสารประกอบได้');
    }

    $evidence_image = "uploads/" . $filename;
}

// =====================================================
// บันทึกคำขอ
// =====================================================

$sql = "
    INSERT INTO leave_requests
        (leave_type_id, student_id, classroom_id, start_date, end_date, reason, evidence_image, request_at, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 'PENDING_ADVISOR')
";

$stmt = $conn->prepare($sql);
$stmt->bind_param(
    "iiissss",
    $leave_type_id,
    $student['student_id'],
    $student['classroom_id'],
    $start_date,
    $end_date,
    $reason,
    $evidence_image
);

if (!$stmt->execute()) {
    $stmt->close();
    fail('เกิดข้อผิดพลาด ไม่สามารถบันทึกคำขอได้');
}

$request_id = $stmt->insert_id;

$stmt->close();

header("Location: detail.php?id=" . $request_id);
exit;
