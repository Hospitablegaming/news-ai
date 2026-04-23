<?php
session_start();

require_once '../config/database.php';
require_once '../config/config.php';
require_once '../models/User.php';
require_once '../models/Prompt.php';
require_once '../models/Analytics.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

try {
    $db = new Database();
    $conn = $db->connect();
    $userModel = new User($conn);

    if (!isset($_GET['action']) && !isset($_POST['action'])) {
    die('Invalid request');
}

$action = $_POST['action'] ?? $_GET['action'];

    if ($action === 'register') {
        $username = trim($_GET['username'] ?? '');
        $firstName = trim($_GET['first_name'] ?? '');
        $lastName = trim($_GET['last_name'] ?? '');
        $email = trim($_GET['email'] ?? '');
        $password = $_GET['password'] ?? '';

        if ($username === '' || $firstName === '' || $lastName === '' || $email === '' || $password === '') {
            die('All fields are required');
        }

        if (strlen($password) < 6) {
            die('Password must be at least 6 characters');
        }

        if ($userModel->getByUsernameOrEmail($username, $email)) {
            die('Username or email already exists');
        }

        $userModel->register($username, $firstName, $lastName, $email, $password);
        header('Location: ../views/login.php?msg=registered');
        exit();
    }

    if ($action === 'login') {
        $email = trim($_GET['email'] ?? '');
        $password = $_GET['password'] ?? '';

        if ($email === '' || $password === '') {
            die('Email and password are required');
        }

        $user = $userModel->login($email, $password);
        if ($user) {
            $userRole = $userModel->getUserRole($user['id']);
            if ($userRole === 'banned') {
                header('Location: ../views/login.php?error=account_banned');
                exit();
            }

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $userRole;
            $_SESSION['subscription_id'] = $user['subscription_id'] ?? 1;
            header('Location: ../views/dashboard.php');
            exit();
        }

        header('Location: ../views/login.php?error=invalid_credentials');
        exit();
    }

    if ($action === 'change_password_public') {
        $email = trim($_GET['email'] ?? '');
        $currentPassword = $_GET['current_password'] ?? '';
        $newPassword = $_GET['new_password'] ?? '';
        $confirmPassword = $_GET['confirm_password'] ?? '';

        if ($email === '' || $currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            header('Location: ../views/login.php?pwd_error=missing_fields');
            exit();
        }

        if ($newPassword !== $confirmPassword) {
            header('Location: ../views/login.php?pwd_error=passwords_not_match');
            exit();
        }

        if (strlen($newPassword) < 6) {
            header('Location: ../views/login.php?pwd_error=password_too_short');
            exit();
        }

        $user = $userModel->login($email, $currentPassword);
        if (!$user) {
            header('Location: ../views/login.php?pwd_error=invalid_credentials');
            exit();
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        if ($userModel->updatePassword($user['id'], $hashedPassword)) {
            header('Location: ../views/login.php?pwd_success=1');
            exit();
        }

        header('Location: ../views/login.php?pwd_error=update_failed');
        exit();
    }

    if ($action === 'forgot_password') {
        $email = trim($_GET['email'] ?? '');

        if ($email === '') {
            header('Location: ../views/forgot_password.php?error=missing_email');
            exit();
        }

        $user = $userModel->getByEmail($email);
        if (!$user) {
            header('Location: ../views/forgot_password.php?error=email_not_found');
            exit();
        }

        // Generate 6-digit verification code
        $verificationCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        
        // Insert into password_resets table
        $stmt = $conn->prepare('INSERT INTO password_resets (email, verification_code) VALUES (?, ?)');
        $stmt->execute([$email, $verificationCode]);

        // Attempt to send email
        $subject = 'Password Reset Verification Code - News AI';
        $message = "Your password reset verification code is: " . $verificationCode . "\n\n";
        $message .= "This code will expire in 15 minutes.\n\n";
        $message .= "If you did not request this, please ignore this email.";

        $mailSent = false;
        $mailFailed = false;

        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;
            $mail->Debugoutput = 'error_log';
            $mail->Host = MAIL_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = MAIL_USERNAME;
            $mail->Password = MAIL_PASSWORD;
            $mail->SMTPSecure = MAIL_ENCRYPTION;
            $mail->Port = MAIL_PORT;
            $mail->CharSet = 'UTF-8';
            $mail->setFrom(MAIL_FROM_ADDRESS, MAIL_FROM_NAME);
            $mail->addAddress($email);
            $mail->Subject = $subject;
            $mail->Body = $message;
            $mail->AltBody = strip_tags($message);
            $mail->isHTML(false);
            $mail->send();
            $mailSent = true;
        } catch (Exception $e) {
            $mailFailed = true;
            $mailError = $mail->ErrorInfo ?? $e->getMessage();
            error_log("[PHPMailer DEBUG] " . ($mail->ErrorInfo ?? $e->getMessage()) . "\n");
        }

        $logsDir = sys_get_temp_dir() . '/news_ai_password_reset';
        if (!is_dir($logsDir)) {
            mkdir($logsDir, 0755, true);
        }
        $logFile = $logsDir . '/verification_events.log';
        error_log("[" . date('Y-m-d H:i:s') . "] Email: $email, Sent=" . ($mailSent ? '1' : '0') . ", Error=" . ($mailFailed ? addslashes($mailError) : 'none') . "\n", 3, $logFile);

        if ($mailFailed) {
            header('Location: ../views/forgot_password.php?error=email_failed');
            exit();
        }

        header('Location: ../views/verify_code.php?email=' . urlencode($email) . '&success=1');
        exit();
    }

    if ($action === 'verify_reset_code') {
        $email = trim($_GET['email'] ?? '');
        $verificationCode = trim($_GET['verification_code'] ?? '');

        if ($email === '' || $verificationCode === '') {
            header('Location: ../views/verify_code.php?email=' . urlencode($email) . '&error=missing_fields');
            exit();
        }

        if (strlen($verificationCode) !== 6 || !ctype_digit($verificationCode)) {
            header('Location: ../views/verify_code.php?email=' . urlencode($email) . '&error=invalid_code');
            exit();
        }

        // Check if code exists, matches email, and is not expired
        $stmt = $conn->prepare('SELECT * FROM password_resets WHERE email = ? AND verification_code = ? AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1');
        $stmt->execute([$email, $verificationCode]);
        $reset = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$reset) {
            header('Location: ../views/verify_code.php?email=' . urlencode($email) . '&error=invalid_code');
            exit();
        }

        // Mark as verified
        $stmt = $conn->prepare('UPDATE password_resets SET verified = 1 WHERE id = ?');
        $stmt->execute([$reset['id']]);

        header('Location: ../views/reset_password.php?email=' . urlencode($email));
        exit();
    }

    if ($action === 'reset_password') {
        $email = trim($_GET['email'] ?? '');
        $newPassword = $_GET['new_password'] ?? '';
        $confirmPassword = $_GET['confirm_password'] ?? '';

        if ($email === '' || $newPassword === '' || $confirmPassword === '') {
            header('Location: ../views/reset_password.php?email=' . urlencode($email) . '&error=missing_fields');
            exit();
        }

        if ($newPassword !== $confirmPassword) {
            header('Location: ../views/reset_password.php?email=' . urlencode($email) . '&error=passwords_not_match');
            exit();
        }

        if (strlen($newPassword) < 6) {
            header('Location: ../views/reset_password.php?email=' . urlencode($email) . '&error=password_too_short');
            exit();
        }

        // Verify that a verified reset code exists for this email
        $stmt = $conn->prepare('SELECT * FROM password_resets WHERE email = ? AND verified = 1 ORDER BY created_at DESC LIMIT 1');
        $stmt->execute([$email]);
        $reset = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$reset) {
            header('Location: ../views/forgot_password.php?error=invalid_request');
            exit();
        }

        // Get user and update password
        $user = $userModel->getByEmail($email);
        if (!$user) {
            header('Location: ../views/forgot_password.php?error=email_not_found');
            exit();
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        if ($userModel->updatePassword($user['id'], $hashedPassword)) {
            // Mark reset as used
            $stmt = $conn->prepare('DELETE FROM password_resets WHERE email = ?');
            $stmt->execute([$email]);
            
            header('Location: ../views/login.php?msg=password_reset_success');
            exit();
        }

        header('Location: ../views/reset_password.php?email=' . urlencode($email) . '&error=update_failed');
        exit();
    }

    if (!isset($_SESSION['user_id'])) {
        die('Not authenticated');
    }

    if ($action === 'update_profile') {
    $username = trim($_POST['username'] ?? '');
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');

        if ($username === '' || $firstName === '' || $lastName === '' || $email === '') {
            header('Location: ../views/profile.php?error=All fields are required');
            exit();
        }

        if ($userModel->getByUsernameOrEmail($username, $email, $_SESSION['user_id'])) {
            header('Location: ../views/profile.php?error=Username or email already exists');
            exit();
        }

        $profilePicPath = null;
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (in_array($_FILES['profile_pic']['type'], $allowedTypes, true)) {
                $uploadDir = dirname(__DIR__) . '/uploads/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($_FILES['profile_pic']['name']));
                $fileName = uniqid('avatar_', true) . '_' . $safeName;
                $targetPath = $uploadDir . $fileName;

                if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $targetPath)) {
                    $profilePicPath = $fileName;
                }
            }
        }

        if ($userModel->updateProfile($_SESSION['user_id'], $username, $firstName, $lastName, $email, $profilePicPath)) {
            $_SESSION['username'] = $username;
            header('Location: ../views/profile.php?updated=1');
            exit();
        }

        header('Location: ../views/profile.php?error=Failed to update profile');
        exit();
    }

    if ($action === 'change_password') {
        $currentPassword = $_GET['current_password'] ?? '';
        $newPassword = $_GET['new_password'] ?? '';
        $confirmPassword = $_GET['confirm_password'] ?? '';

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            header('Location: ../views/profile.php?error=All password fields are required');
            exit();
        }

        if ($newPassword !== $confirmPassword) {
            header('Location: ../views/profile.php?error=New passwords do not match');
            exit();
        }

        if (strlen($newPassword) < 6) {
            header('Location: ../views/profile.php?error=Password must be at least 6 characters');
            exit();
        }

        $user = $userModel->getById($_SESSION['user_id']);
        if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
            header('Location: ../views/profile.php?error=Current password is incorrect');
            exit();
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        if ($userModel->updatePassword($_SESSION['user_id'], $hashedPassword)) {
            header('Location: ../views/profile.php?updated=1');
            exit();
        }

        header('Location: ../views/profile.php?error=Failed to update password');
        exit();
    }

    if ($action === 'delete_account') {
        $promptModel = new Prompt($conn);
        $analyticsModel = new Analytics($conn);

        $promptModel->deleteUserPrompts($_SESSION['user_id']);
        $analyticsModel->deleteUserData($_SESSION['user_id']);

        if ($userModel->delete($_SESSION['user_id'])) {
            session_unset();
            session_destroy();
            echo 'success';
            exit();
        }

        echo 'error';
        exit();
    }

    die('Invalid action');
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}
?>