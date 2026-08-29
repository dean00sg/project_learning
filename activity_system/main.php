<?php

session_start();

$page_title = "กิจกรรมนักเรียน";
$css_path   = "../css/activity.css";

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: user_accounts, user_students, user_staffs,
//                     activities, activity_signups
// โครงสร้างตารางแบบเต็มดูได้ที่ database/activity_system.sql

// =====================================================
// ตรวจสอบ Login
// =====================================================

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/index.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// =====================================================
// ฟังก์ชัน
// =====================================================

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
        "registered" => ["text" => "สมัครแล้ว",  "class" => "registered"],
        "waitlisted" => ["text" => "รอคิว (waitlist)", "class" => "waitlisted"],
        "cancelled"  => ["text" => "ยกเลิกแล้ว",  "class" => "cancelled"],
    ];

    return $map[$status] ?? ["text" => "-", "class" => ""];
}

// =====================================================
// ดึงข้อมูลผู้ใช้งาน
// =====================================================

$sql = "
    SELECT
        ua.user_id, ua.username, ua.role, ua.is_active,

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

if ((int)$user['is_active'] !== 1) {
    die("บัญชีผู้ใช้งานนี้ถูกปิดการใช้งาน");
}

$is_student     = !empty($user['student_id']);
$is_staff       = !empty($user['staff_id']);
$can_organize   = $is_staff || $user['role'] === 'admin';

// =====================================================
// สำหรับ staff/admin: กิจกรรมที่ตัวเองสร้าง (5 รายการล่าสุด)
// =====================================================

$my_activities = [];

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
        LIMIT 5
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $my_activities[] = $row;
    }

    $stmt->close();
}

// =====================================================
// กิจกรรมที่เปิดรับสมัคร (สำหรับนักเรียน)
// =====================================================

$open_activities = [];

if ($is_student) {
    $sql = "
        SELECT
            a.activity_id, a.title, a.category, a.detail, a.start_datetime,
            a.location, a.max_participants, a.total_hours, a.status,

            (SELECT COUNT(*) FROM activity_signups r
                WHERE r.activity_id = a.activity_id AND r.status = 'registered') AS registered_count,

            ar.status AS my_status

        FROM activities a
        LEFT JOIN activity_signups ar
            ON ar.activity_id = a.activity_id AND ar.requester_id = ?
        WHERE a.status = 'open'
        ORDER BY a.start_datetime ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $open_activities[] = $row;
    }

    $stmt->close();
}

// =====================================================
// รายการสมัครล่าสุดของนักเรียน (5 รายการ)
// =====================================================

$my_registrations = [];

if ($is_student) {
    $sql = "
        SELECT
            r.registration_id, r.status, r.registered_at,
            a.activity_id, a.title, a.start_datetime
        FROM activity_signups r
        INNER JOIN activities a ON a.activity_id = r.activity_id
        WHERE r.requester_id = ?
        ORDER BY r.registered_at DESC
        LIMIT 5
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $my_registrations[] = $row;
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
                <div class="brand-icon">🎉</div>
                <div>
                    <h1>ระบบจัดการกิจกรรมนักเรียน</h1>
                    <span>Student Activity System</span>
                </div>
            </div>

            <a href="../index.php" class="back-home">🏠 หน้าหลัก</a>
        </div>
    </header>

    <main class="activity-container">

        <div class="page-heading">
            <div>
                <h2>กิจกรรมนักเรียน</h2>
                <p><?= $is_student ? "สมัครเข้าร่วมกิจกรรมที่เปิดรับอยู่" : "จัดการกิจกรรมที่คุณเปิด" ?></p>
            </div>

            <div style="display:flex; gap:10px;">
                <?php if ($can_organize): ?>
                    <a href="create.php" class="submit-btn" style="text-decoration:none;">+ สร้างกิจกรรมใหม่</a>
                <?php endif; ?>
                <a href="history.php" class="history-btn">📋 ประวัติ<?= $is_student ? "การสมัคร" : "กิจกรรมทั้งหมด" ?></a>
            </div>
        </div>

        <!-- ================================================= -->
        <!-- STAFF/ADMIN: กิจกรรมที่สร้าง -->
        <!-- ================================================= -->
        <?php if ($can_organize): ?>
            <div class="recent-card" style="margin-bottom:25px;">
                <div class="recent-header">
                    <div>
                        <h3>🗂️ กิจกรรมของคุณ</h3>
                        <p>5 รายการล่าสุด</p>
                    </div>
                    <a href="history.php">ดูทั้งหมด →</a>
                </div>

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
                            <?php if (!empty($my_activities)): ?>
                                <?php foreach ($my_activities as $act): ?>
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

        <!-- ================================================= -->
        <!-- STUDENT: กิจกรรมที่เปิดรับสมัคร -->
        <!-- ================================================= -->
        <?php if ($is_student): ?>

            <div class="activity-card" style="margin-bottom:25px;">
                <div class="card-title">
                    <div class="title-icon">🎉</div>
                    <div>
                        <h3>กิจกรรมที่เปิดรับสมัคร</h3>
                        <p>ที่นั่งจำกัด — เต็มแล้วจะเข้าคิว waitlist โดยอัตโนมัติ</p>
                    </div>
                </div>

                <?php if (empty($open_activities)): ?>
                    <div class="notice-box">
                        <div class="notice-icon">ℹ️</div>
                        <div>
                            <strong>ยังไม่มีกิจกรรมเปิดรับสมัครในขณะนี้</strong>
                            <p>กรุณาตรวจสอบใหม่ภายหลัง</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="activity-grid">
                        <?php foreach ($open_activities as $act): ?>
                            <?php
                            $seat_percent = $act['max_participants'] > 0
                                ? min(100, round($act['registered_count'] / $act['max_participants'] * 100))
                                : 0;
                            $is_full = (int)$act['registered_count'] >= (int)$act['max_participants'];
                            ?>
                            <div class="activity-item">
                                <div class="item-category"><?= e(getCategoryName($act['category'])) ?></div>
                                <div class="item-title"><?= e($act['title']) ?></div>
                                <div class="item-meta">
                                    📅 <?= date("d/m/Y H:i", strtotime($act['start_datetime'])) ?><br>
                                    📍 <?= e($act['location'] ?? '-') ?><br>
                                    ⏱️ <?= e($act['total_hours']) ?> ชั่วโมง
                                </div>

                                <div class="seat-bar">
                                    <div class="seat-bar-fill <?= $is_full ? 'full' : '' ?>" style="width:<?= $seat_percent ?>%;"></div>
                                </div>
                                <div class="item-meta">
                                    <?= (int)$act['registered_count'] ?>/<?= (int)$act['max_participants'] ?> ที่นั่ง
                                    <?= $is_full ? '(เต็ม — สมัครจะเข้าคิว)' : '' ?>
                                </div>

                                <div class="item-actions">
                                    <?php if (!empty($act['my_status']) && $act['my_status'] !== 'cancelled'): ?>
                                        <?php $info = getRegStatusInfo($act['my_status']); ?>
                                        <span class="status <?= e($info['class']) ?>"><?= e($info['text']) ?></span>
                                        &nbsp;
                                        <a href="detail.php?id=<?= e($act['activity_id']) ?>">รายละเอียด</a>
                                    <?php else: ?>
                                        <a href="detail.php?id=<?= e($act['activity_id']) ?>" class="submit-btn" style="text-decoration:none;">
                                            <?= $is_full ? '🕒 เข้าคิว waitlist' : '✅ สมัคร' ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="recent-card">
                <div class="recent-header">
                    <div>
                        <h3>📋 การสมัครของคุณ</h3>
                        <p>5 รายการล่าสุด</p>
                    </div>
                    <a href="history.php">ดูทั้งหมด →</a>
                </div>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>กิจกรรม</th>
                                <th>วันที่สมัคร</th>
                                <th>วันที่จัดกิจกรรม</th>
                                <th>สถานะ</th>
                                <th>ดู</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($my_registrations)): ?>
                                <?php foreach ($my_registrations as $reg): ?>
                                    <?php $info = getRegStatusInfo($reg['status']); ?>
                                    <tr>
                                        <td><?= e($reg['title']) ?></td>
                                        <td><?= date("d/m/Y H:i", strtotime($reg['registered_at'])) ?></td>
                                        <td><?= date("d/m/Y H:i", strtotime($reg['start_datetime'])) ?></td>
                                        <td><span class="status <?= e($info['class']) ?>"><?= e($info['text']) ?></span></td>
                                        <td><a href="detail.php?id=<?= e($reg['activity_id']) ?>">ดูรายละเอียด</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="empty-data">ยังไม่มีการสมัครกิจกรรม</td>
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
