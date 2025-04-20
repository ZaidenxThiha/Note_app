<?php
session_start();

// Redirect unauthenticated users to login page
if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit();
}

// Database connection
$db_host = 'mysql';
$db_user = 'root';
$db_pass = 'YourStrong@Passw0rd';
$db_name = 'noteapp';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Fetch user data to check activation status
    $stmt = $pdo->prepare("SELECT is_activated, email FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        session_destroy();
        header("Location: /login.php");
        exit();
    }

    $isUnverified = !$user['is_activated'];
    $userEmail = $user['email'];
} catch (PDOException $e) {
    die("Error fetching user data: " . $e->getMessage());
}

// Handle resend activation email request
if (isset($_GET['resend']) && $isUnverified && isset($userEmail)) {
    $stmt = $pdo->prepare("SELECT activation_token FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $activationToken = $user['activation_token'];
    $userId = $_SESSION['user_id'];
    $display_name = $_SESSION['display_name'];

    $activationLink = "http://localhost/activate.php?token=$activationToken&id=$userId";
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'mailhog';
        $mail->Port = 1025;
        $mail->SMTPAuth = false;
        $mail->SMTPSecure = false;
        $mail->SMTPAutoTLS = false;

        $mail->setFrom('no-reply@noteapp.com', 'My Note');
        $mail->addAddress($userEmail, $display_name);

        $mail->isHTML(true);
        $mail->Subject = 'Activate Your My Note Account';
        $mail->Body = "<p>Hello $display_name,</p>
                       <p>Please click the link below to activate your account:</p>
                       <a href='$activationLink'>Activate Account</a>
                       <p>If you did not register, please ignore this email.</p>";
        $mail->AltBody = "Hello $display_name,\n\nPlease activate your account by visiting the following link: $activationLink\n\nIf you did not register, please ignore this email.";

        $mail->send();
        $message = 'Activation email resent successfully! Please check your email.';
    } catch (Exception $e) {
        $error = "Failed to resend activation email: {$mail->ErrorInfo}";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome - My Note</title>
    <link href='https://fonts.googleapis.com/css?family=Poppins' rel='stylesheet'>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins';
        }

        body {
            background: #fffffd;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .notification {
            background-color: #FF4D4D;
            color: #fff;
            padding: 15px;
            text-align: center;
            font-size: 16px;
            width: 100%;
            position: fixed;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .notification a {
            color: #fff;
            text-decoration: underline;
            cursor: pointer;
        }

        .notification a:hover {
            text-decoration: none;
        }

        .container {
            background: #fffdf4;
            width: 90%;
            height: 90%;
            display: flex;
            border-radius: 50px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }

        .left,
        .right {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .left {
            flex-direction: column;
        }

        .left h1 {
            font-size: 40px;
        }

        .right {
            background: #fffffd;
            border-radius: 30px;
            margin: 30px;
            padding: 20px;
            flex-direction: column;
        }

        .welcome-container {
            width: 100%;
            max-width: 400px;
            display: flex;
            flex-direction: column;
            text-align: center;
        }

        .welcome-container h2 {
            font-size: 36px;
            margin: auto;
            margin-bottom: 20px;
        }

        .welcome-container p {
            font-size: 14px;
            color: #666;
            margin-bottom: 20px;
        }

        .welcome-container a {
            color: #3366ff;
            text-decoration: none;
            font-size: 16px;
        }

        .welcome-container a:hover {
            text-decoration: underline;
        }

        .success-message {
            font-size: 14px;
            color: #28a745;
            text-align: center;
            margin-bottom: 20px;
        }

        /* RESPONSIVE for mobile */
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
                height: auto;
                padding: 20px;
                margin-top: 80px;
            }

            .left,
            .right {
                width: 100%;
                margin: 0;
                border-radius: 0;
                padding: 20px 0;
            }

            .left h1 {
                font-size: 32px;
                text-align: center;
            }

            .right {
                padding: 20px;
                margin: 0;
            }

            .welcome-container {
                max-width: 100%;
            }

            body {
                height: auto;
                padding: 20px 0;
            }
        }
    </style>
</head>
<body>
    <?php if ($isUnverified): ?>
        <div class="notification">
            Your account is unverified. Please check your email to complete the activation process.
            <a href="/welcome.php?resend=true">Resend Activation Email</a>
        </div>
    <?php endif; ?>
    <div class="container">
        <div class="left">
            <h1>My Note</h1>
        </div>
        <div class="right">
            <div class="welcome-container">
                <h2>Welcome to My Note!</h2>
                <p>You have successfully registered. Start taking notes now!</p>
                <?php if (!empty($message)): ?>
                    <p class="success-message"><?php echo htmlspecialchars($message); ?></p>
                <?php endif; ?>
                <a href="/home.php">Go to Home</a>
            </div>
        </div>
    </div>
</body>
</html>