<?php
include '../../db/conn.php';
session_start();

if (isset($_POST['order_number'])) {
    $order_number = $_POST['order_number'];

    $sql = "SELECT customer_name, order_number, product_name, price, quantity, order_date, payment_method, cash_amount 
            FROM checkout 
            WHERE order_number = :order_number AND order_status = 'paid'";

    $stmt = $conn->prepare($sql);
    $stmt->execute([':order_number' => $order_number]);

    $order_details = [];
    $order_details['items'] = [];
    $total_price = 0;

    while ($row = $stmt->fetch()) {
        if (empty($order_details['customer_name'])) {
            $order_details['customer_name'] = $row['customer_name'];
            $order_details['order_number'] = $row['order_number'];
            $order_details['order_date'] = $row['order_date'];
            $order_details['payment_method'] = $row['payment_method'];
            $order_details['cash_amount'] = $row['cash_amount'];
        }

        $item_total = $row['price'] * $row['quantity'];
        $total_price += $row['price'];

        $order_details['items'][] = [
            'order_name' => $row['product_name'],
            'price' => $row['price'],
            'quantity' => $row['quantity'],
        ];
    }

    $order_details['total_price'] = $total_price;

    header('Content-Type: application/json');
    echo json_encode($order_details);
    exit;
}
