<?php

session_start();

$page_title = "ประวัติการเช็คชื่อ";
$css_path   = "../css/attendance.css";

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: user_accounts, user_students, user_staffs,
//                     class_schedule, classroom, attendance
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
// ตรวจสอบผู้ใช้งาน
// =====================================================

$sql = "
    SELECT ua.user_id, us.student_id, ust.staff_id
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

$is_staff = !empty($user['staff_id']);

// =====================================================
// ดึงประวัติ
//
// ครูผู้สอน: เฉพาะคาบที่ตัวเองสอน (ทุกห้อง ทุกคน)
// นักเรียน : เฉพาะประวัติของตัวเอง
// =====================================================

$records = [];

if ($is_staff) {
    $sql = "
        SELECT
            a.attendance_date, a.status, a.remark,
            cs.subject_name,
            c.classroom_type, c.classroom_number_code,
            us.student_code, us.title_name, us.first_name_th, us.last_name_th
        FROM attendance a
        INNER JOIN class_schedule cs ON cs.schedule_id = a.schedule_id
        INNER JOIN classroom c ON c.classroom_id = cs.classroom_id
        INNER JOIN user_students us ON us.student_id = a.student_id
        WHERE cs.staff_id = ?
        ORDER BY a.attendance_date DESC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
} else {
    $sql = "
        SELECT
            a.attendance_date, a.status, a.remark,
            cs.subject_name
        FROM attendance a
        INNER JOIN class_schedule cs ON cs.schedule_id = a.schedule_id
        WHERE a.student_id = ?
        ORDER BY a.attendance_date DESC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user['student_id'] ?? 0);
}

$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $records[] = $row;
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
                <div class="brand-icon">📋</div>
                <div>
                    <h1>ประวัติการเช็คชื่อ<?= $is_staff ? 'ที่คุณสอน' : 'ของฉัน' ?></h1>
                    <span>Class Attendance System</span>
                </div>
            </div>

            <a href="main.php" class="back-home">← กลับ</a>
        </div>
    </header>

    <main class="attendance-container">

        <div class="recent-card" style="margin-top:0;">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>วันที่</th>
                            <th>วิชา</th>
                            <?php if ($is_staff): ?>
                                <th>ห้อง</th>
                                <th>นักเรียน</th>
                            <?php endif; ?>
                            <th>สถานะ</th>
                            <th>หมายเหตุ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($records)): ?>
                            <?php foreach ($records as $r): ?>
                                <?php $info = getStatusInfo($r['status']); ?>
                                <tr>
                                    <td><?= date("d/m/Y", strtotime($r['attendance_date'])) ?></td>
                                    <td><?= e($r['subject_name']) ?></td>
                                    <?php if ($is_staff): ?>
                                        <?php $name = trim(($r['title_name'] ?? "") . " " . $r['first_name_th'] . " " . $r['last_name_th']); ?>
                                        <td><?= e($r['classroom_type']) ?> <?= e($r['classroom_number_code']) ?></td>
                                        <td><?= e($r['student_code']) ?> — <?= e($name) ?></td>
                                    <?php endif; ?>
                                    <td><span class="status <?= e($info['class']) ?>"><?= e($info['text']) ?></span></td>
                                    <td><?= e($r['remark'] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?= $is_staff ? 6 : 4 ?>" class="empty-data">ยังไม่มีประวัติการเข้าเรียน</td>
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
