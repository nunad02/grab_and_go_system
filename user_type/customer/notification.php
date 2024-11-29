<?php
include '../../db/conn.php';
session_start();
ob_start();
if (!isset($_SESSION['account_id'])) {
    header("Location: ../../index.php");
    exit();
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../../phpmailer/src/Exception.php';
require '../../phpmailer/src/PHPMailer.php';
require '../../phpmailer/src/SMTP.php';

if (isset($_POST['order_number']) && isset($_POST['rating']) && isset($_POST['feedback']) && isset($_POST['product_name'])) {
    $order_number = $_POST['order_number'];
    $rating = $_POST['rating'];
    $feedback = $_POST['feedback'];
    $product_name = $_POST['product_name'];

    try {
        $sql = "INSERT INTO feedback (product_id, order_number, product_category, product_name, customer_name, cash_amount, product_image, customer_profile, rating, feedback)
                SELECT product_id, order_number, product_category, product_name, customer_name, cash_amount, product_image, customer_profile, :rating, :feedback
                FROM checkout
                WHERE order_number = :order_number";

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':order_number', $order_number);
        $stmt->bindParam(':rating', $rating);
        $stmt->bindParam(':feedback', $feedback);

        if ($stmt->execute()) {
            $updateSql = "UPDATE checkout SET feedback_status = 'Done' WHERE order_number = :order_number";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bindParam(':order_number', $order_number);

            if ($updateStmt->execute()) {
                $emailContent = "
                <html>
                    <body>
                        <div>
                            <h2>New Feedback Submission</h2>
                            <ul>
                                <li><strong>Order Number:</strong> {$order_number}</li>
                                <li><strong>Product Name:</strong> {$product_name}</li>
                                <li><strong>Rating:</strong> {$rating} out of 5</li>
                            </ul>
                            <p><strong>Feedback:</strong>" . nl2br(htmlspecialchars($feedback)) . "</p>
                            <p><strong>Submitted by:</strong> " . htmlspecialchars($_SESSION['fullname']) . "</p>
                            <p><strong>Email:</strong> " . htmlspecialchars($_SESSION['email']) . "</p>
                            <a href='#'>View Feedback in Admin Panel</a>
                        </div>
                        <div>
                            <p>Thank you for using our platform.</p>
                            <p>If you have any questions, feel free to <a href='mailto:support@omscmpc.gov.ph'>contact us</a>.</p>
                        </div>
                    </body>
                </html>
                ";


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
                    $mail->addAddress('omscmpcgovph@gmail.com');
                    $mail->isHTML(true);
                    $mail->Subject = 'New Order Feedback Submission';
                    $mail->Body    = $emailContent;

                    $mail->send();
                } catch (Exception $e) {
                    echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Feedback submitted but failed to update feedback status.']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to insert feedback.']);
        }
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }

    exit;
}


try {
    if (isset($_SESSION['fullname'])) {
        $customerName = $_SESSION['fullname'];

        $stmt = $conn->prepare("SELECT product_id, order_number, payment_method, cash_amount, reference_num, status, order_date, feedback_status 
                                FROM checkout 
                                WHERE user_type = 'Customer' AND customer_name = ?");
        $stmt->execute([$customerName]);
        $my_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $groupedOrders = [];

        foreach ($my_orders as $orders) {
            if (!isset($groupedOrders[$orders['order_number']])) {
                $feedback_status = $orders['feedback_status'];
                $groupedOrders[$orders['order_number']] = [
                    'product_id' => $orders['product_id'],
                    'order_number' => $orders['order_number'],
                    'payment_method' => $orders['payment_method'],
                    'reference_num' => $orders['reference_num'],
                    'status' => $orders['status'],
                    'order_date' => $orders['order_date'],
                    'total_amount' => $orders['cash_amount'],
                    'feedback_status' => $orders['feedback_status'],
                ];
            }
        }
    } else {
        echo "Error: Customer is not logged in.";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}


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

            $sql = "UPDATE feedback SET customer_profile = ? WHERE customer_name = ?";
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


if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['sendEmail'])) {
    $newPassword = $_POST['newPassword'];
    $newFullname = $_POST['newFullname'];
    $newEmail = $_POST['newEmail'];
    $newAddress = $_POST['newAddress'];
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
            $stmt = $conn->prepare("UPDATE account SET fullname = ?, email = ?, address = ?, password = ? WHERE email = ?");
            $executeParams = [$newFullname, $newEmail, $newAddress, $hashedPassword, $currentEmail];
        } else {
            $stmt = $conn->prepare("UPDATE account SET fullname = ?, email = ?, address = ? WHERE email = ?");
            $executeParams = [$newFullname, $newEmail, $newAddress, $currentEmail];
        }

        if ($stmt->execute($executeParams)) {
            $_SESSION['fullname'] = $newFullname;
            $_SESSION['email'] = $newEmail;
            $_SESSION['address'] = $newAddress;

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
                header('Location: notification.php');
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

$sql = "SELECT COUNT(*) as order_count FROM orders WHERE customer_name = :customer_name AND DATE(order_date) = CURDATE() ";
$stmt = $conn->prepare($sql);
$stmt->execute(['customer_name' => $_SESSION['fullname']]);
$orderCount = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grab & Go | Transactions</title>
    <link rel="shortcut icon" href="../../img/logo.png" type="image/x-icon">
    <?php include_once '../../header.php' ?>
    <link rel="stylesheet" href="../../css/cus_main.css">
    <style>
        .notifications-container {
            padding: 20px;
            max-width: 100%;
            margin: 0 auto;
            margin-top: -30px;
        }

        .notification-section {
            margin-bottom: 30px;
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            padding: 15px;
        }

        .notification-title {
            font-size: 24px;
            font-weight: bold;
            color: #343a40;
            margin-bottom: 10px;
        }

        .notification-item {
            padding: 10px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            transition: background 0.3s;
        }

        .notification-item:hover {
            background: #f1f1f1;
        }

        .order-details {
            flex-grow: 1;
            margin-right: 20px;
        }

        .order-number {
            font-weight: bold;
            color: #007bff;
        }

        .order-status {
            margin-bottom: 5px;
            color: #495057;
        }

        .payment-info {
            text-align: right;
        }

        .payment-method,
        .amount,
        .viewOrder {
            font-size: 14px;
            color: #6c757d;
        }

        .viewOrder {
            color: #007bff;
            text-decoration: none;
            font-weight: bold;
            margin-top: 5px;
            display: block;
            cursor: pointer;
        }

        .viewOrder:hover {
            text-decoration: underline;
        }

        .star-rating {
            font-size: 40px;
            color: #d3d3d3;
        }

        .star-rating .star {
            cursor: pointer;
            padding: 5px;
            transition: color 0.3s ease;
        }

        .star-rating .star:hover {
            color: #FFD700;
        }

        .text-center {
            text-align: center;
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
                <a href="my_cart.php" id="my-cart"><i class="fas fa-shopping-cart"></i> My Cart</a>
                <a href="notification.php" class="active" id="Transactions"> <i class="fas fa-bell"></i> Transactions</a>
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
                <a href="my_cart.php" id="my-cart"><i class="fas fa-shopping-cart"></i> My Cart</a>
                <a href="notification.php" class="active" id="Transactions"><i class="fas fa-bell"></i> Transactions</a>
                <a href="about.php"><i class="fas fa-info-circle"></i> About</a>
            </div>
        </div>
    </div>

    <div class="home-container">
        <nav class="navbar">
            <div class="container-fluid">
                <a class="navbar-brand fw-bold fs-3 ms-2 font-effect-shadow-multiple">TRANSACTIONS</a>
                <div class="position-relative me-3 mt-2 ms-auto">
                    <a href="my_cart.php"> <i class="fas fa-shopping-cart" style="font-size: 1.7rem; color: #007bff;" title="Cart" data-toggle="tooltip"></i></a>

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

                <form id="changePasswordForm" method="post" action="notification.php" class="text-center" style="display: none;">
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

        <div class="notifications-container">
            <?php
            $sections = [
                'In Process' => fn($orders) => $orders['status'] === 'In Process',
                'To Review' => fn($orders) => $orders['status'] === 'Completed' && empty($orders['feedback_status']),
                'Past Orders' => fn($orders) => $orders['status'] === 'Completed' && $orders['feedback_status'] === 'Done'
            ];

            usort($groupedOrders, function ($a, $b) {
                return strtotime($b['order_date']) - strtotime($a['order_date']);
            });

            function renderNotificationItem($orders)
            {
            ?>
                <div class="notification-item">
                    <div class="order-details">
                        <div class="order-number">Order #<?= htmlspecialchars($orders['order_number']); ?></div>
                        <?php if ($orders['status'] === 'Completed'): ?>
                            <div class="order-status">
                                Successfully paid through <?= htmlspecialchars($orders['payment_method']); ?> on <?= date('d M Y h:i A', strtotime($orders['order_date'])); ?>
                            </div>
                        <?php else: ?>
                            <div class="order-status">Order is being processed...</div>
                        <?php endif; ?>
                    </div>
                    <div class="payment-info">
                        <div class="payment-method fw-bold"><?= htmlspecialchars(ucfirst($orders['payment_method'])); ?></div>
                        <div class="amount fw-bold">₱<?= number_format($orders['total_amount'], 2); ?></div>
                        <a class="viewOrder" data-order="<?= htmlspecialchars($orders['order_number']); ?>" data-bs-toggle="modal" data-bs-target="#orderModal">View Order</a>
                    </div>
                </div>
            <?php
            }

            foreach ($sections as $title => $condition) {
                echo "<div class='notification-section'><div class='notification-title'>$title</div>";
                foreach ($groupedOrders as $orders) {
                    if ($condition($orders)) {
                        renderNotificationItem($orders);
                    }
                }
                echo "</div>";
            }
            ?>
        </div>



        <div class="modal fade" id="orderModal" tabindex="-1" aria-labelledby="orderModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white rounded-top">
                        <h5 class="modal-title" id="orderModalLabel">Order Details</h5>
                    </div>
                    <div class="modal-body" style="font-family: Arial, sans-serif;">
                        <h6><strong>Customer Name:</strong> <span id="modalCustomerName"></span></h6>
                        <h6><strong>Order Number:</strong> <span id="modalOrderNumber"></span></h6>
                        <h6><strong>Order Items:</strong></h6>
                        <ul id="modalOrderItemsList" style="list-style-type: disc; padding-left: 25px;"></ul>
                        <h6><strong>Total Price:</strong> <span id="modalTotalPrice"></span></h6>
                        <h6><strong>Order Date:</strong> <span id="modalOrderDate"></span></h6>
                        <h6><strong>Payment Method:</strong> <span id="modalPaymentMethod"></span></h6>
                        <h6><strong>Cash Amount:</strong> <span id="modalCashAmount"></span></h6>
                        <h6 id="referenceNumberSection"><strong>Reference Number:</strong> <span id="modalReferenceNum"></span></h6>
                        <h6 style="display: none;"><strong>Status:</strong> <span id="modalStatus"></span></h6>

                        <div class="ratings">
                            <h6><strong>Rate Your Order:</strong></h6>
                            <div class="star-rating text-center">
                                <span class="star" data-value="5">&#9733;</span>
                                <span class="star" data-value="4">&#9733;</span>
                                <span class="star" data-value="3">&#9733;</span>
                                <span class="star" data-value="2">&#9733;</span>
                                <span class="star" data-value="1">&#9733;</span>
                            </div>
                            <input type="hidden" id="starRatingValue" name="rating">
                            <input type="hidden" id="product_id" name="product_id">
                        </div>

                        <div id="input_feedback">
                            <h6><strong>Feedback:</strong></h6>
                            <textarea id="feedback" class="form-control" rows="3" placeholder="Write your feedback here..."></textarea>
                        </div>

                        <p class="text-success mt-3" id="success"><strong>Feedback already submitted. Thank you!</strong></p>
                    </div>

                    <div class="modal-footer">
                        <a href="notification.php" class="btn btn-primary" id="submitFeedback">Submit Feedback</a>
                        <a href="notification.php" class="btn btn-secondary">Close</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="statusModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="feedbackAlertModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-body">
                        <h5 class="text-center text-success" id="feedbackAlertMessage"></h5>
                    </div>
                </div>
            </div>
        </div>

        <?php include_once '../../includes/goToTop.php'; ?>
        <script>
            document.getElementById('submitFeedback').addEventListener('click', function() {
                const rating = document.getElementById('starRatingValue').value;
                const feedback = document.getElementById('feedback').value;
                const order_number = document.getElementById('modalOrderNumber').innerText;
                const product_name = document.getElementById('modalOrderItemsList').innerText;

                if (rating === '' || feedback === '') {
                    showCustomAlert('Please provide a rating and feedback.');
                } else {
                    const xhr = new XMLHttpRequest();
                    xhr.open('POST', 'feedback.php', true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.onreadystatechange = function() {
                        if (xhr.readyState === 4 && xhr.status === 200) {
                            showCustomAlert('Feedback submitted successfully!');

                            const orderModal = bootstrap.Modal.getInstance(document.getElementById('orderModal'));
                            orderModal.hide();

                            setTimeout(function() {
                                window.location.href = "notification.php";
                            }, 1000);
                        }
                    };

                    xhr.send(`order_number=${order_number}&rating=${rating}&feedback=${encodeURIComponent(feedback)}&product_name=${encodeURIComponent(product_name)}`);
                }
            });

            function showCustomAlert(message) {
                document.getElementById('feedbackAlertMessage').innerText = message;
                const feedbackAlertModal = new bootstrap.Modal(document.getElementById('statusModal'));
                feedbackAlertModal.show();
            }
        </script>

        <script>
            const stars = document.querySelectorAll('.star');
            let ratingValue = 0;

            stars.forEach(star => {
                star.addEventListener('click', function() {
                    ratingValue = this.getAttribute('data-value');
                    document.getElementById('starRatingValue').value = ratingValue;
                    highlightStars(ratingValue);
                });
            });

            function highlightStars(rating) {
                stars.forEach(star => {
                    if (star.getAttribute('data-value') <= rating) {
                        star.style.color = '#FFD700';
                    } else {
                        star.style.color = '#d3d3d3';
                    }
                });
            }
        </script>

    </div>

    <script>
        document.querySelectorAll('.viewOrder').forEach(button => {
            button.addEventListener('click', function() {
                const orderNumber = this.getAttribute('data-order');
                showOrderDetails(orderNumber);
            });
        });

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
                    document.getElementById("modalStatus").innerText = orderDetails.status || 'N/A';
                    document.getElementById("modalTotalPrice").innerText = `₱${orderDetails.total_price.toFixed(2)}`;

                    const referenceNumberSection = document.getElementById("referenceNumberSection");
                    if (orderDetails.payment_method === 'gcash' || orderDetails.payment_method === 'maya') {
                        referenceNumberSection.style.display = 'block';
                        document.getElementById("modalReferenceNum").innerText = orderDetails.reference_num || 'N/A';
                    } else {
                        referenceNumberSection.style.display = 'none';
                    }

                    const orderItemsList = document.getElementById("modalOrderItemsList");
                    orderItemsList.innerHTML = '';
                    orderDetails.items.forEach(item => {
                        const li = document.createElement('li');
                        li.innerText = `×${item.quantity} ${item.order_name} - ₱${item.price}`;
                        orderItemsList.appendChild(li);
                    });

                    if (orderDetails.feedback_status === 'Done' && orderDetails.status === 'Completed') {
                        document.querySelector('.ratings').style.display = 'none';
                        document.getElementById('input_feedback').style.display = 'none';
                        document.getElementById('submitFeedback').style.display = 'none';
                        document.getElementById('success').style.display = 'block';
                    } else if (orderDetails.feedback_status === '' && orderDetails.status === 'In Process') {
                        document.querySelector('.ratings').style.display = 'none';
                        document.getElementById('input_feedback').style.display = 'none';
                        document.getElementById('success').style.display = 'none';
                        document.getElementById('submitFeedback').style.display = 'none';
                    } else if (orderDetails.feedback_status === '' && orderDetails.status === 'Completed') {
                        document.querySelector('.ratings').style.display = 'block';
                        document.getElementById('input_feedback').style.display = 'block';
                        document.getElementById('success').style.display = 'none';
                        document.getElementById('submitFeedback').style.display = 'block';
                    }

                    const modal = new bootstrap.Modal(document.getElementById("orderModal"));
                    modal.show();
                }
            };
            xhr.send("order_number=" + orderNumber);
        }
    </script>


    <script>
        document.addEventListener("click", function(event) {
            var userOptions = document.getElementById("userOptions");
            var welcomeUser = document.getElementById("welcomeUser");
            var isClickInside =
                welcomeUser.contains(event.target) || userOptions.contains(event.target);

            if (!isClickInside) {
                userOptions.style.display = "none";
            }
        });

        document.getElementById("welcomeUser").addEventListener("click", function() {
            var userOptions = document.getElementById("userOptions");
            userOptions.style.display =
                userOptions.style.display === "block" ? "none" : "block";
        });

        document.addEventListener("DOMContentLoaded", function() {
            const hamburgerMenu = document.querySelector(".hamburger-menu");
            const dropdown = document.querySelector(".dropdown");

            hamburgerMenu.addEventListener("click", function() {
                dropdown.classList.toggle("show");

                if (dropdown.classList.contains("show")) {
                    hamburgerMenu.classList.add("close");
                } else {
                    hamburgerMenu.classList.remove("close");
                }
            });
        });

        const dropdown = document.querySelector(".dropdown");

        function toggleDropdown() {
            dropdown.classList.toggle("show");
        }

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
                    window.location.href = 'notification.php';
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