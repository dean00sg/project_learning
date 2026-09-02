<?php

session_start();

$page_title = "ระบบลา/ขออนุญาตนักเรียน";
$css_path   = "../css/leave.css";

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: user_accounts, user_students, user_staffs,
//                     classroom, leave_types, leave_requests
// โครงสร้างตารางแบบเต็มดูได้ที่ database/leave_system.sql

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
        "PENDING_ADVISOR"    => ["text" => "รอครูที่ปรึกษาอนุมัติ", "class" => "pending"],
        "PENDING_DISCIPLINE" => ["text" => "รอฝ่ายปกครองอนุมัติ",   "class" => "pending"],
        "APPROVED"           => ["text" => "อนุมัติแล้ว",           "class" => "approved"],
        "REJECTED"           => ["text" => "ไม่อนุมัติ",            "class" => "rejected"],
        "CANCELLED"          => ["text" => "ยกเลิกแล้ว",            "class" => "cancelled"],
    ];

    return $map[$status] ?? ["text" => $status, "class" => ""];
}

// =====================================================
// ดึงข้อมูลผู้ใช้งาน
// =====================================================

$sql = "
    SELECT
        ua.user_id, ua.role, ua.is_active,
        us.student_id, us.classroom_id AS student_classroom_id,
        ust.staff_id, ust.staff_type_code
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

$is_student    = !empty($user['student_id']);
$is_staff      = !empty($user['staff_id']);
$is_discipline = $is_staff && ($user['staff_type_code'] ?? '') === 'discipline';
$is_admin      = $user['role'] === 'admin';

// =====================================================
// นักเรียน: คำขอล่าสุดของตัวเอง (5 รายการ)
// =====================================================

$my_requests = [];

if ($is_student) {
    $sql = "
        SELECT
            r.request_id, r.start_date, r.end_date, r.request_at, r.status,
            lt.leave_type_name
        FROM leave_requests r
        INNER JOIN leave_types lt ON lt.leave_type_id = r.leave_type_id
        WHERE r.student_id = ?
        ORDER BY r.request_at DESC
        LIMIT 5
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user['student_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $my_requests[] = $row;
    }
    $stmt->close();
}

// =====================================================
// ครูที่ปรึกษา: คำขอที่รออนุมัติในห้องที่ตนดูแล
// (advisor_staff_id เก็บเป็น JSON array ของ user_accounts.user_id)
// =====================================================

$advisor_pending = [];

if ($is_staff) {
    $sql = "
        SELECT
            r.request_id, r.start_date, r.end_date, r.request_at,
            lt.leave_type_name,
            c.classroom_number_code,
            us.student_code, us.title_name, us.first_name_th, us.last_name_th
        FROM leave_requests r
        INNER JOIN leave_types lt ON lt.leave_type_id = r.leave_type_id
        INNER JOIN classroom c ON c.classroom_id = r.classroom_id
        INNER JOIN user_students us ON us.student_id = r.student_id
        WHERE
            r.status = 'PENDING_ADVISOR'
            AND c.advisor_staff_id IS NOT NULL
            AND JSON_VALID(c.advisor_staff_id)
            AND JSON_CONTAINS(c.advisor_staff_id, JSON_ARRAY(?))
        ORDER BY r.request_at ASC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $advisor_pending[] = $row;
    }
    $stmt->close();
}

// =====================================================
// ครูฝ่ายปกครอง: คำขอที่รออนุมัติขั้นฝ่ายปกครอง (ทั้งโรงเรียน)
// =====================================================

$discipline_pending = [];

if ($is_discipline) {
    $sql = "
        SELECT
            r.request_id, r.start_date, r.end_date, r.advisor_approved_at,
            lt.leave_type_name,
            c.classroom_number_code,
            us.student_code, us.title_name, us.first_name_th, us.last_name_th
        FROM leave_requests r
        INNER JOIN leave_types lt ON lt.leave_type_id = r.leave_type_id
        INNER JOIN classroom c ON c.classroom_id = r.classroom_id
        INNER JOIN user_students us ON us.student_id = r.student_id
        WHERE r.status = 'PENDING_DISCIPLINE'
        ORDER BY r.advisor_approved_at ASC
    ";
    $result = $conn->query($sql);
    while ($row = $result->fetch_assoc()) {
        $discipline_pending[] = $row;
    }
}

// =====================================================
// staff/admin: Dashboard ภาพรวม + สถิติตามประเภท
// =====================================================

$dashboard = null;
$type_breakdown = [];

if ($is_staff || $is_admin) {
    $dashboard = [
        "total"     => (int)($conn->query("SELECT COUNT(*) AS n FROM leave_requests")->fetch_assoc()['n'] ?? 0),
        "pending"   => (int)($conn->query("SELECT COUNT(*) AS n FROM leave_requests WHERE status IN ('PENDING_ADVISOR','PENDING_DISCIPLINE')")->fetch_assoc()['n'] ?? 0),
        "approved"  => (int)($conn->query("SELECT COUNT(*) AS n FROM leave_requests WHERE status = 'APPROVED'")->fetch_assoc()['n'] ?? 0),
        "rejected"  => (int)($conn->query("SELECT COUNT(*) AS n FROM leave_requests WHERE status = 'REJECTED'")->fetch_assoc()['n'] ?? 0),
    ];

    $sql = "
        SELECT lt.leave_type_name, COUNT(r.request_id) AS total
        FROM leave_types lt
        LEFT JOIN leave_requests r ON r.leave_type_id = lt.leave_type_id
        GROUP BY lt.leave_type_id, lt.leave_type_name
        ORDER BY total DESC
    ";
    $result = $conn->query($sql);
    $max_type_total = 1;
    while ($row = $result->fetch_assoc()) {
        $type_breakdown[] = $row;
        $max_type_total = max($max_type_total, (int)$row['total']);
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<?php include __DIR__ . "/../includes/head.php"; ?>
<body>
<div class="leave-page">

    <header class="leave-header">
        <div class="header-inner">
            <div class="header-brand">
                <div class="brand-icon">📝</div>
                <div>
                    <h1>ระบบลา/ขออนุญาตนักเรียน</h1>
                    <span>Leave &amp; Permission Request System</span>
                </div>
            </div>

            <a href="../index.php" class="back-home">🏠 หน้าหลัก</a>
        </div>
    </header>

    <main class="leave-container">

        <div class="page-heading">
            <div>
                <h2>ลา/ขออนุญาต</h2>
                <p><?= $is_student ? "ยื่นคำขอลา/ขออนุญาตและติดตามสถานะ" : "ตรวจสอบและอนุมัติคำขอ" ?></p>
            </div>

            <div style="display:flex; gap:10px;">
                <?php if ($is_student): ?>
                    <a href="create.php" class="submit-btn" style="text-decoration:none;">+ สร้างคำขอ</a>
                <?php endif; ?>
                <?php if ($is_staff || $is_admin): ?>
                    <a href="types_main.php" class="history-btn">⚙️ ประเภทการลา</a>
                <?php endif; ?>
                <a href="history.php" class="history-btn">📋 ประวัติทั้งหมด</a>
            </div>
        </div>

        <!-- ================================================= -->
        <!-- STAFF/ADMIN: Dashboard -->
        <!-- ================================================= -->
        <?php if ($dashboard): ?>
            <div class="stat-grid">
                <div class="stat-card">
                    <div class="stat-value"><?= $dashboard['total'] ?></div>
                    <div class="stat-label">คำขอทั้งหมด</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $dashboard['pending'] ?></div>
                    <div class="stat-label">รออนุมัติ</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $dashboard['approved'] ?></div>
                    <div class="stat-label">อนุมัติแล้ว</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $dashboard['rejected'] ?></div>
                    <div class="stat-label">ไม่อนุมัติ</div>
                </div>
            </div>

            <?php if (!empty($type_breakdown)): ?>
                <div class="recent-card" style="margin-top:0; margin-bottom:25px;">
                    <div class="recent-header">
                        <div>
                            <h3>📊 สถิติการลาตามประเภท</h3>
                        </div>
                    </div>
                    <div style="display:flex; flex-direction:column; gap:12px;">
                        <?php foreach ($type_breakdown as $t): ?>
                            <div>
                                <div style="display:flex; justify-content:space-between; font-size:13px; color:#555562; margin-bottom:5px;">
                                    <span><?= e($t['leave_type_name']) ?></span>
                                    <span><?= (int)$t['total'] ?></span>
                                </div>
                                <div style="height:8px; border-radius:5px; background:#f0f0f3; overflow:hidden;">
                                    <div style="height:100%; background:#0d9488; width:<?= round((int)$t['total'] / $max_type_total * 100) ?>%;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- ================================================= -->
        <!-- ครูที่ปรึกษา: คำขอรออนุมัติ -->
        <!-- ================================================= -->
        <?php if ($is_staff): ?>
            <div class="recent-card" style="margin-bottom:25px;">
                <div class="recent-header">
                    <div>
                        <h3>🖊️ คำขอรอครูที่ปรึกษาอนุมัติ</h3>
                        <p><?= count($advisor_pending) ?> รายการ (เฉพาะห้องที่คุณเป็นครูที่ปรึกษา)</p>
                    </div>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>นักเรียน</th>
                                <th>ห้อง</th>
                                <th>ประเภท</th>
                                <th>วันที่</th>
                                <th>ยื่นเมื่อ</th>
                                <th>ดำเนินการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($advisor_pending)): ?>
                                <?php foreach ($advisor_pending as $r): ?>
                                    <?php $name = trim(($r['title_name'] ?? "") . " " . $r['first_name_th'] . " " . $r['last_name_th']); ?>
                                    <tr>
                                        <td><?= e($r['student_code']) ?> — <?= e($name) ?></td>
                                        <td><?= e($r['classroom_number_code']) ?></td>
                                        <td><?= e($r['leave_type_name']) ?></td>
                                        <td><?= date("d/m/Y", strtotime($r['start_date'])) ?>–<?= date("d/m/Y", strtotime($r['end_date'])) ?></td>
                                        <td><?= date("d/m/Y H:i", strtotime($r['request_at'])) ?></td>
                                        <td><a href="detail.php?id=<?= e($r['request_id']) ?>">ตรวจสอบ</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="empty-data">ไม่มีคำขอรออนุมัติ</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- ================================================= -->
        <!-- ครูฝ่ายปกครอง: คำขอรออนุมัติขั้นฝ่ายปกครอง -->
        <!-- ================================================= -->
        <?php if ($is_discipline): ?>
            <div class="recent-card" style="margin-bottom:25px;">
                <div class="recent-header">
                    <div>
                        <h3>🚪 คำขอรอฝ่ายปกครองอนุมัติ</h3>
                        <p><?= count($discipline_pending) ?> รายการ (ผ่านครูที่ปรึกษาแล้ว)</p>
                    </div>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>นักเรียน</th>
                                <th>ห้อง</th>
                                <th>ประเภท</th>
                                <th>วันที่</th>
                                <th>ครูที่ปรึกษาอนุมัติเมื่อ</th>
                                <th>ดำเนินการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($discipline_pending)): ?>
                                <?php foreach ($discipline_pending as $r): ?>
                                    <?php $name = trim(($r['title_name'] ?? "") . " " . $r['first_name_th'] . " " . $r['last_name_th']); ?>
                                    <tr>
                                        <td><?= e($r['student_code']) ?> — <?= e($name) ?></td>
                                        <td><?= e($r['classroom_number_code']) ?></td>
                                        <td><?= e($r['leave_type_name']) ?></td>
                                        <td><?= date("d/m/Y", strtotime($r['start_date'])) ?>–<?= date("d/m/Y", strtotime($r['end_date'])) ?></td>
                                        <td><?= !empty($r['advisor_approved_at']) ? date("d/m/Y H:i", strtotime($r['advisor_approved_at'])) : "-" ?></td>
                                        <td><a href="detail.php?id=<?= e($r['request_id']) ?>">ตรวจสอบ</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="empty-data">ไม่มีคำขอรออนุมัติ</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- ================================================= -->
        <!-- นักเรียน: คำขอล่าสุดของตัวเอง -->
        <!-- ================================================= -->
        <?php if ($is_student): ?>
            <div class="recent-card">
                <div class="recent-header">
                    <div>
                        <h3>📋 คำขอของคุณ</h3>
                        <p>5 รายการล่าสุด</p>
                    </div>
                    <a href="history.php">ดูทั้งหมด →</a>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>ประเภท</th>
                                <th>วันที่</th>
                                <th>ยื่นเมื่อ</th>
                                <th>สถานะ</th>
                                <th>ดู</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($my_requests)): ?>
                                <?php foreach ($my_requests as $r): ?>
                                    <?php $info = getStatusInfo($r['status']); ?>
                                    <tr>
                                        <td><?= e($r['leave_type_name']) ?></td>
                                        <td><?= date("d/m/Y", strtotime($r['start_date'])) ?>–<?= date("d/m/Y", strtotime($r['end_date'])) ?></td>
                                        <td><?= date("d/m/Y H:i", strtotime($r['request_at'])) ?></td>
                                        <td><span class="status <?= e($info['class']) ?>"><?= e($info['text']) ?></span></td>
                                        <td><a href="detail.php?id=<?= e($r['request_id']) ?>">ดูรายละเอียด</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="empty-data">ยังไม่มีคำขอ</td>
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
