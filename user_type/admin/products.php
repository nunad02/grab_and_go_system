<?php
include '../../db/conn.php';
session_start();
ob_start();

if (!isset($_SESSION['account_id'])) {
    header("Location: ../../index.php");
    exit();
}
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$product_category = isset($_GET['product_category']) ? htmlspecialchars($_GET['product_category']) : 'Drinks';
$_SESSION['product_category'] = $product_category;

try {
    $product_stmt = $conn->query("SELECT * FROM products WHERE product_category = '$product_category'");
    $products = $product_stmt->fetchAll(PDO::FETCH_ASSOC);
    $count = 1;
} catch (PDOException $e) {
    die("Error fetching products: " . $e->getMessage());
}

if (isset($_GET['category'])) {
    $_SESSION['category'] = htmlspecialchars($_GET['category']);
}

if (!isset($_SESSION['profile']) || empty($_SESSION['profile'])) {
    $_SESSION['profile'] = 'profile.png';
}

$profileImage = htmlspecialchars('../../img/' . $_SESSION['profile']);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['profileImage'])) {
    $email = $_SESSION['email'];
    $uploadDir = '../../img/';
    $uploadFile = $uploadDir . basename($_FILES['profileImage']['name']);
    $oldProfileImage = $_SESSION['profile'];

    if (move_uploaded_file($_FILES['profileImage']['tmp_name'], $uploadFile)) {
        $newImagePath = basename($_FILES['profileImage']['name']);

        $sql = "UPDATE account SET profile = ? WHERE email = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt->execute([$newImagePath, $email])) {
            $sql = "UPDATE orders SET customer_profile = ? WHERE customer_name = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$newImagePath, $_SESSION['fullname']]);

            $sql = "UPDATE checkout SET customer_profile = ? WHERE customer_name = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$newImagePath, $_SESSION['fullname']]);

            $_SESSION['profile'] = $newImagePath;
            echo json_encode(['success' => true, 'newImagePath' => $newImagePath]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to update the database.']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to upload the file.']);
    }
    exit;
}


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../../phpmailer/src/Exception.php';
require '../../phpmailer/src/PHPMailer.php';
require '../../phpmailer/src/SMTP.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['sendEmail'])) {
    $newPassword = $_POST['newPassword'];
    $newFullname = $_POST['newFullname'];
    $newEmail = $_POST['newEmail'];
    $currentEmail = $_SESSION['email'];
    $currentFullname = $_SESSION['fullname'];

    $hashedPassword = empty($newPassword) ? null : sha1($newPassword);

    if (empty($newFullname)) {
        $newFullname = $_SESSION['fullname'];
    }
    if (empty($newEmail)) {
        $newEmail = $_SESSION['email'];
    }

    try {
        $conn->beginTransaction();

        if ($hashedPassword) {
            $stmt = $conn->prepare("UPDATE account SET fullname = ?, email = ?, password = ? WHERE email = ?");
            $executeParams = [$newFullname, $newEmail, $hashedPassword, $currentEmail];
        } else {
            $stmt = $conn->prepare("UPDATE account SET fullname = ?, email = ? WHERE email = ?");
            $executeParams = [$newFullname, $newEmail, $currentEmail];
        }

        if ($stmt->execute($executeParams)) {
            $_SESSION['fullname'] = $newFullname;
            $_SESSION['email'] = $newEmail;

            $stmt = $conn->prepare("UPDATE orders SET customer_name = ? WHERE customer_name = ?");
            $stmt->execute([$newFullname, $currentFullname]);

            $stmt = $conn->prepare("UPDATE checkout SET customer_name = ? WHERE customer_name = ?");
            $stmt->execute([$newFullname, $currentFullname]);

            $conn->commit();

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
                $mail->addAddress($newEmail, $newFullname);

                $mail->isHTML(true);
                $mail->Subject = 'Your Account Information Has Been Successfully Updated';
                $mail->Body    = 'Dear ' . htmlspecialchars($newFullname) . ',<br><br>' .
                    'We are writing to inform you that your account information has been successfully updated in our system. Please find the updated details of your account below:<br><br>' .
                    '<strong>Account Details:</strong><br>' .
                    'Email: ' . htmlspecialchars($newEmail) . '<br>' .
                    'Full Name: ' . htmlspecialchars($newFullname) . '<br>';

                if ($hashedPassword) {
                    $mail->Body .= 'Password: ' . htmlspecialchars($newPassword) . '<br><br>';
                }

                $mail->Body .= 'If you did not make this request or if you encounter any issues, please do not hesitate to contact our support team immediately.<br><br>' .
                    'We are committed to ensuring the security and accuracy of your account details, and we appreciate your continued trust in our services.<br><br>' .
                    'Thank you for being part of the OMSC MPC community.<br><br>' .
                    'Best regards,<br>' .
                    'The OMSC MPC Team';

                $mail->AltBody = 'Dear ' . htmlspecialchars($newFullname) . ",\n\n" .
                    'We are writing to inform you that your account information has been successfully updated in our system. Please find the updated details of your account below:\n\n' .
                    'Account Details:\n' .
                    'Email: ' . htmlspecialchars($newEmail) . "\n" .
                    'Full Name: ' . htmlspecialchars($newFullname) . "\n";

                if ($hashedPassword) {
                    $mail->AltBody .= 'Password: ' . htmlspecialchars($newPassword) . "\n\n";
                }

                $mail->AltBody .= "If you did not make this request or if you encounter any issues, please contact our support team immediately.\n\n" .
                    "Thank you for being part of the OMSC MPC community.\n\n" .
                    "Best regards,\n" .
                    "The OMSC MPC Team";


                $_SESSION['update_success'] = true;
                header('Location: products.php');
            } catch (Exception $e) {
                $_SESSION['update_error'] = true;
                $mail->ErrorInfo . '");</script>';
            }
        } else {
            $conn->rollBack();
            echo '<script>alert("Error updating account information.");</script>';
        }
    } catch (Exception $e) {
        $conn->rollBack();
        echo '<script>alert("Transaction failed: ' . $e->getMessage() . '");</script>';
    }
    exit();
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
    $exp_date = isset($_POST['exp_date']) ? htmlspecialchars($_POST['exp_date']) : '';

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
        $stmt = $conn->prepare("INSERT INTO products (purchase_date, product_category, product_name, size, stock_status, in_stock, quantity, price, sales, product_image, exp_date) VALUES (:purchase_date, :product_category, :product_name, :size, :stock_status, :in_stock, :quantity, :price, :sales, :product_image, :exp_date)");
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
        $stmt->bindParam(':exp_date', $exp_date);

        if ($stmt->execute()) {
            $_SESSION['add_success'] = true;
            header('Location: products.php');
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
    <title>Grab & Go | Add Products</title>
    <link rel="shortcut icon" href="../../img/logo.png" type="image/x-icon">
    <?php include_once '../../header.php' ?>
    <link rel="stylesheet" href="../../css/cashier_main.css">
    <style>
        .form-container {
            width: 100%;
            height: auto;
            padding-top: 10px;
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

        .btn-category {
            margin-top: 20px;
            max-width: 700px;
            display: flex;
            justify-content: space-between;
            font-family: Arial, sans-serif;
        }

        .btn-category a {
            width: 85px;
            text-decoration: none;
            font-size: 11px;
            text-align: center;
            font-weight: bold;
            color: #000;
        }

        .btn-category a.active,
        .btn-category a.focus,
        .btn-category a.active {
            background-color: #007bff;
            color: white;
        }

        .product-table th {
            background-color: #f2f2f2;
        }



        .input-container {
            position: relative;
            width: 100%;
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

        .fixed-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1000;
        }

        .modal-body label {
            font-weight: bold;
        }

        @media (max-width: 576px) {
            .modal-dialog {
                width: 100%;
                margin: 0;
            }
        }
    </style>
</head>

<body>
    <div class="sidebar">
        <div class="logo-container">
            <div class="cart-icon">
                <img src="../../img/cart.png" alt="Cart Icon" class="cart">
                <img src="../../img/logo.png" alt="Logo" class="logo">
            </div>
            <h2>Grab&Go</h2>
            <div class="button-container">
                <a href="index.php" id="home"> <i class="fas fa-home"></i>Home</a>
                <a href="statistics.php" id="statistics"> <i class="fas fa-chart-line"></i> Statistics</a>
                <a href="products.php" class="active" id="products"><i class="fas fa-boxes"></i> Manage Products </a>
                <a href="feedback.php" id="feedback"><i class="fas fa-comments"></i> Feedback</a>
                <a href="accounts.php" id="accounts"><i class="fas fa-user"></i> Accounts </a>
                <a href="about.php"><i class="fas fa-info-circle"></i> About</a>
            </div>
        </div>
        <div class="hamburger-menu">
            <div class="bar"></div>
            <div class="bar"></div>
            <div class="bar"></div>
        </div>
        <div class="dropdown">
            <div class="button-container">
                <a href="index.php" id="home"> <i class="fas fa-home"></i>Home</a>
                <a href="statistics.php" id="statistics"> <i class="fas fa-chart-line"></i> Statistics</a>
                <a href="products.php" class="active" id="products"><i class="fas fa-boxes"></i> Manage Products </a>
                <a href="feedback.php" id="feedback"><i class="fas fa-comments"></i> Feedback</a>
                <a href="accounts.php" id="accounts"><i class="fas fa-user"></i> Accounts </a>
                <a href="about.php"><i class="fas fa-info-circle"></i> About</a>
            </div>
        </div>
    </div>

    <div class="home-container">
        <nav class="navbar">
            <div class="container-fluid">
                <a class="navbar-brand fw-bold fs-3 ms-2 font-effect-shadow-multiple">MANAGE PRODUCTS</a>
                <img src="<?php echo $profileImage; ?>" alt="Profile" class="d-flex me-2 border border-2 border-primary-subtle" style="height: 40px; width: 40px; border-radius: 50%;" id="welcomeUser" title="Menu" data-toggle="tooltip">
            </div>
        </nav>

        <div id="userOptions" class="user-profile">
            <div class="profile-wrapper">
                <img src="../../img/<?php echo htmlspecialchars($_SESSION['profile']); ?>" alt="click here to update profile" class="d-flex border border-2 border-primary-subtle mb-3" style="height: 100px; width: 100px; border-radius: 50%; margin: 0 auto; cursor: pointer;" id="profileImage" title='Click me to change profile' data-toggle='tooltip'>

                <input type="file" id="profileInput" style="display: none;">
                <div class="profile-details text-center">
                    <p class="fw-bold"><?php echo htmlspecialchars($_SESSION['email']); ?></p>
                    <p><?php echo htmlspecialchars($_SESSION['fullname']); ?></p>
                </div>

                <form id="changePasswordForm" method="post" action="products.php" class="text-center" style="display: none;">
                    <div class="mb-3">
                        <input type="text" class="form-control mb-1" id="newFullname" name="newFullname" placeholder="Fullname">
                        <input type="email" class="form-control mb-1" id="newEmail" name="newEmail" placeholder="Email">

                        <div class="password-container" style="position: relative;">
                            <input type="password" class="form-control mb-1" id="newPassword" name="newPassword" placeholder="Password">
                            <span id="togglePassword" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); cursor: pointer;">
                                👁️
                            </span>
                        </div>
                    </div>

                    <button type="button" id="cancelButton" class="btn btn-sm btn-primary">Cancel</button>
                    <button type="submit" name="sendEmail" class="btn btn-sm btn-primary">Submit</button>
                </form>

                <div class="dropBtn mt-3">
                    <a class="btn text-center fw-bold w-100 btn-drop-menu btn-outline-primary" id="changePasswordButton">
                        Edit Account
                    </a>
                    <a class="btn text-center fw-bold w-100 btn-drop-menu btn-outline-primary my-1" id="logoutButton" data-bs-toggle="modal" data-bs-target="#logoutModal">
                        <i class="fas fa-sign-out-alt"></i>&nbsp; Logout
                    </a>
                </div>
            </div>
        </div>

        <div class="modal fade" id="logoutModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
            <div class="modal-dialog logout-modal-dialog">
                <div class="modal-content logout-modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title text-dark" id="staticBackdropLabel">Logout</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body modal-body-sm">
                        <p class="text-start" style="font-size: 14px;">Are you sure you want to log out? This action will end your session and require you to sign in again.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">No</button>
                        <button type="button" class="btn btn-primary d-block btn-sm">
                            <a href="../../includes/logout.php" style="text-decoration: none; color: white;">Yes</a>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="form-container">

            <div class="row row-cols-1 row-cols-md-6 ms-4 btn-category p-1" style="max-width: 96%; margin: 0 auto;">
                <a href="products.php?product_category=Drinks" id="drinksBtn" data-category="Drinks" class="btn btn-outline-primary btn-active">Drinks</a>
                <a href="products.php?product_category=Powder" id="powderBtn" data-category="Powder" class="btn btn-outline-primary">Powder</a>
                <a href="products.php?product_category=Biscuits" id="biscuitsBtn" data-category="Biscuits" class="btn btn-outline-primary">Biscuits</a>
                <a href="products.php?product_category=Candy" id="CandyBtn" data-category="Candy" class="btn btn-outline-primary">Candy</a>
                <a href="products.php?product_category=Chocolate" id="chocolateBtn" data-category="Chocolate" class="btn btn-outline-primary">Chocolate</a>
                <a href="products.php?product_category=Cans" id="cansBtn" data-category="Cans" class="btn btn-outline-primary">Cans</a>
                <a href="products.php?product_category=Condiments" id="condimentsBtn" data-category="Condiments" class="btn btn-outline-primary">Condiments</a>
                <a href="products.php?product_category=Chips" id="chipsBtn" data-category="Chips" class="btn btn-outline-primary">Chips</a>
                <a href="products.php?product_category=Spread" id="SpreadBtn" data-category="Spread" class="btn btn-outline-primary">Spread</a>
                <a href="products.php?product_category=Noodles" id="noodlesBtn" data-category="Noodles" class="btn btn-outline-primary">Noodles</a>
                <a href="products.php?product_category=Bath_Soap" id="bathSoapBtn" data-category="Bath_Soap" class="btn btn-outline-primary">Bath Soap</a>
                <a href="products.php?product_category=Beauty_Essentials" id="beautyBtn" data-category="Beauty_Essentials" class="btn btn-outline-primary">Beauty</a>
            </div>
            <div class="row row-cols-1 row-cols-md-6 ms-4 btn-category p-1 mb-3" style="max-width: 96%; margin: 0 auto;">
                <a href="products.php?product_category=Pasta" id="PastaBtn" data-category="Pasta" class="btn btn-outline-primary">Pasta</a>
                <a href="products.php?product_category=Conditioner" id="ConditionerBtn" data-category="Conditioner" class="btn btn-outline-primary">Conditioner</a>
                <a href="products.php?product_category=Fabcon" id="FabconBtn" data-category="Fabcon" class="btn btn-outline-primary">Fabcon</a>
                <a href="products.php?product_category=Cologne" id="CologneBtn" data-category="Cologne" class="btn btn-outline-primary">Cologne</a>
                <a href="products.php?product_category=Lotion" id="LotionBtn" data-category="Lotion" class="btn btn-outline-primary">Lotion</a>
                <a href="products.php?product_category=Shampoo" id="ShampooBtn" data-category="Shampoo" class="btn btn-outline-primary">Shampoo</a>
                <a href="products.php?product_category=Toothpaste" id="ToothpasteBtn" data-category="Toothpaste" class="btn btn-outline-primary">Toothpaste</a>
                <a href="products.php?product_category=Toothbrush" id="ToothbrushBtn" data-category="Toothbrush" class="btn btn-outline-primary">Toothbrush</a>
                <a href="products.php?product_category=Hygiene" id="HygieneBtn" data-category="Hygiene" class="btn btn-outline-primary">Hygiene</a>
                <a href="products.php?product_category=Perfume" id="PerfumeBtn" data-category="Perfume" class="btn btn-outline-primary">Perfume</a>
                <a href="products.php?product_category=Cleaner" id="CleanerBtn" data-category="Cleaner" class="btn btn-outline-primary">Cleaner</a>
                <a href="products.php?product_category=Others" id="OthersBtn" data-category="Others" class="btn btn-outline-primary">Others</a>
            </div>
            <form>
                <div class="user-container mb-3" style="width: 95%; margin: 0 auto;">
                    <input type="search" class="w-100 p-1" style="outline: none;" name="search_account" id="search_account" placeholder="Search..." oninput="searchOrder()">
                </div>
            </form>

            <table class="table table-hover product-table" id="orderDetailsTable" style="border: 2px solid #aaa; border-right: none; border-left: none; width: 95%; margin: 0 auto; font-family: Arial, sans-serif;">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>SO</th>
                        <th>Product Name</th>
                        <th>Size</th>
                        <th>Stock Status</th>
                        <th>In Stock</th>
                        <th>Actual</th>
                        <th>Price</th>
                        <th>Solds</th>
                        <th>Exp. Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $count => $product) :
                        $in_stock_text = htmlspecialchars($product['in_stock']);
                        preg_match('/\d+/', $in_stock_text, $matches);
                        $in_stock = isset($matches[0]) ? (int)$matches[0] : 0;
                        $stock_status_text = '';
                        $text_color = '';

                        if ($in_stock == 0) {
                            $text_color = '#FA325a';
                            $stock_status_text = 'Out of Stock';
                        } elseif ($in_stock >= 1 && $in_stock <= 5) {
                            $text_color = '#F548EF';
                            $stock_status_text = 'Running Out Items';
                        } elseif ($in_stock >= 6 && $in_stock <= 10) {
                            $text_color = '#FFE45E';
                            $stock_status_text = 'Low Stock';
                        } elseif ($in_stock >= 11) {
                            $text_color = '#59F97F';
                            $stock_status_text = 'In Stock';
                        }

                        $product_id = $product['product_id'];
                        $rating_query = "SELECT AVG(rating) AS avg_rating FROM feedback WHERE product_id = :product_id";
                        $stmt = $conn->prepare($rating_query);
                        $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
                        $stmt->execute();
                        $rating_row = $stmt->fetch();
                        $avg_rating = $rating_row['avg_rating'];

                        $display_rating = !is_null($avg_rating) ? round($avg_rating, 1) : 0;

                        $full_stars = floor($display_rating);
                        $half_star = ($display_rating - $full_stars >= 0.5) ? 1 : 0;
                        $empty_stars = 5 - $full_stars - $half_star;
                    ?>
                        <tr class="accordion-toggle m-0 product-row" style="cursor: pointer;">
                            <td class="fw-bold count"><?php echo $count + 1; ?></td>
                            <td class="text-start p-2 purchase_date" style="font-size: 15px;"><?php echo htmlspecialchars($product['purchase_date']); ?></td>
                            <td class="text-start p-2 product_id" style="font-size: 15px;"><?php echo htmlspecialchars($product['product_id']); ?></td>
                            <td class="text-start p-2 product_name" style="font-size: 15px;"><?php echo htmlspecialchars($product['product_name']); ?></td>
                            <td class="text-start p-2 size" style="font-size: 15px;"><?php echo htmlspecialchars($product['size']); ?></td>
                            <td class="text-start p-2" style="font-size: 15px;">
                                <span style="color: <?php echo $text_color; ?>; font-weight: bold; "><?php echo $stock_status_text; ?></span>
                            </td>
                            <td class="text-start p-2" style="font-size: 15px;">
                                <span><?php echo htmlspecialchars($product['in_stock']); ?></span>
                            </td>
                            <td class="text-start p-2 quantity" style="font-size: 15px;"><?php echo htmlspecialchars($product['quantity']); ?></td>
                            <td class="text-start p-2 price" style="font-size: 15px;">₱<?php echo htmlspecialchars($product['price']); ?></td>
                            <td class="text-start p-2 price text-center" style="font-size: 15px;"><?php echo htmlspecialchars($product['solds']); ?></td>
                            <td class="text-start p-2" style="font-size: 15px;">
                                <span><?php echo htmlspecialchars($product['exp_date']); ?></span>
                            </td>
                            <td class="text-start p-2">
                                <a class="btn-edit" onclick="editProduct(<?php echo htmlspecialchars(json_encode($product)); ?>)">Edit</a> ||
                                <a data-bs-toggle="modal" data-bs-target="#confirmDeleteModal" class="delete-user text-danger" data-id="<?php echo htmlspecialchars($product['product_id']); ?>" onclick="deleteItem('<?php echo htmlspecialchars($product['product_id']); ?>')">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Delete Confirmation Modal -->
            <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="confirmDeleteModalLabel">Confirm Deletion</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" value="" name="deleteID" id="deleteID" class="form-control mb-3">
                            Are you sure you want to delete this Item?
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" name="btnDeleteProduct" class="btn btn-danger" id="confirmDeleteButton">Delete</button>
                        </div>
                    </div>
                </div>
            </div>
        </div><br><br>

        <div class="btn-container-bottom fixed-btn">
            <button type="button" class="btn btn-primary " onclick="exportToExcel()">
                <i class="fas fa-file-export"></i> Export
            </button>

            <button type="button" class="btn btn-primary" onclick="openAddModal()">
                <i class="fas fa-plus"></i> Add Product
            </button>
        </div>

        <script src="../../js/cashier_script.js"> </script>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.0/xlsx.full.min.js"></script>
        <script>
            function exportToExcel() {
                var table = document.getElementById("orderDetailsTable");

                var wb = XLSX.utils.book_new();
                var ws = XLSX.utils.table_to_sheet(table, {
                    raw: true
                });

                var range = XLSX.utils.decode_range(ws['!ref']);
                range.e.c = range.e.c - 1;
                ws['!ref'] = XLSX.utils.encode_range(range);

                var colWidths = [];
                for (var C = range.s.c; C <= range.e.c; C++) {
                    var maxWidth = 10;
                    for (var R = range.s.r; R <= range.e.r; R++) {
                        var cell = ws[XLSX.utils.encode_cell({
                            r: R,
                            c: C
                        })];
                        if (cell && cell.v) {
                            var cellText = cell.v.toString();
                            maxWidth = Math.max(maxWidth, cellText.length + 2);
                        }
                    }
                    colWidths.push({
                        wch: maxWidth
                    });
                }
                ws['!cols'] = colWidths;

                XLSX.utils.book_append_sheet(wb, ws, "Products");
                XLSX.writeFile(wb, "Products.xlsx");
            }
        </script>

        <form method="post" id="addProductForm" action="products.php" enctype="multipart/form-data">
            <div class="modal fade" id="modalAddProduct" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="modalAddProductLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content shadow-lg border-0 rounded-3">
                        <div class="modal-header bg-primary text-white rounded-top">
                            <h5 class="modal-title" id="modalAddProductLabel">Add Product</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="product_category" class="form-label">Product Category:</label>
                                    <select id="product_category" name="product_category" class="form-select" required>
                                        <option value="" disabled selected>Select product category</option>
                                        <option value="Bath_Soap">Bath Soap</option>
                                        <option value="Beauty_Essentials">Beauty Essentials</option>
                                        <option value="Biscuits">Biscuits</option>
                                        <option value="Candy">Candy</option>
                                        <option value="Cans">Cans</option>
                                        <option value="Chips">Chips</option>
                                        <option value="Chocolate">Chocolate</option>
                                        <option value="Cleaner">Cleaner</option>
                                        <option value="Cologne">Cologne</option>
                                        <option value="Conditioner">Conditioner</option>
                                        <option value="Condiments">Condiments</option>
                                        <option value="Drinks">Drinks</option>
                                        <option value="Fabcon">Fabcon</option>
                                        <option value="Hygiene">Hygiene</option>
                                        <option value="Lotion">Lotion</option>
                                        <option value="Noodles">Noodles</option>
                                        <option value="Others">Others</option>
                                        <option value="Pasta">Pasta</option>
                                        <option value="Perfume">Perfume</option>
                                        <option value="Powder">Powder</option>
                                        <option value="Shampoo">Shampoo</option>
                                        <option value="Spread">Spread</option>
                                        <option value="Toothbrush">Toothbrush</option>
                                        <option value="Toothpaste">Toothpaste</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="product_name" class="form-label">Product Name:</label>
                                    <input type="text" id="product_name" name="product_name" placeholder="Product Name" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="size" class="form-label">Size:</label>
                                    <input type="text" id="size" name="size" placeholder="Size" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label for="stock_status" class="form-label">Stock Status:</label>
                                    <input type="text" id="stock_status" name="stock_status" placeholder="Stock Status" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label for="in_stock" class="form-label">In Stock:</label>
                                    <input type="text" id="in_stock" name="in_stock" placeholder="In Stock" class="form-control" required min="0">
                                </div>
                                <div class="col-md-6">
                                    <label for="quantity" class="form-label">Quantity:</label>
                                    <input type="number" id="quantity" name="quantity" placeholder="Quantity" class="form-control" required min="1">
                                </div>
                                <div class="col-md-6">
                                    <label for="price" class="form-label">Price:</label>
                                    <input type="text" id="price" name="price" placeholder="Price" class="form-control" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="exp_date" class="form-label">Expiration Date:</label>
                                    <input type="date" id="exp_date" name="exp_date" placeholder="Expiration Date" class="form-control" required>
                                </div>
                                <div class="col-md-12 mb-4">
                                    <div class="form-group text-center border border-primary rounded p-3">
                                        <center>
                                            <img id="previewImage" src="#" alt="Preview" class="img-fluid rounded mb-2" style="max-height: 230px; display: none;">
                                        </center>
                                        <label class="btn btn-primary w-100" style="border-radius: 5px;">
                                            Upload Image
                                            <input type="file" id="fileButton" name="product_image" accept="image/*" class="form-control" onchange="displayImage(this)" style="display: none;" required>
                                        </label>
                                        <small class="text-muted">Image must be less than 2MB.</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="addProduct" class="btn btn-success">Submit</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>


        <!-- Edit Product Modal -->
        <form method="post" id="editProductForm" action="update-product.php">
            <div class="modal fade" id="modalEditProduct" tabindex="-1" aria-labelledby="editProductLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editProductLabel">Edit Product</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="editProductId" id="editProductId">
                            <div class="mb-3">
                                <label for="editProductName" class="form-label">Product Name:</label>
                                <input type="text" class="form-control" id="editProductName" name="editProductName" required>
                            </div>
                            <div class="mb-3">
                                <label for="editSize" class="form-label">Size:</label>
                                <input type="text" class="form-control" id="editSize" name="editSize" required>
                            </div>
                            <div class="mb-3">
                                <label for="editInStock" class="form-label">In Stock:</label>
                                <input type="text" class="form-control" id="editInStock" name="editInStock" required>
                            </div>
                            <div class="mb-3">
                                <label for="editQuantity" class="form-label">Quantity:</label>
                                <input type="number" class="form-control" id="editQuantity" name="editQuantity" required>
                            </div>
                            <div class="mb-3">
                                <label for="editPrice" class="form-label">Price:</label>
                                <input type="text" class="form-control" id="editPrice" name="editPrice" required>
                            </div>
                            <div class="mb-3">
                                <label for="editExpDate" class="form-label">Expiration Date:</label>
                                <input type="date" class="form-control" id="editExpDate" name="editExpDate" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="editProduct" class="btn btn-primary">Save changes</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <script>
            function editProduct(product) {
                $('#editProductId').val(product.product_id);
                $('#editProductName').val(product.product_name);
                $('#editSize').val(product.size);
                $('#editInStock').val(product.in_stock);
                $('#editQuantity').val(product.quantity);
                $('#editPrice').val(product.price);
                $('#editExpDate').val(product.exp_date);

                $('#modalEditProduct').modal('show');
            }
        </script>


        <!-- Status Modal -->
        <div class="modal fade" id="statusModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-body">
                        <h5 class="text-center" id="statusMessage"></h5>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <?php include_once '../../includes/goToTop.php'; ?>
    <script>
        function searchOrder() {
            var input = document.getElementById("search_account").value.toLowerCase();
            var rows = document.querySelectorAll(".product-row");

            rows.forEach(function(row) {
                var count = row.querySelector(".count").textContent.toLowerCase();
                var product_id = row.querySelector(".product_id").textContent.toLowerCase();
                var purchase_date = row.querySelector(".purchase_date").textContent.toLowerCase();
                var product_name = row.querySelector(".product_name").textContent.toLowerCase();
                var size = row.querySelector(".size").textContent.toLowerCase();
                var quantity = row.querySelector(".quantity").textContent.toLowerCase();
                var price = row.querySelector(".price").textContent.toLowerCase();

                if (count.includes(input) || product_id.includes(input) || purchase_date.includes(input) || product_name.includes(input) || size.includes(input) || quantity.includes(input) || price.includes(input)) {
                    row.style.display = "table-row";
                } else {
                    row.style.display = "none";
                }
            });
        }
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($_SESSION['delete_success'])) : ?>
                $('#statusMessage').html('<i class="fas fa-check text-success"></i> Product deleted successfully.');
                $('#statusModal').modal('show');
                setTimeout(function() {
                    $('#statusModal').modal('hide');
                    <?php unset($_SESSION['delete_success']); ?>
                }, 2000);
            <?php elseif (isset($_SESSION['delete_error'])) : ?>
                $('#statusMessage').html('<i class="fas fa-warning text-danger"></i> Error deleting product.');
                $('#statusModal').modal('show');
                setTimeout(function() {
                    $('#statusModal').modal('hide');
                    <?php unset($_SESSION['delete_error']); ?>
                }, 2000);
            <?php elseif (isset($_SESSION['edit_success'])) : ?>
                $('#statusMessage').html('<i class="fas fa-check text-success"></i> Product updated successfully.');
                $('#statusModal').modal('show');
                setTimeout(function() {
                    $('#statusModal').modal('hide');
                    <?php unset($_SESSION['edit_success']); ?>
                }, 2000);
            <?php elseif (isset($_SESSION['edit_error'])) : ?>
                $('#statusMessage').html('<i class="fas fa-warning text-danger"></i> Error updating product.');
                $('#statusModal').modal('show');
                setTimeout(function() {
                    $('#statusModal').modal('hide');
                    <?php unset($_SESSION['edit_error']); ?>
                }, 2000);
            <?php elseif (isset($_SESSION['add_success'])) : ?>
                $('#statusMessage').html('<i class="fas fa-check text-success"></i> New product added successfully.');
                $('#statusModal').modal('show');
                setTimeout(function() {
                    $('#statusModal').modal('hide');
                    <?php unset($_SESSION['add_success']); ?>
                }, 2000);
            <?php elseif (isset($_SESSION['add_error'])) : ?>
                $('#statusMessage').html('<i class="fas fa-warning text-danger"></i> Error adding product.');
                $('#statusModal').modal('show');
                setTimeout(function() {
                    $('#statusModal').modal('hide');
                    <?php unset($_SESSION['add_error']); ?>
                }, 2000);
            <?php endif; ?>
        });

        function openAddModal() {
            $('#modalAddProduct').modal('show');
        }
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.delete-user').forEach(function(button) {
                button.addEventListener('click', function() {
                    var itemId = this.getAttribute('data-id');
                    deleteItem(itemId);
                });
            });
        });

        function deleteItem(itemId) {
            $('#deleteID').val(itemId);

            $('#confirmDeleteButton').off('click').on('click', function() {
                $('#confirmDeleteModal').modal('hide');

                fetch('delete_products.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            delete_item_id: itemId
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showStatusModal('Success', 'Item deleted successfully!', 'success');
                            setTimeout(function() {
                                window.location.reload();
                            }, 2000);
                        } else {
                            let errorMessage = 'Error deleting item.';
                            if (data.error) {
                                errorMessage += ' Details: ' + data.error;
                            }
                            showStatusModal('Error', errorMessage, 'danger');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showStatusModal('Error', 'Error deleting item. Please try again later.', 'danger');
                    });
            });
        }

        function showStatusModal(title, message, status) {
            $('#statusModalLabel').text(title);
            $('#statusMessage').html('<i class="fas fa-success text-' + status + '"></i> ' + message);
            $('#statusModal').modal('show');
        }
    </script>

    </script>


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

    <script src="../../js/cashier_script.js"> </script>
    <script>
        document.getElementById("profileImage").addEventListener("click", function() {
            document.getElementById("profileInput").click();
        });

        document.getElementById("profileInput").addEventListener("change", function() {
            const file = this.files[0];
            if (file) {
                const formData = new FormData();
                formData.append("profileImage", file);

                fetch("products.php", {
                        method: "POST",
                        body: formData,
                    })
                    .then((response) => response.json())
                    .then((data) => {
                        if (data.success) {
                            document.getElementById("profileImage").src =
                                "../../img/" + data.newImagePath;
                            document.getElementById("welcomeUser").src =
                                "../../img/" + data.newImagePath;
                        } else {
                            alert("Failed to upload the profile picture.");
                        }
                    })
                    .catch((error) => {
                        console.error("Error:", error);
                    });
            }
        });

        document.addEventListener("DOMContentLoaded", function() {
            const buttons = document.querySelectorAll(".btn-category a");
            const activeCategory = "<?php echo $product_category; ?>";

            buttons.forEach((button) => {
                if (button.getAttribute("data-category") === activeCategory) {
                    button.classList.add("active");
                } else {
                    button.classList.remove("active");
                }

                button.addEventListener("click", function(event) {
                    buttons.forEach((button) => button.classList.remove("active"));
                    this.classList.add("active");
                    localStorage.setItem("activeButton", this.id);
                });
            });
        });

        document
            .getElementById("changePasswordButton")
            .addEventListener("click", function() {
                document.getElementById("changePasswordForm").style.display = "block";
            });

        document.addEventListener("DOMContentLoaded", function() {
            var cancelButton = document.getElementById("cancelButton");

            cancelButton.addEventListener("click", function() {
                document.getElementById("changePasswordForm").style.display = "none";
            });
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($_SESSION['update_error'])): ?>
                $('#statusMessage').html('<i class="fas fa-warning text-danger fw-bold"></i>Error Updating Account information');
                $('#statusModal').modal('show');
                setTimeout(function() {
                    $('#statusModal').modal('hide');
                    <?php unset($_SESSION['update_error']); ?>
                }, 3000);
            <?php elseif (isset($_SESSION['update_success'])): ?>
                $('#statusMessage').html('<i class="fas fa-check text-success fw-bold"></i> Your account information has been updated!');
                $('#statusModal').modal('show');
                setTimeout(function() {
                    $('#statusModal').modal('hide');
                    window.location.href = 'products.php';
                    <?php unset($_SESSION['update_success']); ?>
                }, 3000);
            <?php endif; ?>
        });
    </script>

    <script>
        const togglePassword = document.getElementById('togglePassword');
        const passwordField = document.getElementById('newPassword');

        togglePassword.addEventListener('click', function() {
            const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordField.setAttribute('type', type);

            this.textContent = type === 'password' ? '👁️' : '🙈';
        });
    </script>
</body>

</html>