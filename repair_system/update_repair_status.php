<?php

session_start();

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: user_accounts, user_staffs, repair_requests, repair_process
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
        window.location.href = 'technician.php';
    </script>";
    exit;
}

// =====================================================
// ตรวจสอบสิทธิ์: เฉพาะบุคลากรที่ staff_type_code = 'technician'
// =====================================================

$sql = "
    SELECT ust.staff_id, ust.staff_type_code
    FROM user_accounts ua
    INNER JOIN user_staffs ust ON ust.user_id = ua.user_id
    WHERE ua.user_id = ? AND ua.is_active = 1
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();

$staff = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$staff || $staff['staff_type_code'] !== 'technician') {
    http_response_code(403);
    die("คุณไม่มีสิทธิ์ดำเนินการซ่อม");
}

$staff_id = (int)$staff['staff_id'];

// =====================================================
// รับข้อมูล
// =====================================================

$request_id = (int)($_POST['request_id'] ?? 0);
$action     = $_POST['action'] ?? '';

if ($request_id <= 0 || !in_array($action, ['start', 'complete'], true)) {
    fail('ข้อมูลไม่ถูกต้อง');
}

// =====================================================
// ตรวจสอบว่ารายการอนุมัติแล้ว และดึงสถานะซ่อมปัจจุบัน
// =====================================================

$sql = "
    SELECT r.request_id, r.approved_by, rp.status_repair
    FROM repair_requests r
    LEFT JOIN repair_process rp ON rp.request_id = r.request_id
    WHERE r.request_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $request_id);
$stmt->execute();

$request = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$request) {
    fail('ไม่พบรายการแจ้งซ่อม');
}

if (empty($request['approved_by'])) {
    fail('รายการนี้ยังไม่ได้รับการอนุมัติ');
}

// =====================================================
// เริ่มซ่อม: ต้องยังไม่มีการเริ่มซ่อมมาก่อน
// =====================================================

if ($action === 'start') {
    if ($request['status_repair'] !== null) {
        fail('รายการนี้เริ่มดำเนินการซ่อมไปแล้ว');
    }

    $sql = "
        INSERT INTO repair_process
            (request_id, repair_datetime, staff_repair_id, status_repair, status_datetime)
        VALUES (?, NOW(), ?, 'repairing', NOW())
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $request_id, $staff_id);

    if (!$stmt->execute()) {
        fail('เกิดข้อผิดพลาด: ' . $stmt->error);
    }

    $stmt->close();

    echo "<script>
        alert('เริ่มดำเนินการซ่อมแล้ว');
        window.location.href = 'technician.php';
    </script>";
    exit;
}

// =====================================================
// เสร็จสิ้น: ต้องอยู่ในสถานะกำลังซ่อมอยู่ก่อน
// =====================================================

if ($request['status_repair'] !== 'repairing') {
    fail('รายการนี้ยังไม่ได้เริ่มดำเนินการซ่อม');
}

$sql = "
    UPDATE repair_process
    SET status_repair = 'done', status_datetime = NOW()
    WHERE request_id = ? AND status_repair = 'repairing'
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $request_id);
$stmt->execute();

$affected_rows = $stmt->affected_rows;

$stmt->close();

if ($affected_rows !== 1) {
    fail('เกิดข้อผิดพลาด ไม่สามารถอัปเดตสถานะได้');
}

echo "<script>
    alert('บันทึกการซ่อมเสร็จสิ้นแล้ว');
    window.location.href = 'technician.php';
</script>";
