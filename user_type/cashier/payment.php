<?php
include '../../db/conn.php';
session_start();
ob_start();

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
                header('Location: payment.php');
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
    <title>Grab & Go | Home</title>
    <link rel="shortcut icon" href="../../img/logo.png" type="image/x-icon">
    <?php include_once '../../header.php' ?>
    <link rel="stylesheet" href="../../css/cashier_main.css">
    <style>
        .right-container {
            width: 300px;
            margin: 20px auto;
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            font-family: Verdana, Geneva, Tahoma, sans-serif;
        }

        .input-container {
            grid-column: span 3;
            margin-bottom: 10px;

        }

        .input-container input {
            width: 100%;
            padding: 8px;
            font-size: 25px;
            text-align: right;
            box-sizing: border-box;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-shadow: 0 2px 2px rgba(0, 0, 0, 0.1);
        }

        .button {
            padding: 15px;
            font-size: 20px;
            text-align: center;
            cursor: pointer;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #f9f9f9;
            box-shadow: 0 2px 2px rgba(0, 0, 0, 0.1);
            transition: background-color 0.3s;
        }

        .button:hover {
            background-color: #e0e0e0;
        }



        .payment-method {
            grid-column: span 3;
            display: flex;
            justify-content: space-between;
            text-align: center;
        }

        .cash,
        .balance,
        .total-container {
            font-family: Verdana, Geneva, Tahoma, sans-serif;
        }

        .btn-success {
            background: #2DAC4B;
        }

        #calculator {
            display: flex;
            flex-direction: column;
            width: 100%;
            max-width: 500px;
        }

        #display {
            width: 100%;
            padding: 10px;
            margin-bottom: 10px;
            font-size: 24px;
            text-align: right;
            border: 2px solid #c0c0c0;
        }

        #display {
            border: 2px solid #c0c0c0;
            outline: none;
        }

        .calculator-btn {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }

        .btn-calculator {
            padding: 15px;
            font-size: 18px;
            background-color: #e0e0e0;

            border: none;
            cursor: pointer;
            border-radius: 5px;
            transition: background-color 0.3s, transform 0.2s;
        }

        .btn-calculator:hover {
            background: linear-gradient(135deg, #f0f0f0, #c0c0c0);
            transform: scale(1.05);
        }

        .btn-calculator:active {
            outline: none;
            border: none;
        }

        .button-double {
            grid-column: span 2;
        }

        .button-cancel {
            background-color: #c0c0c0;
            color: #000;
        }

        .btn-calculator.highlight {
            background-color: #74baf8;
        }

        .btn-methods {
            width: 100%;
            margin: 0 auto;
        }

        .qr-title {
            color: #333;
            text-align: center;
        }

        .payment-img {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0 auto;
            margin-top: 10px;
        }

        .payment-img img {
            height: 450px;
            width: 450px;
        }

        .gcash-btn,
        .paymaya-btn {
            width: 220px;
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
                <a href="index.php" class="active" id="home"><i class="fas fa-home"></i> Home</a>
                <a href="process.php" id="my-cart"><i class="fas fa-cart-arrow-down"></i> To Process</a>
                <a href="orders.php" id="my-cart"><i class="fas fa-shopping-basket"></i> Orders</a>
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
                <a href="index.php" class="active" id="home"><i class="fas fa-home"></i> Home</a>
                <a href="process.php" id="my-cart"><i class="fas fa-cart-arrow-down"></i> To Process</a>
                <a href="orders.php" id="my-cart"><i class="fas fa-shopping-basket"></i> Orders</a>
                <a href="inventory.php" id="inventory"><i class="fas fa-sitemap"></i> Inventory </a>
                <a href="about.php"><i class="fas fa-info-circle"></i> About</a>
            </div>
        </div>
    </div>

    <div class="home-container">
        <nav class="navbar">
            <div class="container-fluid">
                <a class="navbar-brand fw-bold fs-3 ms-2 font-effect-shadow-multiple">HOME</a>
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

                <form id="changePasswordForm" method="post" action="payment.php" class="text-center" style="display: none;">
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

        <script>
            document.getElementById('changePasswordButton').addEventListener('click', function() {
                document.getElementById('changePasswordForm').style.display = 'block';
            });

            document.addEventListener('DOMContentLoaded', function() {
                var cancelButton = document.getElementById('cancelButton');

                cancelButton.addEventListener('click', function() {
                    document.getElementById('changePasswordForm').style.display = 'none';
                });
            });
        </script>


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

        <div class="order-container">
            <div class="left-container ms-5" style="width: 500px; float: left;">
                <a href="index.php" style="margin-left: -20px;">
                    <button class="btn fs-6 btn-lowercase btn-sm" id="backBtn">
                        <i class="fas fa-arrow-left"></i> back
                    </button>
                </a>
                <table class="table table-hover" style="border: 2px solid #aaa; border-right: none; border-left: none;">
                    <tbody>
                        <?php
                        $customer_name = htmlspecialchars($_SESSION['fullname']);
                        $sql = "SELECT * FROM orders WHERE order_status = 'cart' AND customer_name = '$customer_name'";
                        $stmt = $conn->query($sql);
                        $totalPrice = 0;
                        $displayedOrderNumbers = [];

                        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                            $totalPrice += $row['price'];
                            $pricePerUnit = $row['price'] / $row['quantity'];
                            $order_number = $row['order_number'];

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
                        ?>
                    </tbody>
                </table>
                <div class="total-container">
                    <div class="fw-bold ms-3" style="float: left;">
                        Total
                    </div>
                    <div class="fw-bold me-3" style="float: right;">
                        &#8369;<?php echo number_format($totalPrice, 2); ?>
                    </div>
                </div><br>

                <div class="btn-container" id="cash-display">
                    <div class="cash">
                        <div class="fw-bold ms-3" style="float: left;" id="payment-method-text">
                            Cash
                        </div>
                        <div class="fw-bold me-3" style="float: right;">
                            <span id="display-amount">&#8369;0</span>
                        </div>
                    </div><br>
                    <div class="balance">
                        <div class="fw-bold ms-3" style="float: left; font-size: 16px; color: #666;">
                            Balance
                        </div>
                        <div class="fw-bold me-3 " style="float: right; font-size: 16px; color: #666;">
                            <span id="display-balance">&#8369;0</span>
                        </div>
                    </div>
                </div><br>

                <!-- Payment Form -->
                <form action="" method="post" id="paymentForm">
                    <div class="payment-details" id="paymentDetails" style="display: none; font-family: Arial, sans-serif;">
                        <div class="form-group">
                            <div>
                                <label class="fw-bold" for="reference_num">Reference No.:</label>
                                <input type="text" id="reference_num" name="reference_num" class="form-control" style="border: 1px solid #333;" maxlength="15" placeholder="XXXX-XXX-XXXXXX" pattern="\d{4}-\d{3}-\d{6}" required oninput="formatAndRestrictNumber(this)">

                                <input type="hidden" id="method-display" name="payment_method" value="cash">
                                <input type="hidden" id="orderNumbers" name="orderNumbers" value='<?php echo json_encode($displayedOrderNumbers); ?>'>
                            </div>

                            <div class="mt-2">
                                <label class="fw-bold" for="amount">Cash Amount:</label>
                                <input type="text" id="paymentAmount" class="form-control" name="paymentAmount" placeholder="Enter amount you paid" oninput="formatCurrency(this)" style="border: 1px solid #333;">
                                <input type="hidden" name="total_amount" id="total_amount">
                            </div>
                        </div>
                    </div>

                    <script>
                        function formatCurrency(input) {
                            let value = input.value.replace(/[^0-9.]/g, '');
                            const amount = parseFloat(value);
                            if (!isNaN(amount)) {
                                input.value = '₱' + Math.floor(amount).toLocaleString('en-PH');
                                document.getElementById('total_amount').value = Math.floor(amount);
                            } else {
                                input.value = '';
                                document.getElementById('total_amount').value = '';
                            }
                        }

                        function formatAndRestrictNumber(input) {
                            let value = input.value.replace(/\D/g, '');

                            let formatted = '';
                            if (value.length > 0) formatted = value.substring(0, 4);
                            if (value.length > 4) formatted += '-' + value.substring(4, 7);
                            if (value.length > 7) formatted += '-' + value.substring(7, 13);

                            input.value = formatted;
                        }

                        function formatCurrency(input) {
                            let value = input.value.replace(/[^0-9.]/g, '');
                            const amount = parseFloat(value);
                            const totalPrice = <?php echo $totalPrice; ?>;

                            const confirmButton = document.getElementById('confirmPaymentBtn');

                            if (!isNaN(amount)) {
                                input.value = '₱' + Math.floor(amount).toLocaleString('en-PH');
                                document.getElementById('total_amount').value = Math.floor(amount);

                                if (amount < totalPrice) {
                                    confirmButton.disabled = true;
                                } else {
                                    confirmButton.disabled = false;
                                }
                            } else {
                                input.value = '';
                                document.getElementById('total_amount').value = '';
                                confirmButton.disabled = true;
                            }
                        }
                    </script>

                    <div class="btn-payment mt-2 w-100 mb-5">
                        <button type="button" id="confirmPaymentBtn" class="btn btn-success w-100 d-block text-light p-2 fw-bold" disabled>
                            Confirm Payment
                        </button>
                    </div>
                </form>
            </div>

        </div>

        <div class="right-container me-5" style="width: 480px; float: right;">
            <div class="payment-method w-100">
                <div class="btn float-start w-50 active-payment" id="cash" style="border: 2px solid #ffd700; border-right: none; border-left: none; border-top: none;">Cash</div>
                <div class="btn float-end w-50" id="other-methods">Other Methods</div>
            </div>
            <div class="input-container" id="calculator">
                <input type="text" id="display" placeholder="₱0" readonly>
                <input type="hidden" id="display-value">
                <div class="calculator-btn">
                    <button class="btn-calculator" value="1">1</button>
                    <button class="btn-calculator" value="2">2</button>
                    <button class="btn-calculator" value="3">3</button>
                    <button class="btn-calculator" value="4">4</button>
                    <button class="btn-calculator" value="5">5</button>
                    <button class="btn-calculator" value="6">6</button>
                    <button class="btn-calculator" value="7">7</button>
                    <button class="btn-calculator" value="8">8</button>
                    <button class="btn-calculator" value="9">9</button>
                    <button class="btn-calculator" value=".">.</button>
                    <button class="btn-calculator" value="0">0</button>
                    <button class="btn-calculator " value="00">00</button>
                    <button class="btn-calculator button-cancel button-double" value="Cancel">CA</button>
                    <button class="btn-calculator" id="eraser-btn"><i class="fas fa-eraser"></i></button>
                </div>
            </div>

            <div class="input-container" id="new-ui" style="display: none; display: flex; justify-content: space-around; align-items: center; margin: 0 auto;">
                <div class="btn-methods" id="btn-methods" style="display: none;">
                    <p class="mt-5 fw-bolder">Other Payment Methods</p>
                    <button class="button" id="gcash-btn"><img src="../../img/gcash-seeklogo.svg" width="200px" height="50px"></button>
                    <button class="button" id="paymaya-btn"><img src="../../img/PayMaya_Logo.png" width="200px" height="50px"></button>

                </div>
            </div>

            <!-- Gcash Panel -->
            <form action="" method="post">
                <div class="right-panel gcash" id="gcash" style="display: none;">
                    <h2>Gcash</h2>
                    <h4 class="qr-title">Scan the QR code to pay</h4>
                    <div class="payment-info payment-img">
                        <img src="../../img/payment/gcash.jpg" />
                    </div>
                </div>
            </form>

            <!-- PayMaya Panel -->
            <form action="" method="post">
                <div class="right-panel paymaya" id="paymaya" style="display: none;">
                    <h2>PayMaya</h2>
                    <h4 class="qr-title">Scan the QR code to pay</h4>
                    <div class="payment-info payment-img">
                        <img src="../../img/payment/paymaya.jpg" />
                    </div>
                </div>
            </form>

        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const display = document.getElementById('display');
                const displayAmount = document.getElementById('display-amount');
                const displayBalance = document.getElementById('display-balance');
                const displayValue = document.getElementById('display-value'); 
                const buttons = document.querySelectorAll('.btn-calculator');
                const confirmPaymentBtn = document.getElementById('confirmPaymentBtn');
                const eraserBtn = document.getElementById('eraser-btn');

                let totalPrice = <?php echo $totalPrice; ?>;
                let isPlaceholderActive = true;

                function formatNumber(num) {
                    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
                }

                function setPlaceholder() {
                    display.value = '₱0';
                    displayValue.value = ''; 
                    isPlaceholderActive = true;
                }

                const storedValue = sessionStorage.getItem('displayValue');
                if (storedValue) {
                    display.value = '₱' + formatNumber(storedValue);
                    displayValue.value = storedValue; 
                    isPlaceholderActive = false;
                } else {
                    setPlaceholder();
                }

                displayAmount.textContent = '₱0';
                displayBalance.textContent = '₱' + formatNumber(totalPrice);

                function checkConfirmPaymentBtn() {
                    const displayAmountValue = parseFloat(displayAmount.textContent.replace('₱', '').replace(/,/g, '') || 0);
                    const totalBalanceValue = parseFloat(displayBalance.textContent.replace('₱', '').replace(/,/g, '') || 0);

                    confirmPaymentBtn.disabled = displayAmountValue < totalPrice;
                }

                function updateDisplay(value) {
                    if (isPlaceholderActive) {
                        display.value = '₱';
                        isPlaceholderActive = false;
                    }

                    let currentValue = display.value.replace('₱', '').replace(/,/g, '');
                    currentValue += value;

                    display.value = '₱' + formatNumber(currentValue);
                    displayValue.value = currentValue; 
                    sessionStorage.setItem('displayValue', currentValue);

                    displayAmount.textContent = '₱' + formatNumber(currentValue);

                    let balance = parseFloat(currentValue || 0) - totalPrice;
                    displayBalance.textContent = '₱' + formatNumber(balance);

                    checkConfirmPaymentBtn();
                }

                function resetDisplay() {
                    setPlaceholder();
                    sessionStorage.removeItem('displayValue');
                    displayAmount.textContent = '₱0';
                    displayBalance.textContent = '₱' + formatNumber(totalPrice);
                    checkConfirmPaymentBtn();
                }

                function eraseDisplay() {
                    let currentValue = display.value.replace('₱', '').replace(/,/g, '');

                    if (currentValue.length === 1) {
                        resetDisplay();
                    } else {
                        currentValue = currentValue.slice(0, -1);
                        display.value = currentValue ? '₱' + formatNumber(currentValue) : '₱0';
                        displayValue.value = currentValue || ''; 
                        sessionStorage.setItem('displayValue', currentValue);

                        displayAmount.textContent = currentValue ? '₱' + formatNumber(currentValue) : '₱0';
                        let balance = totalPrice - parseFloat(currentValue || 0);
                        displayBalance.textContent = '₱' + formatNumber(balance);
                    }

                    checkConfirmPaymentBtn();
                }

                checkConfirmPaymentBtn();

                buttons.forEach(button => {
                    button.addEventListener('click', function() {
                        if (this.classList.contains('button-cancel')) {
                            resetDisplay();
                        } else {
                            updateDisplay(this.value);
                        }
                    });
                });

                eraserBtn.addEventListener('click', eraseDisplay);

                document.addEventListener('keydown', function(e) {
                    const key = e.key;
                    if (!isNaN(key) || key === '.' || key === '0' || key === '00') {
                        updateDisplay(key);

                        const button = document.querySelector(`button[value="${key}"]`);
                        if (button) {
                            button.classList.add('highlight');
                            setTimeout(() => {
                                button.classList.remove('highlight');
                            }, 200);
                        }
                    } else if (key === 'Backspace') {
                        eraseDisplay();

                        eraserBtn.classList.add('highlight');
                        setTimeout(() => {
                            eraserBtn.classList.remove('highlight');
                        }, 200);
                    }
                });


                confirmPaymentBtn.addEventListener('click', function() {
                    const cashElement = document.getElementById('cash');
                    const otherMethodsElement = document.getElementById('other-methods');
                    const onlinePayment = document.querySelector('.active-online-payment');

                    if (cashElement.classList.contains('active-payment')) {
                        const cashAmount = parseFloat(display.value.replace('₱', '').replace(/,/g, ''));

                        if (isNaN(cashAmount) || cashAmount < totalPrice) {
                            alert('Insufficient cash amount.');
                            return;
                        }

                        fetch('fetch_payment.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({
                                    orderNumbers: <?php echo json_encode($displayedOrderNumbers); ?>,
                                    cashAmount: cashAmount
                                })
                            })
                            .then(response => {
                                if (!response.ok) {
                                    throw new Error(`HTTP error! status: ${response.status}`);
                                }
                                return response.json();
                            })
                            .then(data => {
                                console.log('Server response:', data);
                                if (data.success) {
                                    resetDisplay();
                                    const statusModal = new bootstrap.Modal(document.getElementById('statusModal'));

                                    const statusMessage = document.getElementById('statusMessage');
                                    statusMessage.textContent = 'Payment successful!';
                                    statusMessage.classList.add('text-success');

                                    statusModal.show();

                                    setTimeout(() => {
                                        statusModal.hide();
                                        window.location.href = 'index.php?status=payment_success';
                                    }, 2000);
                                } else {
                                    alert('Payment processing failed. Please try again.');
                                }
                            })
                            .catch(error => {
                                console.log('Fetch error:', error);
                                alert('Fetch error:', error);
                                window.location.href = 'index.php';
                            });
                    }
                });

                document.getElementById('confirmPaymentBtn').addEventListener('click', function() {
                    const referenceNum = document.getElementById('reference_num').value;
                    const paymentMethod = document.getElementById('method-display').value;
                    const paymentAmount = document.getElementById('total_amount').value;
                    const orderNumbers = document.getElementById('orderNumbers').value;

                    fetch('fetch_other_method.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                reference_num: referenceNum,
                                payment_method: paymentMethod,
                                paymentAmount: paymentAmount,
                                orderNumbers: JSON.parse(orderNumbers)
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            console.log("Server Response:", data);
                            if (data.success) {
                                resetDisplay();
                                const statusModal = new bootstrap.Modal(document.getElementById('statusModal'));

                                const statusMessage = document.getElementById('statusMessage');
                                statusMessage.textContent = 'Payment successful!';
                                statusMessage.classList.add('text-success');

                                statusModal.show();

                                setTimeout(() => {
                                    statusModal.hide();
                                    window.location.href = 'index.php?status=payment_success';
                                }, 2000);
                            } else {
                                alert("Failed to confirm payment: " + data.message);
                            }
                        })
                        .catch(error => console.error('Error:', error));
                });

                document.getElementById('other-methods').addEventListener('click', function() {
                    document.getElementById('calculator').style.display = 'none';
                    document.getElementById('new-ui').style.display = 'block';
                    document.getElementById('btn-methods').style.display = 'block';
                    this.classList.add('active-payment');
                    document.getElementById('cash').classList.remove('active-payment');
                    document.getElementById('payment-method-text').innerHTML = 'Other Method';

                    resetDisplay();
                    resetStyles(this, 'other-methods');
                });

                document.getElementById('cash').addEventListener('click', function() {
                    document.getElementById('calculator').style.display = 'block';
                    document.getElementById('new-ui').style.display = 'none';
                    document.getElementById('btn-methods').style.display = 'none';
                    document.getElementById('gcash').style.display = 'none';
                    document.getElementById('paymaya').style.display = 'none';
                    this.classList.add('active-payment');
                    document.getElementById('other-methods').classList.remove('active-payment');
                    document.getElementById('payment-method-text').innerHTML = 'Cash';
                    document.getElementById('paymentDetails').style.display = 'none';
                    document.getElementById('cash-display').style.display = 'block';
                    document.getElementById('paymentAmount').value = '';
                    document.getElementById('confirmPaymentBtn').disabled = true;

                    resetDisplay();
                    resetStyles(this, 'cash');
                });

                function resetStyles(element, method) {
                    const borderStyle = 'border: 2px solid #ffd700; border-right: none; border-left: none; border-top: none;';
                    if (method === 'cash') {
                        document.getElementById('cash').style.cssText = borderStyle;
                        document.getElementById('other-methods').style.cssText = '';
                    } else {
                        document.getElementById('other-methods').style.cssText = borderStyle;
                        document.getElementById('cash').style.cssText = '';
                    }
                }

                function resetActivePaymentButtons() {
                    document.querySelectorAll('.button').forEach(function(button) {
                        button.classList.remove('active-online-payment');
                        button.style = '';
                    });
                }

                document.getElementById('gcash-btn').addEventListener('click', function() {
                    resetActivePaymentButtons();
                    document.getElementById('payment-method-text').innerHTML = 'GCash';
                    this.classList.add('active-online-payment');
                    this.style = 'background-color: #74BAF8;';
                    resetDisplay();
                });

                document.getElementById('paymaya-btn').addEventListener('click', function() {
                    resetActivePaymentButtons();
                    document.getElementById('payment-method-text').innerHTML = 'PayMaya';
                    this.classList.add('active-online-payment');
                    this.style = 'background-color: #74BAF8;';
                    resetDisplay();
                });

            });
        </script>

        <script>
            const gcashBtn = document.getElementById('gcash-btn');
            const paymayaBtn = document.getElementById('paymaya-btn');
            const gcashPanel = document.getElementById('gcash');
            const paymayaPanel = document.getElementById('paymaya');

            gcashBtn.addEventListener('click', function() {
                gcashPanel.style.display = 'block';
                paymayaPanel.style.display = 'none';
                document.getElementById('method-display').value = 'gcash';
                document.getElementById('paymentDetails').style.display = 'block';
                document.getElementById('cash-display').style.display = 'none';
            });

            paymayaBtn.addEventListener('click', function() {
                paymayaPanel.style.display = 'block';
                gcashPanel.style.display = 'none';
                document.getElementById('method-display').value = 'maya';
                document.getElementById('paymentDetails').style.display = 'block';
                document.getElementById('cash-display').style.display = 'none';
            });
        </script>

    </div>

    <script>
        document.addEventListener('click', function(event) {
            var userOptions = document.getElementById('userOptions');
            var welcomeUser = document.getElementById('welcomeUser');
            var isClickInside = welcomeUser.contains(event.target) || userOptions.contains(event.target);

            if (!isClickInside) {
                userOptions.style.display = 'none';
            }
        });

        document.getElementById('welcomeUser').addEventListener('click', function() {
            var userOptions = document.getElementById('userOptions');
            userOptions.style.display = (userOptions.style.display === 'block' ? 'none' : 'block');
        });



        document.addEventListener('DOMContentLoaded', function() {
            const hamburgerMenu = document.querySelector('.hamburger-menu');
            const dropdown = document.querySelector('.dropdown');

            hamburgerMenu.addEventListener('click', function() {
                dropdown.classList.toggle('show');

                if (dropdown.classList.contains('show')) {
                    hamburgerMenu.classList.add('close');
                } else {
                    hamburgerMenu.classList.remove('close');
                }
            });
        });


        document.getElementById('profileImage').addEventListener('click', function() {
            document.getElementById('profileInput').click();
        });

        document.getElementById('profileInput').addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const formData = new FormData();
                formData.append('profileImage', file);

                fetch('payment.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.getElementById('profileImage').src = '../../img/' + data.newImagePath;
                            document.getElementById('welcomeUser').src = '../../img/' + data.newImagePath;
                        } else {
                            alert('Failed to upload the profile picture.');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                    });
            }
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
                    window.location.href = 'payment.php';
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