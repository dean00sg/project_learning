<?php

session_start();

$page_title = "งานซ่อมที่รับผิดชอบ";
$css_path   = "../css/repair.css";

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: user_accounts, user_staffs, repair_requests,
//                     classroom, repair_process
// โครงสร้างตารางแบบเต็มดูได้ที่ database/schema.sql

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

function getRequestTypeName($type)
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

// =====================================================
// ตรวจสอบสิทธิ์: เฉพาะบุคลากรที่ staff_type_code = 'technician'
// =====================================================

$sql = "
    SELECT ust.staff_id, ust.staff_type_code
    FROM user_accounts ua
    INNER JOIN user_staffs ust ON ust.user_id = ua.user_id
    WHERE ua.user_id = ? AND ua.is_active = 1
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();

$staff = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$staff || $staff['staff_type_code'] !== 'technician') {
    http_response_code(403);
    die("หน้านี้สำหรับเจ้าหน้าที่ซ่อมบำรุง (technician) เท่านั้น");
}

// =====================================================
// ดึงรายการที่อนุมัติแล้ว และยังไม่เสร็จสิ้น
// (ไม่จำกัดห้อง/ครูที่ปรึกษา เพราะเจ้าหน้าที่ซ่อมดูแลทุกห้อง)
// =====================================================

$sql = "
    SELECT
        r.request_id, r.request_type, r.repair_detail, r.request_image,
        r.approved_at,

        c.classroom_type, c.classroom_number_code, c.classroom_level, c.building,

        rp.status_repair, rp.repair_datetime,

        requester_ua.username AS requester_username,
        requester_us.title_name AS requester_student_title,
        requester_us.first_name_th AS requester_student_first_name,
        requester_us.last_name_th AS requester_student_last_name,
        requester_ust.title_name AS requester_staff_title,
        requester_ust.first_name_th AS requester_staff_first_name,
        requester_ust.last_name_th AS requester_staff_last_name,

        approver_ua.username AS approver_username,
        approver_ust.title_name AS approver_title,
        approver_ust.first_name_th AS approver_first_name,
        approver_ust.last_name_th AS approver_last_name

    FROM repair_requests r
    INNER JOIN classroom c ON c.classroom_id = r.classroom_id
    LEFT JOIN repair_process rp ON rp.request_id = r.request_id
    LEFT JOIN user_accounts requester_ua ON requester_ua.user_id = r.requester_id
    LEFT JOIN user_students requester_us ON requester_us.user_id = r.requester_id
    LEFT JOIN user_staffs requester_ust ON requester_ust.user_id = r.requester_id
    LEFT JOIN user_accounts approver_ua ON approver_ua.user_id = r.approved_by
    LEFT JOIN user_staffs approver_ust ON approver_ust.user_id = r.approved_by
    WHERE
        r.approved_by IS NOT NULL
        AND (rp.status_repair IS NULL OR rp.status_repair != 'done')
    ORDER BY
        CASE WHEN rp.status_repair = 'repairing' THEN 0 ELSE 1 END,
        r.approved_at ASC
";

$queue = [];
$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $queue[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<?php include __DIR__ . "/../includes/head.php"; ?>
<body>
<div class="repair-page">

    <header class="repair-header">
        <div class="header-inner">
            <div class="header-brand">
                <div class="brand-icon">🛠️</div>
                <div>
                    <h1>งานซ่อมที่รับผิดชอบ</h1>
                    <span>Equipment Repair Request System</span>
                </div>
            </div>

            <a href="main.php" class="back-home">← กลับ</a>
        </div>
    </header>

    <main class="repair-container">

        <div class="page-heading">
            <div>
                <h2>คิวงานซ่อม</h2>
                <p>รายการที่อนุมัติแล้ว รอดำเนินการซ่อม หรือกำลังซ่อมอยู่</p>
            </div>
        </div>

        <div class="recent-card">
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>เลขที่</th>
                            <th>วันที่อนุมัติ</th>
                            <th>ห้อง</th>
                            <th>ผู้แจ้ง</th>
                            <th>ประเภท</th>
                            <th>รายละเอียด</th>
                            <th>ครูผู้อนุมัติ</th>
                            <th>สถานะ</th>
                            <th>การดำเนินการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($queue)): ?>
                            <?php foreach ($queue as $item): ?>
                                <?php
                                $is_repairing = ($item['status_repair'] === 'repairing');

                                // ผู้แจ้ง
                                if (!empty($item['requester_student_first_name'])) {
                                    $requester_name = trim(
                                        ($item['requester_student_title'] ?? "") . " " .
                                        $item['requester_student_first_name'] . " " .
                                        $item['requester_student_last_name']
                                    );
                                } elseif (!empty($item['requester_staff_first_name'])) {
                                    $requester_name = trim(
                                        ($item['requester_staff_title'] ?? "") . " " .
                                        $item['requester_staff_first_name'] . " " .
                                        $item['requester_staff_last_name']
                                    );
                                } else {
                                    $requester_name = $item['requester_username'] ?? "-";
                                }

                                // ครูผู้อนุมัติ
                                if (!empty($item['approver_first_name'])) {
                                    $approver_name = trim(
                                        ($item['approver_title'] ?? "") . " " .
                                        $item['approver_first_name'] . " " .
                                        $item['approver_last_name']
                                    );
                                } else {
                                    $approver_name = $item['approver_username'] ?? "-";
                                }
                                ?>
                                <tr>
                                    <td>
                                        <strong>
                                            <a href="detail.php?id=<?= e($item['request_id']) ?>">
                                                #<?= str_pad((int)$item['request_id'], 4, "0", STR_PAD_LEFT) ?>
                                            </a>
                                        </strong>
                                    </td>
                                    <td>
                                        <?= !empty($item['approved_at'])
                                            ? date("d/m/Y H:i", strtotime($item['approved_at']))
                                            : "-" ?>
                                    </td>
                                    <td><?= !empty($item['classroom_number_code']) ? e(getClassroomLabel($item['classroom_type'], $item['classroom_number_code'], $item['classroom_level'])) : "-" ?></td>
                                    <td><?= e($requester_name) ?></td>
                                    <td><?= e(getRequestTypeName($item['request_type'])) ?></td>
                                    <td><?= e(mb_strimwidth($item['repair_detail'] ?? "", 0, 50, "...")) ?></td>
                                    <td><?= e($approver_name) ?></td>
                                    <td>
                                        <span class="status <?= $is_repairing ? "repairing" : "waiting" ?>">
                                            <?= $is_repairing ? "กำลังซ่อม" : "รอดำเนินการซ่อม" ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form
                                            action="update_repair_status.php"
                                            method="POST"
                                            style="display:inline;"
                                            onsubmit="return confirm('<?= $is_repairing ? "ยืนยันว่าซ่อมเสร็จสิ้นแล้ว?" : "ยืนยันเริ่มดำเนินการซ่อม?" ?>');"
                                        >
                                            <input type="hidden" name="request_id" value="<?= e($item['request_id']) ?>">
                                            <input type="hidden" name="action" value="<?= $is_repairing ? "complete" : "start" ?>">
                                            <button type="submit" class="approve-btn">
                                                <?= $is_repairing ? "✓ เสร็จสิ้น" : "🔧 เริ่มซ่อม" ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="empty-data">ไม่มีงานซ่อมที่ต้องดำเนินการ</td>
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
