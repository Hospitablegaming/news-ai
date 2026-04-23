<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../views/login.php');
    exit();
}

require_once '../config/database.php';
require_once '../models/User.php';
require_once '../models/Subscription.php';

try {
    $db = new Database();
    $conn = $db->connect();
    $userModel = new User($conn);
    $subscriptionModel = new Subscription($conn);

    $action = $_GET['action'] ?? '';

    if ($action !== 'update_plan') {
        header('Location: ../views/subscription.php?error=Invalid request');
        exit();
    }

    $planId = intval($_GET['plan_id'] ?? 0);
    if (!$planId) {
        header('Location: ../views/subscription.php?error=Invalid plan selected');
        exit();
    }

    $plan = $subscriptionModel->getById($planId);
    if (!$plan) {
        header('Location: ../views/subscription.php?error=Subscription plan not found');
        exit();
    }

    if ($userModel->updateSubscription($_SESSION['user_id'], $planId)) {
        $_SESSION['subscription_id'] = $planId;
        header('Location: ../views/subscription.php?updated=1');
        exit();
    }

    header('Location: ../views/subscription.php?error=Could not update subscription');
    exit();
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}
?>