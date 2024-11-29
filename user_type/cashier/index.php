<?php
include '../../db/conn.php';
session_start();
ob_start();

$totalItems = 0;
$totalPrice = 0;

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
    $user_type = "Cashier";

    $product_category = isset($_GET['product_category']) ? htmlspecialchars($_GET['product_category']) : 'Drinks';

    $product_name = htmlspecialchars($_POST['product_name']);
    $price_per_unit = htmlspecialchars($_POST['price']);
    $quantity = htmlspecialchars($_POST['quantity']);
    $total_price = $price_per_unit * $quantity;
    $order_status = "cart";
    $status = "Completed";
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

        $stmt = $conn->prepare("INSERT INTO orders (product_id, order_number, customer_name, user_type, order_date, product_category, product_name, price, quantity, order_status, product_image, customer_profile) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$product_id, $order_number, $customer_name, $user_type, $order_date, $product_category, $product_name, $total_price, $quantity, $order_status, $product_image, $customer_profile]);

        $stmt = $conn->prepare("INSERT INTO checkout (product_id, order_number, customer_name, user_type, order_date, product_category, product_name, price, quantity, order_status, status, product_image, customer_profile) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$product_id, $order_number, $customer_name, $user_type, $order_date, $product_category, $product_name, $total_price, $quantity, $order_status, $status, $product_image, $customer_profile]);

        $stmt = $conn->prepare("UPDATE products SET quantity = quantity - ?, solds = solds + ? WHERE product_name = ?");
        $stmt->execute([$quantity, $quantity, $product_name]);

        $conn->commit();

        $_SESSION['product_category'] = $product_category;
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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_quantity'])) {
        $order_id = intval($_POST['order_id']);
        $quantity = intval($_POST['quantity']);
        $price = floatval($_POST['price']);

        if ($quantity > 0) {
            try {
                $stmt = $conn->prepare("UPDATE orders SET quantity = :quantity, price = :price WHERE order_id = :order_id AND customer_name = :customer_name");
                $stmt->bindParam(':quantity', $quantity);
                $stmt->bindParam(':price', $price);
                $stmt->bindParam(':order_id', $order_id);
                $stmt->bindParam(':customer_name', $_SESSION['fullname']);
                $stmt->execute();

                echo json_encode(['success' => true]);
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid quantity']);
        }
    }

    if (isset($_POST['remove_order']) && isset($_POST['order_id'])) {
        $order_id = $_POST['order_id'];
        $stmt = $conn->prepare("DELETE FROM orders WHERE order_id = :order_id ");
        $stmt->bindParam(':order_id', $order_id);
        $stmt->execute();
    }
}

try {
    $sql = "SELECT COUNT(DISTINCT order_number) as order_count FROM checkout WHERE user_type = 'Customer' AND status = 'Completed' AND order_date >= CURDATE() AND order_date < CURDATE() + INTERVAL 1 DAY";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $orderCount = $stmt->fetchColumn();
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grab & Go | Home</title>
    <link rel="shortcut icon" href="../../img/logo.png" type="image/x-icon">
    <?php include_once '../../header.php' ?>
    <link rel="stylesheet" href="../../css/cashier_main.css">
    <style>

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

        .card-details .card-name,
        .card-details .card-price {
            font-family: Arial, sans-serif;
            font-size: 12px;
        }

        .card-details .card-price {
            font-size: 20px;
        }

        #proceedLink.disabled,
        #proceedBtn:disabled {
            pointer-events: none;
            cursor: not-allowed;
            opacity: 0.65;
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
                <a href="process.php" id="my-cart"><i class="fas fa-cart-arrow-down"></i> To Process</a>
                <a href="orders.php" id="my-cart"><i class="fas fa-shopping-basket"></i> Orders</a>
                <a href="disapproved.php" id="disapproved"><i class="fas fa-thumbs-down"></i> Disapproved Order</a>
                <a href="inventory.php" id="inventory"><i class="fas fa-sitemap"></i> Inventory </a>
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
                <a href="process.php" id="my-cart"><i class="fas fa-cart-arrow-down"></i> To Process</a>
                <a href="orders.php" id="my-cart"><i class="fas fa-shopping-basket"></i> Orders</a>
                <a href="disapproved.php" id="disapproved"><i class="fas fa-thumbs-down"></i> Disapproved Order</a>
                <a href="inventory.php" id="inventory"><i class="fas fa-sitemap"></i> Inventory </a>
                <a href="about.php"><i class="fas fa-info-circle"></i> About</a>
            </div>
        </div>
    </div>

    <div class="home-container">
        <nav class="navbar">
            <div class="container-fluid">
                <a class="navbar-brand fw-bold fs-3 ms-2 font-effect-shadow-multiple">HOME</a>
                <div class="position-relative me-3 mt-2 ms-auto">
                    <a href="orders.php"> <i class="fas fa-bell" style="font-size: 1.7rem; color: #007bff;" title="Notification | Online Order" data-toggle="tooltip"></i></a>

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

        <div class="order-container">
            <div class="search-container mt-2">
                <form>
                    <p class="text-start">What would you like to order?</p>
                    <div class="input-container">
                        <i class="fas fa-search icon"></i>
                        <input type="search" name="search_product" id="search_product" placeholder="Search" oninput="search_product()">
                    </div>
                </form>
            </div>

            <div class="row row-cols-1 row-cols-md-6 ms-4 btn-category p-1" style="max-width: 700px;">
                <a href="index.php?product_category=Drinks" id="drinksBtn" data-category="Drinks" class="btn btn-outline-primary btn-active">Drinks</a>
                <a href="index.php?product_category=Powder" id="powderBtn" data-category="Powder" class="btn btn-outline-primary">Powder</a>
                <a href="index.php?product_category=Biscuits" id="biscuitsBtn" data-category="Biscuits" class="btn btn-outline-primary">Biscuits</a>
                <a href="index.php?product_category=Candy" id="CandyBtn" data-category="Candy" class="btn btn-outline-primary">Candy</a>
                <a href="index.php?product_category=Chocolate" id="chocolateBtn" data-category="Chocolate" class="btn btn-outline-primary">Chocolate</a>
                <a href="index.php?product_category=Cans" id="cansBtn" data-category="Cans" class="btn btn-outline-primary">Cans</a>
                <a href="index.php?product_category=Condiments" id="condimentsBtn" data-category="Condiments" class="btn btn-outline-primary">Condiments</a>
                <a href="index.php?product_category=Chips" id="chipsBtn" data-category="Chips" class="btn btn-outline-primary">Chips</a>
            </div>

            <div class="row row-cols-1 row-cols-md-6 ms-4 btn-category p-1 mt-0" style="max-width: 700px;">
                <a href="index.php?product_category=Spread" id="SpreadBtn" data-category="Spread" class="btn btn-outline-primary">Spread</a>
                <a href="index.php?product_category=Noodles" id="noodlesBtn" data-category="Noodles" class="btn btn-outline-primary">Noodles</a>
                <a href="index.php?product_category=Bath_Soap" id="bathSoapBtn" data-category="Bath_Soap" class="btn btn-outline-primary">Bath Soap</a>
                <a href="index.php?product_category=Beauty_Essentials" id="beautyBtn" data-category="Beauty_Essentials" class="btn btn-outline-primary">Beauty</a>
                <a href="index.php?product_category=Pasta" id="PastaBtn" data-category="Pasta" class="btn btn-outline-primary">Pasta</a>
                <a href="index.php?product_category=Conditioner" id="ConditionerBtn" data-category="Conditioner" class="btn btn-outline-primary">Conditioner</a>
                <a href="index.php?product_category=Fabcon" id="FabconBtn" data-category="Fabcon" class="btn btn-outline-primary">Fabcon</a>
                <a href="index.php?product_category=Cologne" id="CologneBtn" data-category="Cologne" class="btn btn-outline-primary">Cologne</a>
            </div>

            <div class="row row-cols-1 row-cols-md-6 ms-4 btn-category p-1 mt-0" style="max-width: 700px;">
                <a href="index.php?product_category=Lotion" id="LotionBtn" data-category="Lotion" class="btn btn-outline-primary">Lotion</a>
                <a href="index.php?product_category=Shampoo" id="ShampooBtn" data-category="Shampoo" class="btn btn-outline-primary">Shampoo</a>
                <a href="index.php?product_category=Toothpaste" id="ToothpasteBtn" data-category="Toothpaste" class="btn btn-outline-primary">Toothpaste</a>
                <a href="index.php?product_category=Toothbrush" id="ToothbrushBtn" data-category="Toothbrush" class="btn btn-outline-primary">Toothbrush</a>
                <a href="index.php?product_category=Hygiene" id="HygieneBtn" data-category="Hygiene" class="btn btn-outline-primary">Hygiene</a>
                <a href="index.php?product_category=Perfume" id="PerfumeBtn" data-category="Perfume" class="btn btn-outline-primary">Perfume</a>
                <a href="index.php?product_category=Cleaner" id="CleanerBtn" data-category="Cleaner" class="btn btn-outline-primary">Cleaner</a>
                <a href="index.php?product_category=Others" id="OthersBtn" data-category="Others" class="btn btn-outline-primary">Others</a>
            </div>

            <div class="orders-container mt-3 ms-4" style=" max-width: 700px;">
                <div class="row row-cols-1 row-cols-md-4 me-2">
                    <?php
                    try {
                        $stmt = $conn->prepare("SELECT * FROM products WHERE product_category = ? ORDER BY product_name ASC");
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
                    ?>
                            <div class="col mb-2 product_card">
                                <div class="card">
                                    <img src="<?php echo $product_image_path; ?>" style="height: 100px; width: auto;" class="card-img-top my-1" alt="<?php echo $product_name; ?>">
                                    <div class="card-body d-flex justify-content-between align-items-center">
                                        <div class="card-details">
                                            <h6 class="card-name"><?php echo $product_name; ?></h6>
                                            <h6 class="card-price">₱<?php echo $price; ?></h6>
                                        </div>
                                        <form method="POST" action="index.php?product_category=<?php echo $product_category; ?>">
                                            <input type="hidden" name="product_name" value="<?php echo $product_name; ?>">
                                            <input type="hidden" name="price" value="<?php echo $price; ?>">
                                            <input type="hidden" name="quantity" value="1">
                                            <input type="hidden" name="product_image" value="<?php echo $product_image; ?>">
                                            <button type="submit" name="addOrder" class="btn btn-add">
                                                <i class="fas fa-add"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                    <?php
                        }

                        $all_products_stmt = $conn->prepare("SELECT * FROM products ORDER BY product_name ASC");
                        $all_products_stmt->execute();
                        $all_products = $all_products_stmt->fetchAll(PDO::FETCH_ASSOC);

                        if (!$product_found) {
                            echo "<p>No products found in this category.</p>";
                        }
                    } catch (PDOException $e) {
                        echo "Error: " . $e->getMessage();
                    }
                    ?>
                </div><br><br>
            </div>
        </div>

        <script>
            const allProducts = <?php echo json_encode($all_products); ?>;
        </script>

        <script>
            function search_product() {
                var input = document.getElementById("search_product").value.toLowerCase();
                var cardsContainer = document.querySelector(".orders-container .row");

                cardsContainer.innerHTML = '';

                if (input !== "") {
                    allProducts.forEach(function(product) {
                        var productName = product.product_name.toLowerCase();

                        if (productName.includes(input)) {
                            var productCard = `
                            <div class="col mb-2 product_card" data-product-name="${product.product_name}">
                                <div class="card">
                                    <img src="../products/${product.product_image}"  style="height: 100px; width: auto;" class="card-img-top my-1" alt="${product.product_name}">
                                    <div class="card-body d-flex justify-content-between align-items-center">
                                        <div class="card-details">
                                            <h6 class="card-name">${product.product_name}</h6>
                                            <h6 class="card-price" >₱${product.price}</h6>
                                        </div>
                                        <button class="btn btn-add" data-bs-toggle="modal" 
                                                data-bs-target="#productModal" 
                                                data-product-name="${product.product_name}" 
                                                data-product-price="${product.price}" 
                                                data-product-quantity="${product.quantity}" 
                                                data-product-solds="${product.solds}" 
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
                            var productCard = `
                            <div class="col mb-2 product_card" data-product-name="${product.product_name}">
                                <div class="card">
                                    <img src="../products/${product.product_image}" style="height: 100px; width: auto;" class="card-img-top my-1" alt="${product.product_name}">
                                    <div class="card-body d-flex justify-content-between align-items-center">
                                        <div class="card-details">
                                            <h6 class="card-name">${product.product_name}</h6>
                                            <h6 class="card-price">₱${product.price}</h6>
                                        </div>
                                        <button class="btn btn-add" data-bs-toggle="modal" 
                                                data-bs-target="#productModal" 
                                                data-product-name="${product.product_name}" 
                                                data-product-price="${product.price}" 
                                                data-product-quantity="${product.quantity}" 
                                                data-product-solds="${product.solds}" 
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

            document.getElementById("search_product").addEventListener("keyup", search_product);
        </script>

        <form action="" method="POST">
            <div class="category-container">
                <div class="order-container" style="margin: 0 auto; overflow-y: auto; margin-top: -150px;">
                    <p class="text-start fw-bold mt-1">+ Add Order</p>
                    <table class="table table-hover " style="border: 2px solid #aaa; border-right: none; border-left: none;">
                        <tbody>
                            <?php
                            $customer_name = htmlspecialchars($_SESSION['fullname']);
                            $sql = "SELECT * FROM orders WHERE order_status = 'cart' AND customer_name = '$customer_name'";
                            $stmt = $conn->query($sql);
                            $totalPrice = 0;
                            $rowCount = 0; 

                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                $rowCount++; 
                                $totalPrice += $row['price'];
                                $pricePerUnit = $row['price'] / $row['quantity'];
                            ?>
                                <tr class="accordion-toggle" style="cursor: pointer;">
                                    <td class="text-start ms-3 fw-bold" style="font-size: 15px;">
                                        <span class="toggle-icon"><i class="fas fa-chevron-right"></i></span>&nbsp;
                                        <span class="quantity-display">x<?php echo htmlspecialchars($row['quantity']); ?></span>&nbsp;
                                        <span><?php echo htmlspecialchars($row['product_name']); ?></span>
                                    </td>
                                    <td class="text-end me-0" style="font-size: 15px;">
                                        <span class="price-display text-primary fw-bold">&#8369;<?php echo number_format($row['price'], 2); ?></span>
                                        <button type="button" class="remove-icon fw-bold text-dark" style="border: none; background: #eee; border-radius: 50%;" data-order-id="<?php echo $row['order_id']; ?>"><i class="fas fa-times"></i></button>
                                    </td>
                                </tr>
                                <tr class="accordion-content" style="display: none;">
                                    <td colspan="2" style="font-size: 15px;">
                                        <input type="hidden" name="order_id[]" value="<?php echo $row['order_id']; ?>">
                                        <input type="hidden" name="price_per_unit[]" value="<?php echo $pricePerUnit; ?>">
                                        <div class="input-group">
                                            <input type="number" class="form-control quantity-input w-75" data-order-id="<?php echo $row['order_id']; ?>" data-price-per-unit="<?php echo $pricePerUnit; ?>" name="quantity[]" placeholder="Enter quantity" value="<?php echo $row['quantity']; ?>">
                                            <button type="button" class="btn btn-sm btn-primary ms-1" onclick="window.location.reload();">Save</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php
                            }
                            ?>
                        </tbody>
                    </table>

                    <div class="btn-container" style="margin-top: auto;">
                        <div class="total-container" style="font-family: Arial, sans-serif;">
                            <div class="fw-bold ms-3" style="float: left;">
                                Total
                            </div>
                            <div class="fw-bold me-3" style="float: right;">
                                &#8369;<?php echo number_format($totalPrice, 2); ?>
                            </div>
                        </div><br>
                            <?php $isDisabled = ($rowCount === 0); ?>

                            <div class="btn-checkout mt-2 mb-4">
                                <a
                                    href="<?php echo $isDisabled ? '#' : 'payment.php'; ?>"
                                    id="proceedLink"
                                    style="text-decoration: none; cursor: <?php echo $isDisabled ? 'not-allowed' : 'pointer'; ?>;">
                                    <button
                                        type="button"
                                        id="proceedBtn"
                                        class="btn d-block w-100 btn-success text-light p-2 fw-bold"
                                        <?php echo $isDisabled ? 'disabled' : ''; ?>
                                        style="cursor: <?php echo $isDisabled ? 'not-allowed' : 'pointer'; ?>;">
                                        Proceed to payment
                                    </button>
                                </a>
                            </div>

                    </div>
                </div>
            </div>
        </form>

    </div>

    <?php include_once '../../includes/goToTop.php'; ?>
    <script src="../../js/cashier_script.js"> </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const proceedLink = document.getElementById('proceedLink');
            const proceedBtn = document.getElementById('proceedBtn');
            const tableRows = document.querySelectorAll('table tbody tr');

            if (tableRows.length === 0) {
                proceedBtn.disabled = true;
                proceedLink.style.pointerEvents = 'none';
                proceedLink.style.cursor = 'not-allowed';
                proceedBtn.style.cursor = 'not-allowed';
            }
        });
    </script>

    <script>
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

        document.addEventListener("DOMContentLoaded", function() {
            const accordions = document.querySelectorAll(".accordion-toggle");

            accordions.forEach(function(accordion) {
                const toggleIcon = accordion.querySelector(".toggle-icon i");

                accordion.addEventListener("click", function() {
                    const content = this.nextElementSibling;
                    if (content.style.display === "none") {
                        content.style.display = "table-row";
                        toggleIcon.classList.remove("fa-chevron-right");
                        toggleIcon.classList.add("fa-chevron-down");
                    } else {
                        content.style.display = "none";
                        toggleIcon.classList.remove("fa-chevron-down");
                        toggleIcon.classList.add("fa-chevron-right");
                    }
                });
            });

            const quantityInputs = document.querySelectorAll(".quantity-input");
            quantityInputs.forEach((input, index) => {
                input.addEventListener("input", function() {
                    const orderId = this.dataset.orderId;
                    const pricePerUnit = parseFloat(this.dataset.pricePerUnit);
                    const newQuantity = parseInt(this.value);
                    if (newQuantity > 0) {
                        const newPrice = pricePerUnit * newQuantity;

                        document.querySelectorAll(".price-display")[index].textContent =
                            "₱" + newPrice.toFixed(2);
                        document.querySelectorAll(".quantity-display")[index].textContent =
                            "x" + newQuantity;

                        fetch("index.php", {
                                method: "POST",
                                headers: {
                                    "Content-Type": "application/x-www-form-urlencoded",
                                },
                                body: new URLSearchParams({
                                    update_quantity: true,
                                    order_id: orderId,
                                    quantity: newQuantity,
                                    price: newPrice,
                                }),
                            })
                            .then((response) => response.json())
                            .then((data) => {
                                if (!data.success) {
                                    console.error("Error:", data.error);
                                }
                            })
                            .catch((error) => console.error("Error:", error));
                    }
                });
            });

            const removeIcons = document.querySelectorAll(".remove-icon");
            removeIcons.forEach((icon) => {
                icon.addEventListener("click", function() {
                    const orderId = this.getAttribute("data-order-id");
                    fetch("", {
                            method: "POST",
                            headers: {
                                "Content-Type": "application/x-www-form-urlencoded",
                            },
                            body: new URLSearchParams({
                                remove_order: true,
                                order_id: orderId,
                            }),
                        })
                        .then((response) => response.text())
                        .then((data) => {
                            location.reload();
                        })
                        .catch((error) => console.error("Error:", error));
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