<?php

session_start();

$page_title = "ประวัติการแจ้งซ่อม";
$css_path   = "../css/repair.css";

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: user_accounts, user_students, user_staffs,
//                     classroom, repair_requests, repair_process
// โครงสร้างตารางแบบเต็มดูได้ที่ database/schema.sql

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/index.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function getClassroomLabel($type, $code, $level)
{
    $parts = [];

    if (!empty($type)) {
        $parts[] = $type;
    }

    $parts[] = $code;

    if ($level !== null && $level !== '') {
        $parts[] = "/ " . $level;
    }

    return implode(' ', $parts);
}

function getTypeName($type)
{
    $types = [
        "computer"        => "คอมพิวเตอร์ / Notebook",
        "projector"       => "โปรเจกเตอร์",
        "printer"         => "เครื่องพิมพ์",
        "network"         => "ระบบเครือข่าย / Internet",
        "electric"        => "ระบบไฟฟ้า",
        "air_conditioner" => "เครื่องปรับอากาศ",
        "other"           => "อื่น ๆ",
    ];

    return $types[$type] ?? $type;
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

if (!$stmt) {
    die("SQL Error: " . $conn->error);
}

$stmt->bind_param("i", $user_id);
$stmt->execute();

$user = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$user) {
    die("ไม่พบข้อมูลผู้ใช้งาน");
}

$is_student = !empty($user['student_id']);
$is_staff   = !empty($user['staff_id']);

$requests = [];

/*
|--------------------------------------------------------------------------
| นักเรียน: เฉพาะรายการของตัวเอง
|--------------------------------------------------------------------------
*/

if ($is_student) {
    $sql = "
        SELECT
            r.request_id, r.request_type, r.request_datetime, r.repair_detail,
            r.request_image, r.approved_by, r.approved_at,

            c.classroom_type, c.classroom_number_code, c.classroom_level,

            us.title_name, us.first_name_th, us.last_name_th,

            rp.status_repair

        FROM repair_requests r
        LEFT JOIN classroom c ON c.classroom_id = r.classroom_id
        LEFT JOIN user_students us ON us.user_id = r.requester_id
        LEFT JOIN repair_process rp ON rp.request_id = r.request_id
        WHERE r.requester_id = ?
        ORDER BY r.request_datetime DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);

/*
|--------------------------------------------------------------------------
| อาจารย์: รายการของตัวเอง หรือห้องที่ตนเป็นครูที่ปรึกษา (advisor_staff_id)
|--------------------------------------------------------------------------
*/

} elseif ($is_staff) {
    $sql = "
        SELECT DISTINCT
            r.request_id, r.request_type, r.request_datetime, r.repair_detail,
            r.request_image, r.approved_by, r.approved_at, r.requester_id,

            c.classroom_type, c.classroom_number_code, c.classroom_level,

            us.title_name AS student_title,
            us.first_name_th AS student_first_name,
            us.last_name_th AS student_last_name,

            ust.title_name AS staff_title,
            ust.first_name_th AS staff_first_name,
            ust.last_name_th AS staff_last_name,

            rp.status_repair

        FROM repair_requests r
        INNER JOIN classroom c ON c.classroom_id = r.classroom_id
        LEFT JOIN user_students us ON us.user_id = r.requester_id
        LEFT JOIN user_staffs ust ON ust.user_id = r.requester_id
        LEFT JOIN repair_process rp ON rp.request_id = r.request_id
        WHERE
            r.requester_id = ?
            OR (
                c.advisor_staff_id IS NOT NULL
                AND JSON_VALID(c.advisor_staff_id)
                AND JSON_CONTAINS(c.advisor_staff_id, JSON_ARRAY(?))
            )
        ORDER BY r.request_datetime DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $user_id);

} else {
    die("บัญชีนี้ไม่มีสิทธิ์ใช้งานระบบแจ้งซ่อม");
}

$stmt->execute();

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
<div class="repair-page">

    <header class="repair-header">
        <div class="header-inner">
            <div class="header-brand">
                <div class="brand-icon">🔧</div>
                <div>
                    <h1>ประวัติการแจ้งซ่อม</h1>
                    <span>Equipment Repair Request System</span>
                </div>
            </div>

            <a href="main.php" class="back-home">← แจ้งซ่อม</a>
        </div>
    </header>

    <main class="repair-container">

        <div class="page-heading">
            <div>
                <h2>ประวัติการแจ้งซ่อม</h2>
                <p>รายการแจ้งซ่อมทั้งหมด</p>
            </div>
        </div>

        <div class="recent-card">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>เลขที่</th>
                            <th>วันที่แจ้ง</th>
                            <th>ผู้แจ้ง</th>
                            <th>ห้อง</th>
                            <th>ประเภท</th>
                            <th>รายละเอียด</th>
                            <th>สถานะ</th>
                            <th>ดู</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($requests)): ?>
                            <?php foreach ($requests as $request): ?>
                                <?php
                                if (!empty($request['student_first_name'])) {
                                    $requester = trim(
                                        ($request['student_title'] ?? "") . " " .
                                        $request['student_first_name'] . " " .
                                        $request['student_last_name']
                                    );
                                } elseif (!empty($request['staff_first_name'])) {
                                    $requester = trim(
                                        ($request['staff_title'] ?? "") . " " .
                                        $request['staff_first_name'] . " " .
                                        $request['staff_last_name']
                                    );
                                } elseif (!empty($request['first_name_th'])) {
                                    $requester = trim(
                                        ($request['title_name'] ?? "") . " " .
                                        $request['first_name_th'] . " " .
                                        $request['last_name_th']
                                    );
                                } else {
                                    $requester = "-";
                                }

                                if ($request['status_repair'] === 'done') {
                                    $status_text  = "ซ่อมเสร็จสิ้น";
                                    $status_class = "done";
                                } elseif ($request['status_repair'] === 'repairing') {
                                    $status_text  = "กำลังซ่อม";
                                    $status_class = "repairing";
                                } elseif (!empty($request['approved_by'])) {
                                    $status_text  = "อนุมัติแล้ว";
                                    $status_class = "approved";
                                } else {
                                    $status_text  = "รอครูอนุมัติ";
                                    $status_class = "waiting";
                                }
                                ?>
                                <tr>
                                    <td><strong>#<?= str_pad((int)$request['request_id'], 4, "0", STR_PAD_LEFT) ?></strong></td>
                                    <td>
                                        <?= !empty($request['request_datetime'])
                                            ? date("d/m/Y H:i", strtotime($request['request_datetime']))
                                            : "-" ?>
                                    </td>
                                    <td><?= e($requester) ?></td>
                                    <td><?= !empty($request['classroom_number_code']) ? e(getClassroomLabel($request['classroom_type'], $request['classroom_number_code'], $request['classroom_level'])) : "-" ?></td>
                                    <td><?= e(getTypeName($request['request_type'])) ?></td>
                                    <td><?= e(mb_strimwidth($request['repair_detail'] ?? "", 0, 60, "...")) ?></td>
                                    <td><span class="status <?= e($status_class) ?>"><?= e($status_text) ?></span></td>
                                    <td><a href="detail.php?id=<?= e($request['request_id']) ?>">ดูรายละเอียด</a></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="empty-data">ยังไม่มีรายการแจ้งซ่อม</td>
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
