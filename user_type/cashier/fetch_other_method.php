<?php
include '../../db/conn.php';
session_start();
ob_start();

$data = json_decode(file_get_contents('php://input'), true);

error_log(print_r($data, true));

if (isset($data['orderNumbers'], $data['reference_num'], $data['payment_method'], $data['paymentAmount'])) {
    $orderNumbers = $data['orderNumbers'];
    $referenceNum = $data['reference_num'];
    $cashAmount = $data['paymentAmount'];
    $paymentMethod = $data['payment_method'];
    date_default_timezone_set('Asia/Manila');
    $order_date = date('Y-m-d h:i A');

    try {
        $conn->beginTransaction();

        foreach ($orderNumbers as $orderNumber) {
            $stmt = $conn->prepare("UPDATE orders SET order_date = ?, order_status = 'paid', payment_method = ?, cash_amount = ? WHERE order_number = ?");
            $stmt->execute([$order_date, $paymentMethod, $cashAmount, $orderNumber]);

            do {
                $newOrderNumber = rand(1000000, 9999999);
                $checkStmt = $conn->prepare("SELECT COUNT(*) FROM checkout WHERE order_number = ?");
                $checkStmt->execute([$newOrderNumber]);
            } while ($checkStmt->fetchColumn() > 0);

            $stmt = $conn->prepare("UPDATE checkout SET order_number = ?, order_date = ?, order_status = 'paid', payment_method = ?, cash_amount = ?, reference_num = ? WHERE order_number = ?");
            $stmt->execute([$newOrderNumber, $order_date, $paymentMethod, $cashAmount, $referenceNum, $orderNumber]);
        }

        $conn->commit();
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error updating payment: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request data.']);
}
