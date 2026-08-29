<?php

session_start();

$page_title = "จัดการรอบกิจกรรม";
$css_path   = "../css/activity.css";

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: user_accounts, activities, activity_sessions,
//                     activity_signups, activity_attendance
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

// =====================================================
// ดึงกิจกรรม + ตรวจสิทธิ์ (เจ้าของกิจกรรม หรือแอดมิน เท่านั้น)
// =====================================================

$sql = "SELECT activity_id, title, organizer_id, total_hours FROM activities WHERE activity_id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $activity_id);
$stmt->execute();
$activity = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$activity) {
    die("ไม่พบกิจกรรม");
}

$sql = "SELECT role FROM user_accounts WHERE user_id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$viewer = $stmt->get_result()->fetch_assoc();
$stmt->close();

$is_admin = $viewer && $viewer['role'] === 'admin';
$is_owner = (int)$activity['organizer_id'] === $user_id;

if (!$is_owner && !$is_admin) {
    http_response_code(403);
    die("คุณไม่มีสิทธิ์จัดการกิจกรรมนี้");
}

// =====================================================
// จำนวนผู้สมัครที่ได้ที่นั่ง (status = 'registered') สำหรับคำนวณ X/Y
// =====================================================

$sql = "SELECT COUNT(*) AS total FROM activity_signups WHERE activity_id = ? AND status = 'registered'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $activity_id);
$stmt->execute();
$registered_count = (int)$stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// =====================================================
// รายการรอบทั้งหมดของกิจกรรมนี้
// =====================================================

$sql = "
    SELECT
        s.session_id, s.session_datetime, s.hours_awarded, s.note,
        (SELECT COUNT(*) FROM activity_attendance at
            WHERE at.session_id = s.session_id AND at.attend_status = 'present') AS present_count,
        (SELECT COUNT(*) FROM activity_attendance at
            WHERE at.session_id = s.session_id) AS checked_count
    FROM activity_sessions s
    WHERE s.activity_id = ?
    ORDER BY s.session_datetime ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $activity_id);
$stmt->execute();

$sessions = [];
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $sessions[] = $row;
}

$stmt->close();

$allocated_hours = 0;

foreach ($sessions as $s) {
    $allocated_hours += (float)$s['hours_awarded'];
}

$remaining_hours = (float)$activity['total_hours'] - $allocated_hours;
?>
<!DOCTYPE html>
<html lang="th">
<?php include __DIR__ . "/../includes/head.php"; ?>
<body>
<div class="activity-page">

    <header class="activity-header">
        <div class="header-inner">
            <div class="header-brand">
                <div class="brand-icon">🗓️</div>
                <div>
                    <h1>จัดการรอบกิจกรรม</h1>
                    <span>Student Activity System</span>
                </div>
            </div>

            <a href="detail.php?id=<?= e($activity_id) ?>" class="back-home">← กลับ</a>
        </div>
    </header>

    <main class="activity-container">
        <div class="activity-card">

            <div class="card-title">
                <div class="title-icon">🗓️</div>
                <div>
                    <h3><?= e($activity['title']) ?></h3>
                    <p>ชั่วโมงรวมทั้งหมด <?= e($activity['total_hours']) ?> — จัดสรรไปแล้ว <?= number_format($allocated_hours, 1) ?> — เหลือ <?= number_format($remaining_hours, 1) ?> ชั่วโมง</p>
                </div>
            </div>

            <?php if ($remaining_hours <= 0): ?>
                <div class="notice-box">
                    <div class="notice-icon">⚠️</div>
                    <div>
                        <strong>จัดสรรครบตามชั่วโมงรวมแล้ว</strong>
                        <p>รอบใหม่ที่เพิ่มจะไม่มีชั่วโมงเหลือให้ใส่ ต้องลบ/แก้รอบเดิมก่อนถ้าต้องการเพิ่มรอบใหม่</p>
                    </div>
                </div>
            <?php endif; ?>

            <form action="store_session.php" method="POST">
                <input type="hidden" name="activity_id" value="<?= e($activity_id) ?>">

                <div class="form-grid">
                    <div class="form-group">
                        <label>วันเวลาของรอบนี้ <span class="required">*</span></label>
                        <input type="datetime-local" name="session_datetime" required>
                    </div>

                    <div class="form-group">
                        <label>ชั่วโมงที่ได้รับ (รอบนี้) — เหลือให้จัดสรร <?= number_format($remaining_hours, 1) ?> <span class="required">*</span></label>
                        <input type="number" name="hours_awarded" step="0.5" min="0" max="<?= e(max(0, $remaining_hours)) ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>หมายเหตุ</label>
                    <input type="text" name="note" placeholder="เช่น ครั้งที่ 1: ปฐมนิเทศ">
                </div>

                <div class="form-actions">
                    <button type="submit" class="submit-btn">+ เพิ่มรอบ</button>
                </div>
            </form>

        </div>

        <div class="recent-card" style="margin-top:25px;">
            <div class="recent-header">
                <div>
                    <h3>📋 รอบทั้งหมด</h3>
                    <p><?= count($sessions) ?> รอบ — ผู้สมัคร <?= $registered_count ?> คน</p>
                </div>
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>วันเวลา</th>
                            <th>หมายเหตุ</th>
                            <th>ชั่วโมง</th>
                            <th>เช็คชื่อแล้ว</th>
                            <th>มาร่วม</th>
                            <th>การดำเนินการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($sessions)): ?>
                            <?php foreach ($sessions as $s): ?>
                                <tr>
                                    <td><?= date("d/m/Y H:i", strtotime($s['session_datetime'])) ?></td>
                                    <td><?= e($s['note'] ?? '-') ?></td>
                                    <td><?= e($s['hours_awarded']) ?></td>
                                    <td><?= (int)$s['checked_count'] ?>/<?= $registered_count ?></td>
                                    <td><?= (int)$s['present_count'] ?></td>
                                    <td><a href="attendance.php?session_id=<?= e($s['session_id']) ?>">✓ เช็คชื่อ</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="empty-data">ยังไม่มีรอบ — เพิ่มรอบแรกด้านบน</td>
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
