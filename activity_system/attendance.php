<?php

session_start();

$page_title = "เช็คชื่อกิจกรรม";
$css_path   = "../css/activity.css";

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: user_accounts, activities, activity_sessions,
//                     activity_signups, activity_attendance, user_students
// โครงสร้างตารางแบบเต็มดูได้ที่ database/activity_system.sql

// =====================================================
// ตรวจสอบ Login
// =====================================================

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/index.php");
    exit;
}

$user_id    = (int)$_SESSION['user_id'];
$session_id = (int)($_GET['session_id'] ?? 0);

if ($session_id <= 0) {
    die("ไม่พบรอบกิจกรรม");
}

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// =====================================================
// ดึงรอบ + กิจกรรม + ตรวจสิทธิ์ (เจ้าของกิจกรรม หรือแอดมิน เท่านั้น)
// =====================================================

$sql = "
    SELECT
        s.session_id, s.session_datetime, s.note,
        a.activity_id, a.title, a.organizer_id
    FROM activity_sessions s
    INNER JOIN activities a ON a.activity_id = s.activity_id
    WHERE s.session_id = ?
    LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $session_id);
$stmt->execute();
$session = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$session) {
    die("ไม่พบรอบกิจกรรม");
}

$sql = "SELECT role FROM user_accounts WHERE user_id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$viewer = $stmt->get_result()->fetch_assoc();
$stmt->close();

$is_admin = $viewer && $viewer['role'] === 'admin';
$is_owner = (int)$session['organizer_id'] === $user_id;

if (!$is_owner && !$is_admin) {
    http_response_code(403);
    die("คุณไม่มีสิทธิ์เช็คชื่อกิจกรรมนี้");
}

// =====================================================
// รายชื่อผู้สมัครที่ได้ที่นั่ง (status = 'registered' เท่านั้น)
// =====================================================

$sql = "
    SELECT
        r.registration_id,
        us.title_name, us.first_name_th, us.last_name_th, us.student_code,
        at.attend_status
    FROM activity_signups r
    INNER JOIN user_students us ON us.user_id = r.requester_id
    LEFT JOIN activity_attendance at ON at.registration_id = r.registration_id AND at.session_id = ?
    WHERE r.activity_id = ? AND r.status = 'registered'
    ORDER BY us.student_code ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $session_id, $session['activity_id']);
$stmt->execute();

$roster = [];
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $roster[] = $row;
}

$stmt->close();
?>
<!DOCTYPE html>
<html lang="th">
<?php include __DIR__ . "/../includes/head.php"; ?>
<body>
<div class="activity-page">

    <header class="activity-header">
        <div class="header-inner">
            <div class="header-brand">
                <div class="brand-icon">✓</div>
                <div>
                    <h1>เช็คชื่อกิจกรรม</h1>
                    <span>Student Activity System</span>
                </div>
            </div>

            <a href="sessions.php?id=<?= e($session['activity_id']) ?>" class="back-home">← กลับ</a>
        </div>
    </header>

    <main class="activity-container">
        <div class="activity-card">

            <div class="card-title">
                <div class="title-icon">✓</div>
                <div>
                    <h3><?= e($session['title']) ?></h3>
                    <p>
                        รอบ: <?= date("d/m/Y H:i", strtotime($session['session_datetime'])) ?>
                        <?= !empty($session['note']) ? '— ' . e($session['note']) : '' ?>
                        — <?= count($roster) ?> คน
                    </p>
                </div>
            </div>

            <?php if (empty($roster)): ?>
                <div class="notice-box">
                    <div class="notice-icon">ℹ️</div>
                    <div><strong>ยังไม่มีนักเรียนสมัครกิจกรรมนี้</strong></div>
                </div>
            <?php else: ?>

                <form action="update_attendance.php" method="POST">
                    <input type="hidden" name="session_id" value="<?= e($session_id) ?>">

                    <?php foreach ($roster as $r): ?>
                        <?php $name = trim(($r['title_name'] ?? "") . " " . $r['first_name_th'] . " " . $r['last_name_th']); ?>
                        <div class="attendance-row">
                            <div class="attendance-name"><?= e($r['student_code']) ?> — <?= e($name) ?></div>
                            <div class="attendance-choice">
                                <label>
                                    <input
                                        type="radio"
                                        name="attendance[<?= e($r['registration_id']) ?>]"
                                        value="present"
                                        <?= ($r['attend_status'] ?? '') === 'present' ? 'checked' : '' ?>
                                    >
                                    มาร่วม
                                </label>
                                <label>
                                    <input
                                        type="radio"
                                        name="attendance[<?= e($r['registration_id']) ?>]"
                                        value="absent"
                                        <?= ($r['attend_status'] ?? '') === 'absent' ? 'checked' : '' ?>
                                    >
                                    ขาด
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="form-actions">
                        <a href="sessions.php?id=<?= e($session['activity_id']) ?>" class="cancel-btn">ยกเลิก</a>
                        <button type="submit" class="submit-btn">💾 บันทึกการเช็คชื่อ</button>
                    </div>
                </form>

            <?php endif; ?>

        </div>
    </main>
</div>
</body>
</html>
