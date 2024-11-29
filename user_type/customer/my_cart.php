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

if (!isset($_SESSION['profile'])) {
    $_SESSION['profile'] = 'profile.jpg';
}

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


// if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_item'])) {
//     if (isset($_POST['selectedProducts']) && is_array($_POST['selectedProducts'])) {
//         $orderIds = $_POST['selectedProducts'];
//         $placeholders = implode(',', array_fill(0, count($orderIds), '?'));

//         $sql = "DELETE FROM orders WHERE order_id IN ($placeholders)";
//         $stmt = $conn->prepare($sql);
//         $stmt->execute($orderIds);

//         header('Location: my_cart.php');
//         exit();
//     }
// }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_item'])) {
    if (isset($_POST['selectedProducts']) && is_array($_POST['selectedProducts'])) {

        foreach ($_POST['selectedProducts'] as $index => $product_name) {
            $order_id = $_POST['order_id'][$index];

            $sql = "DELETE FROM orders WHERE product_name = ? AND order_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$product_name, $order_id]);
        }

        header('Location: my_cart.php');
        exit();
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
                header('Location: my_cart.php');
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
    <title>Grab & Go | My Cart</title>
    <link rel="shortcut icon" href="../../img/logo.png" type="image/x-icon">
    <?php include_once '../../header.php' ?>
    <link rel="stylesheet" href="../../css/cus_main.css">

    <style>
        @media (max-width: 480px) {

            #selectAllBtn,
            #remove_item,
            #checkout {
                font-size: 13px !important;
                margin-top: -25px;
                margin-bottom: 250px;
            }

            #product_img {
                height: 30px !important;
                width: 30px !important;
            }

            #product_qty,
            #product_names {
                font-size: 15px !important;
            }
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

        .checkout {
            text-decoration: none;
            color: #fff;
            display: inline-block;
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
                <a href="my_cart.php" id="my-cart" class="active"><i class="fas fa-shopping-cart"></i> My Cart</a>
                <a href="notification.php" id="Transactions"> <i class="fas fa-bell"></i> Transactions</a>
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
                <a href="my_cart.php" id="my-cart" class="active"><i class="fas fa-shopping-cart"></i> My Cart</a>
                <a href="notification.php" id="Transactions"><i class="fas fa-bell"></i> Transactions</a>
                <a href="about.php"><i class="fas fa-info-circle"></i> About</a>
            </div>
        </div>
    </div>

    <div class="home-container">
        <nav class="navbar">
            <div class="container-fluid">
                <a class="navbar-brand fw-bold fs-3 ms-2 font-effect-shadow-multiple">MY CART</a>
                <div class="position-relative me-3 mt-2 ms-auto">
                    <a href="my_cart.php"> <i class="fas fa-shopping-cart" style="font-size: 1.7rem; color: #007bff;" title="Cart" data-toggle="tooltip"></i></a>

                    <?php if ($orderCount > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            <?php echo $orderCount; ?>
                        </span>
                    <?php endif; ?>
                </div>
                <img src="<?php echo htmlspecialchars('../../img/' . $_SESSION['profile']); ?>" alt="Profile" class="d-flex me-2 border border-2 border-primary-subtle" style="height: 40px; width: 40px; border-radius: 50%;" id="welcomeUser" title="Menu" data-toggle="tooltip">
            </div>
        </nav>

        <div id="userOptions" class="user-profile">
            <div class="profile-wrapper">
                <img src="../../img/<?php echo htmlspecialchars($_SESSION['profile']); ?>" alt="Profile" class="d-flex border border-2 border-primary-subtle mb-3" style="height: 100px; width: 100px; border-radius: 50%; margin: 0 auto; cursor: pointer;" id="profileImage" title='Click me to change profile' data-toggle='tooltip'>

                <input type="file" id="profileInput" style="display: none;">
                <div class="profile-details text-center">
                    <p class="fw-bold"><?php echo htmlspecialchars($_SESSION['email']); ?></p>
                    <p><?php echo htmlspecialchars($_SESSION['fullname']); ?></p>
                </div>

                <form id="changePasswordForm" method="post" action="my_cart.php" class="text-center" style="display: none;">
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

        <form method="post" action="">
            <div class="cart-container">
                <!-- Orders Table -->
                <div class="order-container mt-3" style="max-height: 400px; height: 400px; max-width: 95%; margin: 0 auto; overflow-y: auto; border: 2px solid #aaa; border-right: none; border-left: none;">

                    <table class="table">
                        <tbody>
                            <?php
                            $sql = "SELECT * FROM orders 
                                    WHERE customer_name = :customer_name 
                                    AND DATE(order_date) = CURDATE() 
                                    ORDER BY order_date DESC";
                            $stmt = $conn->prepare($sql);
                            $stmt->execute(['customer_name' => $_SESSION['fullname']]);
                            while ($row = $stmt->fetch()) {
                                $totalItems++;
                                $price = $row['price'];
                                $product_id = $row['product_id'];
                                $order_number = $row['order_number'];
                                $productName = htmlspecialchars($row['product_name']);
                            ?>
                                <tr>
                                    <td class="fs-5 fw-bold align-middle" style="padding-left: 40px;">
                                        <input type="checkbox" name="selectedProducts[]" value="<?php echo htmlspecialchars($row['product_name']); ?>" data-product-name="<?php echo $productName; ?>" data-price="<?php echo $price; ?>" data-order-id="<?php echo $row['order_id']; ?>" style="transform: scale(1.5);" onclick="updateTotal()">

                                        <input type="hidden" name="order_id[]" value="<?php echo $row['order_id']; ?>">

                                        <img src="<?php echo htmlspecialchars('../img/' . $row['product_image']); ?>" id="product_img" alt="Product image" style="width: 50px; height: 50px; margin: 0 20px 0 20px;">
                                        <span id="product_qty"> x<?php echo htmlspecialchars($row['quantity']); ?></span>&nbsp;
                                        <span id="product_names"><?php echo $productName; ?></span>
                                    </td>
                                    <td class="text-end align-middle justify-content-between">
                                        <span class="text-end me-4 text-primary fs-4 fw-bold">&#8369;<?php echo number_format($price, 2); ?></span>
                                    </td>
                                </tr>
                            <?php
                            }
                            ?>
                        </tbody>
                    </table>

                </div>

                <!-- Total Section -->
                <div class="d-flex align-items-center justify-content-between" style="border-bottom: 2px solid #aaa; width: 95%; margin: 0 auto;">
                    <div class="fw-bold fs-4 ms-3">
                        Total: <span style="margin-left: 90px;" id="totalItemsDisplay">0 Item/s</span>
                    </div>
                    <div class="fw-bold fs-4 me-5">
                        &#8369;<span id="totalPriceDisplay">0.00</span>
                    </div>
                </div>

                <div class="btn-container d-flex align-items-center justify-content-between">
                    <div class="fw-bold fs-4 " style="margin-left: 30px; width: 45%;">
                        <button type="button" id="selectAllBtn" class="btn btn-main d-block w-100 btn-secondary p-2 fs-5 fw-bold" onclick="toggleSelectAll()">
                            Select All
                        </button>
                    </div>
                    <div class="fw-bold fs-4 " style="margin-right: 10px; margin-left: 10px; width: 45%;">
                        <button type="submit" id="remove_item" name="remove_item" class="btn btn-main d-block w-100 btn-outline-primary p-2 fs-5 fw-bold"> Remove Item/s</button>
                    </div>
                    <div class="fw-bold fs-4" style="margin-right: 30px; width: 45%;">
                        <button type="button" id="checkout" name="check_out" class="btn btn-main d-block w-100 btn-primary text-light fs-5 p-2 fw-bold"
                            onclick="checkAndRedirect()">
                            Check Out
                        </button>
                    </div>
                </div>

            </div>
        </form>

        <!-- Modal -->
        <div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h1 class="modal-title fs-5" id="exampleModalLabel">Choose Payment Option</h1>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-12">
                                <button class="button" id="cash-btn">Cash</button>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-12">
                                <button class="button" id="online-payment-btn"><img src="../../img/gcash-seeklogo.svg" width="30%"><img src="../../img/PayMaya_Logo.png" width="30%"></button>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" id="create-payment">Proceed to Payment</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../../js/customer_cart.js"></script>
    <?php include_once '../../includes/goToTop.php'; ?>
    <script>
        let totalPrice = 0;
        let totalItems = 0;
        let allChecked = false;

        function loadCheckedProducts() {
            const checkboxes = document.querySelectorAll('input[name="selectedProducts[]"]');
            checkboxes.forEach(function(checkbox) {
                const productName = checkbox.getAttribute('data-product-name');
                const isChecked = localStorage.getItem(productName);
                if (isChecked === 'true') {
                    checkbox.checked = true;
                }
            });
            updateTotal();
        }

        function updateTotal() {
            const checkboxes = document.querySelectorAll('input[name="selectedProducts[]"]');
            totalPrice = 0;
            totalItems = 0;
            let allSelected = true;

            checkboxes.forEach(function(checkbox) {
                const productName = checkbox.getAttribute('data-product-name');
                const productPrice = parseFloat(checkbox.getAttribute('data-price'));
                const product_id = checkbox.getAttribute('data-product_id');

                if (checkbox.checked) {
                    totalPrice += productPrice;
                    totalItems++;
                    localStorage.setItem(productName, 'true');
                } else {
                    localStorage.removeItem(productName);
                    allSelected = false;
                }
            });

            document.getElementById('totalPriceDisplay').textContent = totalPrice.toFixed(2);
            document.getElementById('totalItemsDisplay').textContent = totalItems + ' Item/s';

            const selectAllBtn = document.getElementById('selectAllBtn');
            selectAllBtn.textContent = allSelected ? 'Unselect All' : 'Select All';
        }

        function toggleSelectAll() {
            const checkboxes = document.querySelectorAll('input[name="selectedProducts[]"]');
            const selectAllBtn = document.getElementById('selectAllBtn');

            allChecked = !allChecked;
            checkboxes.forEach(function(checkbox) {
                checkbox.checked = allChecked;
                const productName = checkbox.getAttribute('data-product-name');
                if (allChecked) {
                    localStorage.setItem(productName, 'true');
                } else {
                    localStorage.removeItem(productName);
                }
            });

            selectAllBtn.textContent = allChecked ? 'Unselect All' : 'Select All';
            updateTotal();
        }


        function checkAndRedirect() {
            if (totalItems === 0) {
                alert("Please select at least one product to proceed to checkout.");
                return;
            }

            const selectedProducts = [];
            const orderIds = [];
            const checkboxes = document.querySelectorAll('input[name="selectedProducts[]"]:checked');

            checkboxes.forEach(function(checkbox) {
                selectedProducts.push(encodeURIComponent(checkbox.value));
                orderIds.push(checkbox.getAttribute('data-order-id'));
            });

            const selectedProductsString = selectedProducts.join(',');
            const orderIdsString = orderIds.join(',');


            window.location.href = `checkout.php?total_items=${totalItems}&total_price=${totalPrice}&selected_products=${selectedProductsString}&order_ids=${orderIdsString}`;
        }

        window.onload = loadCheckedProducts;
    </script>

    <script>
        document.getElementById('online-payment-btn').addEventListener('click', function() {
            this.classList.add('active-online-payment');
            this.style = 'background-color: #74BAF8;';
            document.getElementById('cash-btn').style = 'background-color: transparent;';
        });

        document.getElementById('cash-btn').addEventListener('click', function() {
            this.classList.add('active-online-payment');
            this.style = 'background-color: #74BAF8;';
            document.getElementById('online-payment-btn').style = 'background-color: transparent;';
        });

        $('#create-payment').on('click', function() {
            let totalPrice = <?php echo $totalPrice; ?>;

            fetch('payment-window.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        'totalAmount': totalPrice
                    })
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {

                    if (data.is_success != false) {
                        const paymentWindow = window.open(data.checkout_url, '_blank');
                        const paymentInvoiceId = data.invoice_id;

                        const checkPaymentStatus = setInterval(() => {
                            fetch('../cashier/payment_status.php?invoice_id=' + paymentIntentId)
                                .then(statusResponse => statusResponse.json())
                                .then(statusData => {
                                    if (statusData.payment_completed) {
                                        clearInterval(checkPaymentStatus);
                                        paymentWindow.close();
                                        window.location.href = 'payment.php?status=payment_success';
                                        printReceipt(statusData);
                                    }
                                })
                                .catch(error => console.error('Error:', error));
                        }, 5000);

                    } else {
                        alert('Payment initiation failed.');
                    }
                })
                .catch(error => console.error('Error:', error));
        });

        function printReceipt(paymentData) {
            console.log('Printing receipt:', paymentData);
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
                    window.location.href = 'my_cart.php';
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