<?php
include '../../db/conn.php';
session_start();
ob_start();

if (!isset($_SESSION['account_id'])) {
    header("Location: ../../index.php");
    exit();
}

$totalItems = 0;
$totalPrice = 0;

$product_category = isset($_GET['product_category']) ? htmlspecialchars($_GET['product_category']) : 'Drinks';
$_SESSION['product_category'] = $product_category;

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


if (isset($_POST['addOrder'])) {
    $requiredFields = ['product_name', 'price', 'quantity', 'product_image'];

    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field])) {
            echo "Required POST data missing: $field.";
            exit();
        }
    }

    date_default_timezone_set('Asia/Manila');
    $order_date = date('Y-m-d h:i A');
    $customer_name = htmlspecialchars($_SESSION['fullname']);
    $user_type = $_SESSION['category'];
    $product_category = $_SESSION['product_category'];
    $product_name = htmlspecialchars($_POST['product_name']);
    $price_per_unit = htmlspecialchars($_POST['price']);
    $quantity = htmlspecialchars($_POST['quantity']);
    $total_price = $price_per_unit * $quantity;
    $order_status = "cart";
    $product_image = htmlspecialchars($_POST['product_image']);
    $customer_profile = $_SESSION['profile'];

    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare("SELECT order_number, order_status FROM orders WHERE customer_name = ? ORDER BY order_number DESC LIMIT 1");
        $stmt->execute([$customer_name]);
        $latestOrder = $stmt->fetch();

        if ($latestOrder) {
            if ($latestOrder['order_status'] == 'paid') {
                $order_number = $latestOrder['order_number'] + 1;
            } else {
                $order_number = $latestOrder['order_number'];
            }
        } else {
            $order_number = 10001;
        }

        do {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM orders WHERE order_number = ?");
            $stmt->execute([$order_number]);
            $orderExists = $stmt->fetchColumn() > 0;

            if ($orderExists && ($latestOrder && $latestOrder['order_status'] == 'paid')) {
                $order_number++;
            } else {
                break;
            }
        } while ($orderExists);

        $stmt = $conn->prepare("INSERT INTO orders (order_number, customer_name, user_type, order_date, product_category, product_name, price, quantity, order_status, product_image, customer_profile) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$order_number, $customer_name, $user_type, $order_date, $product_category, $product_name, $total_price, $quantity, $order_status, $product_image, $customer_profile]);

        $stmt = $conn->prepare("UPDATE products SET quantity = quantity - ? WHERE product_name = ?");
        $stmt->execute([$quantity, $product_name]);

        $conn->commit();

        header('Location: index.php?status=success');
        exit();
    } catch (PDOException $e) {
        $conn->rollBack();
        echo "Error: " . $e->getMessage();
    }
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_item'])) {
    if (isset($_POST['selectedProducts'])) {
        $selectedProducts = $_POST['selectedProducts'];
        $placeholders = rtrim(str_repeat('?,', count($selectedProducts)), ',');
        $sql = "UPDATE orders SET order_status = 'removed' WHERE product_name IN ($placeholders)";
        $stmt = $conn->prepare($sql);
        $stmt->execute($selectedProducts);
    }
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
                header('Location: index.php');
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
    <title>Grab & Go | Home</title>
    <link rel="shortcut icon" href="../../img/logo.png" type="image/x-icon">
    <?php include_once '../../header.php' ?>
    <link rel="stylesheet" href="../../css/cashier_main.css">
    <style>
        .dashboard-container {
            font-family: Verdana, Geneva, Tahoma, sans-serif;
        }

        .header-container {
            width: 100%;
            max-width: 96%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .calendar-header {
            margin: 0;
        }

        .controls {
            display: flex;
            gap: 10px;
            margin-left: 30px;
        }

        .controls select {
            padding: 5px 10px;
            font-size: 16px;
            background-color: #e2e2e2;
            border-radius: 5px;
            outline: none;
            border: none;
        }

        .calendar {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 3px;
            width: 100%;
            max-width: 96%;
        }

        .calendar .header {
            background-color: #007bff;
            color: #fff;
            font-size: 20px;
            font-weight: bold;
            text-align: center;
            padding: 5px 10px;
        }

        .calendar .day {
            border: 1px solid #007bff;
            padding: 5px 10px;
            text-align: center;
            height: 60px;
            font-size: 23px;
            font-weight: bold;
        }

        .today {
            background-color: #C0DAFE;
        }

        .time-display {
            text-align: center;
            font-size: 20px;
            font-weight: bold;
            color: #fff;
            margin: 0 auto;
            margin-bottom: 1rem;
            margin-top: 1rem;
            width: 96%;
            padding: 5px 10px;
            background-color: #007bff;
            border-radius: 5px;
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
                <a href="index.php" id="home" class="active"> <i class="fas fa-home"></i>Home</a>
                <a href="statistics.php" id="statistics"> <i class="fas fa-chart-line"></i> Statistics</a>
                <a href="products.php" id="products"><i class="fas fa-boxes"></i> Manage Products </a>
                <a href="feedback.php" id="feedback"><i class="fas fa-comments"></i> Feedback</a>
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
                <a href="index.php" id="home" class="active"> <i class="fas fa-home"></i>Home</a>
                <a href="statistics.php" id="statistics"> <i class="fas fa-chart-line"></i> Statistics</a>
                <a href="products.php" id="products"><i class="fas fa-boxes"></i> Manage Products </a>
                <a href="feedback.php" id="feedback"><i class="fas fa-comments"></i> Feedback</a>
                <a href="accounts.php" id="accounts"><i class="fas fa-user"></i> Accounts </a>
                <a href="about.php"><i class="fas fa-info-circle"></i> About</a>
            </div>
        </div>
    </div>

    <div class="home-container">
        <nav class="navbar">
            <div class="container-fluid">
                <a class="navbar-brand fw-bold fs-3 ms-2 font-effect-shadow-multiple">HOME</a>
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

                <form id="changePasswordForm" method="post" action="index.php" class="text-center" style="display: none;">
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

        <div class="modal fade" id="statusModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-body">
                        <h5 class="text-center" id="statusMessage"></h5>
                    </div>
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

        <div class="dashboard-container" style="margin: 0 auto;">
            <div class="time-display" id="timeDisplay"></div>
            <div class="header-container">
                <h5 class="calendar-header text-primary ms-5 fw-bold" id="calendarHeader">Calendar</h5>
                <div class="controls me-2">
                    <select id="monthSelect"></select>
                    <select id="yearSelect"></select>
                </div>
            </div>
            <div class="calendar" id="calendar" style="margin: 0 auto;">
                <div class="header">Sun</div>
                <div class="header">Mon</div>
                <div class="header">Tue</div>
                <div class="header">Wed</div>
                <div class="header">Thu</div>
                <div class="header">Fri</div>
                <div class="header">Sat</div>
            </div>
        </div>

        <?php include_once '../../includes/goToTop.php'; ?>
        <!-- <script src="../../js/cashier_script.js"> </script> -->
        <script>
            const monthNames = [
                "January", "February", "March", "April", "May", "June",
                "July", "August", "September", "October", "November", "December"
            ];

            const monthSelect = document.getElementById('monthSelect');
            const yearSelect = document.getElementById('yearSelect');
            const calendar = document.getElementById('calendar');
            const calendarHeader = document.getElementById('calendarHeader');
            const timeDisplay = document.getElementById('timeDisplay');

            function populateMonthSelect() {
                monthNames.forEach((month, index) => {
                    const option = document.createElement('option');
                    option.value = index;
                    option.textContent = month;
                    monthSelect.appendChild(option);
                });
            }

            function populateYearSelect() {
                const currentYear = new Date().getFullYear();
                for (let year = currentYear - 50; year <= currentYear + 50; year++) {
                    const option = document.createElement('option');
                    option.value = year;
                    option.textContent = year;
                    yearSelect.appendChild(option);
                }
            }

            function updateCalendar(month, year) {
                calendarHeader.textContent = `${monthNames[month]} ${year}`;

                const dayDivs = calendar.querySelectorAll('.day');
                dayDivs.forEach(day => day.remove());

                const firstDay = new Date(year, month, 1).getDay();
                const daysInMonth = new Date(year, month + 1, 0).getDate();
                const today = new Date();
                const isCurrentMonth = today.getMonth() === month && today.getFullYear() === year;

                for (let i = 0; i < firstDay; i++) {
                    const emptyDiv = document.createElement('div');
                    emptyDiv.classList.add('day');
                    calendar.appendChild(emptyDiv);
                }

                for (let day = 1; day <= daysInMonth; day++) {
                    const dayDiv = document.createElement('div');
                    dayDiv.classList.add('day');
                    if (isCurrentMonth && day === today.getDate()) {
                        dayDiv.classList.add('today');
                    }
                    dayDiv.textContent = day;
                    calendar.appendChild(dayDiv);
                }
            }

            function updateTime() {
                const now = new Date();
                let hours = now.getHours();
                const minutes = now.getMinutes().toString().padStart(2, '0');
                const seconds = now.getSeconds().toString().padStart(2, '0');
                const ampm = hours >= 12 ? 'PM' : 'AM';
                hours = hours % 12;
                hours = hours ? hours : 12;
                const timeString = `${hours.toString().padStart(2, '0')}:${minutes}:${seconds} ${ampm}`;
                timeDisplay.textContent = timeString;
            }

            populateMonthSelect();
            populateYearSelect();

            const currentDate = new Date();
            monthSelect.value = currentDate.getMonth();
            yearSelect.value = currentDate.getFullYear();

            monthSelect.addEventListener('change', () => {
                updateCalendar(parseInt(monthSelect.value), parseInt(yearSelect.value));
            });

            yearSelect.addEventListener('change', () => {
                updateCalendar(parseInt(monthSelect.value), parseInt(yearSelect.value));
            });

            updateCalendar(currentDate.getMonth(), currentDate.getFullYear());
            updateTime();
            setInterval(updateTime, 1000);
        </script>
    </div>


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

                fetch("index.php", {
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
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($_SESSION['update_error'])): ?>
                $('#statusMessage').html('<i class="fas fa-warning text-danger fw-bold"></i>Error Updating Account information');
                $('#statusModal').modal('show');
                setTimeout(function() {
                    $('#statusModal').modal('hide');
                    <?php unset($_SESSION['update_error']); ?>
                }, 3000);
            <?php elseif (isset($_SESSION['update_success'])): ?>
                $('#statusMessage').html('<i class="fas fa-check text-success fw-bold"></i> Your account information has been updated!');
                $('#statusModal').modal('show');
                setTimeout(function() {
                    $('#statusModal').modal('hide');
                    window.location.href = 'index.php';
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