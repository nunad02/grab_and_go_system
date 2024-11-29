<?php
include '../../db/conn.php';
session_start();
ob_start();

if (!isset($_SESSION['account_id'])) {
    header("Location: ../../index.php");
    exit();
}

if (!isset($_SESSION['profile']) || empty($_SESSION['profile'])) {
    $_SESSION['profile'] = 'profile.png';
}

$profileImage = htmlspecialchars('../../img/' . $_SESSION['profile']);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['profileImage'])) {
    $email = $_SESSION['email'];
    $uploadDir = '../../img/';
    $uploadFile = $uploadDir . basename($_FILES['profileImage']['name']);
    $oldProfileImage = $_SESSION['profile'];

    if (move_uploaded_file($_FILES['profileImage']['tmp_name'], $uploadFile)) {
        $newImagePath = basename($_FILES['profileImage']['name']);

        $sql = "UPDATE account SET profile = ? WHERE email = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt->execute([$newImagePath, $email])) {
            $sql = "UPDATE orders SET customer_profile = ? WHERE customer_name = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$newImagePath, $_SESSION['fullname']]);

            $sql = "UPDATE checkout SET customer_profile = ? WHERE customer_name = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$newImagePath, $_SESSION['fullname']]);

            $_SESSION['profile'] = $newImagePath;
            echo json_encode(['success' => true, 'newImagePath' => $newImagePath]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update the database.']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to upload the file.']);
    }
    exit;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../../phpmailer/src/Exception.php';
require '../../phpmailer/src/PHPMailer.php';
require '../../phpmailer/src/SMTP.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['sendEmail'])) {
    $newPassword = $_POST['newPassword'];
    $newFullname = $_POST['newFullname'];
    $newEmail = $_POST['newEmail'];
    $currentEmail = $_SESSION['email'];
    $currentFullname = $_SESSION['fullname'];

    $hashedPassword = empty($newPassword) ? null : sha1($newPassword);

    if (empty($newFullname)) {
        $newFullname = $_SESSION['fullname'];
    }
    if (empty($newEmail)) {
        $newEmail = $_SESSION['email'];
    }

    try {
        $conn->beginTransaction();

        if ($hashedPassword) {
            $stmt = $conn->prepare("UPDATE account SET fullname = ?, email = ?, password = ? WHERE email = ?");
            $executeParams = [$newFullname, $newEmail, $hashedPassword, $currentEmail];
        } else {
            $stmt = $conn->prepare("UPDATE account SET fullname = ?, email = ? WHERE email = ?");
            $executeParams = [$newFullname, $newEmail, $currentEmail];
        }

        if ($stmt->execute($executeParams)) {
            $_SESSION['fullname'] = $newFullname;
            $_SESSION['email'] = $newEmail;

            $stmt = $conn->prepare("UPDATE orders SET customer_name = ? WHERE customer_name = ?");
            $stmt->execute([$newFullname, $currentFullname]);

            $stmt = $conn->prepare("UPDATE checkout SET customer_name = ? WHERE customer_name = ?");
            $stmt->execute([$newFullname, $currentFullname]);

            $conn->commit();

            $mail = new PHPMailer(true);

            try {
                $mail->SMTPDebug = 0;
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'omscmpcgovph@gmail.com';
                $mail->Password   = 'lzxtyttgxzvbaxvb';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port       = 465;

                $mail->setFrom('omscmpcgovph@gmail.com', 'OMSC MPC');
                $mail->addAddress($newEmail, $newFullname);

                $mail->isHTML(true);
                $mail->Subject = 'Your Account Information Has Been Successfully Updated';
                $mail->Body    = 'Dear ' . htmlspecialchars($newFullname) . ',<br><br>' .
                    'We are writing to inform you that your account information has been successfully updated in our system. Please find the updated details of your account below:<br><br>' .
                    '<strong>Account Details:</strong><br>' .
                    'Email: ' . htmlspecialchars($newEmail) . '<br>' .
                    'Full Name: ' . htmlspecialchars($newFullname) . '<br>';

                if ($hashedPassword) {
                    $mail->Body .= 'Password: ' . htmlspecialchars($newPassword) . '<br><br>';
                }

                $mail->Body .= 'If you did not make this request or if you encounter any issues, please do not hesitate to contact our support team immediately.<br><br>' .
                    'We are committed to ensuring the security and accuracy of your account details, and we appreciate your continued trust in our services.<br><br>' .
                    'Thank you for being part of the OMSC MPC community.<br><br>' .
                    'Best regards,<br>' .
                    'The OMSC MPC Team';

                $mail->AltBody = 'Dear ' . htmlspecialchars($newFullname) . ",\n\n" .
                    'We are writing to inform you that your account information has been successfully updated in our system. Please find the updated details of your account below:\n\n' .
                    'Account Details:\n' .
                    'Email: ' . htmlspecialchars($newEmail) . "\n" .
                    'Full Name: ' . htmlspecialchars($newFullname) . "\n";

                if ($hashedPassword) {
                    $mail->AltBody .= 'Password: ' . htmlspecialchars($newPassword) . "\n\n";
                }

                $mail->AltBody .= "If you did not make this request or if you encounter any issues, please contact our support team immediately.\n\n" .
                    "Thank you for being part of the OMSC MPC community.\n\n" .
                    "Best regards,\n" .
                    "The OMSC MPC Team";


                $mail->send();
                $_SESSION['update_success'] = true;
                header('Location: accounts.php');
            } catch (Exception $e) {
                $_SESSION['update_error'] = true;
                $mail->ErrorInfo . '");</script>';
            }
        } else {
            $conn->rollBack();
            echo '<script>alert("Error updating account information.");</script>';
        }
    } catch (Exception $e) {
        $conn->rollBack();
        echo '<script>alert("Transaction failed: ' . $e->getMessage() . '");</script>';
    }
    exit();
}

?>



<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grab & Go | Feedback</title>
    <link rel="shortcut icon" href="../../img/logo.png" type="image/x-icon">
    <?php include_once '../../header.php' ?>
    <link rel="stylesheet" href="../../css/cashier_main.css">
    <style>
        .fixed-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1000;
        }

        label,
        h5 {
            font-weight: bold;
        }

        .feedback-table {
            width: 98%;
            margin: 0 auto;
            border-collapse: collapse;
        }

        .feedback-table th,
        .feedback-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        .feedback-table th {
            background-color: #f2f2f2;
        }

        .accordion-button.collapsed .fa-chevron-down {
            transform: rotate(0deg);
            transition: transform 0.3s;
        }

        .accordion-button .fa-chevron-down {
            transform: rotate(180deg);
            transition: transform 0.3s;
        }

        .accordion-button:not(.collapsed) .fa-chevron-right {
            transform: rotate(90deg);
            transition: transform 0.3s;
        }

        .accordion-button.collapsed .fa-chevron-right {
            transform: rotate(0deg);
            transition: transform 0.3s;
        }

        .rotate-icon {
            transform: rotate(90deg);
            transition: transform 0.3s ease;
        }

        .star-rating i {
            color: #f39c12;
            font-size: 16px;
        }

        .star-rating .fa-star-half-alt {
            color: #f1c40f;
        }

        .feedback-row {
            cursor: pointer;
        }

        .feedback-row .extra-row {
            display: none;
            background-color: #f9f9f9;
        }

        .extra-row td {
            padding-left: 40px;
        }

        .main-row:hover .extra-row {
            display: table-row;
        }
    </style>
</head>

<body>
    <div class="sidebar">
        <div class="logo-container">
            <div class="cart-icon">
                <img src="../../img/cart.png" alt="Cart Icon" class="cart">
                <img src="../../img/logo.png" alt="Logo" class="logo">
            </div>
            <h2>Grab&Go</h2>
            <div class="button-container">
                <a href="index.php" id="home"> <i class="fas fa-home"></i>Home</a>
                <a href="statistics.php" id="statistics"> <i class="fas fa-chart-line"></i> Statistics</a>
                <a href="products.php" id="products"><i class="fas fa-boxes"></i> Manage Products </a>
                <a href="feedback.php" class="active" id="feedback"><i class="fas fa-comments"></i> Feedback</a>
                <a href="accounts.php" id="accounts"><i class="fas fa-user"></i> Accounts </a>
                <a href="about.php"><i class="fas fa-info-circle"></i> About</a>
            </div>
        </div>
        <div class="hamburger-menu">
            <div class="bar"></div>
            <div class="bar"></div>
            <div class="bar"></div>
        </div>
        <div class="dropdown">
            <div class="button-container">
                <a href="index.php" id="home"> <i class="fas fa-home"></i>Home</a>
                <a href="statistics.php" id="statistics"> <i class="fas fa-chart-line"></i> Statistics</a>
                <a href="products.php" id="products"><i class="fas fa-boxes"></i> Manage Products </a>
                <a href="feedback.php" class="active" id="feedback"><i class="fas fa-comments"></i> Feedback</a>
                <a href="accounts.php" id="accounts"><i class="fas fa-user"></i> Accounts </a>
                <a href="about.php"><i class="fas fa-info-circle"></i> About</a>
            </div>
        </div>
    </div>

    <div class="home-container">
        <nav class="navbar">
            <div class="container-fluid">
                <a class="navbar-brand fw-bold fs-3 ms-2 font-effect-shadow-multiple">FEEDBACK</a>
                <img src="<?php echo $profileImage; ?>" alt="Profile" class="d-flex me-2 border border-2 border-primary-subtle" style="height: 40px; width: 40px; border-radius: 50%;" id="welcomeUser" title="Menu" data-toggle="tooltip">
            </div>
        </nav>

        <div id="userOptions" class="user-profile">
            <div class="profile-wrapper">
                <img src="../../img/<?php echo htmlspecialchars($_SESSION['profile']); ?>" alt="click here to update profile" class="d-flex border border-2 border-primary-subtle mb-3" style="height: 100px; width: 100px; border-radius: 50%; margin: 0 auto; cursor: pointer;" id="profileImage" title='Click me to change profile' data-toggle='tooltip'>

                <input type="file" id="profileInput" style="display: none;">
                <div class="profile-details text-center">
                    <p class="fw-bold"><?php echo htmlspecialchars($_SESSION['email']); ?></p>
                    <p><?php echo htmlspecialchars($_SESSION['fullname']); ?></p>
                </div>

                <form id="changePasswordForm" method="post" action="account.php" class="text-center" style="display: none;">
                    <div class="mb-3">
                        <input type="text" class="form-control mb-1" id="newFullname" name="newFullname" placeholder="Fullname">
                        <input type="email" class="form-control mb-1" id="newEmail" name="newEmail" placeholder="Email">

                        <div class="password-container" style="position: relative;">
                            <input type="password" class="form-control mb-1" id="newPassword" name="newPassword" placeholder="Password">
                            <span id="togglePassword" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer;">
                                👁️
                            </span>
                        </div>
                    </div>

                    <button type="button" id="cancelButton" class="btn btn-sm btn-primary">Cancel</button>
                    <button type="submit" name="sendEmail" class="btn btn-sm btn-primary">Submit</button>
                </form>

                <div class="dropBtn mt-3">
                    <a class="btn text-center fw-bold w-100 btn-drop-menu btn-outline-primary" id="changePasswordButton">
                        Edit Account
                    </a>
                    <a class="btn text-center fw-bold w-100 btn-drop-menu btn-outline-primary my-1" id="logoutButton" data-bs-toggle="modal" data-bs-target="#logoutModal">
                        <i class="fas fa-sign-out-alt"></i>&nbsp; Logout
                    </a>
                </div>
            </div>
        </div>

        <div class="modal fade" id="logoutModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
            <div class="modal-dialog logout-modal-dialog">
                <div class="modal-content logout-modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title text-dark" id="staticBackdropLabel">Logout</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body modal-body-sm">
                        <p class="text-start" style="font-size: 14px;">Are you sure you want to log out? This action will end your session and require you to sign in again.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">No</button>
                        <button type="button" class="btn btn-primary d-block btn-sm">
                            <a href="../../includes/logout.php" style="text-decoration: none; color: white;">Yes</a>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <?php
        try {
            $stmt_all = $conn->query("SELECT * FROM feedback ORDER BY product_name");
            $all_feedback = $stmt_all->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            die("Error fetching feedback: " . $e->getMessage());
        }

        $product_feedbacks = [];
        foreach ($all_feedback as $feedback) {
            $product_name = $feedback['product_name'];
            // $product_id = $feedback['product_id'];
            if (!isset($product_feedbacks[$product_name])) {
                $product_feedbacks[$product_name] = [
                    'ratings' => [],
                    'feedbacks' => [],
                    'product_category' => [],
                    'image_path' => $feedback['product_image'],
                    'feedback_list' => []
                ];
            }
            $product_feedbacks[$product_name]['ratings'][] = $feedback['rating'];
            $product_feedbacks[$product_name]['feedbacks'][] = $feedback['feedback'];
            $product_feedbacks[$product_name]['product_id'] = $feedback['product_id'];
            $product_feedbacks[$product_name]['product_category'] = $feedback['product_category'];
            $product_feedbacks[$product_name]['feedback_list'][] = $feedback;
        }

        function displayStars($rating)
        {
            $fullStars = floor($rating);
            $halfStar = ($rating - $fullStars) >= 0.5 ? true : false;
            $emptyStars = 5 - $fullStars - ($halfStar ? 1 : 0);

            $stars = str_repeat('<i class="fas fa-star"></i>', $fullStars);
            if ($halfStar) {
                $stars .= '<i class="fas fa-star-half-alt"></i>';
            }
            $stars .= str_repeat('<i class="far fa-star"></i>', $emptyStars);

            return $stars;
        }
        ?>

        <div class="order-container mt-2">
            <div class="row row-cols-1 row-cols-md-6 ms-4 btn-category p-1" style="max-width: 96%; margin: 0 auto;">
                <table class="table table-hover feedback-table" id="feedback-table" style="border: 2px solid #aaa; border-right: none; border-left: none; font-family: Arial, sans-serif;">
                    <form>
                        <div class="user-container w-100 mb-3">
                            <input type="search" class="w-100 p-1" style="outline: none;" name="search_feedback" id="search_feedback" placeholder="Search..." oninput="searchProducts()">
                        </div>
                    </form>
                    <thead>
                        <tr class="fw-bold text-nowrap">
                            <th>#</th>
                            <th>Product Name</th>
                            <th>Product ID</th>
                            <th>Product Category</th>
                            <th>Rating</th>
                            <th>Feedback Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $count = 1;
                        foreach ($product_feedbacks as $product_name => $product_data) :
                            $average_rating = array_sum($product_data['ratings']) / count($product_data['ratings']);
                            $image_path = "../products/" . $product_data['image_path'];

                            if (empty($product_data['image_path']) || !file_exists($image_path)) {
                                $image_path = "../products/profile.png";
                            }
                        ?>
                            <tr class="feedback-row text-nowrap main-row" data-product="<?php echo htmlspecialchars($product_name); ?>" style="cursor: pointer;">
                                <td class="p-2 fw-bold count">
                                    <i class="fas fa-chevron-right me-2"></i>
                                    <?php echo $count++; ?>
                                </td>

                                <td class="p-2 product_name">
                                    <img src="<?php echo htmlspecialchars($image_path); ?>" alt="Profile Image" style="width: 35px; height: 35px; border-radius: 50%;">
                                    <span><?php echo htmlspecialchars($product_name); ?></span>
                                </td>

                                <td class="p-2 product_id">
                                    <?php echo htmlspecialchars(str_replace('_', ' ', $product_data['product_id'] ?? 'N/A')); ?>
                                </td>
                                <td class="p-2 product_category">
                                    <?php echo htmlspecialchars(str_replace('_', ' ', $product_data['product_category'] ?? 'N/A')); ?>
                                </td>
                                <td class="p-2 text-warning">
                                    <?php
                                    echo displayStars($average_rating);
                                    echo " (" . number_format($average_rating, 1) . ")";
                                    ?>
                                </td>
                                <td class="p-2 feedbacks"><?php echo count($product_data['feedbacks']); ?></td>
                            </tr>

                            <tr class="accordion-row" style="display: none;">
                                <td colspan="6">
                                    <div id="accordion-<?php echo $count; ?>">
                                        <?php
                                        foreach ($all_feedback as $fb) :
                                            if ($fb['product_name'] == $product_name && $fb != $product_data['feedbacks'][0]) :
                                                $feedback_id = isset($fb['id']) ? $fb['id'] : uniqid('feedback_');

                                                $profile = $fb["customer_profile"];
                                                $image_path = "../../img/" . $profile;

                                                if (empty($profile) || !file_exists($image_path)) {
                                                    $image_path = "../../img/profile.png";
                                                }
                                        ?>
                                                <div class="accordion-item">
                                                    <h2 class="accordion-header text-dark" id="heading-<?php echo $count . '-' . $feedback_id; ?>">
                                                        <button class="accordion-button text-dark d-flex align-items-center" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo $count . '-' . $feedback_id; ?>" aria-expanded="false" aria-controls="collapse-<?php echo $count . '-' . $feedback_id; ?>">
                                                            <i class="fas fa-chevron-down me-2 text-success"></i>
                                                            <img src="<?php echo htmlspecialchars($image_path); ?>" alt="Profile Image" style="width: 30px; height: 30px; border-radius: 50%; margin-right: 10px;">
                                                            <?php echo htmlspecialchars($fb['customer_name']); ?>
                                                        </button>
                                                    </h2>

                                                    <div id="collapse-<?php echo $count . '-' . $feedback_id; ?>" class="accordion-collapse collapse" aria-labelledby="heading-<?php echo $count . '-' . $feedback_id; ?>" data-bs-parent="#accordion-<?php echo $count; ?>">
                                                        <div class="accordion-body">
                                                            <p class="text-warning"><span class="text-dark"><strong>Rating:</strong></span> <?php echo displayStars($fb['rating']); ?><span class="text-dark"> (<?php echo number_format($fb['rating'], 1); ?>)</span></p>
                                                            <p class="text-dark"><strong>Feedback:</strong> <?php echo htmlspecialchars($fb['feedback']); ?></p>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                            </tr>


                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div><br><br>
        </div>

        <script>
            document.querySelectorAll('.main-row').forEach(function(row) {
                row.addEventListener('click', function() {
                    const accordionRow = row.nextElementSibling;
                    const chevronIcon = row.querySelector('.fa-chevron-right');

                    if (accordionRow.style.display === 'none' || accordionRow.style.display === '') {
                        accordionRow.style.display = 'table-row';
                        chevronIcon.classList.add('rotate-icon');
                    } else {
                        accordionRow.style.display = 'none';
                        chevronIcon.classList.remove('rotate-icon');
                    }
                });
            });
        </script>


        <div class="btn-container-bottom fixed-btn">
            <button type="button" class="btn btn-primary " onclick="exportToExcel()">
                <i class="fas fa-file-export"></i> Export
            </button>


        </div>

    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const mainRows = document.querySelectorAll(".main-row");

            mainRows.forEach(mainRow => {
                mainRow.addEventListener("click", function() {
                    const product = mainRow.getAttribute("data-product");
                    const extraRows = document.querySelectorAll(`.extra-row[data-product='${product}']`);

                    extraRows.forEach(row => {
                        row.style.display = row.style.display === "none" ? "table-row" : "none";
                    });
                });
            });
        });
    </script>


    <!-- Status Modal -->
    <div class="modal fade" id="statusModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-body">
                    <h5 class="text-center" id="statusMessage"></h5>
                </div>
            </div>
        </div>
    </div>

    <?php include_once '../../includes/goToTop.php'; ?>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.0/xlsx.full.min.js"></script>
    <script>
        function exportToExcel() {
            var originalTable = document.getElementById("feedback-table");

            var tableCopy = originalTable.cloneNode(true);
            var rows = tableCopy.querySelectorAll("tr");

            rows.forEach(function(row) {
                if (row.cells.length > 0) {
                    row.deleteCell(row.cells.length - 1);
                }
            });

            var ws = XLSX.utils.table_to_sheet(tableCopy);

            var colWidths = [];
            var range = XLSX.utils.decode_range(ws['!ref']);
            for (var C = range.s.c; C <= range.e.c; ++C) {
                var maxWidth = 10;
                for (var R = range.s.r; R <= range.e.r; ++R) {
                    var cell = ws[XLSX.utils.encode_cell({
                        r: R,
                        c: C
                    })];
                    if (cell && cell.v) {
                        var cellText = cell.v.toString();
                        maxWidth = Math.max(maxWidth, cellText.length + 5);
                    }
                }
                colWidths.push({
                    wch: maxWidth
                });
            }
            ws['!cols'] = colWidths;
            var wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, "Feedbacks");

            XLSX.writeFile(wb, "feedbacks.xlsx");
        }
    </script>

    <script>
        function searchProducts() {
            var input = document.getElementById("search_feedback").value.toLowerCase();
            var rows = document.querySelectorAll(".feedback-row");

            rows.forEach(function(row) {
                var count = row.querySelector(".count").textContent.toLowerCase();
                var product_name = row.querySelector(".product_name").textContent.toLowerCase();
                var product_id = row.querySelector(".product_id").textContent.toLowerCase();
                var product_category = row.querySelector(".product_category").textContent.toLowerCase();
                var feedbacks = row.querySelector(".feedbacks").textContent.toLowerCase();

                if (count.includes(input) || product_name.includes(input) || product_id.includes(input) || product_category.includes(input) || feedbacks.includes(input)) {
                    row.style.display = "table-row";
                } else {
                    row.style.display = "none";
                }
            });
        }
    </script>

    <script src="../../js/cashier_script.js"> </script>
    <script>
        document.getElementById("profileImage").addEventListener("click", function() {
            document.getElementById("profileInput").click();
        });

        document.getElementById("profileInput").addEventListener("change", function() {
            const file = this.files[0];
            if (file) {
                const formData = new FormData();
                formData.append("profileImage", file);

                fetch("feedback.php", {
                        method: "POST",
                        body: formData,
                    })
                    .then((response) => response.json())
                    .then((data) => {
                        if (data.success) {
                            document.getElementById("profileImage").src =
                                "../../img/" + data.newImagePath;
                            document.getElementById("welcomeUser").src =
                                "../../img/" + data.newImagePath;
                        } else {
                            alert("Failed to upload the profile picture.");
                        }
                    })
                    .catch((error) => {
                        console.error("Error:", error);
                    });
            }
        });

        document
            .getElementById("changePasswordButton")
            .addEventListener("click", function() {
                document.getElementById("changePasswordForm").style.display = "block";
            });

        document.addEventListener("DOMContentLoaded", function() {
            var cancelButton = document.getElementById("cancelButton");

            cancelButton.addEventListener("click", function() {
                document.getElementById("changePasswordForm").style.display = "none";
            });
        });
    </script>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const buttons = document.querySelectorAll(".btn-category a");
            const activeCategory = "<?php echo $product_category; ?>";

            buttons.forEach((button) => {
                if (button.getAttribute("data-category") === activeCategory) {
                    button.classList.add("active");
                } else {
                    button.classList.remove("active");
                }

                button.addEventListener("click", function(event) {
                    buttons.forEach((button) => button.classList.remove("active"));
                    this.classList.add("active");
                    localStorage.setItem("activeButton", this.id);
                });
            });
        });


        document
            .getElementById("changePasswordButton")
            .addEventListener("click", function() {
                document.getElementById("changePasswordForm").style.display = "block";
            });

        document.addEventListener("DOMContentLoaded", function() {
            var cancelButton = document.getElementById("cancelButton");

            cancelButton.addEventListener("click", function() {
                document.getElementById("changePasswordForm").style.display = "none";
            });
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($_SESSION['update_error'])): ?>
                $('#statusMessage').html('<i class="fas fa-warning text-danger fw-bold"></i>Error Updating feedback information');
                $('#statusModal').modal('show');
                setTimeout(function() {
                    $('#statusModal').modal('hide');
                    <?php unset($_SESSION['update_error']); ?>
                }, 3000);
            <?php elseif (isset($_SESSION['update_success'])): ?>
                $('#statusMessage').html('<i class="fas fa-check text-success fw-bold"></i> Your feedback information has been updated!');
                $('#statusModal').modal('show');
                setTimeout(function() {
                    $('#statusModal').modal('hide');
                    window.location.href = 'feedbacks.php';
                    <?php unset($_SESSION['update_success']); ?>
                }, 3000);
            <?php endif; ?>
        });
    </script>

    <script>
        const togglePassword = document.getElementById('togglePassword');
        const passwordField = document.getElementById('newPassword');

        togglePassword.addEventListener('click', function() {
            const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordField.setAttribute('type', type);

            this.textContent = type === 'password' ? '👁️' : '🙈';
        });
    </script>
</body>

</html>