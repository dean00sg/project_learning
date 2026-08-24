<?php

session_start();

require_once "../config/db.php";


// ========================================
// รับข้อมูลจากหน้า Login
// ========================================

$username = $_POST["username"] ?? "";
$password = $_POST["password"] ?? "";


// ========================================
// ตรวจสอบว่ากรอกข้อมูลครบหรือไม่
// ========================================

if ($username == "" || $password == "") {

    die("กรุณากรอก Username และ Password");

}


// ========================================
// ค้นหา Username
// ========================================

$sql = "
    SELECT
        user_id,
        username,
        password_hash,
        role,
        is_active
    FROM user_accounts
    WHERE username = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "s",
    $username
);

$stmt->execute();

$result = $stmt->get_result();


// ========================================
// ไม่พบ Username
// ========================================

if ($result->num_rows == 0) {

    die("Username หรือ Password ไม่ถูกต้อง");

}


$user = $result->fetch_assoc();


// ========================================
// ตรวจสอบสถานะบัญชี
// ========================================

if ($user["is_active"] != 1) {

    die("บัญชีนี้ถูกปิดใช้งาน");

}


// ========================================
// ตรวจสอบ Password
// ========================================

if (!password_verify(
    $password,
    $user["password_hash"]
)) {

    die("Username หรือ Password ไม่ถูกต้อง");

}


// ========================================
// กำหนดค่าชื่อเริ่มต้น
// ========================================

$first_name_th = "";
$last_name_th = "";


// ========================================
// ถ้าเป็นนักเรียน
// ========================================

if ($user["role"] == "student") {

    $sql = "
        SELECT
            first_name_th,
            last_name_th
        FROM user_students
        WHERE user_id = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param(
        "i",
        $user["user_id"]
    );

    $stmt->execute();

    $person_result = $stmt->get_result();


    if ($person_result->num_rows > 0) {

        $person = $person_result->fetch_assoc();

        $first_name_th = $person["first_name_th"];

        $last_name_th = $person["last_name_th"];

    }

}


// ========================================
// ถ้าเป็นบุคลากร
// ========================================

elseif ($user["role"] == "staff") {

    $sql = "
        SELECT
            first_name_th,
            last_name_th
        FROM user_staffs
        WHERE user_id = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param(
        "i",
        $user["user_id"]
    );

    $stmt->execute();

    $person_result = $stmt->get_result();


    if ($person_result->num_rows > 0) {

        $person = $person_result->fetch_assoc();

        $first_name_th = $person["first_name_th"];

        $last_name_th = $person["last_name_th"];

    }

}


// ========================================
// ถ้าเป็น Admin
// ========================================

elseif ($user["role"] == "admin") {

    /*
        ตอนนี้ฐานข้อมูลของเธอยังไม่มี
        user_admins

        ดังนั้น Admin จะใช้ username
        เป็นชื่อแสดงชั่วคราว
    */

    $first_name_th = $user["username"];

    $last_name_th = "";

}


// ========================================
// ป้องกัน Session Fixation
// ========================================

session_regenerate_id(true);


// ========================================
// เก็บข้อมูลลง Session
// ========================================

$_SESSION["user_id"] = $user["user_id"];

$_SESSION["username"] = $user["username"];

$_SESSION["role"] = $user["role"];

$_SESSION["first_name_th"] = $first_name_th;

$_SESSION["last_name_th"] = $last_name_th;


// ========================================
// Login สำเร็จ
// ========================================

header("Location: ../index.php");

exit;

?>