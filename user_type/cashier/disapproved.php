<?php
include '../../db/conn.php';
session_start();
ob_start();


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
                header('Location: process.php');
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
    $stmt = $conn->query("
        SELECT 
            order_number, 
            customer_name, 
            order_date, 
            product_name, 
            quantity, 
            price, 
            payment_method, 
            cash_amount, 
            reference_num, 
            status, 
            disapprove_date, 
            disapprove_reason, 
            customer_profile 
        FROM checkout 
        WHERE user_type = 'Customer' 
        AND status = 'Disapproved' 
        AND payment_method IN ('cash', 'charge') 
        ORDER BY disapprove_date DESC
    ");

    $orders = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $order_number = $row['order_number'];
        if (!isset($orders[$order_number])) {
            $orders[$order_number] = [
                'order_number' => $row['order_number'],
                'customer_name' => $row['customer_name'],
                'order_date' => $row['order_date'],
                'payment_method' => $row['payment_method'],
                'cash_amount' => $row['cash_amount'],
                'disapprove_date' => $row['disapprove_date'],
                'disapprove_reason' => $row['disapprove_reason'],
                'customer_profile' => $row['customer_profile'],
                'items' => []
            ];
        }
        $orders[$order_number]['items'][] = [
            'product_name' => $row['product_name'],
            'quantity' => $row['quantity'],
            'price' => $row['price']
        ];
    }
} catch (PDOException $e) {
    die("Error fetching orders: " . $e->getMessage());
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
    <title>Grab & Go | To Process</title>
    <link rel="shortcut icon" href="../../img/logo.png" type="image/x-icon">
    <?php include_once '../../header.php' ?>
    <link rel="stylesheet" href="../../css/cashier_main.css">
    <style>
        .fixed-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 1000;
        }

        label,
        h5 {
            font-weight: bold;
        }

        .account-table {
            width: 98%;
            margin: 0 auto;
            border-collapse: collapse;
        }

        .account-table th,
        .account-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }

        .account-table th {
            background-color: #f2f2f2;
        }

        .collapse {
            transition: height 0.3s ease-out;
            overflow: hidden;
        }

        .toggle-icon {
            transition: transform 0.3s ease;
        }

        .process-row:hover {
            background-color: #f1f1f1;
        }

        .bi-chevron-right,
        .bi-chevron-down {
            cursor: pointer;
        }

        .show {
            display: table-row;
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
                <a href="orders.php" id="orders"><i class="fas fa-shopping-basket"></i> Orders</a>
                <a href="disapproved.php" id="disapproved" class="active"><i class="fas fa-thumbs-down"></i> Disapproved Order</a>
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
                <a href="index.php" id="home"><i class="fas fa-home"></i> Home</a>
                <a href="process.php" id="my-cart" class="active"><i class="fas fa-cart-arrow-down"></i> To Process</a>
                <a href="orders.php" id="orders"><i class="fas fa-shopping-basket"></i> Orders</a>
                <a href="disapproved.php" id="disapproved" class="active"><i class="fas fa-thumbs-down"></i> Disapproved Order</a>
                <a href="inventory.php" id="inventory"><i class="fas fa-sitemap"></i> Inventory </a>
                <a href="about.php"><i class="fas fa-info-circle"></i> About</a>
            </div>
        </div>
    </div>

    <div class="home-container">
        <nav class="navbar">
            <div class="container-fluid">
                <a class="navbar-brand fw-bold fs-3 ms-2 font-effect-shadow-multiple">DISAPPROVED</a>
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

                <form id="changePasswordForm" method="post" action="process.php" class="text-center" style="display: none;">
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

        <div class="order-container mt-2">
            <form>
                <div class="user-container mb-2" style="max-width: 95%; margin: 0 auto;">
                    <input type="search" class="w-100 p-1" style="outline: none;" name="search_process" id="search_process" placeholder="Search..." oninput="searchOrder()">
                </div>
            </form>
            <div class="row row-cols-1 row-cols-md-6 ms-4 btn-category p-1" style="max-width: 96%; margin: 0 auto;">
                <table class="table table-hover process-table" id="process-table" style="border: 2px solid #aaa; border-right: none; border-left: none; font-family: Arial, sans-serif;">
                    <thead>
                        <tr class="fw-bold text-nowrap">
                            <th>#</th>
                            <th>Order Number</th>
                            <th>Customer Name</th>
                            <th>Disapproved Date</th>
                            <th>Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $count = 0;
                        foreach ($orders as $order) :
                            $count++;
                            $profile = $order["customer_profile"];
                            $image_path = "../../img/" . $profile;
                            if (empty($profile) || !file_exists($image_path)) {
                                $image_path = "../../img/profile.png";
                            }
                        ?>
                            <tr class="process-row text-nowrap" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#order-details-<?php echo $order['order_number']; ?>" aria-expanded="false" aria-controls="order-details-<?php echo $order['order_number']; ?>">
                                <td class="p-2 count fw-bold">
                                    <strong><i class="bi bi-chevron-right toggle-icon text-primary fw-bold"></i></strong>
                                    <?php echo $count; ?>
                                </td>
                                <td class="p-2 order_number">
                                    <img src="<?php echo htmlspecialchars($image_path); ?>" alt="Profile Image" style="width: 35px; height: 35px; border-radius: 50%;">
                                    <span><?php echo htmlspecialchars($order['order_number']); ?></span>
                                </td>
                                <td class="p-2 customer_name"><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                <td class="p-2 disapprove_date"><?php echo htmlspecialchars($order['disapprove_date']); ?></td>
                                <td class="p-2 disapprove_reason text-wrap"><?php echo htmlspecialchars($order['disapprove_reason']); ?></td>
                            </tr>
                            <tr id="order-details-<?php echo $order['order_number']; ?>" class="collapse">
                                <td colspan="6">
                                    <ul>
                                        <li><strong>Product Details</strong>
                                            <ul>
                                                <?php foreach ($order['items'] as $item) : ?>
                                                    <li>
                                                        <strong>&times;<?php echo htmlspecialchars($item['quantity']); ?> <?php echo htmlspecialchars($item['product_name']); ?></strong> -
                                                        ₱<?php echo number_format($item['price'], 2); ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </li>
                                        <li><strong>Order Date:</strong> <?php echo htmlspecialchars($order['order_date']); ?></li>
                                        <li><strong>Payment Method:</strong> <?php echo ucfirst(htmlspecialchars($order['payment_method'])); ?></li>

                                        <li><strong>Cash Amount:</strong> ₱<?php echo number_format($order['cash_amount'], 2); ?></li>
                                    </ul>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </div>

        <div class="btn-container-bottom fixed-btn">
            <button type="button" class="btn btn-primary " onclick="exportToExcel()">
                <i class="fas fa-file-export"></i> Export to Excel
            </button>
        </div>

    </div>

    <script>
        document.querySelectorAll('.process-row').forEach(row => {
            row.addEventListener('click', function() {
                const icon = this.querySelector('.toggle-icon');
                const target = this.getAttribute('data-bs-target');
                const collapseElement = document.querySelector(target);

                if (collapseElement.classList.contains('show')) {
                    collapseElement.classList.remove('show');
                    icon.classList.remove('bi-chevron-down');
                    icon.classList.add('bi-chevron-right');
                } else {
                    document.querySelectorAll('.collapse').forEach(el => el.classList.remove('show'));
                    document.querySelectorAll('.toggle-icon').forEach(i => {
                        i.classList.remove('bi-chevron-down');
                        i.classList.add('bi-chevron-right');
                    });

                    collapseElement.classList.add('show');
                    icon.classList.remove('bi-chevron-right');
                    icon.classList.add('bi-chevron-down');
                }
            });
        });
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


    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.0/xlsx.full.min.js"></script>
    <script>
        function exportToExcel() {
            var table = document.getElementById("process-table");
            var ws = XLSX.utils.table_to_sheet(table);

            var wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, "Disapproved Orders");

            XLSX.writeFile(wb, "Disapproved Orders.xlsx");
        }
    </script>

    <script>
        function searchOrder() {
            var input = document.getElementById("search_process").value.toLowerCase();
            var rows = document.querySelectorAll(".process-row");

            rows.forEach(function(row) {
                var count = row.querySelector(".count").textContent.toLowerCase();
                var orderNumber = row.querySelector(".order_number span").textContent.toLowerCase();
                var customerName = row.querySelector(".customer_name").textContent.toLowerCase();
                var disapproveDate = row.querySelector(".disapprove_date").textContent.toLowerCase();
                var disapproveReason = row.querySelector(".disapprove_reason").textContent.toLowerCase();

                var combinedText = `${count} ${orderNumber} ${customerName} ${disapproveDate} ${disapproveReason}`;

                if (combinedText.includes(input)) {
                    row.style.display = "table-row";
                } else {
                    row.style.display = "none";
                }
            });
        }
    </script>


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

                fetch("process.php", {
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
                    window.location.href = 'process.php';
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