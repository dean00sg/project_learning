<?php

session_start();

require_once "../config/db.php";


// =====================================================
// Login
// =====================================================

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];


// =====================================================
// รับข้อมูล
// =====================================================

$request_type =
    trim($_POST['request_type'] ?? "");

$classroom_id =
    (int)($_POST['classroom_id'] ?? 0);

$repair_detail =
    trim($_POST['repair_detail'] ?? "");


// =====================================================
// ตรวจข้อมูล
// =====================================================

if (
    $request_type === "" ||
    $classroom_id <= 0 ||
    $repair_detail === ""
) {

    echo "<script>
        alert('กรุณากรอกข้อมูลให้ครบถ้วน');
        history.back();
    </script>";

    exit;
}


// =====================================================
// ประเภทที่อนุญาต
// =====================================================

$allowed_types = [
    "computer",
    "projector",
    "printer",
    "network",
    "electric",
    "air_conditioner",
    "other"
];

if (!in_array(
    $request_type,
    $allowed_types,
    true
)) {

    echo "<script>
        alert('ประเภทการแจ้งซ่อมไม่ถูกต้อง');
        history.back();
    </script>";

    exit;
}


// =====================================================
// ตรวจ User
//
// ไม่ใช้ staff_id
// =====================================================

$sql = "
    SELECT
        ua.user_id,
        ua.role,

        us.student_id,
        us.classroom_id AS student_classroom_id

    FROM user_accounts ua

    LEFT JOIN user_students us
        ON us.user_id = ua.user_id

    WHERE ua.user_id = ?

    LIMIT 1
";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "i",
    $user_id
);

$stmt->execute();

$result =
    $stmt->get_result();

$user =
    $result->fetch_assoc();

$stmt->close();


if (!$user) {

    echo "<script>
        alert('ไม่พบข้อมูลผู้ใช้งาน');
        history.back();
    </script>";

    exit;
}


// =====================================================
// ถ้าเป็นนักเรียน
//
// ต้องใช้ห้องของนักเรียนเท่านั้น
// =====================================================

if (!empty($user['student_id'])) {

    $student_classroom_id =
        (int)$user['student_classroom_id'];

    if (
        $student_classroom_id <= 0 ||
        $classroom_id !==
        $student_classroom_id
    ) {

        echo "<script>
            alert('ไม่สามารถเลือกห้องเรียนอื่นได้');
            history.back();
        </script>";

        exit;
    }
}


// =====================================================
// ตรวจว่าห้องมีจริง
// =====================================================

$sql = "
    SELECT
        classroom_id
    FROM classroom
    WHERE classroom_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "i",
    $classroom_id
);

$stmt->execute();

$result =
    $stmt->get_result();

$classroom =
    $result->fetch_assoc();

$stmt->close();


if (!$classroom) {

    echo "<script>
        alert('ไม่พบห้องเรียนที่เลือก');
        history.back();
    </script>";

    exit;
}


// =====================================================
// Upload รูป
// =====================================================

$request_image = null;


if (
    isset($_FILES['request_image']) &&
    $_FILES['request_image']['error']
    !== UPLOAD_ERR_NO_FILE
) {

    $file =
        $_FILES['request_image'];


    if (
        $file['error']
        !== UPLOAD_ERR_OK
    ) {

        echo "<script>
            alert('ไม่สามารถอัปโหลดรูปภาพได้');
            history.back();
        </script>";

        exit;
    }


    if (
        $file['size']
        > 5 * 1024 * 1024
    ) {

        echo "<script>
            alert('รูปภาพต้องมีขนาดไม่เกิน 5 MB');
            history.back();
        </script>";

        exit;
    }


    $finfo =
        new finfo(FILEINFO_MIME_TYPE);

    $mime =
        $finfo->file(
            $file['tmp_name']
        );


    $allowed_mime = [

        "image/jpeg" => "jpg",
        "image/png" => "png"

    ];


    if (!isset(
        $allowed_mime[$mime]
    )) {

        echo "<script>
            alert('รองรับเฉพาะ JPG และ PNG เท่านั้น');
            history.back();
        </script>";

        exit;
    }


    $upload_dir =
        __DIR__ . "/uploads/";


    if (!is_dir($upload_dir)) {

        mkdir(
            $upload_dir,
            0755,
            true
        );
    }


    $extension =
        $allowed_mime[$mime];


    $filename =
        "repair_" .
        date("YmdHis") .
        "_" .
        bin2hex(
            random_bytes(5)
        ) .
        "." .
        $extension;


    $target =
        $upload_dir .
        $filename;


    if (!move_uploaded_file(
        $file['tmp_name'],
        $target
    )) {

        echo "<script>
            alert('ไม่สามารถบันทึกรูปภาพได้');
            history.back();
        </script>";

        exit;
    }


    $request_image =
        "uploads/" . $filename;
}


// =====================================================
// INSERT
//
// approved_by = NULL
// หมายถึงรอครูอนุมัติ
// =====================================================

$sql = "
    INSERT INTO repair_requests
    (
        request_type,
        classroom_id,
        requester_id,
        request_datetime,
        approved_by,
        approved_at,
        repair_detail,
        request_image
    )

    VALUES
    (
        ?,
        ?,
        ?,
        NOW(),
        NULL,
        NULL,
        ?,
        ?
    )
";

$stmt =
    $conn->prepare($sql);

if (!$stmt) {

    die(
        "เกิดข้อผิดพลาด: "
        . $conn->error
    );
}

$stmt->bind_param(
    "siiss",
    $request_type,
    $classroom_id,
    $user_id,
    $repair_detail,
    $request_image
);


if ($stmt->execute()) {

    $request_id =
        $stmt->insert_id;

    $stmt->close();

    header(
        "Location: detail.php?id="
        . $request_id
    );

    exit;
}


$error =
    $stmt->error;

$stmt->close();


echo "<script>
    alert('เกิดข้อผิดพลาด: " .
    htmlspecialchars(
        $error,
        ENT_QUOTES,
        'UTF-8'
    ) .
    "');
    history.back();
</script>";