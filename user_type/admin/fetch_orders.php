<?php
include '../../db/conn.php';
session_start();
ob_start();

if (isset($_POST['payment_method'])) {
    $payment_method = $_POST['payment_method'];

    $sql = "SELECT * FROM checkout WHERE order_status = 'paid'";

    if ($payment_method === 'daily') {
        $sql .= " AND DATE(order_date) = CURDATE()";
    } elseif ($payment_method === 'offline') {
        $sql .= " AND user_type = 'Cashier'";
    } elseif ($payment_method === 'cash') {
        $sql .= " AND user_type = 'Customer' AND payment_method = 'cash'";
    } elseif ($payment_method === 'credit') {
        $sql .= " AND user_type = 'Customer' AND payment_method = 'credit'";
    } else {
        $sql .= " AND payment_method = :payment_method";
    }

    $sql .= " ORDER BY order_date DESC";
    $stmt = $conn->prepare($sql);

    if ($payment_method === 'daily' || $payment_method === 'offline' || $payment_method === 'cash' || $payment_method === 'credit') {
        $stmt->execute();
    } else {
        $stmt->execute([':payment_method' => $payment_method]);
    }

    $order_sums = [];
    $displayed_order_numbers = [];

    while ($row = $stmt->fetch()) {
        $order_number = $row['order_number'];
        if (!isset($order_sums[$order_number])) {
            $order_sums[$order_number] = 0;
        }
        $order_sums[$order_number] += $row['price'];
    }

    if ($payment_method === 'daily' || $payment_method === 'offline' || $payment_method === 'cash' || $payment_method === 'credit') {
        $stmt->execute();
    } else {
        $stmt->execute([':payment_method' => $payment_method]);
    }

    while ($row = $stmt->fetch()) {
        $order_number = $row['order_number'];

        if (in_array($order_number, $displayed_order_numbers)) {
            continue;
        }

        $displayed_order_numbers[] = $order_number;

        $customer_profile = $row["customer_profile"];
        $image_path = "../../img/" . $customer_profile;

        if (empty($customer_profile) || !file_exists($image_path)) {
            $image_path = "../../img/default-profile.png";
        }

        $total_price = "₱" . number_format($order_sums[$order_number], 2);
        echo '<tr>';
        echo '<td><img src="' . htmlspecialchars($image_path) . '" alt="Profile Image" style="width: 30px; height: 30px; border-radius: 50%;"> ' . htmlspecialchars($row['customer_name']) . '</td>';
        echo '<td>' . htmlspecialchars($row['order_number']) . '</td>';
        echo '<td>' . $total_price . '</td>';
        echo '<td>' . htmlspecialchars($row['order_date']) . '</td>';
        echo '<td class="text-start p-1">
            <button class="btn btn-success viewOrder p-1 btn-sm"  data-order="' . htmlspecialchars($order_number) . '" data-bs-toggle="modal" data-bs-target="#orderModal">View Order</button>
        </td>';
        echo '</tr>';
    }
}
