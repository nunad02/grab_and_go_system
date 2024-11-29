<?php
include '../../db/conn.php';
session_start();
ob_start();
if (!isset($_SESSION['account_id'])) {
    header("Location: ../../index.php");
    exit();
}

$product_category = isset($_GET['product_category']) ? htmlspecialchars($_GET['product_category']) : 'Drinks';
$_SESSION['product_category'] = $product_category;

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

            $sql = "UPDATE feedback SET customer_profile = ? WHERE customer_name = ?";
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

if (isset($_POST['addOrder'])) {
    $requiredFields = ['product_name', 'price', 'quantity', 'product_image'];

    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field])) {
            echo "Required POST data missing: $field.";
            exit();
        }
    }

    date_default_timezone_set('Asia/Manila');
    $order_date = date('Y-m-d h:i A');
    $customer_name = htmlspecialchars($_SESSION['fullname']);
    $user_type = $_SESSION['category'];
    $product_category = $_SESSION['product_category'];
    $product_name = htmlspecialchars($_POST['product_name']);
    $price_per_unit = htmlspecialchars($_POST['price']);
    $quantity = htmlspecialchars($_POST['quantity']);
    $total_price = $price_per_unit * $quantity;
    $order_status = "cart";
    $product_image = htmlspecialchars($_POST['product_image']);
    $customer_profile = $_SESSION['profile'];

    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare("SELECT product_id FROM products WHERE product_name = ?");
        $stmt->execute([$product_name]);
        $product = $stmt->fetch();

        if ($product) {
            $product_id = $product['product_id'];
        } else {
            echo "Product not found.";
            exit();
        }

        $stmt = $conn->prepare("SELECT order_number, order_status FROM orders WHERE customer_name = ? ORDER BY order_number DESC LIMIT 1");
        $stmt->execute([$customer_name]);
        $latestOrder = $stmt->fetch();

        if ($latestOrder) {
            if ($latestOrder['order_status'] == 'paid') {
                $order_number = $latestOrder['order_number'] + 1;
            } else {
                $order_number = $latestOrder['order_number'];
            }
        } else {
            $order_number = 10001;
        }

        do {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM orders WHERE order_number = ?");
            $stmt->execute([$order_number]);
            $orderExists = $stmt->fetchColumn() > 0;

            if ($orderExists && ($latestOrder && $latestOrder['order_status'] == 'paid')) {
                $order_number++;
            } else {
                break;
            }
        } while ($orderExists);

        $stmt = $conn->prepare("INSERT INTO orders (product_id, order_number, customer_name, user_type, order_date, product_category, product_name, price, quantity, order_status, product_image, customer_profile) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$product_id, $order_number, $customer_name, $user_type, $order_date, $product_category, $product_name, $total_price, $quantity, $order_status, $product_image, $customer_profile]);

        $conn->commit();

        header("Location: index.php?product_category=$product_category");
        exit();
    } catch (PDOException $e) {
        $conn->rollBack();
        echo "Error: " . $e->getMessage();
    }
    exit();
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_item'])) {
    if (isset($_POST['selectedProducts'])) {
        $selectedProducts = $_POST['selectedProducts'];
        $placeholders = rtrim(str_repeat('?,', count($selectedProducts)), ',');
        $sql = "UPDATE orders SET order_status = 'removed' WHERE product_name IN ($placeholders)";
        $stmt = $conn->prepare($sql);
        $stmt->execute($selectedProducts);
    }
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
    $newAddress = $_POST['newAddress'];
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
            $stmt = $conn->prepare("UPDATE account SET fullname = ?, email = ?, address = ?, password = ? WHERE email = ?");
            $executeParams = [$newFullname, $newEmail, $newAddress, $hashedPassword, $currentEmail];
        } else {
            $stmt = $conn->prepare("UPDATE account SET fullname = ?, email = ?, address = ? WHERE email = ?");
            $executeParams = [$newFullname, $newEmail, $newAddress, $currentEmail];
        }

        if ($stmt->execute($executeParams)) {
            $_SESSION['fullname'] = $newFullname;
            $_SESSION['email'] = $newEmail;
            $_SESSION['address'] = $newAddress;

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


                $mail->send();
                $_SESSION['update_success'] = true;
                header('Location: index.php');
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

$sql = "SELECT COUNT(*) as order_count FROM orders WHERE customer_name = :customer_name AND DATE(order_date) = CURDATE() ";
$stmt = $conn->prepare($sql);
$stmt->execute(['customer_name' => $_SESSION['fullname']]);
$orderCount = $stmt->fetchColumn();
?>



<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grab & Go | Home</title>
    <link rel="shortcut icon" href="../../img/logo.png" type="image/x-icon">
    <?php include_once '../../header.php' ?>
    <link rel="stylesheet" href="../../css/cus_main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        h6.card-name::-webkit-scrollbar {
            height: 5px;
        }

        h6.card-name::-webkit-scrollbar-thumb {
            background-color: #888;
            border-radius: 10px;
        }

        h6.card-name::-webkit-scrollbar-thumb:hover {
            background-color: #555;
        }

        h6.card-name::-webkit-scrollbar-track {
            background: #f1f1f1;
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
                <a href="index.php" id="home" class="active"><i class="fas fa-home"></i> Home</a>
                <a href="my_cart.php" id="my-cart"><i class="fas fa-shopping-cart"></i> My Cart</a>
                <a href="notification.php" id="Transactions"> <i class="fas fa-bell"></i> Transactions</a>
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
                <a href="index.php" id="home" class="active"><i class="fas fa-home"></i> Home</a>
                <a href="my_cart.php" id="my-cart"><i class="fas fa-shopping-cart"></i> My Cart</a>
                <a href="notification.php" id="Transactions"><i class="fas fa-bell"></i> Transactions</a>
                <a href="about.php"><i class="fas fa-info-circle"></i> About</a>
            </div>
        </div>
    </div>

    <div class="home-container">
        <nav class="navbar">
            <div class="container-fluid">
                <a class="navbar-brand fw-bold fs-3 ms-2 font-effect-shadow-multiple">HOME</a>
                <div class="position-relative me-3 mt-2 ms-auto">
                    <a href="my_cart.php"> <i class="fas fa-shopping-cart" style="font-size: 1.7rem; color: #007bff;" title="Cart" data-toggle="tooltip"></i></a>

                    <?php if ($orderCount > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            <?php echo $orderCount; ?>
                        </span>
                    <?php endif; ?>
                </div>
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

                <form id="changePasswordForm" method="post" action="index.php" class="text-center" style="display: none;">
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

        <div class="modal fade" id="statusModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-body">
                        <h5 class="text-center" id="statusMessage"></h5>
                    </div>
                </div>
            </div>
        </div>

        <div class="search-container mt-2">
            <form>
                <p class="text-start">What would you like to order?</p>
                <div class="input-container">
                    <i class="fas fa-search icon"></i>
                    <input type="search" name="search_product" id="search_product" placeholder="Search" oninput="search_product()">
                </div>
            </form>
        </div>

        <div class="orders-container mt-3 ms-4" style="font-family: Arial, sans-serif;">
            <div class="row row-cols-1 row-cols-md-3 me-2">
                <?php
                try {
                    $stmt = $conn->prepare("SELECT p.*, IFNULL(AVG(f.rating), 0) AS average_rating 
                        FROM products p
                        LEFT JOIN feedback f ON p.product_name = f.product_name
                        WHERE p.product_category = ?
                        GROUP BY p.product_name
                        ORDER BY p.product_name ASC");
                    $stmt->execute([$product_category]);

                    $product_found = false;
                    while ($product = $stmt->fetch()) {
                        $product_found = true;
                        $product_name = htmlspecialchars($product['product_name']);
                        $size = htmlspecialchars($product['size']);
                        $price = htmlspecialchars($product['price']);
                        $quantity = htmlspecialchars($product['quantity']);
                        $product_id = htmlspecialchars($product['product_id']);
                        $solds = htmlspecialchars($product['solds']);
                        $product_image = htmlspecialchars($product['product_image']);
                        $product_image_path = '../products/' . $product_image;
                        $average_rating = number_format($product['average_rating'], 1);

                        $rating_percentage = ($average_rating / 5) * 100;
                ?>
                        <div class="col mb-2 product_card" data-product-name="<?php echo $product_name; ?>" data-product-category="<?php echo $product['product_category']; ?>">
                            <div class="card">
                                <img src="<?php echo $product_image_path; ?>" style="height: 120px; width: auto;" class="card-img-top my-1" alt="<?php echo $product_name; ?>">
                                <div class="card-body d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 style="font-family: Arial, sans-serif; overflow-x: auto; white-space: nowrap; max-width: 180px;"
                                            class="card-name product-name">
                                            <?php echo (strlen($product_name) > 15) ? substr($product_name, 0, 15) . '...' : $product_name; ?>
                                        </h6>

                                        <h6 style="font-family: Arial, sans-serif;" class="card-price">₱<?php echo $price; ?></h6>
                                        <div class="star-rating" style="font-size: 14px; color: gold; font-family: Arial, sans-serif;">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star <?php echo $i <= round($average_rating) ? 'filled' : ''; ?>"></i>
                                            <?php endfor; ?>
                                            <span class="average-rating ms-2 fw-bold" style="color: black;">
                                                (<?php echo $average_rating; ?>)
                                            </span>
                                        </div>
                                    </div>
                                    <button class="btn btn-add" data-bs-toggle="modal" data-bs-target="#productModal"
                                        data-product-name="<?php echo $product_name; ?>"
                                        data-product-price="<?php echo $price; ?>"
                                        data-product-quantity="<?php echo $quantity; ?>"
                                        data-product-solds="<?php echo $solds; ?>"
                                        data-product-size="<?php echo $size; ?>"
                                        data-product-image="<?php echo $product_image_path; ?>">
                                        <i class="fas fa-add"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                <?php
                    }

                    $all_products_stmt = $conn->prepare("SELECT p.*, IFNULL(AVG(f.rating), 0) AS average_rating FROM products p LEFT JOIN feedback f ON p.product_name = f.product_name GROUP BY p.product_name ORDER BY p.product_name ASC");
                    $all_products_stmt->execute();
                    $all_products = $all_products_stmt->fetchAll(PDO::FETCH_ASSOC);

                    if (!$product_found) {
                        echo "<p>No products found in this category.</p>";
                    }
                } catch (PDOException $e) {
                    echo "Error: " . $e->getMessage();
                }
                ?>
            </div>
        </div>

        <script>
            const allProducts = <?php echo json_encode($all_products); ?>;

            function search_product() {
                var input = document.getElementById("search_product").value.toLowerCase();
                var cardsContainer = document.querySelector(".orders-container .row");
                cardsContainer.innerHTML = '';

                if (input !== "") {
                    allProducts.forEach(function(product) {
                        var productName = product.product_name.toLowerCase();

                        if (productName.includes(input)) {
                            var averageRating = product.average_rating ? parseFloat(product.average_rating) : 0;
                            var ratingStars = '';
                            for (let i = 1; i <= 5; i++) {
                                if (averageRating >= i) {
                                    ratingStars += `<i class="fas fa-star filled"></i>`;
                                } else if (averageRating >= i - 0.5) {
                                    ratingStars += `<i class="fas fa-star-half-alt filled"></i>`;
                                } else {
                                    ratingStars += `<i class="far fa-star"></i>`;
                                }
                            }
                            var productCard = `
                            <div class="col mb-2 product_card" data-product-name="${product.product_name}">
                                <div class="card">
                                    <img src="../products/${product.product_image}" style="height: 120px; width: auto;" class="card-img-top my-1" alt="${product.product_name}">
                                    <div class="card-body d-flex justify-content-between align-items-center">
                                        <div style="font-family: Arial, sans-serif;">
                                             <h6 style="font-family: Arial, sans-serif; overflow-x: auto; white-space: nowrap; max-width: 180px;" 
                                            class="card-name product-name" id="product-name">
                                            ${product.product_name}
                                            </h6>

                                            <h6 style="font-family: Arial, sans-serif;" class="card-price">₱${product.price}</h6>
                                            <div class="star-rating" style="font-size: 14px; font-family: Arial, sans-serif; color: gold;">
                                                ${ratingStars}
                                                <span class="average-rating ms-2 fw-bold" style="color: black;">(${averageRating.toFixed(1)})</span>
                                            </div>
                                        </div>
                                        <button class="btn btn-add" data-bs-toggle="modal" 
                                                data-bs-target="#productModal" 
                                                data-product-name="${product.product_name}" 
                                                data-product-price="${product.price}" 
                                                data-product-quantity="${product.quantity}" 
                                                data-product-solds="${product.solds}" 
                                                data-product-size="${product.size}" 
                                                data-product-image="../products/${product.product_image}">
                                            <i class="fas fa-add"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>`;
                            cardsContainer.innerHTML += productCard;
                        }
                    });
                } else {
                    allProducts.forEach(function(product) {
                        if (product.product_category === '<?php echo $product_category; ?>') {
                            var averageRating = product.average_rating ? parseFloat(product.average_rating) : 0;
                            var ratingStars = '';
                            for (let i = 1; i <= 5; i++) {
                                if (averageRating >= i) {
                                    ratingStars += `<i class="fas fa-star filled"></i>`;
                                } else if (averageRating >= i - 0.5) {
                                    ratingStars += `<i class="fas fa-star-half-alt filled"></i>`;
                                } else {
                                    ratingStars += `<i class="far fa-star"></i>`;
                                }
                            }
                            var productCard = `
                            <div class="col mb-2 product_card" data-product-name="${product.product_name}">
                                <div class="card">
                                    <img src="../products/${product.product_image}" style="height: 120px; width: auto;" class="card-img-top my-1" alt="${product.product_name}">
                                    <div class="card-body d-flex justify-content-between align-items-center">
                                        <div style="font-family: Arial, sans-serif;">
                                            <h6 style="font-family: Arial, sans-serif; overflow-x: auto; white-space: nowrap; max-width: 180px;" 
                                            class="card-name product-name" id="product-name">
                                            ${product.product_name}
                                            </h6>
                                            <h6 style="font-family: Arial, sans-serif;" class="card-price">₱${product.price}</h6>
                                            <div class="star-rating" style="font-size: 14px; font-family: Arial, sans-serif; color: gold;">
                                                ${ratingStars}
                                                <span class="average-rating ms-2 fw-bold" style="color: black;">(${averageRating.toFixed(1)})</span>
                                            </div>
                                        </div>
                                        <button class="btn btn-add" data-bs-toggle="modal" 
                                                data-bs-target="#productModal" 
                                                data-product-name="${product.product_name}" 
                                                data-product-price="${product.price}" 
                                                data-product-quantity="${product.quantity}" 
                                                data-product-solds="${product.solds}" 
                                                data-product-size="${product.size}" 
                                                data-product-image="../products/${product.product_image}">
                                            <i class="fas fa-add"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>`;
                            cardsContainer.innerHTML += productCard;
                        }
                    });
                }
            }

            document.addEventListener("DOMContentLoaded", search_product);
            document.getElementById("search_product").addEventListener("keyup", search_product);
        </script>
        <script>
            const element = document.getElementById('product-name');
            const text = element.textContent;
            if (text.length > 15) {
                element.textContent = text.substring(0, 15) + '...';
            }
        </script>

        <div id="productModal" class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="productModalHeader" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-sm" style="max-width: 300px;">
                <div class="modal-content">
                    <div class="modal-header d-flex justify-content-between align-items-center">
                        <h4 class="text-dark m-0 me-auto" data-bs-dismiss="modal" aria-label="Close"><i class="fas fa-arrow-left"></i></h4>
                        <h4 class="text-dark m-0 ms-auto"><a href="my_cart.php"><i class="fas fa-shopping-cart"></i></a></h4>
                    </div>
                    <div class="modal-body text-center" style="font-family: Arial, sans-serif;">
                        <img id="productImage" alt="Full product" style="width: 80%; height: 200px; margin: 5px 0; object-fit: cover;">
                        <div class="line" style="height: 2px; width: 100%; background: #333;"></div>
                        <h3 class="modal-title fs-6 text-dark text-start" id="productModalHeader"><span id="productTitle" class="fw-bold"></span> <span class="modal-title fs-6 text-success text-end" id="productSize"></span></h3>

                        <h5 class="modal-title fs-6 text-dark text-start" id="productSolds"></h5>
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="quantity-control my-3">
                                <button type="button" class="border-none fw-bold bg-transparent" id="decreaseQuantity">-</button>
                                <span id="quantity">1</span>
                                <button type="button" class="border-none fw-bold bg-transparent" id="increaseQuantity">+</button>
                            </div>
                            <div class="text-container text-end">
                                <div id="product_price" class="fs-4 fw-bold text-primary"></div>
                                <div id="stock_quantity" class="fs-6 fw-bold"></div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer w-100">
                        <form action="" method="POST" id="addToCartForm" enctype="multipart/form-data" class="w-100">
                            <input type="hidden" name="customer_name" id="hiddenPCustomerName" value="<?php echo $_SESSION['fullname']; ?>">
                            <input type="hidden" name="product_name" id="hiddenProductName">
                            <input type="hidden" name="price" id="hiddenProductPrice">
                            <input type="hidden" name="size" id="hiddenProductSize">
                            <input type="hidden" name="quantity" id="hiddenProductQuantity">
                            <input type="hidden" name="product_image" id="hiddenProductImage">
                            <button type="submit" name="addOrder" class="btn btn-add-to-cart d-block w-100 btn-primary text-light"><i class="fas fa-cart-plus"></i> Add to Cart</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="category-container">
            <p class="text-start fw-bold">Categories</p>
            <div class="row row-cols-2" style="margin-top: -10px;">
                <div class="col mb-2">
                    <div class="category-card">
                        <a href="index.php?product_category=Drinks"><img src="../../img/categories/Drinks.png" class="drinks" id="drinks" alt="Drinks"></a>
                    </div>
                </div>
                <div class="col mb-2">
                    <div class="category-card">
                        <a href="index.php?product_category=Powder"><img src="../../img/categories/Powder.png" class="powder" id="powder" alt="Powder"></a>
                    </div>
                </div>
                <div class="col mb-2">
                    <div class="category-card">
                        <a href="index.php?product_category=Biscuits"><img src="../../img/categories/Biscuits.png" class="biscuits" id="biscuits" alt="Biscuits"></a>
                    </div>
                </div>
                <div class="col mb-2">
                    <div class="category-card">
                        <a href="index.php?product_category=Candy"><img src="../../img/categories/Candy.png" class="candy" id="candy" alt="Candy"></a>
                    </div>
                </div>
                <div class="col mb-2">
                    <div class="category-card">
                        <a href="index.php?product_category=Chocolate"><img src="../../img/categories/Chocolate.png" class="chocolate" id="chocolate" alt="Chocolate"></a>
                    </div>
                </div>
                <div class="col mb-2">
                    <div class="category-card">
                        <a href="index.php?product_category=Cans"><img src="../../img/categories/Cans.png" class="cans" id="cans" alt="Cans"></a>
                    </div>
                </div>
                <div class="col mb-2">
                    <div class="category-card">
                        <a href="index.php?product_category=Condiments"><img src="../../img/categories/Condiments.png" class="condiments" id="condiments" alt="Condiments"></a>
                    </div>
                </div>
                <div class="col mb-2">
                    <div class="category-card">
                        <a href="index.php?product_category=Chips"><img src="../../img/categories/Chips.png" class="chips" id="chips" alt="Chips"></a>
                    </div>
                </div>
                <div class="col mb-2">
                    <div class="category-card">
                        <a href="index.php?product_category=Spread"><img src="../../img/categories/Spread.png" class="spread" id="spread" alt="Spread"></a>
                    </div>
                </div>
                <div class="col mb-2">
                    <div class="category-card">
                        <a href="index.php?product_category=Noodles"><img src="../../img/categories/Noodles.png" class="noodles" id="noodles" alt="Noodles"></a>
                    </div>
                </div>
                <div class="col mb-2">
                    <div class="category-card">
                        <a href="index.php?product_category=Pasta"><img src="../../img/categories/Pasta.png" class="pasta" id="pasta" alt="Pasta"></a>
                    </div>
                </div>
                <div class="col mb-2">
                    <div class="category-card">
                        <a href="index.php?product_category=Bath_Soap"><img src="../../img/categories/Bath_Soap.png" class="bath_soap" id="bath_soap" alt="Bath_Soap"></a>
                    </div>
                </div>
                <div class="col mb-2">
                    <div class="category-card">
                        <a href="index.php?product_category=Conditioner"><img src="../../img/categories/Conditioner.png" class="conditioner" id="conditioner" alt="Conditioner"></a>
                    </div>
                </div>
                <div class="col mb-2">
                    <div class="category-card">
                        <a href="index.php?product_category=Fabcon"><img src="../../img/categories/Fabcon.png" class="fabcon" id="fabcon" alt="Fabcon"></a>
                    </div>
                </div>
                <div class="col mb-2">
                    <div class="category-card">
                        <a href="index.php?product_category=Cologne"><img src="../../img/categories/Cologne.png" class="cologne" id="cologne" alt="Cologne"></a>
                    </div>
                </div>
                <div class="col mb-2">
                    <div class="category-card">
                        <a href="index.php?product_category=Lotion"><img src="../../img/categories/Lotion.png" class="lotion" id="lotion" alt="Lotion"></a>
                    </div>
                </div>
                <div class="col mb-2">
                    <div class="category-card">
                        <a href="index.php?product_category=Shampoo"><img src="../../img/categories/Shampoo.png" class="shampoo" id="shampoo" alt="Shampoo"></a>
                    </div>
                </div>
                <div class="col mb-2">
                    <div class="category-card">
                        <a href="index.php?product_category=Toothpaste"><img src="../../img/categories/Toothpaste.png" class="toothpaste" id="toothpaste" alt="Toothpaste"></a>
                    </div>
                </div>
                <div class="col mb-2">
                    <div class="category-card">
                        <a href="index.php?product_category=Toothbrush"><img src="../../img/categories/Toothbrush.png" class="toothbrush" id="toothbrush" alt="Toothbrush"></a>
                    </div>
                </div>
                <div class="col mb-2">
                    <div class="category-card">
                        <a href="index.php?product_category=Hygiene"><img src="../../img/categories/Hygiene.png" class="hygiene" id="hygiene" alt="Hygiene"></a>
                    </div>
                </div>
                <div class="col mb-2">
                    <div class="category-card">
                        <a href="index.php?product_category=Perfume"><img src="../../img/categories/Perfume.png" class="perfume" id="perfume" alt="Perfume"></a>
                    </div>
                </div>
                <div class="col mb-2">
                    <div class="category-card">
                        <a href="index.php?product_category=Beauty_Essentials"><img src="../../img/categories/Beauty_Essentials.png" class="beauty_essentials" id="beauty_essentials" alt="Beauty Essentials"></a>
                    </div>
                </div>
                <div class="col mb-2">
                    <div class="category-card">
                        <a href="index.php?product_category=Cleaner"><img src="../../img/categories/Cleaner.png" class="cleaner" id="cleaner" alt="Cleaner"></a>
                    </div>
                </div>
                <div class="col mb-2">
                    <div class="category-card">
                        <a href="index.php?product_category=Others"><img src="../../img/categories/Others.png" class="others" id="others" alt="Others"></a>
                    </div>
                </div>

            </div><br><br>
        </div>
    </div>
   
    <?php include_once '../../includes/goToTop.php'; ?>
    <script>
        document.addEventListener("click", function(event) {
            var userOptions = document.getElementById("userOptions");
            var welcomeUser = document.getElementById("welcomeUser");
            var isClickInside =
                welcomeUser.contains(event.target) || userOptions.contains(event.target);

            if (!isClickInside) {
                userOptions.style.display = "none";
            }
        });

        document.getElementById("welcomeUser").addEventListener("click", function() {
            var userOptions = document.getElementById("userOptions");
            userOptions.style.display =
                userOptions.style.display === "block" ? "none" : "block";
        });

        document.addEventListener("DOMContentLoaded", function() {
            const links = document.querySelectorAll(".button-container a");

            const activeLink = localStorage.getItem("activeLink");
            if (activeLink) {
                document.getElementById(activeLink).classList.add("active");
            }

            links.forEach((link) => {
                link.addEventListener("click", function() {
                    links.forEach((link) => link.classList.remove("active"));
                    this.classList.add("active");

                    localStorage.setItem("activeLink", this.id);
                });
            });
        });

        document.addEventListener("DOMContentLoaded", function() {
            const hamburgerMenu = document.querySelector(".hamburger-menu");
            const dropdown = document.querySelector(".dropdown");

            hamburgerMenu.addEventListener("click", function() {
                dropdown.classList.toggle("show");

                if (dropdown.classList.contains("show")) {
                    hamburgerMenu.classList.add("close");
                } else {
                    hamburgerMenu.classList.remove("close");
                }
            });
        });

        const dropdown = document.querySelector('.dropdown');

        function toggleDropdown() {
            dropdown.classList.toggle('show');
        }

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

        document.addEventListener("DOMContentLoaded", function() {
            const categoryImages = document.querySelectorAll(".category-card img");

            categoryImages.forEach((image) => {
                image.addEventListener("click", function(event) {
                    categoryImages.forEach((img) => img.classList.remove("selected"));
                    this.classList.add("selected");
                });
            });

            const category = "<?php echo $product_category; ?>";
            const categoryImageId = category
                .replace(/ & /g, "_")
                .replace(/ /g, "_")
                .toLowerCase();
            const categoryImage = document.getElementById(categoryImageId);
            if (categoryImage) {
                categoryImage.classList.add("selected");
            }
        });

        document.getElementById("profileImage").addEventListener("click", function() {
            document.getElementById("profileInput").click();
        });

        document.getElementById("profileInput").addEventListener("change", function() {
            const file = this.files[0];
            if (file) {
                const formData = new FormData();
                formData.append("profileImage", file);

                fetch("index.php", {
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
            const quantityElement = document.getElementById("quantity");
            const increaseButton = document.getElementById("increaseQuantity");
            const decreaseButton = document.getElementById("decreaseQuantity");
            const productPriceElement = document.getElementById("product_price");
            let basePrice = 0;

            const updatePrice = () => {
                let quantity = parseInt(quantityElement.textContent, 10);
                let newPrice = basePrice * quantity;
                productPriceElement.textContent = `₱${newPrice.toFixed(2)}`;
                document.getElementById("hiddenProductQuantity").value = quantity;
            };

            increaseButton.addEventListener("click", function() {
                let quantity = parseInt(quantityElement.textContent, 10);
                quantityElement.textContent = quantity + 1;
                updatePrice();
            });

            decreaseButton.addEventListener("click", function() {
                let quantity = parseInt(quantityElement.textContent, 10);
                if (quantity > 1) {
                    quantityElement.textContent = quantity - 1;
                    updatePrice();
                }
            });

            const modal = document.getElementById("productModal");
            const productTitle = document.getElementById("productTitle");
            const productSolds = document.getElementById("productSolds");
            const productSize = document.getElementById("productSize");
            const stockQuantity = document.getElementById("stock_quantity");
            const productImage = document.getElementById("productImage");

            modal.addEventListener("show.bs.modal", function(event) {
                const button = event.relatedTarget;
                const productName = button.getAttribute("data-product-name");
                basePrice = parseFloat(button.getAttribute("data-product-price"));
                const stockQuantityText = button.getAttribute("data-product-quantity");
                const productSoldsText = button.getAttribute("data-product-solds");
                const productSizeText = button.getAttribute("data-product-size");
                const productImgSrc = button.getAttribute("data-product-image");

                productTitle.textContent = productName;
                productPriceElement.textContent = `₱${basePrice.toFixed(2)}`;
                stockQuantity.textContent = `Stock/s: ${stockQuantityText}`;
                productSolds.textContent = `Sold/s: ${productSoldsText}`;
                productSize.textContent = `(${productSizeText})`;
                productImage.src = productImgSrc;

                quantityElement.textContent = 1;
                document.getElementById("hiddenProductName").value = productName;
                document.getElementById("hiddenProductPrice").value = basePrice;
                document.getElementById("hiddenProductSize").value = productSize;
                document.getElementById("hiddenProductQuantity").value = 1;
                document.getElementById("hiddenProductImage").value = productImgSrc;
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
                    window.location.href = 'index.php';
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