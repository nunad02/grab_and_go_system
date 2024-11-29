<?php
include '../../db/conn.php';
session_start();
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $product_id = $_POST['editProductId'];
    $product_name = $_POST['editProductName'];
    $size = $_POST['editSize'];
    $in_stock = $_POST['editInStock'];
    $quantity = $_POST['editQuantity'];
    $price = $_POST['editPrice'];
    $exp_date = $_POST['editExpDate'];

    try {
        $stmt = $conn->prepare("UPDATE products SET product_name = :product_name, size = :size, in_stock = :in_stock, quantity = :quantity, price = :price, exp_date = :exp_date WHERE product_id = :product_id");
        $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
        $stmt->bindParam(':product_name', $product_name, PDO::PARAM_STR);
        $stmt->bindParam(':size', $size, PDO::PARAM_STR);
        $stmt->bindParam(':in_stock', $in_stock, PDO::PARAM_STR);
        $stmt->bindParam(':quantity', $quantity, PDO::PARAM_STR);
        $stmt->bindParam(':price', $price, PDO::PARAM_STR);
        $stmt->bindParam(':exp_date', $exp_date, PDO::PARAM_STR);

        if ($stmt->execute()) {
            $_SESSION['edit_success'] = true;
            echo json_encode(['success' => true]);
        } else {
            $_SESSION['edit_error'] = true;
            echo json_encode(['success' => false]);
        }
    } catch (PDOException $e) {
        $_SESSION['edit_error'] = true;
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    $_SESSION['status'] = 'error';
    $_SESSION['status_message'] = 'Invalid request method.';
}

header('Location: products.php');
exit;
