<?php

session_start();

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: class_schedule, user_students, attendance, leave_requests
// โครงสร้างตารางแบบเต็มดูได้ที่ database/attendance_system.sql

// =====================================================
// ตรวจสอบ Login
// =====================================================

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/index.php");
    exit;
}

$user_id     = (int)$_SESSION['user_id'];
$schedule_id = (int)($_POST['schedule_id'] ?? 0);
$date        = $_POST['date'] ?? "";
$attendance  = $_POST['attendance'] ?? [];

function fail($message, $schedule_id, $date)
{
    echo "<script>
        alert(" . json_encode($message, JSON_UNESCAPED_UNICODE) . ");
        window.location.href = 'take.php?schedule_id=" . (int)$schedule_id . "&date=" . urlencode($date) . "';
    </script>";
    exit;
}

if ($schedule_id <= 0 || $date === "" || strtotime($date) === false) {
    fail('ข้อมูลไม่ถูกต้อง', $schedule_id, $date);
}

// =====================================================
// ตรวจสิทธิ์: ครูผู้สอนของคาบนี้ หรือแอดมิน เท่านั้น
// =====================================================

$sql = "SELECT classroom_id, staff_id FROM class_schedule WHERE schedule_id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $schedule_id);
$stmt->execute();
$schedule = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$schedule) {
    fail('ไม่พบคาบเรียน', $schedule_id, $date);
}

$sql = "SELECT role FROM user_accounts WHERE user_id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$viewer = $stmt->get_result()->fetch_assoc();
$stmt->close();

$is_admin = $viewer && $viewer['role'] === 'admin';
$is_owner = (int)$schedule['staff_id'] === $user_id;

if (!$is_owner && !$is_admin) {
    http_response_code(403);
    fail('คุณไม่มีสิทธิ์เช็คชื่อคาบเรียนนี้', $schedule_id, $date);
}

/**
 * นักเรียนคนนี้มีใบลาที่ APPROVED ครอบคลุมวันที่นี้หรือไม่
 */
function findApprovedLeaveId($conn, $student_id, $date)
{
    $sql = "
        SELECT request_id
        FROM leave_requests
        WHERE student_id = ? AND status = 'APPROVED'
            AND start_date <= ? AND end_date >= ?
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $student_id, $date, $date);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ? (int)$row['request_id'] : null;
}

// =====================================================
// บันทึกการเช็คชื่อทีละคน (ตรวจว่านักเรียนเป็นของห้องนี้จริง)
// =====================================================

foreach ($attendance as $student_id => $entry) {
    $student_id = (int)$student_id;
    $status     = $entry['status'] ?? '';
    $remark     = trim($entry['remark'] ?? '');

    if (!in_array($status, ['PRESENT', 'LATE', 'ABSENT', 'LEAVE'], true)) {
        continue;
    }

    $sql = "SELECT student_id FROM user_students WHERE student_id = ? AND classroom_id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $student_id, $schedule['classroom_id']);
    $stmt->execute();
    $belongs = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$belongs) {
        continue;
    }

    $leave_request_id = findApprovedLeaveId($conn, $student_id, $date);

    if ($leave_request_id) {
        // มีใบลาอนุมัติจริงในวันนี้ -> บังคับเป็น "ลา" เสมอ ไม่ว่าครูจะส่งสถานะอะไรมา
        $status = 'LEAVE';
    } elseif ($status === 'LEAVE') {
        continue; // ไม่มีใบลาอนุมัติจริง ไม่ยอมให้บันทึกเป็น "ลา"
    }

    $sql = "
        INSERT INTO attendance (schedule_id, student_id, attendance_date, checkin_at, status, leave_request_id, remark, checked_by)
        VALUES (?, ?, ?, NOW(), ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            checkin_at = NOW(), status = VALUES(status), leave_request_id = VALUES(leave_request_id),
            remark = VALUES(remark), checked_by = VALUES(checked_by)
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iissisi", $schedule_id, $student_id, $date, $status, $leave_request_id, $remark, $user_id);
    $stmt->execute();
    $stmt->close();
}

echo "<script>
    alert('บันทึกการเช็คชื่อเรียบร้อยแล้ว');
    window.location.href = 'take.php?schedule_id=" . (int)$schedule_id . "&date=" . urlencode($date) . "';
</script>";
