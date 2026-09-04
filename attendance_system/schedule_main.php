<?php

session_start();

$page_title = "จัดการตารางสอน";
$css_path   = "../css/attendance.css";

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: class_schedule, classroom, user_accounts, user_staffs
// โครงสร้างตารางแบบเต็มดูได้ที่ database/attendance_system.sql

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

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function getDayName($day_of_week)
{
    $days = [1 => "จันทร์", 2 => "อังคาร", 3 => "พุธ", 4 => "พฤหัสบดี", 5 => "ศุกร์", 6 => "เสาร์", 7 => "อาทิตย์"];

    return $days[(int)$day_of_week] ?? "-";
}

// =====================================================
// รายการตารางสอนทั้งหมด
// =====================================================

$sql = "
    SELECT
        cs.schedule_id, cs.subject_code, cs.subject_name, cs.day_of_week,
        cs.start_time, cs.end_time, cs.room, cs.is_active,

        c.classroom_type, c.classroom_number_code,

        ust.title_name, ust.first_name_th, ust.last_name_th

    FROM class_schedule cs
    INNER JOIN classroom c ON c.classroom_id = cs.classroom_id
    LEFT JOIN user_staffs ust ON ust.user_id = cs.staff_id
    ORDER BY c.classroom_number_code, cs.day_of_week, cs.start_time
";

$schedules = [];
$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $schedules[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<?php include __DIR__ . "/../includes/head.php"; ?>
<body>
<div class="attendance-page">

    <header class="attendance-header">
        <div class="header-inner">
            <div class="header-brand">
                <div class="brand-icon">🗓️</div>
                <div>
                    <h1>จัดการตารางสอน</h1>
                    <span>Class Attendance System</span>
                </div>
            </div>

            <a href="main.php" class="back-home">← กลับ</a>
        </div>
    </header>

    <main class="attendance-container">

        <div class="page-heading">
            <div>
                <h2>ตารางสอนทั้งหมด</h2>
                <p><?= count($schedules) ?> คาบ</p>
            </div>

            <a href="schedule_create.php" class="submit-btn" style="text-decoration:none;">+ เพิ่มคาบเรียน</a>
        </div>

        <div class="recent-card" style="margin-top:0;">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>วิชา</th>
                            <th>ห้อง</th>
                            <th>วัน/เวลา</th>
                            <th>ห้องสอน</th>
                            <th>ครูผู้สอน</th>
                            <th>สถานะ</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($schedules)): ?>
                            <?php foreach ($schedules as $s): ?>
                                <?php $teacher_name = trim(($s['title_name'] ?? "") . " " . ($s['first_name_th'] ?? "") . " " . ($s['last_name_th'] ?? "")); ?>
                                <tr>
                                    <td>
                                        <?= e($s['subject_name']) ?>
                                        <?php if (!empty($s['subject_code'])): ?>
                                            <span style="color:#92929c;">(<?= e($s['subject_code']) ?>)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e($s['classroom_type']) ?> <?= e($s['classroom_number_code']) ?></td>
                                    <td><?= e(getDayName($s['day_of_week'])) ?> <?= substr($s['start_time'], 0, 5) ?>-<?= substr($s['end_time'], 0, 5) ?></td>
                                    <td><?= e($s['room'] ?? '-') ?></td>
                                    <td><?= $teacher_name !== "" ? e($teacher_name) : "-" ?></td>
                                    <td><span class="status <?= $s['is_active'] ? 'active' : 'inactive' ?>"><?= $s['is_active'] ? 'ใช้งาน' : 'ปิดใช้งาน' ?></span></td>
                                    <td><a href="schedule_edit.php?id=<?= e($s['schedule_id']) ?>">แก้ไข</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="empty-data">ยังไม่มีตารางสอน</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</div>
</body>
</html>
