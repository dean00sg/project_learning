<?php

session_start();

$page_title = "ประวัติกิจกรรม";
$css_path   = "../css/activity.css";

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: user_accounts, user_students, user_staffs,
//                     activities, activity_signups, activity_sessions, activity_attendance
// โครงสร้างตารางแบบเต็มดูได้ที่ database/activity_system.sql

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/index.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function getCategoryName($category)
{
    $categories = [
        "club"        => "ชมรม/ชุมนุม",
        "volunteer"   => "จิตอาสา",
        "trip"        => "ทัศนศึกษา",
        "competition" => "การแข่งขัน",
        "other"       => "อื่น ๆ",
    ];

    return $categories[$category] ?? ($category ?: "-");
}

function getRegStatusInfo($status)
{
    $map = [
        "registered" => ["text" => "สมัครแล้ว",         "class" => "registered"],
        "waitlisted" => ["text" => "รอคิว (waitlist)",  "class" => "waitlisted"],
        "cancelled"  => ["text" => "ยกเลิกแล้ว",         "class" => "cancelled"],
    ];

    return $map[$status] ?? ["text" => "-", "class" => ""];
}

/*
|--------------------------------------------------------------------------
| ตรวจสอบ User
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT ua.user_id, ua.role, us.student_id, ust.staff_id
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

$is_student   = !empty($user['student_id']);
$can_organize = !empty($user['staff_id']) || $user['role'] === 'admin';

// =====================================================
// นักเรียน: ประวัติการสมัครกิจกรรมทั้งหมด + ชั่วโมงสะสม
// =====================================================

if ($is_student) {

    $sql = "
        SELECT
            r.registration_id, r.status, r.registered_at,
            a.activity_id, a.title, a.category, a.start_datetime,

            (SELECT COUNT(*) FROM activity_sessions s
                WHERE s.activity_id = a.activity_id) AS session_count,

            (SELECT COUNT(*) FROM activity_attendance at
                WHERE at.registration_id = r.registration_id AND at.attend_status = 'present') AS attended_count,

            (SELECT COALESCE(SUM(s.hours_awarded), 0) FROM activity_attendance at
                INNER JOIN activity_sessions s ON s.session_id = at.session_id
                WHERE at.registration_id = r.registration_id AND at.attend_status = 'present') AS hours_earned

        FROM activity_signups r
        INNER JOIN activities a ON a.activity_id = r.activity_id
        WHERE r.requester_id = ?
        ORDER BY r.registered_at DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $registrations = [];
    $total_hours   = 0;

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $registrations[] = $row;
        $total_hours    += (float)$row['hours_earned'];
    }

    $stmt->close();
}

// =====================================================
// staff/admin: กิจกรรมทั้งหมดที่ตัวเองสร้าง
// =====================================================

if ($can_organize) {

    $sql = "
        SELECT
            a.activity_id, a.title, a.category, a.start_datetime,
            a.max_participants, a.status,

            (SELECT COUNT(*) FROM activity_signups r
                WHERE r.activity_id = a.activity_id AND r.status = 'registered') AS registered_count,

            (SELECT COUNT(*) FROM activity_signups r
                WHERE r.activity_id = a.activity_id AND r.status = 'waitlisted') AS waitlist_count

        FROM activities a
        WHERE a.organizer_id = ?
        ORDER BY a.created_at DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $organized = [];
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $organized[] = $row;
    }

    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="th">
<?php include __DIR__ . "/../includes/head.php"; ?>
<body>
<div class="activity-page">

    <header class="activity-header">
        <div class="header-inner">
            <div class="header-brand">
                <div class="brand-icon">📋</div>
                <div>
                    <h1>ประวัติกิจกรรม</h1>
                    <span>Student Activity System</span>
                </div>
            </div>

            <a href="main.php" class="back-home">← กิจกรรม</a>
        </div>
    </header>

    <main class="activity-container">

        <?php if ($is_student): ?>

            <div class="page-heading">
                <div>
                    <h2>ประวัติการสมัครกิจกรรม</h2>
                    <p>รวมชั่วโมงกิจกรรมสะสม (นับเฉพาะกิจกรรมที่เช็คชื่อว่า "มาร่วม")</p>
                </div>
            </div>

            <div class="stat-grid" style="grid-template-columns: repeat(1, 1fr); max-width:260px; margin-bottom:25px;">
                <div class="stat-card stat-open">
                    <div class="stat-value"><?= number_format($total_hours, 1) ?></div>
                    <div class="stat-label">ชั่วโมงสะสมทั้งหมด</div>
                </div>
            </div>

            <div class="recent-card">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>กิจกรรม</th>
                                <th>ประเภท</th>
                                <th>วันที่สมัคร</th>
                                <th>วันที่เริ่ม</th>
                                <th>สถานะการสมัคร</th>
                                <th>เข้าร่วม</th>
                                <th>ชั่วโมงที่ได้</th>
                                <th>ดู</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($registrations)): ?>
                                <?php foreach ($registrations as $reg): ?>
                                    <?php $info = getRegStatusInfo($reg['status']); ?>
                                    <tr>
                                        <td><?= e($reg['title']) ?></td>
                                        <td><?= e(getCategoryName($reg['category'])) ?></td>
                                        <td><?= date("d/m/Y H:i", strtotime($reg['registered_at'])) ?></td>
                                        <td><?= date("d/m/Y H:i", strtotime($reg['start_datetime'])) ?></td>
                                        <td><span class="status <?= e($info['class']) ?>"><?= e($info['text']) ?></span></td>
                                        <td><?= (int)$reg['attended_count'] ?>/<?= (int)$reg['session_count'] ?> รอบ</td>
                                        <td><?= number_format((float)$reg['hours_earned'], 1) ?></td>
                                        <td><a href="detail.php?id=<?= e($reg['activity_id']) ?>">ดูรายละเอียด</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="empty-data">ยังไม่มีการสมัครกิจกรรม</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php endif; ?>

        <?php if ($can_organize): ?>

            <div class="page-heading" style="<?= $is_student ? 'margin-top:35px;' : '' ?>">
                <div>
                    <h2>กิจกรรมที่คุณสร้าง</h2>
                    <p>ทั้งหมดที่เคยเปิด</p>
                </div>
            </div>

            <div class="recent-card">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>ชื่อกิจกรรม</th>
                                <th>ประเภท</th>
                                <th>วันที่จัด</th>
                                <th>ผู้สมัคร/ที่นั่ง</th>
                                <th>รอคิว</th>
                                <th>สถานะ</th>
                                <th>การดำเนินการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($organized)): ?>
                                <?php foreach ($organized as $act): ?>
                                    <tr>
                                        <td><?= e($act['title']) ?></td>
                                        <td><?= e(getCategoryName($act['category'])) ?></td>
                                        <td><?= date("d/m/Y H:i", strtotime($act['start_datetime'])) ?></td>
                                        <td><?= (int)$act['registered_count'] ?>/<?= (int)$act['max_participants'] ?></td>
                                        <td><?= (int)$act['waitlist_count'] ?></td>
                                        <td><span class="status <?= e($act['status']) ?>"><?= e($act['status']) ?></span></td>
                                        <td>
                                            <a href="detail.php?id=<?= e($act['activity_id']) ?>">รายละเอียด</a>
                                            &nbsp;|&nbsp;
                                            <a href="sessions.php?id=<?= e($act['activity_id']) ?>">รอบ/เช็คชื่อ</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="empty-data">คุณยังไม่ได้สร้างกิจกรรม</td>
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
