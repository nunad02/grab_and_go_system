<?php
include '../../db/conn.php';
session_start();
ob_start();

$totalItems = 0;
$totalPrice = 0;

$product_category = isset($_GET['product_category']) ? htmlspecialchars($_GET['product_category']) : 'Drinks';
$_SESSION['product_category'] = $product_category;

$totalInStock = 0;
$totalLowStock = 0;
$totalRunningOut = 0;
$totalOutOfStock = 0;

$sql = "SELECT * FROM products WHERE product_category = '$product_category'";
$stmt = $conn->query($sql);

if ($stmt) {
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $in_stock_text = htmlspecialchars($row['quantity']);
        preg_match('/\d+/', $in_stock_text, $matches);
        $in_stock = isset($matches[0]) ? (int)$matches[0] : 0;

        if ($in_stock == 0) {
            $totalOutOfStock += $in_stock;
        } elseif ($in_stock >= 1 && $in_stock <= 5) {
            $totalRunningOut += $in_stock;
        } elseif ($in_stock >= 6 && $in_stock <= 10) {
            $totalLowStock += $in_stock;
        } elseif ($in_stock >= 11) {
            $totalInStock += $in_stock;
        }
    }
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


                $mail->send();
                $_SESSION['update_success'] = true;
                header('Location: inventory.php');
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
    <title>Grab & Go | Inventory</title>
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

        .dashboard-container p {
            font-weight: bold;
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
                <a href="index.php" id="home"><i class="fas fa-home"></i> Home</a>
                <a href="process.php" id="my-cart"><i class="fas fa-cart-arrow-down"></i> To Process</a>
                <a href="orders.php" id="my-cart"><i class="fas fa-shopping-basket"></i> Orders</a>
                <a href="disapproved.php" id="disapproved"><i class="fas fa-thumbs-down"></i> Disapproved Order</a>
                <a href="inventory.php" class="active" id="inventory"><i class="fas fa-sitemap"></i> Inventory </a>
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
                <a href="index.php" id="home"><i class="fas fa-home"></i> Home</a>
                <a href="process.php" id="my-cart"><i class="fas fa-cart-arrow-down"></i> To Process</a>
                <a href="orders.php" id="my-cart"><i class="fas fa-shopping-basket"></i> Orders</a>
                <a href="disapproved.php" id="disapproved"><i class="fas fa-thumbs-down"></i> Disapproved Order</a>
                <a href="inventory.php" class="active" id="inventory"><i class="fas fa-sitemap"></i> Inventory </a>
                <a href="about.php"><i class="fas fa-info-circle"></i> About</a>
            </div>
        </div>
    </div>

    <div class="home-container">
        <nav class="navbar">
            <div class="container-fluid">
                <a class="navbar-brand fw-bold fs-3 ms-2 font-effect-shadow-multiple">INVENTORY</a>
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

                <form id="changePasswordForm" method="post" action="inventory.php" class="text-center" style="display: none;">
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

        <div class="modal fade" id="statusModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-body">
                        <h5 class="text-center" id="statusMessage"></h5>
                    </div>
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

        <div class="order-container">
            <div class="dashboard-container mt-2 ms-4" style="margin: 0 auto;">
                <div class="row row-cols-1 row-cols-md-4 me-2">
                    <div class="col mb-2 product_card">
                        <div class="card" style="height: 140px; width: 250px; background-color: #59F97F;">
                            <div class="card-body d-flex flex-column justify-content-between align-items-center text-center">
                                <p class="dash-header mb-0">In Stock</p>
                                <p class="dash-total mb-3" style="font-size: 50px;"><?php echo $totalInStock; ?></p>
                                <p class="dash-footer">Packs/Cans/Bottles</p>
                            </div>
                        </div>
                    </div>

                    <div class="col mb-2 product_card">
                        <div class="card" style="height: 140px; width: 250px; background-color: #FFE45E;">
                            <div class="card-body d-flex flex-column justify-content-between align-items-center text-center">
                                <p class="dash-header mb-0">Low Stock</p>
                                <p class="dash-total mb-3" style="font-size: 50px;"><?php echo $totalLowStock; ?></p>
                                <p class="dash-footer">Packs/Cans/Bottles</p>
                            </div>
                        </div>
                    </div>

                    <div class="col mb-2 product_card">
                        <div class="card" style="height: 140px; width: 250px; background-color: #F548EF;">
                            <div class="card-body d-flex flex-column justify-content-between align-items-center text-center">
                                <p class="dash-header mb-0">Running Out Items</p>
                                <p class="dash-total mb-3" style="font-size: 50px;"><?php echo $totalRunningOut; ?></p>
                                <p class="dash-footer ">Packs/Cans/Bottles</p>
                            </div>
                        </div>
                    </div>

                    <div class="col mb-2 product_card">
                        <div class="card" style="height: 140px; width: 250px; background-color: #FA325a;">
                            <div class="card-body d-flex flex-column justify-content-between align-items-center text-center">
                                <p class="dash-header mb-0">Out of Stock</p>
                                <p class="dash-total mb-3" style="font-size: 50px;"><?php echo $totalOutOfStock; ?></p>
                                <p class="dash-footer ">Packs/Cans/Bottles</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>


            <div class="row row-cols-1 row-cols-md-6 ms-4 btn-category p-1" style="max-width: 96%; margin: 0 auto;">
                <a href="inventory.php?product_category=Drinks" id="drinksBtn" data-category="Drinks" class="btn btn-outline-primary btn-active">Drinks</a>
                <a href="inventory.php?product_category=Powder" id="powderBtn" data-category="Powder" class="btn btn-outline-primary">Powder</a>
                <a href="inventory.php?product_category=Biscuits" id="biscuitsBtn" data-category="Biscuits" class="btn btn-outline-primary">Biscuits</a>
                <a href="inventory.php?product_category=Candy" id="CandyBtn" data-category="Candy" class="btn btn-outline-primary">Candy</a>
                <a href="inventory.php?product_category=Chocolate" id="chocolateBtn" data-category="Chocolate" class="btn btn-outline-primary">Chocolate</a>
                <a href="inventory.php?product_category=Cans" id="cansBtn" data-category="Cans" class="btn btn-outline-primary">Cans</a>
                <a href="inventory.php?product_category=Condiments" id="condimentsBtn" data-category="Condiments" class="btn btn-outline-primary">Condiments</a>
                <a href="inventory.php?product_category=Chips" id="chipsBtn" data-category="Chips" class="btn btn-outline-primary">Chips</a>
                <a href="inventory.php?product_category=Spread" id="SpreadBtn" data-category="Spread" class="btn btn-outline-primary">Spread</a>
                <a href="inventory.php?product_category=Noodles" id="noodlesBtn" data-category="Noodles" class="btn btn-outline-primary">Noodles</a>
                <a href="inventory.php?product_category=Bath_Soap" id="bathSoapBtn" data-category="Bath_Soap" class="btn btn-outline-primary">Bath Soap</a>
                <a href="inventory.php?product_category=Beauty_Essentials" id="beautyBtn" data-category="Beauty_Essentials" class="btn btn-outline-primary">Beauty</a>
            </div>
            <div class="row row-cols-1 row-cols-md-6 ms-4 btn-category p-1" style="max-width: 96%; margin: 0 auto;">
                <a href="inventory.php?product_category=Pasta" id="PastaBtn" data-category="Pasta" class="btn btn-outline-primary">Pasta</a>
                <a href="inventory.php?product_category=Conditioner" id="ConditionerBtn" data-category="Conditioner" class="btn btn-outline-primary">Conditioner</a>
                <a href="inventory.php?product_category=Fabcon" id="FabconBtn" data-category="Fabcon" class="btn btn-outline-primary">Fabcon</a>
                <a href="inventory.php?product_category=Cologne" id="CologneBtn" data-category="Cologne" class="btn btn-outline-primary">Cologne</a>
                <a href="inventory.php?product_category=Lotion" id="LotionBtn" data-category="Lotion" class="btn btn-outline-primary">Lotion</a>
                <a href="inventory.php?product_category=Shampoo" id="ShampooBtn" data-category="Shampoo" class="btn btn-outline-primary">Shampoo</a>
                <a href="inventory.php?product_category=Toothpaste" id="ToothpasteBtn" data-category="Toothpaste" class="btn btn-outline-primary">Toothpaste</a>
                <a href="inventory.php?product_category=Toothbrush" id="ToothbrushBtn" data-category="Toothbrush" class="btn btn-outline-primary">Toothbrush</a>
                <a href="inventory.php?product_category=Hygiene" id="HygieneBtn" data-category="Hygiene" class="btn btn-outline-primary">Hygiene</a>
                <a href="inventory.php?product_category=Perfume" id="PerfumeBtn" data-category="Perfume" class="btn btn-outline-primary">Perfume</a>
                <a href="inventory.php?product_category=Cleaner" id="CleanerBtn" data-category="Cleaner" class="btn btn-outline-primary">Cleaner</a>
                <a href="inventory.php?product_category=Others" id="OthersBtn" data-category="Others" class="btn btn-outline-primary">Others</a>
            </div>


            <table class="table table-hover" id="orderDetailsTable" style="border: 2px solid #aaa; border-right: none; border-left: none; width: 95%; margin: 0 auto; font-family: Arial, sans-serif;">
    <tbody>
        <tr class="fw-bold">
            <th>Date</th>
            <th>SO</th>
            <th>Product Name</th>
            <th>Size</th>
            <th>Stock Status</th>
            <th>Stockroom</th>
            <th>Actual</th>
            <th>Price</th>
            <th>Solds</th>
            <th>Sales</th>
            <th>Rating</th>
        </tr>
        <?php
        $sql = "SELECT * FROM products WHERE product_category = '$product_category'";
        $stmt = $conn->query($sql);

        echo '<div class="title my-2">
                <span class="text-dark text-start fs-4 ms-5 fw-bold ">Products Ordered List</span>
              </div>';

        if ($stmt) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $in_stock_text = htmlspecialchars($row['quantity']);
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

                $product_id = $row['product_id'];

                // Calculate average rating
                $rating_query = "SELECT AVG(rating) AS avg_rating FROM feedback WHERE product_id = :product_id";
                $stmt_rating = $conn->prepare($rating_query);
                $stmt_rating->bindParam(':product_id', $product_id, PDO::PARAM_INT);
                $stmt_rating->execute();
                $rating_row = $stmt_rating->fetch();
                $avg_rating = $rating_row['avg_rating'];
                $display_rating = !is_null($avg_rating) ? round($avg_rating, 1) : '0';

                // Calculate total sales
                $sales_query = "SELECT SUM(price * quantity) AS total_sales FROM checkout WHERE product_id = :product_id";
                $stmt_sales = $conn->prepare($sales_query);
                $stmt_sales->bindParam(':product_id', $product_id, PDO::PARAM_INT);
                $stmt_sales->execute();
                $sales_row = $stmt_sales->fetch();
                $total_sales = !is_null($sales_row['total_sales']) ? number_format($sales_row['total_sales'], 2) : '0.00';

                // Display stars
                $full_stars = floor($display_rating);
                $half_star = ($display_rating - $full_stars) >= 0.5 ? 1 : 0;
                $empty_stars = 5 - ($full_stars + $half_star);
        ?>
                <tr class="accordion-toggle m-0" style="cursor: pointer;">
                    <td class="text-start p-2" style="font-size: 15px;">
                        <span><?php echo htmlspecialchars($row['purchase_date']); ?></span>
                    </td>
                    <td class="text-start p-2" style="font-size: 15px;">
                        <span><?php echo htmlspecialchars($row['product_id']); ?></span>
                    </td>
                    <td class="text-start p-2" style="font-size: 15px;">
                        <span><?php echo htmlspecialchars($row['product_name']); ?></span>
                    </td>
                    <td class="text-start p-2" style="font-size: 15px;">
                        <span><?php echo htmlspecialchars($row['size']); ?></span>
                    </td>
                    <td class="text-start p-2" style="font-size: 15px;">
                        <span style="color: <?php echo $text_color; ?>; font-weight: bold; "><?php echo $stock_status_text; ?></span>
                    </td>
                    <td class="text-start p-2" style="font-size: 15px;">
                    <span><?php echo htmlspecialchars($row['in_stock']); ?></span>
                    </td>
                    <td class="text-start p-2" style="font-size: 15px;">
                        <span><?php echo htmlspecialchars($row['quantity']); ?></span>
                    </td>
                    <td class="text-start p-2" style="font-size: 15px;">
                        <span>₱<?php echo htmlspecialchars($row['price']); ?></span>
                    </td>
                    <td class="text-start p-2 text-center" style="font-size: 15px;">
                        <span><?php echo htmlspecialchars($row['solds']); ?></span>
                    </td>
                    <td class="text-start p-2" style="font-size: 15px;">
                        <span>₱<?php echo $total_sales; ?></span>
                    </td>
                    <td class="text-start p-2" style="font-size: 15px;">
                        <span>
                            <?php
                            for ($i = 0; $i < $full_stars; $i++) {
                                echo '<i class="fas fa-star" style="color: #FFD700;"></i>';
                            }
                            if ($half_star) {
                                echo '<i class="fas fa-star-half-alt" style="color: #FFD700;"></i>';
                            }
                            for ($i = 0; $i < $empty_stars; $i++) {
                                echo '<i class="far fa-star" style="color: #FFD700;"></i>';
                            }
                            ?>
                        </span>
                    </td>
                </tr>
        <?php
            }
        }
        ?>
    </tbody>
</table>


        </div><br><br>
    </div>

    <?php include_once '../../includes/goToTop.php'; ?>
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

                fetch("inventory.php", {
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
                    window.location.href = 'inventory.php';
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