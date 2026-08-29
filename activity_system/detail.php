<?php

session_start();

$page_title = "รายละเอียดกิจกรรม";
$css_path   = "../css/activity.css";

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: user_accounts, user_students, user_staffs,
//                     activities, activity_signups, activity_sessions, activity_attendance
// โครงสร้างตารางแบบเต็มดูได้ที่ database/activity_system.sql

// =====================================================
// ตรวจสอบ Login
// =====================================================

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/index.php");
    exit;
}

$user_id     = (int)$_SESSION['user_id'];
$activity_id = (int)($_GET['id'] ?? 0);

if ($activity_id <= 0) {
    die("ไม่พบกิจกรรม");
}

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

// =====================================================
// ดึงข้อมูลกิจกรรม
// =====================================================

$sql = "
    SELECT
        a.activity_id, a.title, a.category, a.detail, a.organizer_id,
        a.start_datetime, a.location, a.max_participants, a.total_hours, a.status,

        ua.username AS organizer_username,
        ust.title_name AS organizer_title,
        ust.first_name_th AS organizer_first_name,
        ust.last_name_th AS organizer_last_name,

        (SELECT COUNT(*) FROM activity_signups r
            WHERE r.activity_id = a.activity_id AND r.status = 'registered') AS registered_count,

        (SELECT COUNT(*) FROM activity_sessions s
            WHERE s.activity_id = a.activity_id) AS session_count,

        (SELECT COALESCE(SUM(s.hours_awarded), 0) FROM activity_sessions s
            WHERE s.activity_id = a.activity_id) AS allocated_hours

    FROM activities a
    LEFT JOIN user_accounts ua ON ua.user_id = a.organizer_id
    LEFT JOIN user_staffs ust ON ust.user_id = a.organizer_id
    WHERE a.activity_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $activity_id);
$stmt->execute();

$activity = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$activity) {
    die("ไม่พบกิจกรรม");
}

if (!empty($activity['organizer_first_name'])) {
    $organizer_name = trim(
        ($activity['organizer_title'] ?? "") . " " .
        $activity['organizer_first_name'] . " " .
        $activity['organizer_last_name']
    );
} else {
    $organizer_name = $activity['organizer_username'] ?? "-";
}

// =====================================================
// ตรวจสอบผู้ใช้งาน / สิทธิ์
// =====================================================

$sql = "
    SELECT ua.role, us.student_id, ust.staff_id
    FROM user_accounts ua
    LEFT JOIN user_students us ON us.user_id = ua.user_id
    LEFT JOIN user_staffs ust ON ust.user_id = ua.user_id
    WHERE ua.user_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();

$viewer = $stmt->get_result()->fetch_assoc();

$stmt->close();

$is_student = $viewer && !empty($viewer['student_id']);
$is_owner   = $viewer && (int)$activity['organizer_id'] === $user_id;
$is_admin   = $viewer && $viewer['role'] === 'admin';
$can_manage = $is_owner || $is_admin;

// =====================================================
// นักเรียน: สถานะการสมัครของตัวเอง
// =====================================================

$my_registration = null;

if ($is_student) {
    $sql = "
        SELECT registration_id, status, registered_at
        FROM activity_signups
        WHERE activity_id = ? AND requester_id = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $activity_id, $user_id);
    $stmt->execute();

    $my_registration = $stmt->get_result()->fetch_assoc();

    $stmt->close();
}

$is_active_registration = $my_registration && in_array($my_registration['status'], ['registered', 'waitlisted'], true);
$is_full                = (int)$activity['registered_count'] >= (int)$activity['max_participants'];
$can_register            = $is_student && $activity['status'] === 'open' && !$is_active_registration;

// =====================================================
// รายการรอบ (session) ของกิจกรรมนี้ — ทุกคนดูได้
// =====================================================

$sql = "SELECT session_id, session_datetime, hours_awarded, note FROM activity_sessions WHERE activity_id = ? ORDER BY session_datetime ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $activity_id);
$stmt->execute();

$sessions = [];
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $sessions[] = $row;
}

$stmt->close();

// =====================================================
// นักเรียน: สรุปการเข้าร่วม + ชั่วโมงที่ได้ของตัวเอง
// =====================================================

$my_attended_count = 0;
$my_hours_earned    = 0;

if ($my_registration) {
    $sql = "
        SELECT COUNT(*) AS attended, COALESCE(SUM(s.hours_awarded), 0) AS hours_earned
        FROM activity_attendance at
        INNER JOIN activity_sessions s ON s.session_id = at.session_id
        WHERE at.registration_id = ? AND at.attend_status = 'present'
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $my_registration['registration_id']);
    $stmt->execute();
    $my_summary = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $my_attended_count = (int)$my_summary['attended'];
    $my_hours_earned    = (float)$my_summary['hours_earned'];
}

// =====================================================
// ผู้จัดกิจกรรม/แอดมิน: รายชื่อผู้สมัคร (พร้อมสรุปจำนวนรอบที่มาร่วม)
// =====================================================

$roster = [];

if ($can_manage) {
    $sql = "
        SELECT
            r.registration_id, r.status, r.registered_at,

            us.title_name, us.first_name_th, us.last_name_th, us.student_code,

            (SELECT COUNT(*) FROM activity_attendance at
                WHERE at.registration_id = r.registration_id AND at.attend_status = 'present') AS present_count

        FROM activity_signups r
        INNER JOIN user_students us ON us.user_id = r.requester_id
        WHERE r.activity_id = ? AND r.status IN ('registered', 'waitlisted')
        ORDER BY FIELD(r.status, 'registered', 'waitlisted'), r.registered_at ASC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $activity_id);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $roster[] = $row;
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
                    <h1>รายละเอียดกิจกรรม</h1>
                    <span>Student Activity System</span>
                </div>
            </div>

            <a href="main.php" class="back-home">← กลับ</a>
        </div>
    </header>

    <main class="activity-container">
        <div class="activity-card">

            <div class="card-title">
                <div class="title-icon">🎉</div>
                <div>
                    <h3><?= e($activity['title']) ?></h3>
                    <p><?= e(getCategoryName($activity['category'])) ?> — ผู้จัด: <?= e($organizer_name) ?></p>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>วันที่เริ่มกิจกรรม (อ้างอิง)</label>
                    <input type="text" value="<?= date("d/m/Y H:i", strtotime($activity['start_datetime'])) ?>" readonly>
                </div>

                <div class="form-group">
                    <label>สถานที่</label>
                    <input type="text" value="<?= e($activity['location'] ?? '-') ?>" readonly>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>ที่นั่ง</label>
                    <input type="text" value="<?= (int)$activity['registered_count'] ?>/<?= (int)$activity['max_participants'] ?> <?= $is_full ? '(เต็ม)' : '' ?>" readonly>
                </div>

                <div class="form-group">
                    <label>ชั่วโมงรวมทั้งหมด (จัดสรรแล้ว <?= (int)$activity['session_count'] ?> รอบ)</label>
                    <input type="text" value="<?= number_format((float)$activity['allocated_hours'], 1) ?> / <?= e($activity['total_hours']) ?> ชั่วโมง" readonly>
                </div>
            </div>

            <?php if (!empty($activity['detail'])): ?>
                <div class="form-group">
                    <label>รายละเอียด</label>
                    <textarea rows="4" readonly><?= e($activity['detail']) ?></textarea>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label>สถานะกิจกรรม</label>
                <div><span class="status <?= e($activity['status']) ?>"><?= e($activity['status']) ?></span></div>
            </div>

            <?php if ($can_manage): ?>
                <div class="form-actions" style="border-top:none; padding-top:0; justify-content:flex-start; margin-top:0;">
                    <a href="sessions.php?id=<?= e($activity['activity_id']) ?>" class="history-btn" style="text-decoration:none;">🗓️ จัดการรอบ/เช็คชื่อ (<?= (int)$activity['session_count'] ?> รอบ)</a>
                </div>
            <?php endif; ?>

            <?php if ($can_manage && in_array($activity['status'], ['open', 'closed'], true)): ?>
                <div class="form-actions" style="border-top:none; padding-top:0; justify-content:flex-start;">
                    <?php if ($activity['status'] === 'open'): ?>
                        <form action="update_activity_status.php" method="POST">
                            <input type="hidden" name="activity_id" value="<?= e($activity['activity_id']) ?>">
                            <input type="hidden" name="status" value="closed">
                            <button type="submit" class="cancel-btn">🔒 ปิดรับสมัคร</button>
                        </form>
                    <?php endif; ?>
                    <form action="update_activity_status.php" method="POST" onsubmit="return confirm('ยืนยันยกเลิกกิจกรรมนี้?');">
                        <input type="hidden" name="activity_id" value="<?= e($activity['activity_id']) ?>">
                        <input type="hidden" name="status" value="cancelled">
                        <button type="submit" class="reject-btn">✕ ยกเลิกกิจกรรม</button>
                    </form>
                    <form action="update_activity_status.php" method="POST">
                        <input type="hidden" name="activity_id" value="<?= e($activity['activity_id']) ?>">
                        <input type="hidden" name="status" value="finished">
                        <button type="submit" class="approve-btn">✓ จบกิจกรรมแล้ว</button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- ================================================= -->
            <!-- นักเรียน: สมัคร / ยกเลิก -->
            <!-- ================================================= -->
            <?php if ($is_student): ?>

                <?php if ($is_active_registration): ?>
                    <?php $info = getRegStatusInfo($my_registration['status']); ?>
                    <div class="notice-box">
                        <div class="notice-icon">✅</div>
                        <div>
                            <strong>สถานะของคุณ: <?= e($info['text']) ?></strong>
                            <p>
                                สมัครเมื่อ <?= date("d/m/Y H:i", strtotime($my_registration['registered_at'])) ?><br>
                                มาร่วมแล้ว <?= $my_attended_count ?>/<?= (int)$activity['session_count'] ?> รอบ — ได้ <?= number_format($my_hours_earned, 1) ?> ชั่วโมง
                            </p>
                        </div>
                    </div>

                    <form action="register.php" method="POST" style="margin-top:15px;" onsubmit="return confirm('ยืนยันยกเลิกการสมัครกิจกรรมนี้?');">
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="activity_id" value="<?= e($activity['activity_id']) ?>">
                        <div class="form-actions" style="border-top:none; padding-top:0;">
                            <button type="submit" class="reject-btn">✕ ยกเลิกการสมัคร</button>
                        </div>
                    </form>

                <?php elseif ($can_register): ?>
                    <form action="register.php" method="POST">
                        <input type="hidden" name="action" value="register">
                        <input type="hidden" name="activity_id" value="<?= e($activity['activity_id']) ?>">
                        <div class="form-actions" style="border-top:none; padding-top:0;">
                            <button type="submit" class="submit-btn">
                                <?= $is_full ? '🕒 สมัคร (จะเข้าคิว waitlist)' : '✅ สมัครเข้าร่วม' ?>
                            </button>
                        </div>
                    </form>

                <?php elseif ($my_registration && $my_registration['status'] === 'cancelled'): ?>
                    <?php if ($activity['status'] === 'open'): ?>
                        <form action="register.php" method="POST">
                            <input type="hidden" name="action" value="register">
                            <input type="hidden" name="activity_id" value="<?= e($activity['activity_id']) ?>">
                            <div class="form-actions" style="border-top:none; padding-top:0;">
                                <button type="submit" class="submit-btn">สมัครอีกครั้ง</button>
                            </div>
                        </form>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="notice-box">
                        <div class="notice-icon">ℹ️</div>
                        <div><strong>กิจกรรมนี้ปิดรับสมัครแล้ว</strong></div>
                    </div>
                <?php endif; ?>

            <?php endif; ?>

        </div>

        <!-- ================================================= -->
        <!-- รายการรอบทั้งหมด (ทุกคนดูได้) -->
        <!-- ================================================= -->
        <div class="recent-card" style="margin-top:25px;">
            <div class="recent-header">
                <div>
                    <h3>🗓️ รอบกิจกรรมทั้งหมด</h3>
                    <p><?= count($sessions) ?> รอบ</p>
                </div>
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>วันเวลา</th>
                            <th>หมายเหตุ</th>
                            <th>ชั่วโมงที่ได้</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($sessions)): ?>
                            <?php foreach ($sessions as $s): ?>
                                <tr>
                                    <td><?= date("d/m/Y H:i", strtotime($s['session_datetime'])) ?></td>
                                    <td><?= e($s['note'] ?? '-') ?></td>
                                    <td><?= e($s['hours_awarded']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="empty-data">ยังไม่มีการกำหนดรอบกิจกรรม</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ================================================= -->
        <!-- ผู้จัด/แอดมิน: รายชื่อผู้สมัคร -->
        <!-- ================================================= -->
        <?php if ($can_manage): ?>
            <div class="recent-card" style="margin-top:25px;">
                <div class="recent-header">
                    <div>
                        <h3>👥 รายชื่อผู้สมัคร</h3>
                        <p><?= count($roster) ?> คน</p>
                    </div>
                    <a href="sessions.php?id=<?= e($activity['activity_id']) ?>">✓ จัดการรอบ/เช็คชื่อ →</a>
                </div>

                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>รหัสนักเรียน</th>
                                <th>ชื่อ-สกุล</th>
                                <th>วันที่สมัคร</th>
                                <th>สถานะ</th>
                                <th>มาร่วม</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($roster)): ?>
                                <?php foreach ($roster as $r): ?>
                                    <?php
                                    $info = getRegStatusInfo($r['status']);
                                    $name = trim(($r['title_name'] ?? "") . " " . $r['first_name_th'] . " " . $r['last_name_th']);
                                    ?>
                                    <tr>
                                        <td><?= e($r['student_code']) ?></td>
                                        <td><?= e($name) ?></td>
                                        <td><?= date("d/m/Y H:i", strtotime($r['registered_at'])) ?></td>
                                        <td><span class="status <?= e($info['class']) ?>"><?= e($info['text']) ?></span></td>
                                        <td><?= (int)$r['present_count'] ?>/<?= (int)$activity['session_count'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="empty-data">ยังไม่มีผู้สมัคร</td>
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
