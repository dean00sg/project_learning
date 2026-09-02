<?php

session_start();

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: leave_types
// โครงสร้างตารางแบบเต็มดูได้ที่ database/leave_system.sql

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

$leave_type_name = trim($_POST['leave_type_name'] ?? "");
$detail           = trim($_POST['detail'] ?? "");
$requires_discipline_approval = isset($_POST['requires_discipline_approval']) ? 1 : 0;

if ($leave_type_name === "") {
    fail('กรุณากรอกชื่อประเภทการลา');
}

$sql = "INSERT INTO leave_types (leave_type_name, detail, requires_discipline_approval, is_active) VALUES (?, ?, ?, 1)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssi", $leave_type_name, $detail, $requires_discipline_approval);

if (!$stmt->execute()) {
    $stmt->close();
    fail('เกิดข้อผิดพลาด ไม่สามารถบันทึกได้');
}

$stmt->close();

header("Location: types_main.php");
exit;
