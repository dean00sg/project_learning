<?php

session_start();

$page_title = "ประวัติคำขอลา/ขออนุญาต";
$css_path   = "../css/leave.css";

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: user_accounts, user_students, user_staffs,
//                     classroom, leave_types, leave_requests
// โครงสร้างตารางแบบเต็มดูได้ที่ database/leave_system.sql

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
// ตรวจสอบผู้ใช้งาน
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
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    die("ไม่พบข้อมูลผู้ใช้งาน");
}

$is_student = !empty($user['student_id']);
$is_staff   = !empty($user['staff_id']);
$is_admin   = $user['role'] === 'admin';
$can_see_all = $is_staff || $is_admin;

// =====================================================
// ดึงรายการ
// =====================================================

$select_columns = "
    r.request_id, r.start_date, r.end_date, r.request_at, r.status,
    lt.leave_type_name,
    c.classroom_number_code,
    us.student_code, us.title_name, us.first_name_th, us.last_name_th
";

$joins = "
    FROM leave_requests r
    INNER JOIN leave_types lt ON lt.leave_type_id = r.leave_type_id
    INNER JOIN classroom c ON c.classroom_id = r.classroom_id
    INNER JOIN user_students us ON us.student_id = r.student_id
";

if ($can_see_all) {
    $sql = "SELECT $select_columns $joins ORDER BY r.request_at DESC";
    $stmt = $conn->prepare($sql);
} elseif ($is_student) {
    $sql = "SELECT $select_columns $joins WHERE r.student_id = ? ORDER BY r.request_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user['student_id']);
} else {
    die("บัญชีนี้ไม่มีสิทธิ์ใช้งานระบบนี้");
}

$stmt->execute();

$requests = [];
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $requests[] = $row;
}

$stmt->close();
?>
<!DOCTYPE html>
<html lang="th">
<?php include __DIR__ . "/../includes/head.php"; ?>
<body>
<div class="leave-page">

    <header class="leave-header">
        <div class="header-inner">
            <div class="header-brand">
                <div class="brand-icon">📋</div>
                <div>
                    <h1>ประวัติคำขอลา/ขออนุญาต</h1>
                    <span>Leave &amp; Permission Request System</span>
                </div>
            </div>

            <a href="main.php" class="back-home">← กลับ</a>
        </div>
    </header>

    <main class="leave-container">

        <div class="page-heading">
            <div>
                <h2>ประวัติคำขอ</h2>
                <p><?= $can_see_all ? "คำขอทั้งหมดในระบบ" : "คำขอของคุณทั้งหมด" ?></p>
            </div>
        </div>

        <div class="recent-card">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <?php if ($can_see_all): ?>
                                <th>นักเรียน</th>
                                <th>ห้อง</th>
                            <?php endif; ?>
                            <th>ประเภท</th>
                            <th>วันที่</th>
                            <th>ยื่นเมื่อ</th>
                            <th>สถานะ</th>
                            <th>ดู</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($requests)): ?>
                            <?php foreach ($requests as $r): ?>
                                <?php $info = getStatusInfo($r['status']); ?>
                                <tr>
                                    <?php if ($can_see_all): ?>
                                        <?php $name = trim(($r['title_name'] ?? "") . " " . $r['first_name_th'] . " " . $r['last_name_th']); ?>
                                        <td><?= e($r['student_code']) ?> — <?= e($name) ?></td>
                                        <td><?= e($r['classroom_number_code']) ?></td>
                                    <?php endif; ?>
                                    <td><?= e($r['leave_type_name']) ?></td>
                                    <td><?= date("d/m/Y", strtotime($r['start_date'])) ?>–<?= date("d/m/Y", strtotime($r['end_date'])) ?></td>
                                    <td><?= date("d/m/Y H:i", strtotime($r['request_at'])) ?></td>
                                    <td><span class="status <?= e($info['class']) ?>"><?= e($info['text']) ?></span></td>
                                    <td><a href="detail.php?id=<?= e($r['request_id']) ?>">ดูรายละเอียด</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?= $can_see_all ? 7 : 5 ?>" class="empty-data">ยังไม่มีคำขอ</td>
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
