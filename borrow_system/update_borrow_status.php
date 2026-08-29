<?php

session_start();

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: user_accounts, user_staffs, equipment_item, borrow_requests
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
        window.location.href = 'officer.php';
    </script>";
    exit;
}

// =====================================================
// ตรวจสอบสิทธิ์: เฉพาะบุคลากรที่ staff_type_code = 'equipment_officer'
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

if (!$staff || $staff['staff_type_code'] !== 'equipment_officer') {
    http_response_code(403);
    die("คุณไม่มีสิทธิ์ดำเนินการรายการยืม-คืน");
}

// =====================================================
// รับข้อมูล
// =====================================================

$borrow_id        = (int)($_POST['borrow_id'] ?? 0);
$return_condition = $_POST['return_condition'] ?? '';
$return_detail    = trim($_POST['return_detail'] ?? '');

if ($borrow_id <= 0) {
    fail('ไม่พบรายการแจ้งยืม');
}

if (!in_array($return_condition, ['normal', 'damaged'], true)) {
    fail('กรุณาระบุสภาพอุปกรณ์');
}

if ($return_condition === 'damaged' && $return_detail === '') {
    fail('กรุณาระบุรายละเอียดความเสียหาย');
}

// =====================================================
// ดึงรายการปัจจุบัน
// =====================================================

$sql = "
    SELECT borrow_id, item_id, return_requested_at, return_item_at
    FROM borrow_requests
    WHERE borrow_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $borrow_id);
$stmt->execute();

$request = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$request) {
    fail('ไม่พบรายการแจ้งยืม');
}

if (empty($request['return_requested_at'])) {
    fail('รายการนี้ยังไม่ได้แจ้งคืน');
}

if (!empty($request['return_item_at'])) {
    fail('รายการนี้ยืนยันการคืนไปแล้ว');
}

// =====================================================
// ยืนยันการคืน
// =====================================================

$conn->begin_transaction();

try {
    $sql = "
        UPDATE borrow_requests
        SET return_item_by = ?, return_item_at = NOW(), return_condition = ?, return_detail = ?
        WHERE borrow_id = ? AND return_item_at IS NULL
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issi", $user_id, $return_condition, $return_detail, $borrow_id);
    $stmt->execute();

    if ($stmt->affected_rows !== 1) {
        throw new Exception('ไม่สามารถยืนยันการคืนได้');
    }

    $stmt->close();

    $new_item_status = $return_condition === 'damaged' ? 'damaged' : 'available';

    $sql = "UPDATE equipment_item SET status = ? WHERE item_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $new_item_status, $request['item_id']);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

} catch (Exception $e) {
    $conn->rollback();
    fail('เกิดข้อผิดพลาด: ' . $e->getMessage());
}

echo "<script>
    alert('ยืนยันการคืนอุปกรณ์เรียบร้อยแล้ว');
    window.location.href = 'officer.php';
</script>";
