<?php
session_start();

// Database connection
$db_host = 'mysql';
$db_user = 'noteapp_user';
$db_pass = 'YourStrong@Passw0rd';
$db_name = 'noteapp';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Include PHPMailer
require __DIR__ . '/vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Check if the user is logged in and unverified
$isUnverified = false;
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT is_activated, email FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $isUnverified = !$user['is_activated'];
        $userEmail = $user['email'];
    }
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

$error = '';
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';

    if (empty($email)) {
        $error = 'Please enter your email address.';
    } else {
        try {
            // Check if email exists
            $stmt = $pdo->prepare("SELECT id, display_name FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $error = 'Email not found.';
            } else {
                // Generate OTP and reset token
                $otp = sprintf("%06d", mt_rand(100000, 999999)); // 6-digit OTP
                $resetToken = bin2hex(random_bytes(32));
                $userId = $user['id'];
                $display_name = $user['display_name'];

                // Store OTP and reset token in the database
                $stmt = $pdo->prepare("UPDATE users SET reset_otp = ?, reset_token = ?, reset_expiry = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?");
                $stmt->execute([$otp, $resetToken, $userId]);

                // Send reset email using PHPMailer
                $resetLink = "http://localhost/reset_password.php?token=$resetToken&id=$userId";
                $mail = new PHPMailer(true);
                try {
                    // Server settings
                    $mail->isSMTP();
                    $mail->Host = 'mailhog'; // MailHog SMTP host
                    $mail->Port = 1025; // MailHog SMTP port
                    $mail->SMTPAuth = false; // MailHog doesn't require authentication
                    $mail->SMTPSecure = false; // No SSL/TLS for MailHog
                    $mail->SMTPAutoTLS = false; // Disable auto TLS

                    // Recipients
                    $mail->setFrom('no-reply@noteapp.com', 'My Note');
                    $mail->addAddress($email, $display_name);

                    // Content
                    $mail->isHTML(true);
                    $mail->Subject = 'Reset Your My Note Password';
                    $mail->Body = "<p>Hello $display_name,</p>
                                   <p>We received a request to reset your password. Use the following OTP and link to reset your password:</p>
                                   <p><strong>OTP:</strong> $otp</p>
                                   <p><a href='$resetLink'>Click here to reset your password</a></p>
                                   <p>This link and OTP will expire in 1 hour. If you did not request a password reset, please ignore this email.</p>";
                    $mail->AltBody = "Hello $display_name,\n\nWe received a request to reset your password. Use the following OTP and link to reset your password:\n\nOTP: $otp\n\nLink: $resetLink\n\nThis link and OTP will expire in 1 hour. If you did not request a password reset, please ignore this email.";

                    $mail->send();
                    $message = 'A password reset link and OTP have been sent to your email.';
                } catch (Exception $e) {
                    $error = "Failed to send reset email: {$mail->ErrorInfo}";
                }
            }
        } catch (PDOException $e) {
            $error = 'Error processing reset request: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password Request - My Note</title>
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

        .reset-form {
            width: 100%;
            max-width: 400px;
            display: flex;
            flex-direction: column;
        }

        .reset-form h2 {
            font-size: 36px;
            margin: auto;
        }

        .reset-form label {
            font-size: 18px;
            margin: 10px 0 5px;
        }

        .reset-form input {
            padding: 15px;
            border: none;
            background: #dedede;
            border-radius: 30px;
            margin-bottom: 20px;
            font-size: 16px;
            width: 100%;
        }

        button {
            padding: 10px;
            border: none;
            background: #dedede;
            border-radius: 10px;
            cursor: pointer;
            width: 70px;
            align-self: center;
            font-size: 24px;
            font-weight: bold;
        }

        button:hover {
            background: #fffffd;
            font-size: xx-large;
        }

        .links {
            margin-top: 50px;
            font-size: 14px;
            text-align: left;
        }

        .links a {
            color: #3366ff;
            text-decoration: none;
        }

        .links a:hover {
            text-decoration: underline;
        }

        .error-message, .success-message {
            font-size: 14px;
            text-align: center;
            margin-bottom: 20px;
        }

        .error-message {
            color: red;
        }

        .success-message {
            color: #28a745;
        }

        .loading-spinner {
            display: none;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3366ff;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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

            .reset-form {
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
            <a href="/reset_request.php?resend=true">Resend Activation Email</a>
        </div>
    <?php endif; ?>
    <div class="container">
        <div class="left">
            <h1>My Note</h1>
        </div>
        <div class="right">
            <h2>Reset Password</h2>
            <form class="reset-form" id="reset-form" action="/reset_request.php" method="POST" onsubmit="showLoading()">
                <?php if (!empty($error)): ?>
                    <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
                <?php endif; ?>
                <?php if (!empty($message)): ?>
                    <p class="success-message"><?php echo htmlspecialchars($message); ?></p>
                <?php endif; ?>
                <label for="email">Email</label>
                <input type="email" name="email" id="email" placeholder="Enter your email" required>
                <button type="submit" class="submit-btn">
                    <span class="arrow">→</span>
                </button>
                <div class="loading-spinner" id="loading-spinner"></div>
                <div class="links">
                    <p>Back to <a href="/login.php">Log In</a></p>
                </div>
            </form>
        </div>
    </div>
    <script>
        function showLoading() {
            const submitBtn = document.querySelector('.submit-btn');
            const loadingSpinner = document.getElementById('loading-spinner');
            submitBtn.disabled = true;
            submitBtn.style.opacity = '0.6';
            loadingSpinner.style.display = 'block';
        }
    </script>
</body>
</html>