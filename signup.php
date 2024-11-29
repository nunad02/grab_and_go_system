<?php
include 'db/conn.php';
session_start();
ob_start();

if (isset($_GET['category'])) {
    $_SESSION['category'] = htmlspecialchars($_GET['category']);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fullname = htmlspecialchars($_POST['fullname']);
    $email = htmlspecialchars($_POST['email']);
    $password = $_POST['password'];
    $hashed_password = sha1($password);

    $category = isset($_SESSION['category']) ? $_SESSION['category'] : 'customer';
    $status = 'active';
    $date_created = date('Y-m-d');

    try {
        $checkEmailStmt = $conn->prepare("SELECT * FROM account WHERE email = :email");
        $checkEmailStmt->bindParam(':email', $email);
        $checkEmailStmt->execute();

        if ($checkEmailStmt->rowCount() > 0) {
            $_SESSION['email_exists'] = true;
        } else {
            $stmt = $conn->prepare("INSERT INTO account (fullname, email, password, date_created, category, status) 
                                    VALUES (:fullname, :email, :password, :date_created, :category, :status)");
            $stmt->bindParam(':fullname', $fullname);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password', $hashed_password);
            $stmt->bindParam(':date_created', $date_created);
            $stmt->bindParam(':category', $category);
            $stmt->bindParam(':status', $status);

            if ($stmt->execute()) {
                $_SESSION['account_created'] = true;
                header('Location: signup.php');
                exit();
            } else {
                echo "Error: Could not execute the statement.";
            }
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
    <?php include_once 'header.php' ?>
    <link rel="stylesheet" href="css/login.css">
</head>

<body>
    <div class="sidebar">
        <div class="logo-container">
            <div class="cart-icon">
                <img src="img/cart.png" alt="Cart Icon" class="cart">
                <img src="img/logo.png" alt="Logo" class="logo">
            </div>
            <h2>Grab&Go</h2>
            <div class="text-one">
                <h1>Welcome Back!</h1>
            </div>
            <div class="text-two">
                <p>To keep connected with us please <br>login with your personal info</p>
            </div>
            <div class="btn-container">
                <a href="login.php?<?php echo isset($_SESSION['category']) ? $_SESSION['category'] : ''; ?>" class="signBtn">SIGN IN</a>
            </div>
        </div>
    </div>

    <div class="modal fade" id="successModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-body">
                    <h5 class="text-center"><i class="fas fa-check-circle text-success"></i> Your account has been successfully created! You can now log in and start using it.</h5>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="errorModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-body">
                    <h5 class="text-center"><i class="fas fa-exclamation-circle text-danger"></i> Email is already existed. Please use a different email.</h5>
                </div>
            </div>
        </div>
    </div>

    <div class="form-container">
        <div>
            <h1 style="color: #1494D5;">Create Account</h1>
        </div>
        <div>
            <p>Sign up to get started</p>
        </div>

        <form action="signup.php" method="POST">
            <div class="user-info">
                <div class="input-container">
                    <i class="fas fa-user icon"></i>
                    <input type="text" name="fullname" placeholder="Name" required>
                </div>
                <div class="input-container">
                    <i class="fas fa-envelope icon"></i>
                    <input type="email" name="email" placeholder="Email" required>
                </div>
                <div class="input-container password-container">
                    <i class="fas fa-lock icon"></i>
                    <input type="password" name="password" placeholder="Password" id="password" required>
                    <i class="fas fa-eye" id="togglePassword" onclick="togglePasswordVisibility()"></i>
                </div>
                <button type="submit" class="btn-sign-up">SIGN UP</button>
            </div>
        </form>
    </div>

    <script>
        function togglePasswordVisibility() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('togglePassword');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        $(document).ready(function() {
            <?php if (isset($_SESSION['account_created'])) : ?>
                $('#successModal').modal('show');
                setTimeout(function() {
                    $('#successModal').modal('hide');
                }, 2500);
                <?php unset($_SESSION['account_created']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['email_exists'])) : ?>
                $('#errorModal').modal('show');
                setTimeout(function() {
                    $('#errorModal').modal('hide');
                }, 2500);
                <?php unset($_SESSION['email_exists']); ?>
            <?php endif; ?>
        });
    </script>

</body>

</html>