<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$conversationId = intval($_GET['conversation_id'] ?? 0);
if ($conversationId > 0) {
    header('Location: conversation.php?conversation_id=' . $conversationId);
} else {
    header('Location: dashboard.php');
}
exit();
?>
