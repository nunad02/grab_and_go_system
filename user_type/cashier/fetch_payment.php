<?php
include '../../db/conn.php';
session_start();
ob_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (isset($data['orderNumbers']) && isset($data['cashAmount'])) {
        $orderNumbers = $data['orderNumbers'];
        $cashAmount = $data['cashAmount'];
        date_default_timezone_set('Asia/Manila');
        $order_date = date('Y-m-d h:i A');

        try {
            $conn->beginTransaction();

            foreach ($orderNumbers as $orderNumber) {
                $stmt = $conn->prepare("UPDATE orders SET order_date = ?, order_status = 'paid', payment_method = 'cash', cash_amount = ? WHERE order_number = ?");
                $stmt->execute([$order_date, $cashAmount, $orderNumber]);

                $newOrderNumber = rand(1000000, 9999999);

                while (true) {
                    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM checkout WHERE order_number = ?");
                    $checkStmt->execute([$newOrderNumber]);
                    if ($checkStmt->fetchColumn() == 0) {
                        break;
                    }
                    $newOrderNumber = rand(1000000, 9999999);
                }

                $stmt = $conn->prepare("UPDATE checkout SET order_number = ?, order_date = ?, order_status = 'paid', payment_method = 'cash', cash_amount = ? WHERE order_number = ?");
                $stmt->execute([$newOrderNumber, $order_date, $cashAmount, $orderNumber]);
            }

            $conn->commit();
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            $conn->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid request data.']);
    }
}