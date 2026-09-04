<?php

session_start();

$page_title = "เช็คชื่อ";
$css_path   = "../css/attendance.css";

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: class_schedule, classroom, user_students, attendance, leave_requests
// โครงสร้างตารางแบบเต็มดูได้ที่ database/attendance_system.sql
// (leave_requests อยู่ที่ database/leave_system.sql — ใช้ตรวจใบลาอนุมัติ)

// =====================================================
// ตรวจสอบ Login
// =====================================================

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/index.php");
    exit;
}

$user_id     = (int)$_SESSION['user_id'];
$schedule_id = (int)($_GET['schedule_id'] ?? 0);
$date        = $_GET['date'] ?? date("Y-m-d");

if ($schedule_id <= 0) {
    die("ไม่พบคาบเรียน");
}

if (strtotime($date) === false) {
    $date = date("Y-m-d");
}

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function getDayName($day_of_week)
{
    $days = [1 => "จันทร์", 2 => "อังคาร", 3 => "พุธ", 4 => "พฤหัสบดี", 5 => "ศุกร์", 6 => "เสาร์", 7 => "อาทิตย์"];

    return $days[(int)$day_of_week] ?? "-";
}

/**
 * นักเรียนคนนี้มีใบลาที่ APPROVED ครอบคลุมวันที่นี้หรือไม่
 * (เช็คชื่อวันที่มีใบลาอนุมัติ -> บังคับสถานะ "ลา" อัตโนมัติ ครูแก้ไม่ได้)
 */
function findApprovedLeave($conn, $student_id, $date)
{
    $sql = "
        SELECT r.request_id, lt.leave_type_name
        FROM leave_requests r
        INNER JOIN leave_types lt ON lt.leave_type_id = r.leave_type_id
        WHERE r.student_id = ? AND r.status = 'APPROVED'
            AND r.start_date <= ? AND r.end_date >= ?
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $student_id, $date, $date);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

// =====================================================
// ดึงคาบเรียน + ตรวจสิทธิ์ (ครูผู้สอนของคาบนี้ หรือแอดมิน เท่านั้น)
// =====================================================

$sql = "
    SELECT cs.*, c.classroom_type, c.classroom_number_code
    FROM class_schedule cs
    INNER JOIN classroom c ON c.classroom_id = cs.classroom_id
    WHERE cs.schedule_id = ?
    LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $schedule_id);
$stmt->execute();
$schedule = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$schedule) {
    die("ไม่พบคาบเรียน");
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
    die("คุณไม่มีสิทธิ์เช็คชื่อคาบเรียนนี้");
}

// =====================================================
// รายชื่อนักเรียนในห้อง + สถานะที่เช็คไว้แล้ว (ถ้ามี) + ใบลาอนุมัติ
// =====================================================

$sql = "
    SELECT student_id, student_code, title_name, first_name_th, last_name_th
    FROM user_students
    WHERE classroom_id = ?
    ORDER BY student_code
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $schedule['classroom_id']);
$stmt->execute();

$roster = [];
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $sql2 = "SELECT status, remark FROM attendance WHERE schedule_id = ? AND student_id = ? AND attendance_date = ? LIMIT 1";
    $stmt2 = $conn->prepare($sql2);
    $stmt2->bind_param("iis", $schedule_id, $row['student_id'], $date);
    $stmt2->execute();
    $existing = $stmt2->get_result()->fetch_assoc();
    $stmt2->close();

    $row['existing_status'] = $existing['status'] ?? null;
    $row['existing_remark'] = $existing['remark'] ?? '';
    $row['approved_leave']  = findApprovedLeave($conn, $row['student_id'], $date);

    $roster[] = $row;
}

$stmt->close();
?>
<!DOCTYPE html>
<html lang="th">
<?php include __DIR__ . "/../includes/head.php"; ?>
<body>
<div class="attendance-page">

    <header class="attendance-header">
        <div class="header-inner">
            <div class="header-brand">
                <div class="brand-icon">✓</div>
                <div>
                    <h1><?= e($schedule['subject_name']) ?></h1>
                    <span>
                        ห้อง <?= e($schedule['classroom_type']) ?> <?= e($schedule['classroom_number_code']) ?>
                        • <?= e(getDayName($schedule['day_of_week'])) ?> <?= substr($schedule['start_time'], 0, 5) ?>-<?= substr($schedule['end_time'], 0, 5) ?>
                    </span>
                </div>
            </div>

            <a href="main.php" class="back-home">← กลับ</a>
        </div>
    </header>

    <main class="attendance-container">

        <form method="GET" action="take.php" style="display:flex; gap:8px; align-items:center; margin-bottom:20px; justify-content:flex-end;">
            <input type="hidden" name="schedule_id" value="<?= e($schedule_id) ?>">
            <input type="date" name="date" value="<?= e($date) ?>" style="width:auto;">
            <button type="submit" class="cancel-btn">ไปวันที่นี้</button>
        </form>

        <div class="attendance-card">
            <form action="store_attendance.php" method="POST">
                <input type="hidden" name="schedule_id" value="<?= e($schedule_id) ?>">
                <input type="hidden" name="date" value="<?= e($date) ?>">

                <?php if (empty($roster)): ?>
                    <div class="notice-box">
                        <div class="notice-icon">ℹ️</div>
                        <div><strong>ห้องนี้ยังไม่มีนักเรียน</strong></div>
                    </div>
                <?php else: ?>

                    <?php foreach ($roster as $r): ?>
                        <?php
                        $name = trim(($r['title_name'] ?? "") . " " . $r['first_name_th'] . " " . $r['last_name_th']);
                        $current = $r['existing_status'] ?? 'PRESENT';
                        ?>
                        <div class="attendance-row">
                            <div class="attendance-name"><?= e($r['student_code']) ?> — <?= e($name) ?></div>
                            <div class="attendance-choice">
                                <?php if ($r['approved_leave']): ?>
                                    <input type="hidden" name="attendance[<?= e($r['student_id']) ?>][status]" value="LEAVE">
                                    <span class="status leave">🟡 ลา (<?= e($r['approved_leave']['leave_type_name']) ?>)</span>
                                <?php else: ?>
                                    <label>
                                        <input type="radio" name="attendance[<?= e($r['student_id']) ?>][status]" value="PRESENT" <?= $current === 'PRESENT' ? 'checked' : '' ?>>
                                        มา
                                    </label>
                                    <label>
                                        <input type="radio" name="attendance[<?= e($r['student_id']) ?>][status]" value="LATE" <?= $current === 'LATE' ? 'checked' : '' ?>>
                                        สาย
                                    </label>
                                    <label>
                                        <input type="radio" name="attendance[<?= e($r['student_id']) ?>][status]" value="ABSENT" <?= $current === 'ABSENT' ? 'checked' : '' ?>>
                                        ขาด
                                    </label>
                                <?php endif; ?>
                                <input type="text" name="attendance[<?= e($r['student_id']) ?>][remark]" value="<?= e($r['existing_remark']) ?>" placeholder="หมายเหตุ" style="width:160px;">
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="form-actions">
                        <a href="main.php" class="cancel-btn">ยกเลิก</a>
                        <button type="submit" class="submit-btn">✅ บันทึกการเช็คชื่อ</button>
                    </div>

                <?php endif; ?>
            </form>
        </div>

    </main>
</div>
</body>
</html>
