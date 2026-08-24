<?php

require_once "../config/db.php";


// =================================
// รับข้อมูลจาก edit.php
// =================================

$user_id = $_POST["user_id"];

$username = $_POST["username"];
$role = $_POST["role"];
$is_active = $_POST["is_active"];

$citizen_id = $_POST["citizen_id"];
$title_name = $_POST["title_name"];

$first_name_th = $_POST["first_name_th"];
$last_name_th = $_POST["last_name_th"];

$email = $_POST["email"];
$phone = $_POST["phone"];


// =================================
// แก้ไขข้อมูล user_accounts
// =================================

$sql = "
    UPDATE user_accounts
    SET
        username = ?,
        role = ?,
        is_active = ?
    WHERE user_id = ?
";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "ssii",
    $username,
    $role,
    $is_active,
    $user_id
);

$stmt->execute();


// =================================
// ถ้าเป็นนักเรียน
// =================================

if ($role == "student") {

    $sql = "
        UPDATE user_students
        SET
            citezen_id = ?,
            title_name = ?,
            first_name_th = ?,
            last_name_th = ?,
            email = ?,
            phone = ?
        WHERE user_id = ?
    ";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param(
        "ssssssi",
        $citizen_id,
        $title_name,
        $first_name_th,
        $last_name_th,
        $email,
        $phone,
        $user_id
    );

    $stmt->execute();
}


// =================================
// ถ้าเป็นบุคลากร
// =================================

if ($role == "staff") {

    $sql = "
        UPDATE user_staffs
        SET
            citezen_id = ?,
            title_name = ?,
            first_name_th = ?,
            last_name_th = ?,
            email = ?,
            phone = ?
        WHERE user_id = ?
    ";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param(
        "ssssssi",
        $citizen_id,
        $title_name,
        $first_name_th,
        $last_name_th,
        $email,
        $phone,
        $user_id
    );

    $stmt->execute();
}


// =================================
// กลับหน้า main
// =================================

header("Location: main.php");

exit;

?>