<?php

/*
=====================================================================
Mock API ข้อมูลนักเรียน (จำลอง API ภายนอกสำหรับฝึกเขียนโค้ดดึงข้อมูล)

ไฟล์นี้ไม่เกี่ยวข้องกับฐานข้อมูลของระบบ ไม่ต้อง login
แค่คืนค่า JSON array ของนักเรียน เหมือน REST API พื้นฐานทั่วไป

ทดสอบเรียกตรง ๆ ได้ที่: api_demo/mock_students_api.php
=====================================================================
*/

header("Content-Type: application/json; charset=utf-8");

$students = [
    [
        "citizen_id"          => "1100100000011",
        "title_name"          => "นาย",
        "first_name_th"       => "กิตติ",
        "last_name_th"        => "แสงทอง",
        "first_name_en"       => "Kitti",
        "last_name_en"        => "Saengthong",
        "birthday"            => "2010-05-14",
        "sex"                 => "M",
        "email"               => "kitti.s@example.com",
        "phone"               => "0891234501",
        "classroom_number_code" => "ม.1/1",
    ],
    [
        "citizen_id"          => "1100100000022",
        "title_name"          => "นางสาว",
        "first_name_th"       => "จิรา",
        "last_name_th"        => "เพชรดี",
        "first_name_en"       => "Jira",
        "last_name_en"        => "Petchdee",
        "birthday"            => "2010-08-02",
        "sex"                 => "F",
        "email"               => "jira.p@example.com",
        "phone"               => "0891234502",
        "classroom_number_code" => "ม.1/1",
    ],
    [
        "citizen_id"          => "1100100000033",
        "title_name"          => "นาย",
        "first_name_th"       => "ธนากร",
        "last_name_th"        => "วงศ์ษา",
        "first_name_en"       => "Thanakorn",
        "last_name_en"        => "Wongsa",
        "birthday"            => "2010-01-20",
        "sex"                 => "M",
        "email"               => "thanakorn.w@example.com",
        "phone"               => "0891234503",
        "classroom_number_code" => "ม.1/2",
    ],
    [
        "citizen_id"          => "1100100000044",
        "title_name"          => "นางสาว",
        "first_name_th"       => "ปิยะดา",
        "last_name_th"        => "รักเรียน",
        "first_name_en"       => "Piyada",
        "last_name_en"        => "Rakrian",
        "birthday"            => "2010-11-30",
        "sex"                 => "F",
        "email"               => "piyada.r@example.com",
        "phone"               => "0891234504",
        "classroom_number_code" => "ม.1/2",
    ],
    [
        "citizen_id"          => "1100100000055",
        "title_name"          => "นาย",
        "first_name_th"       => "ณัฐวุฒิ",
        "last_name_th"        => "ใจดี",
        "first_name_en"       => "Nattawut",
        "last_name_en"        => "Jaidee",
        "birthday"            => "2010-03-09",
        "sex"                 => "M",
        "email"               => "nattawut.j@example.com",
        "phone"               => "0891234505",
        "classroom_number_code" => "ม.1/1",
    ],
];

echo json_encode($students, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
