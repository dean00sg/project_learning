<?php

session_start();


// ตรวจสอบว่ Login แล้วหรือยัง

if (!isset($_SESSION["user_id"])) {

    header("Location: ../login/index.php");

    exit;
}


require_once "../config/db.php";

$sql = "
    SELECT
        user_id,
        username,
        role,
        is_active
    FROM user_accounts
    ORDER BY user_id DESC
";

$result = $conn->query($sql);

?>

<!DOCTYPE html>
<html lang="th">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>จัดการผู้ใช้งาน</title>

    <link rel="stylesheet"
          href="../css/user.css">

</head>

<body>

<div class="container">


    <!-- หัวข้อ -->

    <div class="page-header">

        <div>

            <h1>จัดการผู้ใช้งาน</h1>

            <p>รายการบัญชีผู้ใช้งานในระบบ</p>

        </div>


        <a href="create.php"
           class="btn btn-primary">

            + เพิ่มผู้ใช้งาน

        </a>

    </div>


    <!-- ตาราง -->

    <div class="card">

        <table class="table">

            <thead>

                <tr>

                    <th>ID</th>

                    <th>Username</th>

                    <th>ประเภท</th>

                    <th>สถานะ</th>

                    <th>จัดการ</th>

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


                        <!-- Username -->

                        <td>

                            <?= htmlspecialchars(
                                $row["username"]
                            ) ?>

                        </td>


                        <!-- Role -->

                        <td>

                            <?php

                            if ($row["role"] == "student") {

                                echo "นักเรียน";

                            } elseif ($row["role"] == "staff") {

                                echo "บุคลากร";

                            } else {

                                echo htmlspecialchars(
                                    $row["role"]
                                );

                            }

                            ?>

                        </td>


                        <!-- สถานะ -->

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


                        <!-- จัดการ -->

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
                        colspan="5"
                        class="no-data"
                    >
                        ยังไม่มีผู้ใช้งาน
                    </td>

                </tr>

            <?php } ?>


            </tbody>

        </table>

    </div>

</div>

</body>

</html>