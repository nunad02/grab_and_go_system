<?php
include 'db/conn.php';
session_start();
ob_start();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grab & Go | HOME</title>
    <?php include_once 'header.php' ?>
    <link rel="stylesheet" href="css/styles.css">
</head>

<body>
    <div class="sidebar">
        <div class="logo-container">
            <div class="cart-icon">
                <img src="img/cart.png" alt="Cart Icon" class="cart">
                <img src="img/logo.png" alt="Logo" class="logo">
                <p>OMSC MPC</p>
            </div>
            <h2>Grab&Go</h2>
        </div>
        <img src="img/side.jpg" class="bottom-img" alt="">
    </div>
    <div class="custom-alert" id="customAlert" style="display: none;">
        <p class="fw-bold">omscmpc.grab&go.com.ph says</p>
        <p>Please select login category</p>
        <button onclick="closeCustomAlert()">OK</button>
    </div>
    <div class="home-container"><br>
        <h1>Customer/Cashier/Admin</h1>
        <div class="form-container">
            <div class="image">
                <img src="img/omsc.png" alt="Logo" class="logo-left">
                <img src="img/logo.png" alt="Logo" class="logo-right">
            </div>
            <div class="text">
                <p>OCCIDENTAL MINDORO STATE COLLEGE <br> MULTI-PURPOSE COOPERATIVE (OMSC MPC)</p>
            </div>
            <div class="option">
                <form id="userForm" action="login.php" method="post">
                    <div class="user-select">
                        <select id="userSelect" name="category" class="user-select" required>
                            <option value="" disabled selected>- Select type of user to log in -</option>
                            <option value="Customer">Customer</option>
                            <option value="Cashier">Cashier</option>
                            <option value="Admin">Admin</option>
                        </select>
                    </div>
                    <div class="btn-container">
                        <button type="submit" class="btn btn-primary" onclick="validateForm()">Next</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showCustomAlert() {
            document.getElementById('customAlert').style.display = 'block';
        }

        function closeCustomAlert() {
            document.getElementById('customAlert').style.display = 'none';
        }

        function validateForm() {
            var userSelect = document.getElementById('userSelect');
            if (userSelect.value === "") {
                showCustomAlert();
            } else {
                document.getElementById('userForm').submit();
            }
        }

        setTimeout(showCustomAlert, 2000);
    </script>
</body>

</html>
