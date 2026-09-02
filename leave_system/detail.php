<?php

session_start();

$page_title = "รายละเอียดคำขอ";
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

$user_id    = (int)$_SESSION['user_id'];
$request_id = (int)($_GET['id'] ?? 0);

if ($request_id <= 0) {
    die("ไม่พบคำขอ");
}

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
// ดึงคำขอ
// =====================================================

$sql = "
    SELECT
        r.*,
        lt.leave_type_name, lt.requires_discipline_approval,

        c.classroom_number_code,

        us.user_id AS student_user_id, us.student_code,
        us.title_name AS student_title, us.first_name_th AS student_first_name, us.last_name_th AS student_last_name,

        advisor_ua.username AS advisor_username,
        advisor_us.title_name AS advisor_staff_title,
        advisor_us.first_name_th AS advisor_staff_first_name,
        advisor_us.last_name_th AS advisor_staff_last_name,

        discipline_ua.username AS discipline_username,
        discipline_us.title_name AS discipline_staff_title,
        discipline_us.first_name_th AS discipline_staff_first_name,
        discipline_us.last_name_th AS discipline_staff_last_name

    FROM leave_requests r
    INNER JOIN leave_types lt ON lt.leave_type_id = r.leave_type_id
    INNER JOIN classroom c ON c.classroom_id = r.classroom_id
    INNER JOIN user_students us ON us.student_id = r.student_id
    LEFT JOIN user_accounts advisor_ua ON advisor_ua.user_id = r.advisor_approved_by
    LEFT JOIN user_staffs advisor_us ON advisor_us.user_id = r.advisor_approved_by
    LEFT JOIN user_accounts discipline_ua ON discipline_ua.user_id = r.discipline_approved_by
    LEFT JOIN user_staffs discipline_us ON discipline_us.user_id = r.discipline_approved_by
    WHERE r.request_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $request_id);
$stmt->execute();

$request = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$request) {
    die("ไม่พบคำขอ");
}

$student_name = trim(($request['student_title'] ?? "") . " " . $request['student_first_name'] . " " . $request['student_last_name']);

$advisor_name = "-";
if (!empty($request['advisor_staff_first_name'])) {
    $advisor_name = trim(($request['advisor_staff_title'] ?? "") . " " . $request['advisor_staff_first_name'] . " " . $request['advisor_staff_last_name']);
} elseif (!empty($request['advisor_username'])) {
    $advisor_name = $request['advisor_username'];
}

$discipline_name = "-";
if (!empty($request['discipline_staff_first_name'])) {
    $discipline_name = trim(($request['discipline_staff_title'] ?? "") . " " . $request['discipline_staff_first_name'] . " " . $request['discipline_staff_last_name']);
} elseif (!empty($request['discipline_username'])) {
    $discipline_name = $request['discipline_username'];
}

// =====================================================
// ตรวจสอบผู้ใช้งาน / สิทธิ์
// =====================================================

$sql = "
    SELECT ua.role, us.student_id, ust.staff_id, ust.staff_type_code
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

$is_owner      = $viewer && (int)$request['student_user_id'] === $user_id;
$is_staff      = $viewer && !empty($viewer['staff_id']);
$is_discipline = $is_staff && ($viewer['staff_type_code'] ?? '') === 'discipline';
$is_admin      = $viewer && $viewer['role'] === 'admin';

$is_advisor_of_this = false;

if ($is_staff) {
    $sql = "
        SELECT classroom_id FROM classroom
        WHERE
            classroom_id = ?
            AND advisor_staff_id IS NOT NULL
            AND JSON_VALID(advisor_staff_id)
            AND JSON_CONTAINS(advisor_staff_id, JSON_ARRAY(?))
        LIMIT 1
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $request['classroom_id'], $user_id);
    $stmt->execute();
    $is_advisor_of_this = (bool)$stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$is_owner && !$is_advisor_of_this && !$is_discipline && !$is_admin && !$is_staff) {
    http_response_code(403);
    die("คุณไม่มีสิทธิ์ดูคำขอนี้");
}

$can_advisor_act    = ($is_advisor_of_this || $is_admin) && $request['status'] === 'PENDING_ADVISOR';
$can_discipline_act = ($is_discipline || $is_admin) && $request['status'] === 'PENDING_DISCIPLINE';
$can_cancel          = $is_owner && in_array($request['status'], ['PENDING_ADVISOR', 'PENDING_DISCIPLINE'], true);

$status_info = getStatusInfo($request['status']);
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
                    <h1>รายละเอียดคำขอ</h1>
                    <span>Leave &amp; Permission Request System</span>
                </div>
            </div>

            <a href="main.php" class="back-home">← กลับ</a>
        </div>
    </header>

    <main class="leave-container">
        <div class="leave-card">

            <div class="card-title">
                <div class="title-icon">📝</div>
                <div>
                    <h3><?= e($request['leave_type_name']) ?></h3>
                    <p>เลขที่ #<?= str_pad((int)$request['request_id'], 4, "0", STR_PAD_LEFT) ?> — <?= e($student_name) ?> (<?= e($request['student_code']) ?>) — ห้อง <?= e($request['classroom_number_code']) ?></p>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>วันที่ลา/ขออนุญาต</label>
                    <input type="text" value="<?= date("d/m/Y", strtotime($request['start_date'])) ?> - <?= date("d/m/Y", strtotime($request['end_date'])) ?>" readonly>
                </div>
                <div class="form-group">
                    <label>ยื่นคำขอเมื่อ</label>
                    <input type="text" value="<?= date("d/m/Y H:i", strtotime($request['request_at'])) ?>" readonly>
                </div>
            </div>

            <div class="form-group">
                <label>เหตุผล</label>
                <textarea rows="3" readonly><?= e($request['reason']) ?></textarea>
            </div>

            <?php if (!empty($request['evidence_image'])): ?>
                <div class="form-group">
                    <label>เอกสารประกอบ</label>
                    <img src="<?= e($request['evidence_image']) ?>" alt="เอกสารประกอบ" style="max-width:100%; max-height:400px; border-radius:10px;">
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label>สถานะ</label>
                <div><span class="status <?= e($status_info['class']) ?>"><?= e($status_info['text']) ?></span></div>
            </div>

            <?php if ($request['status'] === 'REJECTED' && !empty($request['reject_reason'])): ?>
                <div class="notice-box">
                    <div class="notice-icon">⚠️</div>
                    <div>
                        <strong>เหตุผลที่ไม่อนุมัติ</strong>
                        <p><?= e($request['reject_reason']) ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($can_cancel): ?>
                <form action="cancel.php" method="POST" onsubmit="return confirm('ยืนยันยกเลิกคำขอนี้?');" style="margin-top:20px;">
                    <input type="hidden" name="request_id" value="<?= e($request_id) ?>">
                    <button type="submit" class="reject-btn">✕ ยกเลิกคำขอ</button>
                </form>
            <?php endif; ?>

        </div>

        <!-- ================================================= -->
        <!-- ขั้นตอนการอนุมัติ -->
        <!-- ================================================= -->
        <div class="recent-card" style="margin-top:25px; padding:25px;">
            <div class="recent-header">
                <div>
                    <h3>🧭 ขั้นตอนการอนุมัติ</h3>
                    <p><?= $request['requires_discipline_approval'] ? "ต้องผ่านครูที่ปรึกษาและฝ่ายปกครอง" : "ต้องผ่านครูที่ปรึกษาเท่านั้น" ?></p>
                </div>
            </div>

            <div class="timeline">
                <div class="timeline-step">
                    <div class="timeline-dot <?= !empty($request['advisor_approved_at']) ? ($request['status'] === 'REJECTED' && empty($request['discipline_approved_at']) ? 'rejected' : 'done') : '' ?>">1</div>
                    <div>
                        <strong>ครูที่ปรึกษา</strong>
                        <span>
                            <?php if (!empty($request['advisor_approved_at'])): ?>
                                <?= e($advisor_name) ?> — <?= date("d/m/Y H:i", strtotime($request['advisor_approved_at'])) ?>
                            <?php else: ?>
                                ยังไม่ดำเนินการ
                            <?php endif; ?>
                        </span>
                    </div>
                </div>

                <?php if ($request['requires_discipline_approval']): ?>
                    <div class="timeline-step">
                        <div class="timeline-dot <?= !empty($request['discipline_approved_at']) ? ($request['status'] === 'REJECTED' ? 'rejected' : 'done') : '' ?>">2</div>
                        <div>
                            <strong>ครูฝ่ายปกครอง</strong>
                            <span>
                                <?php if (!empty($request['discipline_approved_at'])): ?>
                                    <?= e($discipline_name) ?> — <?= date("d/m/Y H:i", strtotime($request['discipline_approved_at'])) ?>
                                <?php elseif ($request['status'] === 'PENDING_DISCIPLINE'): ?>
                                    รอดำเนินการ
                                <?php else: ?>
                                    ยังไม่ถึงขั้นนี้
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($can_advisor_act): ?>
                <form action="approve_advisor.php" method="POST" style="margin-top:25px; padding-top:20px; border-top:1px solid #eeeeef;" onsubmit="return prepareApproval(this, 'advisor_action', 'advisor_reject_reason');">
                    <input type="hidden" name="request_id" value="<?= e($request_id) ?>">
                    <input type="hidden" name="action" id="advisor_action" value="">
                    <div class="form-group" id="advisor_reject_group" style="display:none;">
                        <label>เหตุผลที่ไม่อนุมัติ <span class="required">*</span></label>
                        <textarea name="reject_reason" id="advisor_reject_reason" rows="2"></textarea>
                    </div>
                    <div style="display:flex; gap:10px;">
                        <button type="submit" class="approve-btn" onclick="document.getElementById('advisor_action').value='approve';">✓ อนุมัติ</button>
                        <button type="submit" class="reject-btn" onclick="document.getElementById('advisor_action').value='reject'; return toggleReject('advisor_reject_group', 'advisor_reject_reason', event, this.form);">✕ ไม่อนุมัติ</button>
                    </div>
                </form>
            <?php endif; ?>

            <?php if ($can_discipline_act): ?>
                <form action="approve_discipline.php" method="POST" style="margin-top:25px; padding-top:20px; border-top:1px solid #eeeeef;" onsubmit="return prepareApproval(this, 'discipline_action', 'discipline_reject_reason');">
                    <input type="hidden" name="request_id" value="<?= e($request_id) ?>">
                    <input type="hidden" name="action" id="discipline_action" value="">
                    <div class="form-group" id="discipline_reject_group" style="display:none;">
                        <label>เหตุผลที่ไม่อนุมัติ <span class="required">*</span></label>
                        <textarea name="reject_reason" id="discipline_reject_reason" rows="2"></textarea>
                    </div>
                    <div style="display:flex; gap:10px;">
                        <button type="submit" class="approve-btn" onclick="document.getElementById('discipline_action').value='approve';">✓ อนุมัติ</button>
                        <button type="submit" class="reject-btn" onclick="document.getElementById('discipline_action').value='reject'; return toggleReject('discipline_reject_group', 'discipline_reject_reason', event, this.form);">✕ ไม่อนุมัติ</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>

    </main>
</div>

<script>
function toggleReject(groupId, textareaId, event, form) {
    var group = document.getElementById(groupId);
    var textarea = document.getElementById(textareaId);

    if (group.style.display === 'none') {
        event.preventDefault();
        group.style.display = 'block';
        textarea.required = true;
        textarea.focus();
        return false;
    }

    return true;
}

function prepareApproval(form, actionFieldId, textareaId) {
    var action = document.getElementById(actionFieldId).value;

    if (action === 'reject') {
        var textarea = document.getElementById(textareaId);
        if (textarea.value.trim() === '') {
            alert('กรุณากรอกเหตุผลที่ไม่อนุมัติ');
            return false;
        }
        return confirm('ยืนยันไม่อนุมัติคำขอนี้?');
    }

    return confirm('ยืนยันอนุมัติคำขอนี้?');
}
</script>

</body>
</html>
