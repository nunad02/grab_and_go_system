<?php
include '../../db/conn.php';
session_start();
ob_start();


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../../phpmailer/src/Exception.php';
require '../../phpmailer/src/PHPMailer.php';
require '../../phpmailer/src/SMTP.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get the order number from the POST request
        $orderNumber = $_POST['order_number'];

        // Set the timezone to Asia/Manila
        date_default_timezone_set('Asia/Manila');
        $disapprove_date = date('Y-m-d h:i A');

        // Update the order status to Disapproved and set the disapprove date
        $stmt = $conn->prepare("UPDATE checkout SET status = 'Disapproved', disapprove_date = ? WHERE order_number = ?");
        $stmt->execute([$disapprove_date, $orderNumber]);

        // Fetch order details
        $stmt = $conn->prepare("SELECT order_number, customer_name, order_date, product_name, quantity, price, cash_amount, disapprove_date FROM checkout WHERE order_number = ?");
        $stmt->execute([$orderNumber]);
        $orderDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($orderDetails) {
            // Fetch customer email
            $stmt = $conn->prepare("SELECT email FROM account WHERE fullname = ?");
            $stmt->execute([$orderDetails[0]['customer_name']]);
            $customerEmail = $stmt->fetchColumn();

            if ($customerEmail) {
                // Email content
                $emailContent = "
                    <html>
                        <head>
                            <style>
                                body { font-family: Arial, sans-serif; color: #333; }
                                h3 { color: #cc0000; }
                                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                                table, th, td { border: 1px solid #ddd; }
                                th, td { padding: 8px; text-align: left; }
                                th { background-color: #f2f2f2; }
                                .footer { font-size: 12px; color: #777; margin-top: 20px; }
                            </style>
                        </head>
                        <body>
                            <h3>Order Disapproval Notification</h3>
                            <p>Your order has been disapproved. Here are the details:</p>
                            <p><strong>Order Number:</strong> {$orderDetails[0]['order_number']}</p>
                            <p><strong>Full Name:</strong> {$orderDetails[0]['customer_name']}</p>
                            <p><strong>Order Date:</strong> {$orderDetails[0]['order_date']}</p>
                            
                            <table>
                                <thead>
                                    <tr>
                                        <th>Product Name</th>
                                        <th>Quantity</th>
                                        <th>Price</th>
                                    </tr>
                                </thead>
                                <tbody>";

                foreach ($orderDetails as $order) {
                    $emailContent .= "
                        <tr>
                            <td>{$order['product_name']}</td>
                            <td>{$order['quantity']}</td>
                            <td>₱{$order['price']}</td>
                        </tr>";
                }

                $emailContent .= "
                            </tbody>
                        </table>

                        <p><strong>Cash Amount:</strong> ₱{$orderDetails[0]['cash_amount']}</p>
                        <p><strong>Disapproval Date:</strong> {$disapprove_date}</p>

                        <p class='footer'>Thank you for your understanding.</p>
                    </body>
                </html>
                ";

                // Sending the email
                require 'PHPMailer/PHPMailerAutoload.php';
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'omscmpcgovph@gmail.com'; // Your email
                $mail->Password   = 'lzxtyttgxzvbaxvb'; // Your email password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port       = 465;

                $mail->setFrom('omscmpcgovph@gmail.com', 'OMSC MPC');
                $mail->addAddress($customerEmail);
                $mail->isHTML(true);
                $mail->Subject = 'Order Disapproval Notification';
                $mail->Body    = $emailContent;

                $mail->send();

                echo json_encode(['success' => true, 'message' => 'Order disapproved and email sent.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Customer email not found.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Order details not found.']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}


?>