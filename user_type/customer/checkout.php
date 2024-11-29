<?php
include '../../db/conn.php';
session_start();
if (!isset($_SESSION['account_id'])) {
    header("Location: ../../index.php");
    exit();
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../../phpmailer/src/Exception.php';
require '../../phpmailer/src/PHPMailer.php';
require '../../phpmailer/src/SMTP.php';

$totalItems = isset($_GET['total_items']) ? (int)$_GET['total_items'] : 0;
$totalPrice = isset($_GET['total_price']) ? (float)$_GET['total_price'] : 0;
$formattedTotalPrice = number_format($totalPrice, 2);

$selectedProducts = isset($_GET['selected_products']) ? explode(',', $_GET['selected_products']) : [];
$product_ids = isset($_GET['product_id']) ? explode(',', $_GET['product_id']) : [];
$orderIds = isset($_GET['order_ids']) ? explode(',', $_GET['order_ids']) : [];

if (isset($_POST['place_order'])) {
    $paymentMethod = $_POST['payment'] ?? null;
    $paymentAmount = $_POST['paymentAmountDisplay'];
    $reference_num = $_POST['reference_num'];
    $preferredTime = $_POST['preferred_time'] ?? null;
    $customerName = $_SESSION['fullname'] ?? '';
    $email = $_SESSION['email'] ?? '';
    $preferredTimeFormatted = DateTime::createFromFormat('H:i', $preferredTime)->format('h:i A');


    if (!empty($selectedProducts)) {
        $generatedOrderNumber = rand(1000000, 9999999);
        date_default_timezone_set('Asia/Manila');
        $order_date = date('Y-m-d h:i A');

        foreach ($selectedProducts as $order_id) {
            try {
                // Fetch product details
                $stmt = $conn->prepare("SELECT product_id, user_type, product_category, product_name, quantity, price, product_image, customer_profile, customer_name 
                                        FROM orders 
                                        WHERE order_id = :order_id");
                $stmt->bindParam(':order_id', $orderIds[array_search($order_id, $selectedProducts)]);
                $stmt->execute();
                $row = $stmt->fetch();

                if ($row) {
                    $product_id = $row["product_id"];
                    $orderStatus = ($paymentMethod === 'gcash' || $paymentMethod === 'maya') ? 'Completed' : 'In Process';

                    $insertCheckoutSql = "INSERT INTO checkout (product_id, order_number, customer_name, user_type, order_date, product_category, product_name, quantity, price, payment_method, cash_amount, reference_num, order_status, status, product_image, customer_profile, preferred_time)
                                          VALUES (:product_id, :order_number, :customer_name, :user_type, :order_date, :product_category, :product_name, :quantity, :price, :payment_method, :cash_amount, :reference_num, 'paid', :status, :product_image, :customer_profile, :preferred_time)";

                    $insertStmt = $conn->prepare($insertCheckoutSql);

                    $insertStmt->execute([
                        'product_id' => $product_id,
                        'order_number' => $generatedOrderNumber,
                        'customer_name' => $customerName,
                        'user_type' => $row['user_type'],
                        'order_date' => $order_date,
                        'product_category' => $row['product_category'],
                        'product_name' => $row['product_name'],
                        'quantity' => $row['quantity'],
                        'price' => $row['price'],
                        'payment_method' => $paymentMethod,
                        'cash_amount' => $paymentAmount,
                        'reference_num' => $reference_num,
                        'status' => $orderStatus,
                        'product_image' => $row['product_image'],
                        'customer_profile' => $row['customer_profile'],
                        'preferred_time' => $preferredTimeFormatted
                    ]);

                    $checkoutQtyStmt = $conn->prepare("SELECT quantity FROM checkout WHERE product_id = :product_id AND order_number = :order_number");
                    $checkoutQtyStmt->execute([
                        'product_id' => $product_id,
                        'order_number' => $generatedOrderNumber
                    ]);
                    $checkoutRow = $checkoutQtyStmt->fetch();

                    if ($checkoutRow) {
                        $checkoutQuantity = $checkoutRow['quantity'];

                        $updateProductStmt = $conn->prepare("UPDATE products 
                                                             SET quantity = quantity - :checkout_quantity, 
                                                                 solds = solds + :checkout_quantity 
                                                             WHERE product_id = :product_id");
                        $updateProductStmt->execute([
                            'checkout_quantity' => $checkoutQuantity,
                            'product_id' => $product_id
                        ]);
                    }
                }
            } catch (PDOException $e) {
                echo "Error: " . htmlspecialchars($e->getMessage());
            }
        }

        $emailContent = '
        <html>
        <head>
            <style>
                body { font-family: "Helvetica Neue", Arial, sans-serif; margin: 0; padding: 20px; background-color: #fafafa; color: #333; }
                p { line-height: 1.6; margin-bottom: 16px; }
                h2 { font-size: 18px; color: #2c3e50; margin-bottom: 12px; }
                ul { list-style-type: none; padding-left: 0; margin: 0; }
                ul li { background-color: #fff; padding: 10px; margin-bottom: 10px; border-radius: 6px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); }
                ul li strong { color: #34495e; }
                .product-details { background-color: #ecf0f1; padding: 15px; margin-top: 20px; border-radius: 6px; }
                .product-details ul li { background-color: #fff; }
                .footer { font-size: 14px; color: #7f8c8d; margin-top: 30px; text-align: center; }
                .footer a { color: #3498db; text-decoration: none; }
                .contact-info { background-color: #3498db; padding: 15px; border-radius: 6px; color: white; margin-top: 20px; }
            </style>
        </head>
        <body>
            <h2>Hi, ' . htmlspecialchars($customerName) . '!</h2>
            <p>Thank you for your order! We are happy to inform you that your order has been successfully processed. Please find the details below:</p>
            
            <ul>
                <li><strong>Order Number:</strong> ' . htmlspecialchars($generatedOrderNumber) . '</li>
                <li><strong>Customer Name:</strong> ' . htmlspecialchars($customerName) . '</li>
                <li><strong>Order Date:</strong> ' . htmlspecialchars($order_date) . '</li>
                <li><strong>Payment Method:</strong> ' . htmlspecialchars($paymentMethod) . '</li>
                <li><strong>Cash Amount:</strong> ₱' . htmlspecialchars(number_format($paymentAmount, 2)) . '</li>';

        if ($paymentMethod !== 'cash' && $paymentMethod !== 'credit') {
            $emailContent .= '<li><strong>Reference Number:</strong> ' . htmlspecialchars($reference_num) . '</li>';
        }

        $emailContent .= '
                <li><strong>Preferred Time:</strong> ' . htmlspecialchars($preferredTimeFormatted) . '</li>
            </ul>
            
            <div class="product-details">
                <p><strong>Product Details:</strong></p>
                <ul>';

        foreach ($selectedProducts as $index => $order_id) {
            try {
                $stmt = $conn->prepare("SELECT product_name, quantity, price FROM orders WHERE order_id = :order_id");
                $stmt->bindParam(':order_id', $orderIds[$index]);
                $stmt->execute();
                $row = $stmt->fetch();

                if ($row) {
                    $emailContent .= '
                    <li>
                        <strong>Product:</strong> ' . htmlspecialchars($row['product_name']) . '<br>
                        <strong>Quantity:</strong> ' . htmlspecialchars($row['quantity']) . '<br>
                        <strong>Price:</strong> ₱' . htmlspecialchars(number_format($row['price'], 2)) . '
                    </li>';
                }
            } catch (PDOException $e) {
                echo "Error fetching order details: " . htmlspecialchars($e->getMessage());
            }
        }

        $emailContent .= '
                </ul>
            </div>
            
            <p>Your items will be ready for pickup or delivery by the preferred time. If there are any updates, we’ll notify you via email or SMS.</p>
            
            <p>If you have any questions or need further assistance, feel free to reach out to our customer support team. We’re happy to help!</p>
            
            <div class="contact-info">
                <p><strong>Customer Support</strong><br>
                Phone: +639632076854<br>
                Email: omscmpcgovph@gmail.com<br>
                Hours: Monday to Friday, 7 AM - 6 PM</p>
            </div>
            
            <p>We appreciate you and look forward to serving you again!</p>
            
            <div class="footer">
                <p>Sincerely,<br>
                The OMSC MPC Team</p>
                <p><a href="mailto:omscmpcgovph@gmail.com">Contact Us</a></p>
            </div>
        </body>
        </html>';

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
            $mail->addAddress($_SESSION['email']);
            $mail->isHTML(true);
            $mail->Subject = 'Order Confirmation';
            $mail->Body = $emailContent;

            $mail->send();
        } catch (Exception $e) {
            echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        }

        foreach ($orderIds as $orderId) {
            try {
                $updateOrderSql = "UPDATE orders SET payment_method = :payment_method, cash_amount = :cash_amount, order_status = 'paid' WHERE order_id = :order_id";
                $updateStmt = $conn->prepare($updateOrderSql);
                $updateStmt->execute([
                    'payment_method' => $paymentMethod,
                    'cash_amount' => $paymentAmount,
                    'order_id' => $orderId
                ]);
            } catch (PDOException $e) {
                echo "Error updating order: " . htmlspecialchars($e->getMessage());
            }
        }

        $_SESSION['update_success'] = true;
        echo '
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <link rel="shortcut icon" href="../../img/logo.png" type="image/x-icon">
            <title>Loading...</title>
            <style>
                body {
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    height: 100vh;
                    margin: 0;
                    font-family: Arial, sans-serif;
                    background-color: #f3f3f3;
                }
                .loading-container {
                    text-align: center;
                }
                .spinner {
                    width: 40px;
                    height: 40px;
                    border: 4px solid #ddd;
                    border-top: 4px solid #3498db;
                    border-radius: 50%;
                    animation: spin 1s linear infinite;
                    margin: 0 auto;
                }
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
                .loading-text {
                    margin-top: 15px;
                    font-size: 18px;
                    color: #333;
                }
            </style>
        </head>
        <body>
            <div class="loading-container">
                <div class="spinner"></div>
                <div class="loading-text">Please wait...</div>
            </div>
            <script>
              
                setTimeout(function() {
                    window.location.href = "checkout.php";
                }, 2000);
            </script>
        </body>
        </html>
        ';
        exit();
    } else {
        echo "<p>Please select at least one product to order.</p>";
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="shortcut icon" href="../../img/logo.png" type="image/x-icon">
    <title>Grab & Go | Check out</title>
    <?php include_once '../../header.php' ?>
    <link rel="stylesheet" href="../../css/signup.css">
    <link rel="stylesheet" href="../../css/checkout.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-timepicker/0.5.2/css/bootstrap-timepicker.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-timepicker/0.5.2/js/bootstrap-timepicker.min.js"></script>
    <style>
        .qr-title {
            color: azure;
            text-align: center;
        }

        .payment-img {
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0 auto;
            margin-top: 20px;
        }

        .payment-img img {
            height: 450px;
            width: 450px;
        }

        .is-invalid {
            border-color: red;
        }

        button[disabled] {
            background-color: grey;
            color: white;
            cursor: not-allowed;
            opacity: 0.7;
        }
    </style>
</head>

<body>

    <div class="modal fade" id="statusModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-body">
                    <h5 class="text-center" id="statusMessage"></h5>
                </div>
            </div>
        </div>
    </div>

    <form action="" method="POST">
        <div class="checkout-container">
            <div class="left-panel">
                <h2 class="text-primary">Checkout</h2>

                <div class="payment-method">
                    <label>
                        <input type="radio" name="payment" value="gcash" onclick="updatePaymentInfo('gcash')" checked>
                        <img src="../../img/gcash.png" alt="GCash">
                    </label>
                    <label>
                        <input type="radio" name="payment" value="maya" onclick="updatePaymentInfo('maya')">
                        <img src="../../img/paymaya.jfif" alt="Maya">
                    </label>
                    <label>
                        <input type="radio" name="payment" value="cash" onclick="updatePaymentInfo('cash')">
                        <img src="../../img/pickup.png" alt="Cash on Pickup">
                    </label>
                    <label>
                        <input type="radio" name="payment" value="credit" onclick="updatePaymentInfo('credit')">
                        <img src="../../img/credit.png" alt="credit">
                    </label>
                </div>

                <div class="time-selection">
                    <input type="hidden" name="selected_products" value="<?php echo implode(',', $selectedProducts); ?>">

                    <label for="preferredTime">Select Preferred Time</label>
                    <select id="preferredTime" name="preferred_time" class="form-control">
                        <option value="" disabled selected>Select Time</option>
                        <?php
                        $startTime = new DateTime('06:00');
                        $endTime = new DateTime('20:00');
                        $interval = new DateInterval('PT30M');
                        $period = new DatePeriod($startTime, $interval, $endTime->add($interval));

                        $defaultTime = isset($_POST['preferred_time']) ? $_POST['preferred_time'] : $startTime->format('H:i');

                        foreach ($period as $time) {
                            $selected = ($time->format('H:i') === $defaultTime) ? 'selected' : '';
                            echo '<option value="' . $time->format('H:i') . '" ' . $selected . '>' . $time->format('h:i A') . '</option>';
                        }
                        ?>
                    </select>
                </div>

                <div class="payment-details" id="paymentDetails">
                    <div class="form-group row">
                        <div class="col-md-6">
                            <label for="reference_num">Reference. No.:</label>
                            <input type="text" id="reference_num" name="reference_num" maxlength="15" pattern="\d{4}-\d{3}-\d{6}" placeholder="XXXX-XXX-XXXXXX" class="form-control" oninput="formatAndRestrictNumber(this)" required>
                        </div>
                        <div class="col-md-6">
                            <label for="paymentAmount">Cash Amount:</label>
                            <input type="text" id="paymentAmount" class="paymentAmount form-control" name="paymentAmount" placeholder="Enter amount you paid" oninput="formatCurrency(this)">
                        </div>
                    </div>
                </div>

                <script>
                    function formatAndRestrictNumber(input) {
                        let value = input.value.replace(/\D/g, '');

                        let formatted = '';
                        if (value.length > 0) formatted = value.substring(0, 4);
                        if (value.length > 4) formatted += '-' + value.substring(4, 7);
                        if (value.length > 7) formatted += '-' + value.substring(7, 13);

                        input.value = formatted;
                    }
                </script>

                <div class="total-payment">
                    <span class="total-text">Total Payment:</span>
                    <span class="amount">₱<?php echo $formattedTotalPrice; ?></span>
                    <input type="hidden" name="total_price" value="<?php echo $totalPrice; ?>">
                    <input type="hidden" id="paymentAmountDisplay" name="paymentAmountDisplay">
                </div>

                <div class="button-container">
                    <a href="my_cart.php" class="btn-cancel">Cancel</a>
                    <button type="submit" name="place_order" id="placeOrderButton" class="btn-primary" disabled>
                        Place Order
                    </button>
                </div>
            </div>

            <div class="right-panel gcash" id="gcash">
                <h2>Gcash</h2>
                <h4 class="qr-title">Scan the QR code to pay</h4>
                <div class="payment-info payment-img">
                    <img src="../../img/payment/gcash.jpg" />
                </div>
            </div>

            <div class="right-panel paymaya" id="paymaya" style="display: none;">
                <h2>PayMaya</h2>
                <h4 class="qr-title">Scan the QR code to pay</h4>
                <div class="payment-info payment-img">
                    <img src="../../img/payment/paymaya.jpg" />
                </div>
            </div>

            <div class="right-panel cash" id="cash" style="display: none;">
                <h2>Cash on Pickup</h2>
                <div class="payment-info">
                    <label for="pickupDetails">Pickup Details</label>
                    <input type="text" id="pickupDetails" name="pickupDetails" class="form-control" value="<?php echo htmlspecialchars($_SESSION['fullname']); ?>">
                </div>
                <div class="payment-info">
                    <label for="paymentAmountPickup">Amount</label>
                    <input type="text" id="paymentAmountPickup" class="paymentAmount form-control" name="paymentAmount" oninput="formatCurrency(this)">
                </div>
            </div>

            <div class="right-panel credit" id="credit" style="display: none;">
                <h2>Credit</h2>
                <div class="payment-info">
                    <label for="pickupDetails">Credit to Account</label>
                    <input type="text" id="pickupDetails" class="form-control" value="<?php echo htmlspecialchars($_SESSION['fullname']); ?>">
                </div>
                <div class="payment-info">
                    <label for="paymentAmountCredit">Amount</label>
                    <input type="text" id="paymentAmountCredit" class="paymentAmount form-control" name="paymentAmount" oninput="formatCurrency(this)">
                </div>
            </div>

        </div>
    </form>

    <script>
        function updatePaymentInfo(paymentType) {
            const paymentAmount = document.getElementById('paymentAmount');
            const paymentAmountPickup = document.getElementById('paymentAmountPickup');
            const paymentAmountCredit = document.getElementById('paymentAmountCredit');

            paymentAmount.value = '';
            paymentAmountPickup.value = '';
            paymentAmountCredit.value = '';

            const gcash = document.getElementById('gcash');
            const paymaya = document.getElementById('paymaya');
            const cash = document.getElementById('cash');
            const credit = document.getElementById('credit');
            const paymentDetails = document.getElementById('paymentDetails');
            const referenceNum = document.getElementById('reference_num');
            const submitButton = document.getElementById('placeOrderButton');

            gcash.style.display = (paymentType === 'gcash') ? 'block' : 'none';
            paymaya.style.display = (paymentType === 'maya') ? 'block' : 'none';
            cash.style.display = (paymentType === 'cash') ? 'block' : 'none';
            credit.style.display = (paymentType === 'credit') ? 'block' : 'none';

            referenceNum.required = (paymentType === 'gcash' || paymentType === 'maya');
            paymentDetails.style.display = (paymentType === 'gcash' || paymentType === 'maya') ? 'block' : 'none';

            submitButton.disabled = true;
            submitButton.style.backgroundColor = 'gray';
            validatePayment(paymentAmount, submitButton);
        }

        function formatCurrency(input) {
            let value = input.value.replace(/[^0-9.]/g, '');
            const amount = parseFloat(value);
            if (!isNaN(amount)) {
                input.value = '₱' + Math.floor(amount).toLocaleString('en-PH');
                document.getElementById('paymentAmountDisplay').value = Math.floor(amount);
            } else {
                input.value = '';
                document.getElementById('paymentAmountDisplay').value = '';
            }

            const submitButton = document.getElementById('placeOrderButton');
            validatePayment(input, submitButton);
        }

        function validatePayment(paymentAmountInput, submitButton) {
            const totalPrice = parseFloat('<?php echo $totalPrice; ?>');
            const paymentAmount = parseFloat(paymentAmountInput.value.replace(/[^0-9.]/g, '')) || 0;

            if (paymentAmount < totalPrice || paymentAmount === 0) {
                submitButton.disabled = true;
                submitButton.style.backgroundColor = 'gray';
            } else {
                submitButton.disabled = false;
                submitButton.style.backgroundColor = '';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const paymentAmountInput = document.getElementById('paymentAmount');
            const submitButton = document.getElementById('placeOrderButton');

            paymentAmountInput.addEventListener('input', function() {
                const paymentValue = this.value.trim();
                const paymentAmount = parseFloat(paymentValue.replace(/[^0-9.]/g, '')) || 0;
                validatePayment(paymentAmountInput, submitButton);
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
                $('#statusMessage').html('<i class="fas fa-check text-success fw-bold"></i> Order Placed successfully!');
                $('#statusModal').modal('show');
                setTimeout(function() {
                    $('#statusModal').modal('hide');
                    window.location.href = 'index.php';
                    <?php unset($_SESSION['update_success']); ?>
                }, 3000);
            <?php endif; ?>
        });
    </script>
</body>

</html>