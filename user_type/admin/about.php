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
                header('Location: about.php');
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
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grab & Go | About</title>
    <link rel="shortcut icon" href="../../img/logo.png" type="image/x-icon">
    <?php include_once '../../header.php' ?>
    <link rel="stylesheet" href="../../css/cus_main.css">
    <style>
        .dev-container {
            display: flex;
            gap: 10px;
            max-width: 98%;
            width: 96%;
            margin: 10px auto;
        }

        .column {
            width: 100%;
            height: auto;
            border: 2px solid #007bff;
            border-radius: 10px;
            box-sizing: border-box;
            padding: 20px;
            position: relative;
            background-color: #fff;
            transition: background-color 0.3s ease, box-shadow 0.3s ease, transform 0.3s ease;
        }

        .column:hover {
            background-color: #f1faff;
            box-shadow: 0 8px 20px rgba(0, 123, 255, 0.15);
            transform: translateY(-8px);
        }

        .column img {
            display: block;
            margin: 0 auto;
            border-radius: 50%;
            border: 3px solid #ff8c00;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .column img:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 15px rgba(0, 123, 255, 0.2);
        }

        .column .info {
            overflow: hidden;
        }

        .column h6 {
            margin-top: 10px;
            font-size: 12px;
        }

        .column::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 0;
            height: 0;
            transform: rotate(270deg);
            border-left: 50px solid transparent;
            border-bottom: 50px solid #007bff;
            transition: border-bottom-color 0.3s ease, transform 0.3s ease;
        }

        .column:hover::after {
            border-bottom-color: #ff8c00;
        }

        .info h6 i {
            color: #007bff;
            margin-right: 8px;
        }

        .info strong {
            font-weight: bold;
        }

        .info span {
            color: #555;
        }

        /* Main Container */
        .user-main-container {
            width: 96%;
            margin: 0 auto;
            position: relative;
            overflow: hidden;
            border: 2px solid #007bff;
            border-radius: 10px;
            background-color: rgba(255, 255, 255, 0.9);
        }

        .slider {
            display: flex;
            transition: transform 0.5s ease-in-out;
        }

        .slide {
            min-width: 100%;
            text-align: center;
            padding: 40px;
        }

        .slide img {
            float: left;
            max-width: 100%;
            height: 320px;
            border-radius: 6px;
            margin-left: 30px;
            box-shadow:
                0 6px 12px rgba(0, 0, 0, 0.25),
                0 3px 6px rgba(0, 0, 0, 0.2);
        }

        .guide {
            padding: 20px;
            background-color: rgba(240, 240, 240, 0.9);
            box-shadow:
                0 6px 12px rgba(0, 0, 0, 0.25),
                0 3px 6px rgba(0, 0, 0, 0.2);
            border-radius: 6px;
            margin: 0 35px 0 0;
            width: 470px;
            height: 320px;
            float: right;
        }

        .guide h6 {
            font-size: 1.2rem;
            color: #333;
            text-align: left;
            line-height: 1.7;
        }

        .slider-controls {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 98%;
            padding: 10px;
            border-radius: 10px;
        }

        .slider-controls button {
            background: rgba(0, 0, 0, 0.5);
            color: #fff;
            border: none;
            padding: 10px 15px;
            cursor: pointer;
            border-radius: 50%;
            font-size: 1.5rem;
            transition: background 0.3s;
        }


        .slider-controls button:hover {
            background: rgba(0, 0, 0, 0.8);
        }


        @media screen and (max-width: 576px) {
            .dev-container {
                flex-direction: column;
                gap: 20px;
                padding-bottom: 70px;
            }

            .column img {
                float: none;
                display: block;
                margin: 0 auto 20px;
            }
        }

        @media (max-width: 768px) {
            .guide {
                width: 100%;
                margin: 10px 0;
            }

            .guide h6 {
                font-size: 0.9rem;
            }

            .slide img {
                height: auto;
                margin: 0 auto 10px;
            }

            .slider-controls button {
                font-size: 1.2rem;
                padding: 8px 12px;
            }
        }

        @media (max-width: 480px) {
            .guide h6 {
                font-size: 0.8rem;
            }

            .slider-controls button {
                font-size: 1.5rem;
                padding: 5px 10px;
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
                <a href="products.php" id="products"><i class="fas fa-boxes"></i> Manage Products </a>
                <a href="feedback.php" id="feedback"><i class="fas fa-comments"></i> Feedback</a>
                <a href="accounts.php" id="accounts"><i class="fas fa-user"></i> Accounts </a>
                <a href="about.php" class="active"><i class="fas fa-info-circle"></i> About</a>
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
                <a href="accounts.php" id="accounts"><i class="fas fa-user"></i> Accounts </a>
                <a href="about.php" class="active"><i class="fas fa-info-circle"></i> About</a>
            </div>
        </div>
    </div>

    <div class="home-container">
        <nav class="navbar">
            <div class="container-fluid">
                <a class="navbar-brand fw-bold fs-3 ms-2 font-effect-shadow-multiple">ABOUT</a>
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

                <form id="changePasswordForm" method="post" action="about.php" class="text-center" style="display: none;">
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

        <div class="dev-container">
            <div class="column">
                <p class="fw-bold text-dark text-center fs-2" style="color: #007bff;">About Grab & Go</p>
                <p>Grab&Go: A Web Application for Smart Grocery Shopping was designed and developed to help busy professionals of Occidental Mindoro State College (OMSC) go grocery shopping despite their busy schedules. The system consists of 3 user types: customer, cashier, and admin. Each user has different features and functionalities within the system. The system provides a convenient and hassle-free grocery shopping experience.</p>
            </div>
        </div><br>

        <p class="fw-bold text-dark text-center fs-2" style="color: #007bff;">Our Team</p>
        <div class="dev-container">
            <div class="column">
                <img src="../../img/developers/claire.jpg" alt="Marie Claire P. Amar" height="100px" width="100px">
                <div class="info">
                    <h6><i class="fas fa-user"></i><strong>Name:</strong> <span>Marie Claire P. Amar</span></h6>
                    <h6><i class="fas fa-envelope"></i><strong>Email Address:</strong> <span>marieclaireamar25@gmail.com</span></h6>
                    <h6><i class="fas fa-map-marker-alt"></i><strong>Address:</strong> <span>Poblacion, Calintaan, Occidental Mindoro</span></h6>
                </div>
            </div>
            <div class="column">
                <img src="../../img/developers/ruby.jpg" alt="Ruby Jane O. Saluba" height="100px" width="100px">
                <div class="info">
                    <h6><i class="fas fa-user"></i><strong>Name:</strong> <span>Ruby Jane O. Saluba</span></h6>
                    <h6><i class="fas fa-envelope"></i><strong>Email Address:</strong> <span>rubyjanesaluba@gmail.com</span></h6>
                    <h6><i class="fas fa-map-marker-alt"></i><strong>Address:</strong> <span>Poblacion, Calintaan, Occidental Mindoro</span></h6>
                </div>
            </div>
            <div class="column">
                <img src="../../img/developers/nemharie.jpg" alt="Nemharie H. Fuentes" height="100px" width="100px">
                <div class="info">
                    <h6><i class="fas fa-user"></i><strong>Name:</strong> <span>Nemharie H. Fuentes</span></h6>
                    <h6><i class="fas fa-envelope"></i><strong>Email Address:</strong> <span>nemharief@gmail.com</span></h6>
                    <h6><i class="fas fa-map-marker-alt"></i><strong>Address:</strong> <span>Mabini Ext. Labangan, Poblacion, San Jose, Occidental Mindoro</span></h6>
                </div>
            </div>
            <div class="column">
                <img src="../../img/developers/bea.jpg" alt="Bea Claudette A. Malillos" height="100px" width="100px">
                <div class="info">
                    <h6><i class="fas fa-user"></i><strong>Name:</strong> <span>Bea Claudette A. Malillos</span></h6>
                    <h6><i class="fas fa-envelope"></i><strong>Email Address:</strong> <span>beamalillos18@gmail.com</span></h6>
                    <h6><i class="fas fa-map-marker-alt"></i><strong>Address:</strong> <span>Bagong Sikat, San Jose, Occidental Mindoro</span></h6>
                </div>
            </div>

            <div class="column">
                <img src="../../img/developers/ferline.jpg" alt="" height="100px" width="100px">
                <div class="info">
                    <h6><i class="fas fa-user"></i><strong>Name:</strong> <span>Ferline Joyce B. Jaravata</span></h6>
                    <h6><i class="fas fa-envelope"></i><strong>Email Address:</strong> <span>jaravataferline@gmail.com</span></h6>
                    <h6><i class="fas fa-map-marker-alt"></i><strong>Address:</strong> <span>Barangay Mabini Annex, San Jose, Occidental Mindoro</span></h6>
                </div>
            </div>
        </div><br>

        <p class="fw-bold text-dark text-center fs-2" style="color: #007bff;">User's Guide</p>
        <div class="user-main-container">
            <div class="slider">
                <div class="slide">
                    <img src="../../img/user_manual/step1.png" alt="Step 1">
                    <div class="guide">
                        <h6><strong><span class="text-primary">(1)</span></strong> Select Admin to continue and click “Next”.</h6>
                    </div>
                </div>
                <div class="slide">
                    <img src="../../img/user_manual/step16.png" alt="Step 2">
                    <div class="guide">
                        <h6><strong><span class="text-primary">(2)</span></strong> Enter the Email Address and Password of the Admin and then click “Sign In” to access the system.</h6>
                    </div>
                </div>
                <div class="slide">
                    <img src="../../img/user_manual/step17.png" alt="Step 3">
                    <div class="guide">
                        <h6>
                            <strong><span class="text-primary">(3)</span></strong> Display the time and calendar. <br>
                        </h6>
                    </div>
                </div>
                <div class="slide">
                    <img src="../../img/user_manual/step18.png" alt="Step 4">
                    <div class="guide">
                        <h6>
                            <strong><span class="text-primary">(4)</span></strong> Display the charts of daily, monthly, and payment method sales. <br>
                            <strong><span class="text-primary">(5)</span></strong> Click any buttons and it will display order sales of different methods.<br>
                            <strong><span class="text-primary">(6)</span></strong> Click “View Order” to display the customer’s order.
                        </h6>
                    </div>
                </div>
                <div class="slide">
                    <img src="../../img/user_manual/step19.png" alt="Step 5">
                    <div class="guide">
                        <h6>
                            <strong><span class="text-primary">(7)</span></strong> Click any buttons at the top to display the inventory of each category. <br>
                            <strong><span class="text-primary">(8)</span></strong> Use “Search bar” to search for specific products.<br>
                            <strong><span class="text-primary">(9)</span></strong> Click “Add Product” button to add new product.<br>
                            <strong><span class="text-primary">(10)</span></strong> Click “Export” to generate a report.
                        </h6>
                    </div>
                </div>
                <div class="slide">
                    <img src="../../img/user_manual/step21.png" alt="Step 6">
                    <div class="guide">
                        <h6>
                            <strong><span class="text-primary">(11)</span></strong> Use “Search bar” to search for specific customer. <br>
                            <strong><span class="text-primary">(12)</span></strong> Click “Edit” or “Delete” to erase or change something.<br>
                            <strong><span class="text-primary">(13)</span></strong> Click “Add Account” button to add account.<br>
                            <strong><span class="text-primary">(14)</span></strong> Click “Export” to generate a report.
                        </h6>
                    </div>
                </div>
                <div class="slide">
                    <img src="../../img/user_manual/step22.png" alt="Step 7">
                    <div class="guide">
                        <h6>
                            <strong><span class="text-primary">(15)</span></strong> It display the purpose of the system and the team behind it. <br>
                            <strong><span class="text-primary">(16)</span></strong> Click the profile and it will display the edit account and logout button.<br>
                        </h6>
                    </div>
                </div>
            </div>

            <div class="slider-controls">
                <button id="prevBtn">❮</button>
                <button id="nextBtn">❯</button>
            </div>
        </div>

        <br><br><br><br>
    </div>

    <?php include_once '../../includes/goToTop.php'; ?>
    <script>
        const slider = document.querySelector('.slider');
        const slides = document.querySelectorAll('.slide');
        const prevBtn = document.getElementById('prevBtn');
        const nextBtn = document.getElementById('nextBtn');

        let currentIndex = 0;

        function updateSlider() {
            slider.style.transform = `translateX(-${currentIndex * 100}%)`;
        }

        prevBtn.addEventListener('click', () => {
            currentIndex = (currentIndex > 0) ? currentIndex - 1 : slides.length - 1;
            updateSlider();
        });

        nextBtn.addEventListener('click', () => {
            currentIndex = (currentIndex < slides.length - 1) ? currentIndex + 1 : 0;
            updateSlider();
        });
    </script>
    <script src="../../js/cashier_script.js"> </script>
    <script>
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

        document.getElementById("profileImage").addEventListener("click", function() {
            document.getElementById("profileInput").click();
        });

        document.getElementById("profileInput").addEventListener("change", function() {
            const file = this.files[0];
            if (file) {
                const formData = new FormData();
                formData.append("profileImage", file);

                fetch("about.php", {
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
                    window.location.href = 'about.php';
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