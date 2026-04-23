<?php
session_start();

require_once '../config/database.php';
require_once '../models/User.php';

try {
    $db = new Database();
    $conn = $db->connect();
    $userModel = new User($conn);

    // Check if user is authenticated and is admin
    if (!isset($_SESSION['user_id'])) {
        die('Not authenticated');
    }

    if (!$userModel->isAdmin($_SESSION['user_id'])) {
        die('Access denied. Admin privileges required.');
    }

    if (!isset($_GET['action'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit();
    }

    $action = $_GET['action'];
    header('Content-Type: application/json');

    if ($action === 'update_user_role') {
        $userId = $_GET['user_id'] ?? 0;
        $roleId = $_GET['role_id'] ?? 1;

        if ($userId <= 0 || $roleId < 1) {
            die('Invalid user or role');
        }

        if ($userModel->updateRole($userId, $roleId)) {
            echo json_encode(['success' => true, 'message' => 'Role updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update role']);
        }
        exit();
    }

    if ($action === 'ban_user' || $action === 'unban_user' || $action === 'revoke_subscription' || $action === 'delete_user') {
        $userId = (int) ($_GET['user_id'] ?? 0);
        if ($userId <= 0) {
            die('Invalid user');
        }

        if ($userId === $_SESSION['user_id']) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Cannot perform this action on your own account']);
            exit();
        }
    }

    if ($action === 'ban_user') {
        if ($userModel->banUser($userId)) {
            echo json_encode(['success' => true, 'message' => 'User banned successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to ban user']);
        }
        exit();
    }

    if ($action === 'unban_user') {
        if ($userModel->unbanUser($userId)) {
            echo json_encode(['success' => true, 'message' => 'User unbanned successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to unban user']);
        }
        exit();
    }

    if ($action === 'revoke_subscription') {
        $freePlanId = $userModel->getRoleIdByName('member');
        if ($userModel->updateSubscription($userId, 1)) {
            echo json_encode(['success' => true, 'message' => 'Subscription revoked successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to revoke subscription']);
        }
        exit();
    }

    if ($action === 'delete_user') {
        if ($userModel->delete($userId)) {
            echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete user']);
        }
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']);

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
