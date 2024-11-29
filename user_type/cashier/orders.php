<?php
include '../../db/conn.php';
session_start();
ob_start();

$totalItems = 0;
$totalPrice = 0;
$cash_amount = 0;

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
                header('Location: orders.php');
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

if (!isset($_GET['order_type'])) {
    header("Location: orders.php?order_type=history");
    exit;
}

try {
    $sql = "SELECT COUNT(DISTINCT order_number) as order_count FROM checkout WHERE user_type = 'Customer' AND status = 'Completed' AND order_date >= CURDATE() AND order_date < CURDATE() + INTERVAL 1 DAY";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $orderCount = $stmt->fetchColumn();
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grab & Go | Orders</title>
    <link rel="shortcut icon" href="../../img/logo.png" type="image/x-icon">
    <?php include_once '../../header.php' ?>
    <link rel="stylesheet" href="../../css/cashier_main.css">
    <style>
        .btn-category {
            margin-top: 10px;
            max-width: 700px;
            display: flex;
            justify-content: space-between;
            font-family: Arial, sans-serif;
        }

        .btn-category button {
            width: 33%;
            text-decoration: none;
            font-size: 14px;
            text-align: center;
            font-weight: bold;
            color: #000;
        }

        .btn-category button.active,
        .btn-category button.focus,
        .btn-category button.active {
            background-color: #007bff;
            color: white;
        }

        #search_order {
            border-radius: 20px;
            border: 1px solid #007bff;
            text-align: center;
        }

        #search_order:active,
        #search_order:focus,
        #search_order:active {
            outline: none;
        }

        .order-table-container {
            max-width: 700px;
            margin-top: 20px;
            border: 2px solid #aaa;
            padding: 10px;
            border-radius: 5px;
            background-color: #f9f9f9;
        }

        .order-table {
            width: 100%;
            border-collapse: collapse;
        }

        .order-table th,
        .order-table td {
            border: 1px solid #ddd;
            padding: 8px;
        }

        .order-table th {
            background-color: #f2f2f2;
            text-align: left;
        }

        .invoice-layout {
            display: none;
        }

        @media print {
            @page {
                size: A5;
                margin: 10mm;
            }

            html,
            body {
                margin: 0;
                padding: 0;
                font-family: Arial, sans-serif;
                width: 100%;
                height: 100%;
                display: flex;
                justify-content: center;
                align-items: center;
                background-color: #fff;
            }

            .sidebar,
            .navbar,
            .btn-category,
            .input-container,
            .content,
            .btn-payment {
                display: none;
            }

            .invoice-layout {
                display: block;
                max-width: 100%;
                text-align: center;
            }

            .order-container {

                position: absolute;
                top: 0;
                right: 30px;
                left: 50%;
                transform: translate(-50%, 0);
                width: 340px;
                padding: 10px;
                box-shadow: none;
                border: none;
                margin: 0 auto;
                margin-top: -100px;
            }

            .invoice-header {
                text-align: center;
                margin-bottom: 10px;
            }

            .invoice-header img {
                max-width: 80px;
                display: block;
                margin: 0 auto;
            }

            .table {
                width: 100%;
                border-collapse: collapse;
                margin: 0 auto;
            }

            .table th,
            .table td {
                border: 1px solid #aaa;
                padding: 2px;
                text-align: left;
            }

            .table th {
                background-color: #f2f2f2;
            }

            .footer {
                margin-top: 20px;
                text-align: center;
                font-size: 12px;
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
                <a href="index.php" id="home"><i class="fas fa-home"></i> Home</a>
                <a href="process.php" id="my-cart"><i class="fas fa-cart-arrow-down"></i> To Process</a>
                <a href="orders.php" class="active" id="my-cart"><i class="fas fa-shopping-basket"></i> Orders</a>
                <a href="disapproved.php" id="disapproved"><i class="fas fa-thumbs-down"></i> Disapproved Order</a>
                <a href="inventory.php" id="inventory"><i class="fas fa-sitemap"></i> Inventory </a>
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
                <a href="index.php" id="home"><i class="fas fa-home"></i> Home</a>
                <a href="process.php" id="my-cart"><i class="fas fa-cart-arrow-down"></i> To Process</a>
                <a href="orders.php" class="active" id="my-cart"><i class="fas fa-shopping-basket"></i> Orders</a>
                <a href="disapproved.php" id="disapproved"><i class="fas fa-thumbs-down"></i> Disapproved Order</a>
                <a href="inventory.php" id="inventory"><i class="fas fa-sitemap"></i> Inventory </a>
                <a href="about.php"><i class="fas fa-info-circle"></i> About</a>
            </div>
        </div>
    </div>

    <div class="home-container">
        <nav class="navbar">
            <div class="container-fluid">
                <a class="navbar-brand fw-bold fs-3 ms-2 font-effect-shadow-multiple">ORDERS</a>
                <div class="position-relative me-3 mt-2 ms-auto">
                    <a href="orders.php"> <i class="fas fa-bell" style="font-size: 1.7rem; color: #007bff;" title="Notification | Online Order" data-toggle="tooltip"></i></a>

                    <?php if ($orderCount > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            <?php echo $orderCount; ?>
                        </span>
                    <?php endif; ?>
                </div>
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

                <form id="changePasswordForm" method="post" action="orders.php" class="text-center" style="display: none;">
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


        <div class="order-container" style="font-family: Arial, sans-serif;">

            <form class="row row-cols-1 row-cols-md-6 ms-4 btn-category p-1" method="GET" action="orders.php" style="max-width: 700px;">
                <form class="row row-cols-1 row-cols-md-6 ms-4 btn-category p-1" method="GET" action="orders.php" style="max-width: 700px;">
                    <button type="submit" name="order_type" value="history" class="btn btn-outline-primary <?php echo isset($_GET['order_type']) && $_GET['order_type'] == 'history' ? 'active' : ''; ?>">Order History</button>
                    <button type="submit" name="order_type" value="new" class="btn btn-outline-primary <?php echo isset($_GET['order_type']) && $_GET['order_type'] == 'new' ? 'active' : ''; ?>">Online Order</button>
                    <button type="submit" name="order_type" value="offline" class="btn btn-outline-primary <?php echo isset($_GET['order_type']) && $_GET['order_type'] == 'offline' ? 'active' : ''; ?>">Walk In</button>
                </form>

            </form>

            <div class="row row-cols-1 row-cols-md-6 ms-4 content" style="max-width: 700px;">
                <table class="table table-hover order-table" style="border: 2px solid #aaa; border-right: none; border-left: none; max-height: 500px;">
                    <p class="text-start fw-bold mt-2 w-100 fs-4" id="title">
                        <?php
                        if (isset($_GET['order_type'])) {
                            switch ($_GET['order_type']) {
                                case 'history':
                                    echo 'Order History';
                                    break;
                                case 'new':
                                    echo 'Online Order';
                                    break;
                                case 'offline':
                                    echo 'Walk In';
                                    break;
                                default:
                                    echo 'Order History';
                            }
                        } else {
                            echo 'Order History';
                        }
                        ?>
                    </p>
                    <form>
                        <div class="input-container">
                            <input type="search" class="w-100 p-1" name="search_order" id="search_order" placeholder="Search Customer or Order Number" oninput="searchOrder()">
                        </div>
                    </form>
                    <tbody id="orderData">
                        <tr class="fw-bold">
                            <td>
                                <?php
                                if (isset($_GET['order_type'])) {
                                    switch ($_GET['order_type']) {
                                        case "history":
                                        case "new":
                                            echo "Customer";
                                            break;
                                        case "offline":
                                            echo "Cashier";
                                            break;
                                        default:
                                            echo "Customer";
                                    }
                                } else {
                                    echo 'Customer';
                                }
                                ?>
                            </td>
                            <td>Order #</td>
                            <td>Date Ordered</td>
                            <?php
                            if (isset($_GET['order_type']) && ($_GET['order_type'] == 'history' || $_GET['order_type'] == 'new')) {
                                echo '<td>Date Approved</td>';
                            }
                            ?>
                            <td>Action</td>
                        </tr>

                        <?php
                        if (isset($_SESSION['fullname'])) {
                            $customer_name = htmlspecialchars($_SESSION['fullname']);
                        } else {
                            echo "<tr><td colspan='4'>Session data missing.</td></tr>";
                            return;
                        }
                        if (isset($_GET['order_type'])) {
                            switch ($_GET['order_type']) {
                                case 'history':
                                    $sql = "SELECT * FROM checkout WHERE status = 'Completed' AND order_date < CURDATE() GROUP BY order_number ORDER BY order_date DESC";
                                    break;
                                case 'new':
                                    $sql = "SELECT * FROM checkout WHERE user_type = 'Customer' AND status = 'Completed' AND appr_date >= CURDATE() AND appr_date < CURDATE() + INTERVAL 1 DAY GROUP BY order_number ORDER BY appr_date DESC";
                                    break;
                                case 'offline':
                                    $sql = "SELECT * FROM checkout WHERE user_type = 'Cashier' AND status = 'Completed' AND order_date >= CURDATE() AND order_date < CURDATE() + INTERVAL 1 DAY GROUP BY order_number ORDER BY order_date DESC";
                                    break;
                                default:
                                    $sql = "SELECT * FROM checkout WHERE status = 'Completed' AND order_date < CURDATE() GROUP BY order_number ORDER BY order_date DESC";
                                    break;
                            }
                        } else {
                            $sql = "SELECT * FROM checkout WHERE status = 'Completed' AND order_date < CURDATE() GROUP BY order_number ORDER BY order_date DESC";
                        }

                        $stmt = $conn->query($sql);

                        if ($stmt) {
                            $displayedOrderNumbers = [];
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $profile = $row["customer_profile"];
                                $image_path = "../../img/" . $profile;

                                if (empty($profile) || !file_exists($image_path)) {
                                    $image_path = "../../img/profile.png";
                                }
                        ?>
                                <tr class="order-row" style="cursor: pointer;">
                                    <td class="text-start p-2">
                                        <img src="<?php echo htmlspecialchars($image_path); ?>" alt="Profile Image" style="width: 35px; height: 35px; border-radius: 50%;">
                                        <span class="customer-name"><?php echo htmlspecialchars($row['customer_name']); ?></span>
                                    </td>
                                    <td class="text-start p-2">
                                        <span class="order-number"><?php echo htmlspecialchars($row['order_number']); ?></span>
                                    </td>
                                    <td class="text-start p-2 ">
                                        <span class="order-date text-primary"><?php echo htmlspecialchars($row['order_date']); ?></span>
                                    </td>
                                    <?php
                                    if (isset($_GET['order_type']) && ($_GET['order_type'] == 'history' || $_GET['order_type'] == 'new')) {
                                        echo '<td class="text-start p-2"><span class="text-muted">' . htmlspecialchars($row['appr_date']) . '</span></td>';
                                    }
                                    ?>
                                    <td class="text-start p-2">
                                        <form action="orders.php?status=<?php echo htmlspecialchars($row['order_status']); ?>&order_type=<?php echo isset($_GET['order_type']) ? htmlspecialchars($_GET['order_type']) : 'history'; ?>" method="post">
                                            <input type="hidden" name="order_number" value="<?php echo htmlspecialchars($row['order_number']); ?>">
                                            <input type="hidden" name="order_type" value="<?php echo isset($_GET['order_type']) ? htmlspecialchars($_GET['order_type']) : 'history'; ?>">
                                            <button type="submit" class="btn btn-primary btn-sm">View</button>
                                        </form>
                                    </td>
                                </tr>

                        <?php

                            }
                        } else {
                            echo "<tr><td colspan='4'>No orders found.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div><br><br>

            <div class="category-container" style="float: right;">
                <div class="order-container" style="margin: 0 auto; overflow-y: auto; position: absolute; top: 80px; right: 30px; width: 340px;">
                    <div class="invoice-header text-center" id="invoiceHeader" style="display: none;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <img src="../../img/omsc.png" alt="Logo" style="max-width: 90px; height: auto;" />
                            <img src="../../img/logo.png" alt="Logo" style="max-width: 100px; height: auto;" />
                        </div>
                        <h2 class="fw-bold">Grab and Go</h2>
                        <p style="font-size: 14px;">Occidental Mindoro State College <br> Multi-Purpose Cooperative (OMSC MPC)</p>
                        <p id="invoiceDate" style="font-size: 14px;">
                            Date: <span><?php date_default_timezone_set('Asia/Manila');
                                        echo date('Y-m-d h:i A'); ?></span>
                        </p>

                    </div>
                    <table class="table table-hover" id="orderDetailsTable" style="border: 2px solid #aaa; border-right: none; border-left: none;">
                        <tbody>
                            <?php
                            if (isset($_POST['order_number'])) {
                                $order_number = htmlspecialchars($_POST['order_number']);

                                $sql = "SELECT * FROM checkout WHERE order_number = '$order_number'";
                                $stmt = $conn->query($sql);

                                if ($stmt) {
                                    $totalPrice = 0;
                                    $displayedOrderNumbers = [];
                                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        $totalPrice += $row['price'];
                                        $order_number = $row['order_number'];
                                        $cash_amount = $row['cash_amount'];

                                        if (!in_array($order_number, $displayedOrderNumbers)) {
                                            echo '<p class="text-start fw-bold mt-2">Order ID #<span class="text-primary">' . htmlspecialchars($order_number) . '</span></p>';
                                            echo '<p class="text-start fs-6" style="margin-top: -20px;">' . htmlspecialchars($customer_name) . '</p>';
                                            $displayedOrderNumbers[] = $order_number;
                                        }
                            ?>
                                        <tr class="accordion-toggle m-0" style="cursor: pointer;">
                                            <td class="text-start p-2 fw-bold" style="font-size: 15px;">
                                                <span class="quantity-display">x<?php echo htmlspecialchars($row['quantity']); ?></span>&nbsp;
                                                <span><?php echo htmlspecialchars($row['product_name']); ?></span>
                                            </td>
                                            <td class="text-end p-2" style="font-size: 15px;">
                                                <span class="price-display text-primary fw-bold">&#8369;<?php echo number_format($row['price'], 2); ?></span>
                                            </td>
                                        </tr>
                            <?php
                                    }
                                }
                            }
                            ?>
                        </tbody>
                    </table>
                    <div class="total-container">
                        <div class="fw-bold ms-3" style="float: left;">
                            Total
                        </div>
                        <div class="fw-bold me-3" style="float: right;" id="totalPrice">
                            &#8369;<?php echo number_format($totalPrice, 2); ?>
                        </div>
                    </div><br>
                    <div class="btn-container">
                        <div class="cash">
                            <div class="fw-bold ms-3" style="float: left;">
                                Cash
                            </div>
                            <div class="fw-bold me-3" style="float: right;">
                                <span id="display-amount"> &#8369;<?php echo number_format($cash_amount, 2); ?></span>
                            </div>
                        </div><br>
                        <div class="balance">
                            <div class="fw-bold ms-3" style="float: left; font-size: 14px;">
                                Balance
                            </div>
                            <div class="fw-bold me-3" style="float: right; font-size: 14px;">
                                <span id="display-balance">&#8369;0</span>
                            </div>
                        </div>
                    </div><br>
                    <div class="footer" style="display: none;">
                        <p style="font-size: 14px;">Thank you for shopping with us!</p>
                        <p style="font-size: 14px;">Visit us again!</p>
                        <p style="font-size: 12px;">&copy; <?php echo date("Y"); ?> Grab and Go</p>
                    </div>
                    <div class="btn-payment mt-2 w-100 mb-5">
                        <button type="button" id="printInvoiceButton" class="btn btn-success w-100 d-block text-light p-2 fw-bold">Print Invoice</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include_once '../../includes/goToTop.php'; ?>
    <script src="../../js/cashier_script.js"> </script>
    <script>
        document.getElementById('printInvoiceButton').addEventListener('click', function() {
            document.getElementById('invoiceHeader').style.display = 'block';
            document.querySelector('.footer').style.display = 'block';

            window.print();

            document.getElementById('invoiceHeader').style.display = 'none';
            document.querySelector('.footer').style.display = 'none';
        });
    </script>

    <script>
        function formatCurrency(amount) {
            return new Intl.NumberFormat('en-PH', {
                style: 'currency',
                currency: 'PHP'
            }).format(amount);
        }

        const totalPriceElement = document.getElementById('totalPrice');
        const displayAmountElement = document.getElementById('display-amount');
        const displayBalanceElement = document.getElementById('display-balance');

        const totalPrice = parseFloat(totalPriceElement.innerHTML.replace(/[^\d.-]/g, ''));
        const cashAmount = parseFloat(displayAmountElement.innerHTML.replace(/[^\d.-]/g, ''));

        const balance = cashAmount - totalPrice;

        displayBalanceElement.innerHTML = formatCurrency(balance);
        document.getElementById('printInvoice').addEventListener('click', function() {
            window.print();
        });

        function searchOrder() {
            var input = document.getElementById("search_order").value.toLowerCase();
            var rows = document.querySelectorAll(".order-row");

            rows.forEach(function(row) {
                var customerName = row.querySelector(".customer-name").textContent.toLowerCase();
                var orderNumber = row.querySelector(".order-number").textContent.toLowerCase();

                if (customerName.includes(input) || orderNumber.includes(input)) {
                    row.style.display = "table-row";
                } else {
                    row.style.display = "none";
                }
            });
        }
    </script>
    <script>
        document.getElementById("profileImage").addEventListener("click", function() {
            document.getElementById("profileInput").click();
        });

        document.getElementById("profileInput").addEventListener("change", function() {
            const file = this.files[0];
            if (file) {
                const formData = new FormData();
                formData.append("profileImage", file);

                fetch("orders.php", {
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
    </script>
    <script>
        function searchOrder() {
            var input = document.getElementById("search_order").value.toLowerCase();
            var rows = document.querySelectorAll(".order-row");

            rows.forEach(function(row) {
                var customerName = row.querySelector(".customer-name").textContent.toLowerCase();
                var orderNumber = row.querySelector(".order-number").textContent.toLowerCase();

                if (customerName.includes(input) || orderNumber.includes(input)) {
                    row.style.display = "table-row";
                } else {
                    row.style.display = "none";
                }
            });
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
                    window.location.href = 'orders.php';
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