<?php

session_start();

$page_title = "เช็คชื่อเข้าเรียน";
$css_path   = "../css/attendance.css";

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: user_accounts, user_students, user_staffs,
//                     classroom, class_schedule, attendance
// โครงสร้างตารางแบบเต็มดูได้ที่ database/attendance_system.sql

// =====================================================
// ตรวจสอบ Login
// =====================================================

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/index.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function getDayName($day_of_week)
{
    $days = [1 => "จันทร์", 2 => "อังคาร", 3 => "พุธ", 4 => "พฤหัสบดี", 5 => "ศุกร์", 6 => "เสาร์", 7 => "อาทิตย์"];

    return $days[(int)$day_of_week] ?? "-";
}

function getStatusInfo($status)
{
    $map = [
        "PRESENT" => ["text" => "มาเรียน", "class" => "present"],
        "LATE"    => ["text" => "มาสาย",   "class" => "late"],
        "ABSENT"  => ["text" => "ขาดเรียน", "class" => "absent"],
        "LEAVE"   => ["text" => "ลา",       "class" => "leave"],
    ];

    return $map[$status] ?? ["text" => $status, "class" => ""];
}

// =====================================================
// ดึงข้อมูลผู้ใช้งาน
// =====================================================

$sql = "
    SELECT
        ua.user_id, ua.role,
        us.student_id,
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
    die("ไม่พบข้อมูลผู้ใช้งาน");
}

$is_student = !empty($user['student_id']);
$is_staff   = !empty($user['staff_id']);

// =====================================================
// ครูผู้สอน: คาบเรียนของตัวเอง + จำนวนที่เช็คแล้ววันนี้
// =====================================================

$my_schedules = [];

if ($is_staff) {
    $today = date("Y-m-d");

    $sql = "
        SELECT
            cs.schedule_id, cs.subject_name, cs.day_of_week, cs.start_time, cs.end_time,
            c.classroom_type, c.classroom_number_code,
            (SELECT COUNT(*) FROM user_students s WHERE s.classroom_id = cs.classroom_id) AS student_count,
            (SELECT COUNT(*) FROM attendance a WHERE a.schedule_id = cs.schedule_id AND a.attendance_date = ?) AS checked_today
        FROM class_schedule cs
        INNER JOIN classroom c ON c.classroom_id = cs.classroom_id
        WHERE cs.staff_id = ? AND cs.is_active = 1
        ORDER BY cs.day_of_week, cs.start_time
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $today, $user_id);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $my_schedules[] = $row;
    }

    $stmt->close();
}

// =====================================================
// นักเรียน: สรุปสถิติ + ประวัติล่าสุด 5 รายการ
// =====================================================

$student_summary = ["PRESENT" => 0, "LATE" => 0, "ABSENT" => 0, "LEAVE" => 0];
$my_attendance   = [];

if ($is_student) {
    $sql = "
        SELECT status, COUNT(*) AS total
        FROM attendance
        WHERE student_id = ?
        GROUP BY status
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user['student_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        if (isset($student_summary[$row['status']])) {
            $student_summary[$row['status']] = (int)$row['total'];
        }
    }
    $stmt->close();

    $sql = "
        SELECT a.attendance_date, a.status, cs.subject_name
        FROM attendance a
        INNER JOIN class_schedule cs ON cs.schedule_id = a.schedule_id
        WHERE a.student_id = ?
        ORDER BY a.attendance_date DESC
        LIMIT 5
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user['student_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $my_attendance[] = $row;
    }
    $stmt->close();
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
                <div class="brand-icon">✅</div>
                <div>
                    <h1>เช็คชื่อเข้าเรียน</h1>
                    <span>Class Attendance System</span>
                </div>
            </div>

            <a href="../index.php" class="back-home">🏠 หน้าหลัก</a>
        </div>
    </header>

    <main class="attendance-container">

        <div class="page-heading">
            <div>
                <h2>เช็คชื่อเข้าเรียน</h2>
                <p><?= $is_student ? "ดูสถิติและประวัติการเข้าเรียนของคุณ" : "เช็คชื่อนักเรียนตามคาบสอนของคุณ" ?></p>
            </div>

            <div style="display:flex; gap:10px;">
                <?php if ($is_staff): ?>
                    <a href="schedule_main.php" class="history-btn">🗓️ จัดการตารางสอน</a>
                <?php endif; ?>
                <a href="history.php" class="history-btn">📋 ประวัติทั้งหมด</a>
            </div>
        </div>

        <!-- ================================================= -->
        <!-- นักเรียน: สรุปสถิติ -->
        <!-- ================================================= -->
        <?php if ($is_student): ?>
            <div class="stat-grid">
                <div class="stat-card">
                    <div class="stat-value"><?= $student_summary['PRESENT'] ?></div>
                    <div class="stat-label">มาเรียน</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $student_summary['LATE'] ?></div>
                    <div class="stat-label">มาสาย</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $student_summary['ABSENT'] ?></div>
                    <div class="stat-label">ขาดเรียน</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $student_summary['LEAVE'] ?></div>
                    <div class="stat-label">ลา</div>
                </div>
            </div>

            <div class="recent-card">
                <div class="recent-header">
                    <div>
                        <h3>📋 ประวัติล่าสุด</h3>
                        <p>5 รายการล่าสุด</p>
                    </div>
                    <a href="history.php">ดูทั้งหมด →</a>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>วันที่</th>
                                <th>วิชา</th>
                                <th>สถานะ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($my_attendance)): ?>
                                <?php foreach ($my_attendance as $a): ?>
                                    <?php $info = getStatusInfo($a['status']); ?>
                                    <tr>
                                        <td><?= date("d/m/Y", strtotime($a['attendance_date'])) ?></td>
                                        <td><?= e($a['subject_name']) ?></td>
                                        <td><span class="status <?= e($info['class']) ?>"><?= e($info['text']) ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="empty-data">ยังไม่มีประวัติการเข้าเรียน</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- ================================================= -->
        <!-- ครูผู้สอน: คาบเรียนของตัวเอง -->
        <!-- ================================================= -->
        <?php if ($is_staff): ?>
            <div class="recent-card" style="margin-top:0;">
                <div class="recent-header">
                    <div>
                        <h3>🗓️ คาบเรียนของคุณ</h3>
                        <p><?= count($my_schedules) ?> คาบ</p>
                    </div>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>วิชา</th>
                                <th>ห้อง</th>
                                <th>วัน/เวลา</th>
                                <th>นักเรียน</th>
                                <th>เช็คแล้ววันนี้</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($my_schedules)): ?>
                                <?php foreach ($my_schedules as $s): ?>
                                    <tr>
                                        <td><?= e($s['subject_name']) ?></td>
                                        <td><?= e($s['classroom_type']) ?> <?= e($s['classroom_number_code']) ?></td>
                                        <td><?= e(getDayName($s['day_of_week'])) ?> <?= substr($s['start_time'], 0, 5) ?>-<?= substr($s['end_time'], 0, 5) ?></td>
                                        <td><?= (int)$s['student_count'] ?> คน</td>
                                        <td><?= (int)$s['checked_today'] ?>/<?= (int)$s['student_count'] ?></td>
                                        <td>
                                            <a href="take.php?schedule_id=<?= e($s['schedule_id']) ?>&date=<?= date("Y-m-d") ?>" class="submit-btn" style="padding:6px 14px; font-size:12px;">✓ เช็คชื่อวันนี้</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="empty-data">คุณยังไม่มีคาบสอน</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

    </main>
</div>
</body>
</html>
