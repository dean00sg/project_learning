<?php

session_start();

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: user_accounts, user_students, user_staffs,
//                     classroom, repair_requests
// โครงสร้างตารางแบบเต็มดูได้ที่ database/schema.sql

// =====================================================
// ตรวจสอบ Login
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

// =====================================================
// รับข้อมูล
// =====================================================

$request_type  = trim($_POST['request_type'] ?? "");
$classroom_id  = (int)($_POST['classroom_id'] ?? 0);
$repair_detail = trim($_POST['repair_detail'] ?? "");

// =====================================================
// ตรวจข้อมูล
// =====================================================

if ($request_type === "" || $classroom_id <= 0 || $repair_detail === "") {
    fail('กรุณากรอกข้อมูลให้ครบถ้วน');
}

$allowed_types = [
    "computer", "projector", "printer",
    "network", "electric", "air_conditioner", "other",
];

if (!in_array($request_type, $allowed_types, true)) {
    fail('ประเภทการแจ้งซ่อมไม่ถูกต้อง');
}

// =====================================================
// ตรวจ User
// =====================================================

$sql = "
    SELECT
        ua.user_id, ua.role,
        us.student_id, us.classroom_id AS student_classroom_id,
        ust.staff_id
    FROM user_accounts ua
    LEFT JOIN user_students us ON us.user_id = ua.user_id
    LEFT JOIN user_staffs ust ON ust.user_id = ua.user_id
    WHERE ua.user_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();

$user = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$user) {
    fail('ไม่พบข้อมูลผู้ใช้งาน');
}

// นักเรียนต้องแจ้งซ่อมเฉพาะห้องของตัวเองเท่านั้น
if (!empty($user['student_id'])) {
    $student_classroom_id = (int)$user['student_classroom_id'];

    if ($student_classroom_id <= 0 || $classroom_id !== $student_classroom_id) {
        fail('ไม่สามารถเลือกห้องเรียนอื่นได้');
    }
}

// บุคลากรต้องแจ้งซ่อมเฉพาะห้องที่ตนเป็นครูที่ปรึกษาเท่านั้น
if (!empty($user['staff_id'])) {
    $sql = "
        SELECT classroom_id
        FROM classroom
        WHERE
            classroom_id = ?
            AND advisor_staff_id IS NOT NULL
            AND JSON_VALID(advisor_staff_id)
            AND JSON_CONTAINS(advisor_staff_id, JSON_ARRAY(?))
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $classroom_id, $user_id);
    $stmt->execute();

    $advised_classroom = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    if (!$advised_classroom) {
        fail('คุณไม่ได้เป็นครูที่ปรึกษาของห้องเรียนนี้');
    }
}

// =====================================================
// ตรวจว่าห้องมีจริง
// =====================================================

$sql = "SELECT classroom_id FROM classroom WHERE classroom_id = ? LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $classroom_id);
$stmt->execute();

$classroom = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$classroom) {
    fail('ไม่พบห้องเรียนที่เลือก');
}

// =====================================================
// Upload รูป
// =====================================================

$request_image = null;

if (isset($_FILES['request_image']) && $_FILES['request_image']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['request_image'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        fail('ไม่สามารถอัปโหลดรูปภาพได้');
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        fail('รูปภาพต้องมีขนาดไม่เกิน 5 MB');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);

    $allowed_mime = [
        "image/jpeg" => "jpg",
        "image/png"  => "png",
    ];

    if (!isset($allowed_mime[$mime])) {
        fail('รองรับเฉพาะ JPG และ PNG เท่านั้น');
    }

    $upload_dir = __DIR__ . "/uploads/";

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $extension = $allowed_mime[$mime];
    $filename  = "repair_" . date("YmdHis") . "_" . bin2hex(random_bytes(5)) . "." . $extension;
    $target    = $upload_dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        fail('ไม่สามารถบันทึกรูปภาพได้');
    }

    $request_image = "uploads/" . $filename;
}

// =====================================================
// INSERT (approved_by = NULL หมายถึงรอครูอนุมัติ)
// =====================================================

$sql = "
    INSERT INTO repair_requests
        (request_type, classroom_id, requester_id, request_datetime, approved_by, approved_at, repair_detail, request_image)
    VALUES (?, ?, ?, NOW(), NULL, NULL, ?, ?)
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("เกิดข้อผิดพลาด: " . $conn->error);
}

$stmt->bind_param("siiss", $request_type, $classroom_id, $user_id, $repair_detail, $request_image);

if ($stmt->execute()) {
    $request_id = $stmt->insert_id;

    $stmt->close();

    header("Location: detail.php?id=" . $request_id);
    exit;
}

$error = $stmt->error;

$stmt->close();

fail('เกิดข้อผิดพลาด: ' . $error);
