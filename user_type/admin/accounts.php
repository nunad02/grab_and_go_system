<?php
include '../../db/conn.php';
session_start();
ob_start();

if (!isset($_SESSION['account_id'])) {
    header("Location: ../../index.php");
    exit();
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
                header('Location: accounts.php');
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
    $stmt = $conn->query("SELECT * FROM account");
    $account = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $count = 1;
} catch (PDOException $e) {
    die("Error fetching account: " . $e->getMessage());
}

if (isset($_POST['addAccount'])) {
    $fullname = htmlspecialchars($_POST['fullname']);
    $email = htmlspecialchars($_POST['email']);
    $password = $_POST['password'];
    $hashed_password = sha1($password);

    $category = htmlspecialchars($_POST['category']);
    $status = 'active';
    $date_created = date('Y-m-d');

    try {
        $stmt = $conn->prepare("INSERT INTO account (fullname, email, password, date_created, category, status) VALUES (:fullname, :email, :password, :date_created, :category, :status)");
        $stmt->bindParam(':fullname', $fullname);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password', $hashed_password);
        $stmt->bindParam(':date_created', $date_created);
        $stmt->bindParam(':category', $category);
        $stmt->bindParam(':status', $status);

        if ($stmt->execute()) {
            $_SESSION['add_success'] = true;
            header('Location: accounts.php');
            exit();
        } else {
            echo "Error: Could not execute the statement.";
        }
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }

    $conn = null;
}
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (isset($data['delete_account_id'])) {
        $deleteID = $data['delete_account_id'];

        try {
            $stmt = $conn->prepare("DELETE FROM account WHERE account_id = ?");
            if ($stmt->execute([$deleteID])) {
                $_SESSION['delete_success'] = true;
                echo json_encode(['success' => true]);
            } else {
                $_SESSION['delete_error'] = true;
                echo json_encode(['success' => false]);
            }
        } catch (PDOException $e) {
            $_SESSION['delete_error'] = true;
            echo json_encode(['success' => false]);
        }
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $accountId = $_POST['editAccountId'];
    $fullname = $_POST['editFullname'];
    $email = $_POST['editEmail'];
    $category = $_POST['editCategory'];

    try {
        $stmt = $conn->prepare("UPDATE account SET fullname = ?, email = ?, category = ? WHERE account_id = ?");
        if ($stmt->execute([$fullname, $email, $category, $accountId])) {
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
    exit;
}
?>



<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grab & Go | Accounts</title>
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
                <a href="products.php" id="products"><i class="fas fa-boxes"></i> Manage Products </a>
                <a href="feedback.php" id="feedback"><i class="fas fa-comments"></i> Feedback</a>
                <a href="accounts.php" class="active" id="accounts"><i class="fas fa-user"></i> Accounts </a>
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
                <a href="products.php" id="products"><i class="fas fa-boxes"></i> Manage Products </a>
                <a href="feedback.php" id="feedback"><i class="fas fa-comments"></i> Feedback</a>
                <a href="accounts.php" class="active" id="accounts"><i class="fas fa-user"></i> Accounts </a>
                <a href="about.php"><i class="fas fa-info-circle"></i> About</a>
            </div>
        </div>
    </div>

    <div class="home-container">
        <nav class="navbar">
            <div class="container-fluid">
                <a class="navbar-brand fw-bold fs-3 ms-2 font-effect-shadow-multiple">ACCOUNTS</a>
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

                <form id="changePasswordForm" method="post" action="account.php" class="text-center" style="display: none;">
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
            <div class="row row-cols-1 row-cols-md-6 ms-4 btn-category p-1" style="max-width: 96%; margin: 0 auto;">
                <table class="table table-hover account-table" id="account-table" style="border: 2px solid #aaa; border-right: none; border-left: none; font-family: Arial, sans-serif;">
                    <form>
                        <div class="user-container w-100 mb-3">
                            <input type="search" class="w-100 p-1" style="outline: none;" name="search_account" id="search_account" placeholder="Search..." oninput="searchOrder()">
                        </div>
                    </form>
                    <thead>
                        <tr class="fw-bold text-nowrap">
                            <th>#</th>
                            <th>Account ID</th>
                            <th>Fullname</th>
                            <th>Email</th>
                            <th>Date Created</th>
                            <th>User type</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($account as $count => $accounts) :
                            $profile = $accounts["profile"];
                            $image_path = "../../img/" . $profile;

                            if (empty($profile) || !file_exists($image_path)) {
                                $image_path = "../../img/profile.png";
                            }
                        ?>

                            <tr class="account-row text-nowrap" style="cursor: pointer;">
                                <td class="fw-bold count"><?php echo $count + 1; ?></td>
                                <td class="p-2 account_id">
                                    <img src="<?php echo htmlspecialchars($image_path); ?>" alt="Profile Image" style="width: 35px; height: 35px; border-radius: 50%;">
                                    <span><?php echo htmlspecialchars($accounts['account_id']); ?></span>
                                </td>
                                <td class="p-2 fullname"><?php echo htmlspecialchars($accounts['fullname']); ?></td>
                                <td class="p-2 email"><?php echo htmlspecialchars($accounts['email']); ?></td>
                                <td class="p-2 date_created"><?php echo htmlspecialchars($accounts['date_created']); ?></td>
                                <td class="p-2 category"><?php echo htmlspecialchars($accounts['category']); ?></td>
                                <td class="p-2">
                                    <a href="#" class="editAccount text-primary" data-id="<?php echo htmlspecialchars($accounts['account_id']); ?>" data-fullname="<?php echo htmlspecialchars($accounts['fullname']); ?>" data-email="<?php echo htmlspecialchars($accounts['email']); ?>" data-category="<?php echo htmlspecialchars($accounts['category']); ?>" data-bs-toggle="modal" data-bs-target="#modalEditAccount">Edit</a> ||
                                    <a href="javascript:void(0)" class="text-danger" onclick="deleteAccount(<?php echo htmlspecialchars($accounts['account_id']); ?>)">Delete</a>

                                </td>

                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div><br><br>
        </div>

        <div class="btn-container-bottom fixed-btn">
            <button type="button" class="btn btn-primary " onclick="exportToExcel()">
                <i class="fas fa-file-export"></i> Export
            </button>

            <button type="button" class="btn btn-primary" onclick="openAddModal()">
                <i class="fas fa-plus"></i> Add Account
            </button>
        </div>

    </div>

    <form method="post" id="addAccountForm" action="accounts.php">
        <div class="modal fade" id="modalAddAccount" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="modalAddAccountLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalAddAccountLabel">Add Account</h5>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="addAccountId" name="AccountId">
                        <div class="mb-3">
                            <label for="addAccountName">Full Name:</label>
                            <input type="text" class="form-control" id="addAccountName" name="fullname" placeholder="Full Name" required>
                        </div>
                        <div class="mb-3">
                            <label for="addAccountEmail">Email Address:</label>
                            <input type="email" id="addAccountEmail" name="email" class="form-control" placeholder="Email Address" required>
                        </div>
                        <div class="mb-3">
                            <label for="addAccountPassword">Password:</label>
                            <input type="password" id="addAccountPassword" name="password" class="form-control" placeholder="Password" required>
                        </div>
                        <div class="mb-3">
                            <label for="addAccountCategory">Category:</label>
                            <select id="addAccountCategory" name="category" class="form-select">
                                <option value="" disabled selected> Select Category</option>
                                <option value="Customer">Customer</option>
                                <option value="Cashier">Cashier</option>
                                <option value="Admin">Admin</option>
                            </select>
                        </div>

                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="addAccount" class="btn btn-primary">Submit</button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <!-- Edit Account Modal -->
    <form id="editAccountForm">
        <div class="modal fade" id="modalEditAccount" tabindex="-1" aria-labelledby="editAccountLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">

                    <div class="modal-header">
                        <h5 class="modal-title" id="editAccountLabel">Edit Account</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="editAccountId" id="editAccountId">
                        <div class="mb-3">
                            <label for="editFullname" class="form-label">Fullname</label>
                            <input type="text" class="form-control" id="editFullname" name="editFullname" required>
                        </div>
                        <div class="mb-3">
                            <label for="editEmail" class="form-label">Email</label>
                            <input type="email" class="form-control" id="editEmail" name="editEmail" required>
                        </div>
                        <div class="mb-3">
                            <label for="editCategory" class="form-label">Category</label>
                            <select name="editCategory" class="form-select" id="editCategory" required>
                                <option value="" disabled selected>Select category</option>
                                <option value="Customer">Customer</option>
                                <option value="Cashier">Cashier</option>
                                <option value="Admin">Admin</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="editAccount" class="btn btn-primary">Save changes</button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <script src="../../js/cashier_script.js"> </script>

    <script>
        document.querySelectorAll('.editAccount').forEach(function(element) {
            element.addEventListener('click', function() {
                document.getElementById('editAccountId').value = this.dataset.id;
                document.getElementById('editFullname').value = this.dataset.fullname;
                document.getElementById('editEmail').value = this.dataset.email;
                document.getElementById('editCategory').value = this.dataset.category;
            });
        });

        document.getElementById('editAccountForm').addEventListener('submit', function(event) {
            event.preventDefault();
            var formData = new FormData(this);

            fetch('accounts.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        document.getElementById('statusMessage').innerHTML = '<i class="fas fa-warning text-danger"></i> Error updating account.';
                        $('#statusModal').modal('show');
                        setTimeout(function() {
                            $('#statusModal').modal('hide');
                        }, 2000);
                    }
                })

        });
    </script>

    <!-- Confirmation Modal -->
    <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-labelledby="confirmDeleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmDeleteModalLabel">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete this account?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteButton">Delete</button>
                </div>
            </div>
        </div>
    </div>

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

    <?php include_once '../../includes/goToTop.php'; ?>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.0/xlsx.full.min.js"></script>
    <script>
        function exportToExcel() {
            var originalTable = document.getElementById("account-table");

            var tableCopy = originalTable.cloneNode(true);
            var rows = tableCopy.querySelectorAll("tr");

            rows.forEach(function(row) {
                if (row.cells.length > 0) {
                    row.deleteCell(row.cells.length - 1);
                }
            });

            var ws = XLSX.utils.table_to_sheet(tableCopy);

            var colWidths = [];
            var range = XLSX.utils.decode_range(ws['!ref']);
            for (var C = range.s.c; C <= range.e.c; ++C) {
                var maxWidth = 10;
                for (var R = range.s.r; R <= range.e.r; ++R) {
                    var cell = ws[XLSX.utils.encode_cell({
                        r: R,
                        c: C
                    })];
                    if (cell && cell.v) {
                        var cellText = cell.v.toString();
                        maxWidth = Math.max(maxWidth, cellText.length + 5);
                    }
                }
                colWidths.push({
                    wch: maxWidth
                });
            }
            ws['!cols'] = colWidths;
            var wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, "Accounts");

            XLSX.writeFile(wb, "Accounts.xlsx");
        }
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($_SESSION['delete_success'])) : ?>
                $('#statusMessage').html('<i class="fas fa-check text-success"></i> Account deleted successfully.');
                $('#statusModal').modal('show');
                setTimeout(function() {
                    $('#statusModal').modal('hide');
                    <?php unset($_SESSION['delete_success']); ?>
                }, 2000);
            <?php elseif (isset($_SESSION['delete_error'])) : ?>
                $('#statusMessage').html('<i class="fas fa-warning text-danger"></i> Error deleting account.');
                $('#statusModal').modal('show');
                setTimeout(function() {
                    $('#statusModal').modal('hide');
                    <?php unset($_SESSION['delete_error']); ?>
                }, 2000);
            <?php elseif (isset($_SESSION['edit_success'])) : ?>
                $('#statusMessage').html('<i class="fas fa-check text-success"></i> Account updated successfully.');
                $('#statusModal').modal('show');
                setTimeout(function() {
                    $('#statusModal').modal('hide');
                    <?php unset($_SESSION['edit_success']); ?>
                }, 2000);
            <?php elseif (isset($_SESSION['edit_error'])) : ?>
                $('#statusMessage').html('<i class="fas fa-warning text-danger"></i> Error updating account.');
                $('#statusModal').modal('show');
                setTimeout(function() {
                    $('#statusModal').modal('hide');
                    <?php unset($_SESSION['edit_error']); ?>
                }, 2000);
            <?php elseif (isset($_SESSION['add_success'])) : ?>
                $('#statusMessage').html('<i class="fas fa-check text-success"></i> Account successfully created.');
                $('#statusModal').modal('show');
                setTimeout(function() {
                    $('#statusModal').modal('hide');
                    <?php unset($_SESSION['add_success']); ?>
                }, 2000);
            <?php elseif (isset($_SESSION['add_error'])) : ?>
                $('#statusMessage').html('<i class="fas fa-warning text-danger"></i> Error creating account.');
                $('#statusModal').modal('show');
                setTimeout(function() {
                    $('#statusModal').modal('hide');
                    <?php unset($_SESSION['add_error']); ?>
                }, 2000);
            <?php endif; ?>
        });

        function openAddModal() {

            $('#modalAddAccount').modal('show');

        }

        function searchOrder() {
            var input = document.getElementById("search_account").value.toLowerCase();
            var rows = document.querySelectorAll(".account-row");

            rows.forEach(function(row) {
                var count = row.querySelector(".count").textContent.toLowerCase();
                var account_id = row.querySelector(".account_id").textContent.toLowerCase();
                var fullname = row.querySelector(".fullname").textContent.toLowerCase();
                var email = row.querySelector(".email").textContent.toLowerCase();
                var date_created = row.querySelector(".date_created").textContent.toLowerCase();
                var category = row.querySelector(".category").textContent.toLowerCase();

                if (count.includes(input) || account_id.includes(input) || fullname.includes(input) || email.includes(input) || date_created.includes(input) || category.includes(input)) {
                    row.style.display = "table-row";
                } else {
                    row.style.display = "none";
                }
            });
        }
    </script>

    <script>
        function deleteAccount(accountId) {
            var confirmDeleteModal = new bootstrap.Modal(document.getElementById('confirmDeleteModal'), {});
            confirmDeleteModal.show();

            document.getElementById('confirmDeleteButton').onclick = function() {
                confirmDeleteModal.hide();

                fetch('accounts.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            delete_account_id: accountId
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.location.reload();
                        } else {
                            $('#statusMessage').html('<i class="fas fa-warning text-danger"></i> Error deleting account.');
                            $('#statusModal').modal('show');
                            setTimeout(function() {
                                $('#statusModal').modal('hide');
                            }, 2000);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        $('#statusMessage').html('<i class="fas fa-warning text-danger"></i> Error deleting account.');
                        $('#statusModal').modal('show');
                        setTimeout(function() {
                            $('#statusModal').modal('hide');
                        }, 2000);
                    });
            };
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

                fetch("accounts.php", {
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
                    window.location.href = 'accounts.php';
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