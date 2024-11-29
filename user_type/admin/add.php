<?php
include '../../db/conn.php';
session_start();
ob_start();

if (isset($_GET['category'])) {
    $_SESSION['category'] = htmlspecialchars($_GET['category']);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $product_category = isset($_POST['product_category']) ? htmlspecialchars($_POST['product_category']) : '';
    $product_name = isset($_POST['product_name']) ? htmlspecialchars($_POST['product_name']) : '';
    $size = isset($_POST['size']) ? htmlspecialchars($_POST['size']) : '';
    $stock_status = isset($_POST['stock_status']) ? htmlspecialchars($_POST['stock_status']) : '';
    $in_stock = isset($_POST['in_stock']) ? htmlspecialchars($_POST['in_stock']) : '';
    $quantity = isset($_POST['quantity']) ? htmlspecialchars($_POST['quantity']) : '';
    $price = isset($_POST['price']) ? htmlspecialchars($_POST['price']) : '';
    $sales = isset($_POST['sales']) ? htmlspecialchars($_POST['sales']) : '';

    $product_image = '';
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] == UPLOAD_ERR_OK) {
        $target_dir = "../../products/";
        $target_file = $target_dir . basename($_FILES["product_image"]["name"]);
        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        $check = getimagesize($_FILES["product_image"]["tmp_name"]);
        if ($check !== false) {
            if (move_uploaded_file($_FILES["product_image"]["tmp_name"], $target_file)) {
                $product_image = $target_file;
            } else {
                echo "Sorry, there was an error uploading your file.";
            }
        } else {
            echo "File is not an image.";
        }
    }

    $purchase_date = date('Y-m-d');

    try {
        $stmt = $conn->prepare("INSERT INTO products (purchase_date, product_category, product_name, size, stock_status, in_stock, quantity, price, sales, product_image) VALUES (:purchase_date, :product_category, :product_name, :size, :stock_status, :in_stock, :quantity, :price, :sales, :product_image)");
        $stmt->bindParam(':purchase_date', $purchase_date);
        $stmt->bindParam(':product_category', $product_category);
        $stmt->bindParam(':product_name', $product_name);
        $stmt->bindParam(':size', $size);
        $stmt->bindParam(':stock_status', $stock_status);
        $stmt->bindParam(':in_stock', $in_stock);
        $stmt->bindParam(':quantity', $quantity);
        $stmt->bindParam(':price', $price);
        $stmt->bindParam(':sales', $sales);
        $stmt->bindParam(':product_image', $product_image);

        if ($stmt->execute()) {
            header('Location: index.php?status=success');
            exit();
        } else {
            echo "Error: Could not execute the statement.";
        }
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }

    $conn = null;
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grab & Go | SIGN UP</title>
    <?php include_once '../../header.php' ?>
    <link rel="shortcut icon" href="../../img/logo.png" type="image/x-icon">
    <style>
        body {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Georgia, sans-serif;
        }


        .form-container {
            width: 100%;
            height: auto;
            background-color: #C0DAFE;
            padding-top: 40px;
            transition: width 0.3s ease, margin-left 0.3s ease;
        }

        .form-container h1 {
            text-align: center;
            font-size: 50px;
            font-weight: bold;
            color: #fff;
        }

        .form-container p {
            margin-top: -10px;
            font-size: 18px;
            text-align: center;
            font-weight: 400;
        }

        .user-info {
            margin-top: 50px;
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
        }

        .input-container {
            position: relative;
            width: 90%;
            max-width: 700px;
            margin-bottom: 20px;
        }

        .user-info input,
        .user-info select {
            width: 100%;
            font-size: 20px;
            font-weight: 400;
            padding: 5px 20px;
            border-radius: 5px;
            border: 1px solid #ccc;
            color: #000;
        }

        .user-info button {
            background-color: #74BAF8;
            padding: 10px 50px;
            margin-top: 30px;
            text-decoration: none;
            color: #fff;
            font-size: 20px;
            font-weight: bold;
            border-radius: 20px;
            border: none;
        }

        .user-info button:hover {
            background-color: #1494D5;
        }

        @media (max-width: 768px) {
            .form-container {
                width: 100%;
                margin-left: 0;
                padding: 20px;
            }

            .form-container h1 {
                font-size: 30px;
            }

            .form-container p {
                font-size: 16px;
            }

            .input-container {
                width: 100%;
            }

            .user-info input {
                font-size: 18px;
                padding: 10px 20px 10px 45px;
            }

            .user-info button {
                padding: 10px 30px;
                font-size: 18px;
            }
        }

        @media (max-width: 480px) {

            .form-container h1 {
                font-size: 24px;
            }

            .form-container p {
                font-size: 14px;
            }

            .user-info input {
                font-size: 16px;
                padding: 8px 15px 8px 45px;
            }

            .user-info button {
                padding: 8px 20px;
                font-size: 16px;
            }
        }
    </style>
</head>

<body>
    <div class="form-container">
        <div>
            <h1>ADD NEW PRODUCT</h1>
        </div>
        <form action="index.php" method="POST" enctype="multipart/form-data">
            <div class="user-info">
                <div class="input-container">
                    <input type="hidden" name="purchase_date">
                </div>
                <div class="input-container">
                    <select id="product_category" name="product_category" class="product-category p-2 ">
                        <option value="" disabled selected>Select product category</option>
                        <option value="Drinks">Drinks</option>
                        <option value="Biscuits">Biscuits</option>
                        <option value="Coffee_and_Milk">Coffee & Milk</option>
                        <option value="Sweets">Sweets</option>
                        <option value="Canned_Goods">Canned Goods</option>
                        <option value="Condiments">Condiments</option>
                        <option value="Snacks">Snacks</option>
                        <option value="Noodles">Noodles</option>
                        <option value="Soap">Soap</option>
                        <option value="Beauty_Essentials">Beauty Essentials</option>
                    </select>
                </div>
                <div class="input-container">
                    <input type="text" name="product_name" placeholder="Product Name">
                </div>
                <div class="input-container">
                    <input type="text" name="size" placeholder="Size">
                </div>
                <div class="input-container">
                    <input type="text" name="stock_status" placeholder="Stock Status">
                </div>
                <div class="input-container">
                    <input type="text" name="in_stock" placeholder="In Stock">
                </div>
                <div class="input-container">
                    <input type="text" name="quantity" placeholder="Quantity">
                </div>
                <div class="input-container">
                    <input type="text" name="price" placeholder="Price">
                </div>
                <!-- <div class="input-container">
                    <input type="text" name="sales" placeholder="Sales">
                </div> -->
                <div class="input-container">

                    <div class="form-group" style="border: 2px solid #000; padding: 10px; max-height: 100%; height: 250px; width: 100%;">
                        <center><img id="previewImage" src="#" alt="Preview" style="max-width: 100%; height: 230px; display: none;"></center>
                    </div>
                    <center>
                        <label class="btn btn-update btn-file w-100 my-1 bg-primary text-light ">
                            Upload Image <input type="file" id="fileButton" name="product_image" accept="image/*" class="form-control mb-3" onchange="displayImage(this)" style="display: none;">
                        </label>
                    </center>
                </div>
                <button type="submit" class="btn-sign-up">SUBMIT</button>
            </div><br><br>
        </form>
    </div>

    <script>
        function displayImage(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();

                reader.onload = function(e) {
                    document.getElementById('previewImage').src = e.target.result;
                    document.getElementById('previewImage').style.display = 'block';
                }

                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>

</html>