<?php

session_start();

// =====================================================
// ตรวจสอบสิทธิ์: เฉพาะบุคลากร (staff) และผู้ดูแลระบบ (admin)
// =====================================================

if (
    !isset($_SESSION['user_id']) ||
    !in_array($_SESSION['role'] ?? '', ['staff', 'admin'], true)
) {
    header("Location: ../login/index.php");
    exit;
}

require_once "../config/db.php";

// ตารางที่ใช้ในไฟล์นี้: user_accounts, user_students, user_staffs
// โครงสร้างตารางแบบเต็มดูได้ที่ database/schema.sql


// ========================================
// ดึงข้อมูลผู้ใช้งาน
// ========================================

$sql = "
    SELECT
        ua.user_id,
        ua.username,
        ua.role,
        ua.is_active,

        us.student_code,
        us.first_name_th AS student_first_name,
        us.last_name_th AS student_last_name,

        ust.first_name_th AS staff_first_name,
        ust.last_name_th AS staff_last_name

    FROM user_accounts ua

    LEFT JOIN user_students us
        ON us.user_id = ua.user_id

    LEFT JOIN user_staffs ust
        ON ust.user_id = ua.user_id

    ORDER BY ua.user_id DESC
";


$result = $conn->query($sql);

?>

<!DOCTYPE html>

<html lang="th">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        จัดการผู้ใช้งาน
    </title>

    <link
        rel="stylesheet"
        href="../css/user.css"
    >

</head>


<body>


<div class="container">


    <!-- =================================
         Header
    ================================== -->

    <div class="page-header">

        <div>

            <h1>
                จัดการผู้ใช้งาน
            </h1>

            <p>
                เพิ่ม แก้ไข และจัดการข้อมูลผู้ใช้งาน
            </p>

        </div>


        <a
            href="create.php"
            class="btn btn-primary"
        >

            + เพิ่มผู้ใช้งาน

        </a>

    </div>


    <!-- =================================
         ตาราง
    ================================== -->

    <div class="card">

        <table class="table">

            <thead>

                <tr>

                    <th>ID</th>

                    <th>
                        รหัสนักเรียน
                    </th>

                    <th>
                        Username
                    </th>

                    <th>
                        ชื่อ - นามสกุล
                    </th>

                    <th>
                        ประเภท
                    </th>

                    <th>
                        สถานะ
                    </th>

                    <th>
                        จัดการ
                    </th>

                </tr>

            </thead>


            <tbody>


            <?php if ($result->num_rows > 0) { ?>


                <?php while ($row = $result->fetch_assoc()) { ?>


                    <tr>


                        <!-- ID -->

                        <td>

                            <?= htmlspecialchars(
                                $row["user_id"]
                            ) ?>

                        </td>


                        <!-- Student Code -->

                        <td>

                            <?php

                            if ($row["role"] == "student") {

                                echo htmlspecialchars(
                                    $row["student_code"] ?? "-"
                                );

                            } else {

                                echo "-";

                            }

                            ?>

                        </td>


                        <!-- Username -->

                        <td>

                            <?= htmlspecialchars(
                                $row["username"]
                            ) ?>

                        </td>


                        <!-- Name -->

                        <td>

                            <?php

                            if ($row["role"] == "student") {

                                echo htmlspecialchars(
                                    trim(
                                        ($row["student_first_name"] ?? "")
                                        . " "
                                        . ($row["student_last_name"] ?? "")
                                    )
                                );

                            }
                            elseif ($row["role"] == "staff") {

                                echo htmlspecialchars(
                                    trim(
                                        ($row["staff_first_name"] ?? "")
                                        . " "
                                        . ($row["staff_last_name"] ?? "")
                                    )
                                );

                            }
                            else {

                                echo "-";

                            }

                            ?>

                        </td>


                        <!-- Role -->

                        <td>

                            <?php

                            if ($row["role"] == "student") {

                                echo "นักเรียน";

                            }
                            elseif ($row["role"] == "staff") {

                                echo "บุคลากร";

                            }
                            elseif ($row["role"] == "admin") {

                                echo "ผู้ดูแลระบบ";

                            }
                            else {

                                echo htmlspecialchars(
                                    $row["role"]
                                );

                            }

                            ?>

                        </td>


                        <!-- Status -->

                        <td>

                            <?php if ($row["is_active"]) { ?>

                                <span class="status-active">

                                    ใช้งาน

                                </span>

                            <?php } else { ?>

                                <span class="status-inactive">

                                    ปิดใช้งาน

                                </span>

                            <?php } ?>

                        </td>


                        <!-- Edit -->

                        <td>

                            <a
                                href="edit.php?id=<?= $row["user_id"] ?>"
                                class="btn btn-edit"
                            >

                                แก้ไข

                            </a>

                        </td>


                    </tr>


                <?php } ?>


            <?php } else { ?>


                <tr>

                    <td
                        colspan="7"
                        class="no-data"
                    >

                        ยังไม่มีข้อมูลผู้ใช้งาน

                    </td>

                </tr>


            <?php } ?>


            </tbody>

        </table>

    </div>


</div>


</body>

</html>