<?php

session_start();

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: classroom
// โครงสร้างตารางแบบเต็มดูได้ที่ database/repair_system.sql

// =====================================================
// ตรวจสอบสิทธิ์: เฉพาะบุคลากร (staff) และผู้ดูแลระบบ (admin)
// =====================================================

if (
    !isset($_SESSION['user_id']) ||
    !in_array($_SESSION['role'] ?? '', ['staff', 'admin'], true)
) {
    header("Location: ../login/index.php");
    exit;
}

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

$classroom_type        = trim($_POST['classroom_type'] ?? "");
$classroom_number_code = trim($_POST['classroom_number_code'] ?? "");
$classroom_level        = $_POST['classroom_level'] ?? "";
$building               = trim($_POST['building'] ?? "");
$advisor_ids            = $_POST['advisor_ids'] ?? [];

if ($classroom_number_code === "") {
    fail('กรุณากรอกรหัสห้อง');
}

$classroom_level = $classroom_level !== "" ? (int)$classroom_level : null;

$advisor_ids = array_values(array_unique(array_map('intval', $advisor_ids)));
$advisor_staff_id = !empty($advisor_ids) ? json_encode($advisor_ids) : null;

// =====================================================
// บันทึกห้องเรียน
// =====================================================

$sql = "
    INSERT INTO classroom (classroom_type, classroom_number_code, classroom_level, advisor_staff_id, building)
    VALUES (?, ?, ?, ?, ?)
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ssiss", $classroom_type, $classroom_number_code, $classroom_level, $advisor_staff_id, $building);

if (!$stmt->execute()) {
    $stmt->close();
    fail('เกิดข้อผิดพลาด ไม่สามารถบันทึกห้องเรียนได้');
}

$stmt->close();

header("Location: classroom_main.php");
exit;
