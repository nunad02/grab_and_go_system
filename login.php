<?php
include 'db/conn.php';
session_start();
ob_start();

if (isset($_GET['category'])) {
    $_SESSION['category'] = htmlspecialchars($_GET['category']);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['category'])) {
        $_SESSION['category'] = $_POST['category'];
    }
    if (isset($_POST['submit'])) {
        $email = $_POST['email'];
        $password = sha1($_POST['password']);
        $category = $_SESSION['category'];

        try {
            $stmt = $conn->prepare("SELECT * FROM account WHERE email = :email AND password = :password AND category = :category AND status = 'active'");
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password', $password);
            $stmt->bindParam(':category', $category);
            $stmt->execute();

            $row = $stmt->fetch();

            if ($row) {
                $_SESSION['account_id'] = $row['account_id'];
                $_SESSION['fullname'] = $row['fullname'];
                $_SESSION['email'] = $row['email'];
                $_SESSION['password'] = $row['password'];
                $_SESSION['date_created'] = $row['date_created'];
                $_SESSION['category'] = $row['category'];
                $_SESSION['status'] = $row['status'];
                $_SESSION['profile'] = $row['profile'];
                $_SESSION['address'] = $row['address'];
                $_SESSION['charge'] = $row['charge'];
                
                $_SESSION['login_success'] = true;
                
                switch ($category) {
                    case 'Admin':
                        $_SESSION['redirect_to'] = 'user_type/admin/index.php';
                        break;
                    case 'Cashier':
                        $_SESSION['redirect_to'] = 'user_type/cashier/index.php';
                        break;
                    default:
                        $_SESSION['redirect_to'] = 'user_type/customer/index.php';
                        break;
                }
                header('Location: login.php');
                exit;
            } else {
                $_SESSION['login_failed'] = true;
                header('Location: login.php');
                exit;
            }
        } catch (PDOException $e) {
            echo "Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grab & Go | LOGIN</title>
    <?php include_once 'header.php' ?>
    <link rel="stylesheet" href="css/signup.css">
    <style>
        .full-width-form-container {
            width: 100%;
        }
        .hide-sidebar .sidebar {
            display: none;
        }
    </style>
</head>

<body class="<?php echo (isset($_SESSION['category']) && ($_SESSION['category'] == 'Admin' || $_SESSION['category'] == 'Cashier')) ? 'hide-sidebar' : ''; ?>">
    <div class="sidebar">
        <div class="logo-container">
            <div class="cart-icon">
                <img src="img/cart.png" alt="Cart Icon" class="cart">
                <img src="img/logo.png" alt="Logo" class="logo">
            </div>
            <h2>Grab&Go</h2>
            <div class="text-one">
                <h1>Hello Friend!</h1>
            </div>
            <div class="text-two">
                <p>Enter your personal details <br>and start grocery with us</p>
            </div>
            <div class="btn-container">
                <a href="signup.php?category=<?php echo isset($_SESSION['category']) ? $_SESSION['category'] : ''; ?>" class="signBtn">SIGN UP</a>
            </div>
        </div>
    </div>

    <div class="form-container <?php echo (isset($_SESSION['category']) && ($_SESSION['category'] == 'Admin' || $_SESSION['category'] == 'Cashier')) ? 'full-width-form-container' : ''; ?>">
        <div>
            <h1 style="color: #1494D5;">Sign in to Grab&Go</h1>
        </div>
        <div>
            <p>Please login to your account</p>
        </div>
        <form action="" method="POST">
            <div class="user-info">
                <div class="input-container">
                    <i class="fas fa-envelope icon"></i>
                    <input type="email" name="email" placeholder="Email" required>
                    <input type="hidden" name="category" value="<?php echo isset($_SESSION['category']) ? $_SESSION['category'] : ''; ?>">
                </div>
                <div class="input-container password-container">
                    <i class="fas fa-lock icon"></i>
                    <input type="password" name="password" placeholder="Password" id="password" required>
                    <i class="fas fa-eye" id="togglePassword" onclick="togglePasswordVisibility()"></i>
                </div>
                <button type="submit" name="submit" class="btn-sign-up">SIGN IN</button>
                <a href="index.php" class="mt-3">Cancel and Return to Homepage</a>
            </div>
        </form>
    </div>

    <div class="modal fade" id="statusModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-body">
                    <h5 class="text-center" id="statusMessage"></h5>
                </div>
            </div>
        </div>
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

        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($_SESSION['login_failed'])): ?>
                $('#statusMessage').html('<i class="fas fa-warning text-danger"></i> Login failed. Please check your credentials.');
                $('#statusModal').modal('show');
                setTimeout(function() {
                    $('#statusModal').modal('hide');
                    <?php unset($_SESSION['login_failed']); ?>
                }, 2000);
            <?php elseif (isset($_SESSION['login_success'])): ?>
                $('#statusMessage').html('<i class="fas fa-check text-success"></i> Login successfully.');
                $('#statusModal').modal('show');
                setTimeout(function() {
                    $('#statusModal').modal('hide');
                    window.location.href = '<?php echo $_SESSION['redirect_to']; ?>';
                    <?php unset($_SESSION['login_success']); ?>
                }, 2000);
            <?php endif; ?>
        });
    </script>
</body>

</html>
