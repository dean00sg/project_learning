<?php

session_start();

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: user_accounts, user_staffs, classroom, repair_requests
// โครงสร้างตารางแบบเต็มดูได้ที่ database/schema.sql

// =====================================================
// ตรวจสอบ Login
// =====================================================

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/index.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];

/**
 * ตรวจว่า $user_id เป็นครูที่ปรึกษาของห้อง โดยอ่านจากคอลัมน์
 * classroom.advisor_staff_id ซึ่งเก็บเป็น JSON array ของ staff user_id
 * เช่น "[1]" หรือ "[1,4]"
 */
function isAdvisorOf($advisor_staff_id, $user_id)
{
    if (empty($advisor_staff_id) || !is_string($advisor_staff_id)) {
        return false;
    }

    json_decode($advisor_staff_id);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return false;
    }

    $advisor_ids = json_decode($advisor_staff_id, true);

    if (!is_array($advisor_ids)) {
        return false;
    }

    foreach ($advisor_ids as $advisor_id) {
        if ((int)$advisor_id === (int)$user_id) {
            return true;
        }
    }

    return false;
}

function fail($message)
{
    echo "<script>
        alert(" . json_encode($message, JSON_UNESCAPED_UNICODE) . ");
        window.location.href = 'main.php';
    </script>";
    exit;
}

// =====================================================
// รับ request_id
// =====================================================

$request_id = (int)($_POST['request_id'] ?? 0);

if ($request_id <= 0) {
    fail('ไม่พบรายการแจ้งซ่อม');
}

// =====================================================
// ตรวจสอบว่า User เป็นบุคลากร/อาจารย์หรือไม่
// =====================================================

$sql = "
    SELECT ua.user_id, ua.role, ua.is_active, ust.staff_id
    FROM user_accounts ua
    LEFT JOIN user_staffs ust ON ust.user_id = ua.user_id
    WHERE ua.user_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("เกิดข้อผิดพลาด SQL: " . $conn->error);
}

$stmt->bind_param("i", $user_id);
$stmt->execute();

$user = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$user) {
    fail('ไม่พบข้อมูลผู้ใช้งาน');
}

if (empty($user['staff_id'])) {
    fail('บัญชีนี้ไม่มีสิทธิ์เป็นอาจารย์หรือบุคลากร');
}

// =====================================================
// ตรวจสอบรายการ
//
// สำคัญ:
// 1. request_id ต้องมีจริง และ approved_by ต้องยังเป็น NULL
// 2. ผู้อนุมัติต้องเป็นครูที่ปรึกษาของห้องนั้น (advisor_staff_id)
// 3. ผู้อนุมัติต้องไม่ใช่เจ้าของรายการเอง
// =====================================================

$sql = "
    SELECT r.request_id, r.requester_id, r.classroom_id, r.approved_by,
           c.advisor_staff_id
    FROM repair_requests r
    INNER JOIN classroom c ON c.classroom_id = r.classroom_id
    WHERE r.request_id = ? AND r.approved_by IS NULL
    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("เกิดข้อผิดพลาด SQL: " . $conn->error);
}

$stmt->bind_param("i", $request_id);
$stmt->execute();

$request = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$request) {
    fail('รายการนี้ไม่มีอยู่ หรือได้รับการอนุมัติแล้ว');
}

$is_owner   = (int)$request['requester_id'] === $user_id;
$is_advisor = isAdvisorOf($request['advisor_staff_id'] ?? null, $user_id);

if ($is_owner) {
    fail('ไม่สามารถอนุมัติรายการแจ้งซ่อมของตนเองได้');
}

if (!$is_advisor) {
    fail('คุณไม่มีสิทธิ์อนุมัติรายการแจ้งซ่อมนี้');
}

// =====================================================
// อนุมัติ (approved_by = user_id ของครูที่ปรึกษา)
// =====================================================

$sql = "
    UPDATE repair_requests
    SET approved_by = ?, approved_at = NOW()
    WHERE request_id = ? AND approved_by IS NULL
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("เกิดข้อผิดพลาด SQL: " . $conn->error);
}

$stmt->bind_param("ii", $user_id, $request_id);
$stmt->execute();

$affected_rows = $stmt->affected_rows;
$error         = $stmt->error;

$stmt->close();

if ($affected_rows === 1) {
    echo "<script>
        alert('อนุมัติรายการแจ้งซ่อมเรียบร้อยแล้ว');
        window.location.href = 'main.php';
    </script>";
    exit;
}

fail('เกิดข้อผิดพลาด: ' . $error);
