<?php
include '../../db/conn.php';
session_start();

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
                    <h2>New Feedback Submission</h2>
                    <p><strong>Order Number:</strong> {$order_number}</p>
                    <p><strong>Product Name:</strong> {$product_name}</p>
                    <p><strong>Rating:</strong> {$rating} out of 5</p>
                    <p><strong>Feedback:</strong>" . nl2br(htmlspecialchars($feedback)) . "</p>
                    <p><strong>Submitted by:</strong> " . htmlspecialchars($_SESSION['fullname']) . "</p>
                    <p><strong>Email:</strong> " . htmlspecialchars($_SESSION['email']) . "</p>
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
            <div class="loading-text">Feedback submitted successfully! Redirecting...</div>
        </div>
        <script>
            setTimeout(function() {
                window.location.href = "notification.php";  
            }, 1000);
        </script>
    </body>
    </html>
    ';
    exit;
}
