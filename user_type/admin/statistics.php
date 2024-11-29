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
                header('Location: statistics.php');
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

$currentDate = new DateTime();
$currentDate->setISODate($currentDate->format("o"), $currentDate->format("W"));
$weekStart = $currentDate->format("Y-m-d 00:00:00");
$weekEnd = $currentDate->modify('+6 days')->format("Y-m-d 23:59:59");

$weekStartFormatted = (new DateTime($weekStart))->format('M j');
$weekEndFormatted = (new DateTime($weekEnd))->format('M j');
$dateRange = "($weekStartFormatted - $weekEndFormatted)";

// Query for Chart 1
$sql = "SELECT DATE(order_date) as order_date, SUM(price) as total_price 
        FROM checkout 
        WHERE order_status = 'paid' AND order_date BETWEEN :weekStart AND :weekEnd 
        GROUP BY DATE(order_date)";

$stmt = $conn->prepare($sql);
$stmt->execute([':weekStart' => $weekStart, ':weekEnd' => $weekEnd]);
$salesData = $stmt->fetchAll(PDO::FETCH_ASSOC);

$weeklySales = [];
foreach ($salesData as $row) {
    $date = $row['order_date'];
    $weeklySales[$date] = $row['total_price'];
}

$dataPoints = [];
for ($i = 0; $i < 7; $i++) {
    $date = (new DateTime($weekStart))->modify("+$i days")->format('Y-m-d');
    $day = (new DateTime($date))->format('D');
    $dataPoints[] = ["label" => $day, "y" => isset($weeklySales[$date]) ? $weeklySales[$date] : 0];
}

// Query for Chart 2
$currentYear = date("Y");

$sql2 = "SELECT MONTH(order_date) as order_month, SUM(price) as total_price 
        FROM checkout 
        WHERE order_status = 'paid' AND YEAR(order_date) = :currentYear 
        GROUP BY MONTH(order_date)";

$stmt = $conn->prepare($sql2);
$stmt->execute([':currentYear' => $currentYear]);
$salesData2 = $stmt->fetchAll(PDO::FETCH_ASSOC);

$monthlySales = [];
foreach ($salesData2 as $row) {
    $month = $row['order_month'];
    $monthlySales[$month] = $row['total_price'];
}

$dataPoints2 = [];
$labels2 = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
for ($i = 1; $i <= 12; $i++) {
    $dataPoints2[] = ["label" => $labels2[$i - 1], "y" => isset($monthlySales[$i]) ? $monthlySales[$i] : 0];
}

$sql = "SELECT COUNT(*) as total_customers FROM account WHERE category = 'Customer'";
$stmt = $conn->prepare($sql);
$stmt->execute();
$result = $stmt->fetch();
$totalCustomers = $result['total_customers'];

// Query for Chart 3
$tables = ['checkout'];
$weekdays = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];
$dataPoints3 = [];

foreach ($weekdays as $day) {
    $dataPoints3[$day] = [
        "gcash_count" => 0,
        "maya_count" => 0,
        "cash_count" => 0,
        "credit_count" => 0,
        "gcash_sum" => 0,
        "maya_sum" => 0,
        "cash_sum" => 0,
        "credit_sum" => 0
    ];
}

$lastSunday = date("Y-m-d", strtotime("last Monday"));
$nextSaturday = date("Y-m-d", strtotime("next Sunday"));

foreach ($tables as $table) {
    $queryPayment = "
    SELECT 
        DAYNAME(order_date) AS weekday,
        COUNT(DISTINCT CASE WHEN payment_method = 'gcash' THEN order_number ELSE NULL END) AS gcash_count,
        SUM(CASE WHEN payment_method = 'gcash' THEN cash_amount ELSE 0 END) AS gcash_sum,
        COUNT(DISTINCT CASE WHEN payment_method = 'maya' THEN order_number ELSE NULL END) AS maya_count,
        SUM(CASE WHEN payment_method = 'maya' THEN cash_amount ELSE 0 END) AS maya_sum,
        COUNT(DISTINCT CASE WHEN payment_method = 'cash' THEN order_number ELSE NULL END) AS cash_count,
        SUM(CASE WHEN payment_method = 'cash' THEN cash_amount ELSE 0 END) AS cash_sum,
        COUNT(DISTINCT CASE WHEN payment_method = 'credit' THEN order_number ELSE NULL END) AS credit_count,
        SUM(CASE WHEN payment_method = 'credit' THEN cash_amount ELSE 0 END) AS credit_sum
    FROM (
        SELECT 
            order_number, 
            payment_method,
            DATE(order_date) AS order_date,
            MAX(cash_amount) AS cash_amount
        FROM $table
        WHERE DATE(order_date) BETWEEN '$lastSunday' AND '$nextSaturday'
        GROUP BY order_number, payment_method, DATE(order_date)
    ) AS grouped_orders
    GROUP BY DAYNAME(order_date)";

    $resultPayment = $conn->query($queryPayment);

    while ($dataPayment = $resultPayment->fetch(PDO::FETCH_ASSOC)) {
        $weekday = $dataPayment['weekday'];
        if (isset($dataPoints3[$weekday])) {
            // Add the counts for each payment method
            $dataPoints3[$weekday]['gcash_count'] += $dataPayment['gcash_count'];
            $dataPoints3[$weekday]['maya_count'] += $dataPayment['maya_count'];
            $dataPoints3[$weekday]['cash_count'] += $dataPayment['cash_count'];
            $dataPoints3[$weekday]['credit_count'] += $dataPayment['credit_count'];

            // Add the sums for each payment method
            $dataPoints3[$weekday]['gcash_sum'] += $dataPayment['gcash_sum'];
            $dataPoints3[$weekday]['maya_sum'] += $dataPayment['maya_sum'];
            $dataPoints3[$weekday]['cash_sum'] += $dataPayment['cash_sum'];
            $dataPoints3[$weekday]['credit_sum'] += $dataPayment['credit_sum'];
        }
    }
}

$dataPointsGcash = [];
$dataPointsMaya = [];
$dataPointsCash = [];
$dataPointsCredit = [];

foreach ($weekdays as $day) {
    $dataPointsGcash[] = [
        "label" => $day,
        "y" => $dataPoints3[$day]['gcash_sum'],
        "count" => $dataPoints3[$day]['gcash_count']
    ];
    $dataPointsMaya[] = [
        "label" => $day,
        "y" => $dataPoints3[$day]['maya_sum'],
        "count" => $dataPoints3[$day]['maya_count']
    ];
    $dataPointsCash[] = [
        "label" => $day,
        "y" => $dataPoints3[$day]['cash_sum'],
        "count" => $dataPoints3[$day]['cash_count']
    ];
    $dataPointsCredit[] = [
        "label" => $day,
        "y" => $dataPoints3[$day]['credit_sum'],
        "count" => $dataPoints3[$day]['credit_count']
    ];
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grab & Go | Statistics</title>
    <link rel="shortcut icon" href="../../img/logo.png" type="image/x-icon">
    <?php include_once '../../header.php' ?>
    <link rel="stylesheet" href="../../css/cashier_main.css">
    <style>
        .chart-container,
        #orderTableContainer {
            width: 96%;
            margin: 0 auto;
        }

        .dev-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
            max-width: 98%;
            margin: 10px auto;
        }

        .column {
            flex: 1 1 calc(50% - 20px);
            border: 2px solid #007bff;
            border-radius: 10px;
            box-sizing: border-box;
            padding: 20px;
            background-color: #fff;
            transition: background-color 0.3s ease, box-shadow 0.3s ease, transform 0.3s ease;
            text-align: center;
        }

        .column .chart {
            width: 100%;
            max-width: 100%;
            height: auto;
            margin: 0 auto;
        }

        .btn-active {
            background-color: #007bff;
            color: white;
        }

        .btn-outline-primary {
            border-radius: 0;
        }

        #orderModal .modal-content {
            border-radius: 8px;
            box-shadow: 0px 0px 15px rgba(0, 0, 0, 0.3);
            font-family: 'Tahoma', sans-serif;
        }

        #orderModal .modal-header {
            background-color: #007bff;
            padding: 15px;
            border-bottom: 1px solid #007bff;
        }

        #orderModal .modal-title {
            color: white;
            font-size: 1.5rem;
        }

        #orderModal .modal-body {
            font-size: 1rem;
            color: #333;
            line-height: 1.6;
        }

        h6 {
            font-size: 1.1rem;
        }

        #modalOrderItemsList li {
            margin-bottom: 8px;
        }

        .btn-close {
            color: white;
            opacity: 0.8;
        }

        .btn-close:hover {
            opacity: 1;
        }

        .modal-footer {
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }


        .dashboard-container p {
            font-weight: bold;
        }

        @media screen and (max-width: 576px) {
            .dev-container {
                flex-direction: column;
                gap: 20px;
                padding-bottom: 70px;
            }

            .column img {
                float: none;
                display: block;
                margin: 0 auto 20px;
            }
        }

        @media screen and (max-width: 1024px) {
            .column {
                flex: 1 1 calc(100% - 20px);
            }
        }

        @media screen and (max-width: 768px) {
            .chart-container {
                width: 100%;
            }

            .column {
                padding: 15px;
            }

            .chart {
                height: 250px;
            }
        }

        @media screen and (max-width: 480px) {
            .chart {
                height: 200px;
            }

            .column {
                padding: 10px;
            }
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
                <a href="statistics.php" class="active" id="statistics"> <i class="fas fa-chart-line"></i> Statistics</a>
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
                <a href="index.php" id="home"> <i class="fas fa-home"></i>Home</a>
                <a href="statistics.php" class="active" id="statistics"> <i class="fas fa-chart-line"></i> Statistics</a>
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
                <a class="navbar-brand fw-bold fs-3 ms-2 font-effect-shadow-multiple">STATISTICS</a>
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

                <form id="changePasswordForm" method="post" action="statistics.php" class="text-center" style="display: none;">
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

        <div class="modal fade" id="statusModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-body">
                        <h5 class="text-center" id="statusMessage"></h5>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="orderModal" tabindex="-1" aria-labelledby="orderModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="orderModalLabel">Order Details</h5>
                    </div>
                    <div class="modal-body">
                        <h6><strong>Customer Name:</strong> <span id="modalCustomerName"></span></h6>
                        <h6><strong>Order Number:</strong> <span id="modalOrderNumber"></span></h6>
                        <h6><strong>Order Items:</strong></h6>
                        <ul id="modalOrderItemsList" style="list-style-type: disc; padding-left: 25px;"></ul>
                        <h6><strong>Total Price:</strong> <span id="modalTotalPrice"></span></h6>
                        <h6><strong>Order Date:</strong> <span id="modalOrderDate"></span></h6>
                        <h6><strong>Payment Method:</strong> <span id="modalPaymentMethod"></span></h6>
                        <h6><strong>Cash Amount:</strong> <span id="modalCashAmount"></span></h6>
                    </div>
                    <div class="modal-footer">
                        <a href="statistics.php" class="btn btn-secondary">Close</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="order-container mt-2 mb-2">
            <div class="chart-container mb-4" style="margin: 0 auto;">
                <div class="dev-container">
                    <div class="column">
                        <div id="weeklySalesChart" class="chart" style="height: 300px;"></div>
                    </div>
                    <div class="column">
                        <div id="monthlySalesChart" class="chart" style="height: 300px;"></div>
                    </div>
                </div>
                <div class="dev-container">
                    <div class="column">
                        <div id="chartContainer" class="chart" style="height: 300px;"></div>
                    </div>
                </div>
            </div>

        </div>
        <div class="row row-cols-1 row-cols-md-6 ms-4 btn-category p-1" style="max-width: 96%; margin: 0 auto;">
            <button id="dailySales" class="btn btn-outline-primary btn-active" data-payment-method="daily">Daily Sales</button>
            <button id="offlineSales" class="btn btn-outline-primary" data-payment-method="offline">Walk In Sales</button>
            <button id="cashOnPickup" class="btn btn-outline-primary" data-payment-method="cash">Cash on Pickup</button>
            <button id="gcash" class="btn btn-outline-primary" data-payment-method="gcash">Gcash</button>
            <button id="payMaya" class="btn btn-outline-primary" data-payment-method="maya">PayMaya</button>
            <button id="credit" class="btn btn-outline-primary" data-payment-method="credit">Credit</button>
        </div>

        <div id="orderTableContainer">
            <table class="table table-hover mt-2" id="orderDetailsTable" style="border: 2px solid #aaa; border-right: none; border-left: none; width: 96%; margin: 0 auto; font-family: Arial, sans-serif;">
                <thead>
                    <tr class="fw-bold">
                        <th>Customer's Name</th>
                        <th>Order Number</th>
                        <th>Total Price</th>
                        <th>Date Ordered</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="orderTableBody">

                </tbody>
            </table>
        </div><br><br>
    </div>

    <?php include_once '../../includes/goToTop.php'; ?>
    <script src="../../js/cashier_script.js"> </script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const buttons = document.querySelectorAll(".btn-category button");

            buttons.forEach(button => {
                button.addEventListener("click", function() {
                    buttons.forEach(btn => btn.classList.remove("btn-active"));
                    this.classList.add("btn-active");

                    const paymentMethod = this.getAttribute("data-payment-method");
                    fetchOrders(paymentMethod);
                });
            });

            document.getElementById("orderTableBody").addEventListener("click", function(event) {
                if (event.target.classList.contains("viewOrder")) {
                    const orderNumber = event.target.getAttribute("data-order");
                    showOrderDetails(orderNumber);
                }
            });

            document.addEventListener("DOMContentLoaded", function() {
                const paymentMethod = 'daily';
                fetchOrders(paymentMethod);
            });

            function fetchOrders(paymentMethod) {
                const xhr = new XMLHttpRequest();
                xhr.open("POST", "fetch_orders.php", true);
                xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");

                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4 && xhr.status === 200) {
                        document.getElementById("orderTableBody").innerHTML = xhr.responseText;
                    }
                };
                xhr.send("payment_method=" + paymentMethod);
            }


            function showOrderDetails(orderNumber) {
                const xhr = new XMLHttpRequest();
                xhr.open("POST", "fetch_order_details.php", true);
                xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");

                xhr.onreadystatechange = function() {
                    if (xhr.readyState === 4 && xhr.status === 200) {
                        const orderDetails = JSON.parse(xhr.responseText);

                        document.getElementById("modalCustomerName").innerText = orderDetails.customer_name || 'N/A';
                        document.getElementById("modalOrderNumber").innerText = orderDetails.order_number || 'N/A';
                        document.getElementById("modalOrderDate").innerText = orderDetails.order_date || 'N/A';
                        document.getElementById("modalPaymentMethod").innerText =
                            (orderDetails.payment_method ?
                                orderDetails.payment_method.charAt(0).toUpperCase() + orderDetails.payment_method.slice(1) :
                                'N/A');
                        document.getElementById("modalCashAmount").innerText = orderDetails.cash_amount ? `₱${orderDetails.cash_amount}` : 'N/A';
                        document.getElementById("modalTotalPrice").innerText = `₱${orderDetails.total_price.toFixed(2)}`;
                        const orderItemsList = document.getElementById("modalOrderItemsList");
                        orderItemsList.innerHTML = '';

                        orderDetails.items.forEach(item => {
                            const li = document.createElement('li');
                            li.innerText = `×${item.quantity} ${item.order_name} - ₱${item.price} `;
                            orderItemsList.appendChild(li);
                        });

                        const modal = new bootstrap.Modal(document.getElementById("orderModal"));
                        modal.show();
                    }
                };
                xhr.send("order_number=" + orderNumber);
            }

        });
    </script>

    <script>
        const paymentMethod = 'daily';
        fetchOrders(paymentMethod);

        function fetchOrders(paymentMethod) {
            const xhr = new XMLHttpRequest();
            xhr.open("POST", "fetch_orders.php", true);
            xhr.setRequestHeader("Content-type", "application/x-www-form-urlencoded");

            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    document.getElementById("orderTableBody").innerHTML = xhr.responseText;
                }
            };
            xhr.send("payment_method=" + paymentMethod);
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

                fetch("statistics.php", {
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


    <script src="https://cdn.canvasjs.com/canvasjs.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        window.onload = function() {

            var chart = new CanvasJS.Chart("weeklySalesChart", {
                animationEnabled: true,
                title: {
                    text: "DAILY SALES <?php echo $dateRange; ?>"
                },
                axisY: {
                    prefix: "₱",
                    labelFontSize: 12,
                    title: "",
                    includeZero: true,
                },
                toolTip: {
                    enabled: true
                },
                data: [{
                    type: "area",
                    indexLabel: "₱{y}",
                    dataPoints: <?php echo json_encode($dataPoints, JSON_NUMERIC_CHECK); ?>
                }]
            });
            chart.render();

            var chart2 = new CanvasJS.Chart("monthlySalesChart", {
                animationEnabled: true,
                title: {
                    text: "Monthly Sales <?php echo $currentYear; ?>"
                },

                axisY: {
                    prefix: "₱",
                    labelFontSize: 12,
                    title: "",
                    includeZero: true,
                },
                toolTip: {
                    enabled: true
                },
                data: [{
                    type: "bar",
                    indexLabel: "₱{y}",
                    indexLabelFontSize: 14,
                    indexLabelFontColor: "black",
                    indexLabelPlacement: "outside",

                    dataPoints: <?php echo json_encode($dataPoints2, JSON_NUMERIC_CHECK); ?>
                }]
            });
            chart2.render();

            var chart3 = new CanvasJS.Chart("chartContainer", {
                animationEnabled: true,
                title: {
                    text: "Weekly Sales by Payment Method"
                },
                toolTip: {
                    shared: true,
                    contentFormatter: function(e) {
                        let content = e.entries[0].dataPoint.label + "<br/>";
                        e.entries.forEach(function(entry) {
                            content += `${entry.dataSeries.name}: Revenue - ₱${entry.dataPoint.y} | Total - ${entry.dataPoint.count}<br/>`;
                        });
                        return content;
                    }
                },
                legend: {
                    fontSize: 12,
                    cursor: "pointer",
                    itemclick: function(e) {
                        if (typeof(e.dataSeries.visible) === "undefined" || e.dataSeries.visible) {
                            e.dataSeries.visible = false;
                        } else {
                            e.dataSeries.visible = true;
                        }
                        chart3.render();
                    }
                },
                axisX: {
                    labelFontSize: 12,
                    labelFontWeight: 700,
                    interval: 1,
                },
                axisY: {
                    // title: "Revenue",
                    prefix: "₱",
                    labelFontSize: 12,
                },
                data: [{
                        type: "stackedColumn",
                        name: "GCash",
                        showInLegend: true,
                        dataPoints: <?php echo json_encode($dataPointsGcash, JSON_NUMERIC_CHECK); ?>
                    },

                    {
                        type: "stackedColumn",
                        name: "Cash",
                        showInLegend: true,
                        dataPoints: <?php echo json_encode($dataPointsCash, JSON_NUMERIC_CHECK); ?>
                    },
                    {
                        type: "stackedColumn",
                        name: "PayMaya",
                        showInLegend: true,
                        dataPoints: <?php echo json_encode($dataPointsMaya, JSON_NUMERIC_CHECK); ?>
                    },
                    {
                        type: "stackedColumn",
                        name: "Credit",
                        showInLegend: true,
                        dataPoints: <?php echo json_encode($dataPointsCredit, JSON_NUMERIC_CHECK); ?>
                    }
                ]
            });

            chart3.render();

        }
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
                    window.location.href = 'statistics.php';
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