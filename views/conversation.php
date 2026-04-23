<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require_once '../config/database.php';
require_once '../models/Prompt.php';
require_once '../models/User.php';

$db = new Database();
$conn = $db->connect();
$promptModel = new Prompt($conn);
$userModel = new User($conn);
$user = $userModel->getById($_SESSION['user_id']);

$conversationId = intval($_GET['conversation_id'] ?? 0);
if (!$conversationId) {
    header('Location: dashboard.php');
    exit();
}

$messages = $promptModel->getConversation($conversationId);
if (empty($messages) || $messages[0]['user_id'] != $_SESSION['user_id']) {
    header('Location: dashboard.php?error=Conversation not found or unauthorized');
    exit();
}

$userConversations = $promptModel->getUserConversations($_SESSION['user_id']);

function safeHtmlWithLinks($text) {
    // Allow only <a> tags with safe href, target, and rel attributes
    // Use regex to find and validate links
    $text = preg_replace_callback(
        '/<a\s+([^>]*?)>(.*?)<\/a>/is',
        function($matches) {
            $attributes = $matches[1];
            $content = $matches[2];

            // Parse attributes
            $href = '';
            $target = '';
            $rel = '';

            // Extract href attribute
            if (preg_match('/href="([^"]*)"/i', $attributes, $hrefMatch)) {
                $href = $hrefMatch[1];
            } elseif (preg_match("/href='([^']*)'/i", $attributes, $hrefMatch)) {
                $href = $hrefMatch[1];
            }

            // Extract target attribute
            if (preg_match('/target="([^"]*)"/i', $attributes, $targetMatch)) {
                $target = $targetMatch[1];
            } elseif (preg_match("/target='([^']*)'/i", $attributes, $targetMatch)) {
                $target = $targetMatch[1];
            }

            // Extract rel attribute
            if (preg_match('/rel="([^"]*)"/i', $attributes, $relMatch)) {
                $rel = $relMatch[1];
            } elseif (preg_match("/rel='([^']*)'/i", $attributes, $relMatch)) {
                $rel = $relMatch[1];
            }

            // Validate href - must be http or https URL
            if (empty($href) || !filter_var($href, FILTER_VALIDATE_URL) ||
                !(stripos($href, 'http://') === 0 || stripos($href, 'https://') === 0)) {
                // Invalid link, return just the content
                return htmlspecialchars($content);
            }

            // Build safe link
            $safeLink = '<a href="' . htmlspecialchars($href) . '" target="_blank" rel="noopener noreferrer">' . htmlspecialchars($content) . '</a>';

            return $safeLink;
        },
        $text
    );

    return $text;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Conversation - AI News Bias Analysis</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
  <nav class="navbar">
    <div class="navbar-brand">
      <a href="dashboard.php">
        <img src="../assets/img/news-ai-logo.png" alt="News AI logo" style="width: 34px; height: auto;">
        <span class="brand-text">News AI</span>
      </a>
    </div>
    <div class="navbar-menu">
      <span class="user-greeting">Welcome, <?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?></span>
      <a href="profile.php" title="Account Settings">👤</a>
      <a href="../logout.php">Logout</a>
    </div>
  </nav>
  
  <div class="main-layout">
    <aside class="sidebar">
      <ul class="sidebar-menu">
        <li><a href="dashboard.php">Create Prompt</a></li>
        <li><a href="public_prompts.php">Prompt Feed</a></li>
        <li class="sidebar-heading">Your Conversations</li>
        <?php if (!empty($userConversations)): ?>
          <?php foreach ($userConversations as $conversation): ?>
            <li class="sidebar-subitem"><a href="dashboard.php?conversation_id=<?= intval($conversation['conversation_id']) ?>"<?= intval($conversation['conversation_id']) === $conversationId ? ' class="active"' : '' ?>><?= summarizeHeadline($conversation['prompt']) ?></a></li>
          <?php endforeach; ?>
        <?php else: ?>
          <li class="sidebar-subitem"><span class="sidebar-note">No conversations yet</span></li>
        <?php endif; ?>
      </ul>
    </aside>
    
    <main class="content">
      <div class="container-centered">
        <div class="conversation-header-full">
          <h1>Conversation <?= $conversationId ?></h1>
          <div class="conversation-actions">
            <button class="btn btn-primary" onclick="window.location.href='dashboard.php?conversation_id=<?= $conversationId ?>'">Continue Conversation</button>
            <button class="btn btn-secondary" onclick="editChat(<?= $conversationId ?>)">Edit</button>
            <button class="btn btn-danger" onclick="deleteChat(<?= $conversationId ?>)">Delete</button>
          </div>
        </div>

        <?php foreach ($messages as $message): ?>
          <div class="conversation-entry-full">
            <div class="entry-block user-entry">
              <div class="entry-label">Prompt</div>
              <div class="entry-text"><?= nl2br(htmlspecialchars($message['prompt'])) ?></div>
            </div>
            <div class="entry-block assistant-entry">
              <div class="entry-label">Response</div>
              <div class="entry-text"><?= nl2br(safeHtmlWithLinks($message['response'])) ?></div>
            </div>
            <div class="entry-meta"><?= date('M d, Y H:i', strtotime($message['created_at'])) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    </main>
  </div>
  
  <script>
    function deleteChat(id) {
      if (confirm('Are you sure you want to delete this conversation?')) {
        fetch('../controllers/PromptController.php?action=delete&id=' + id + '&type=conversation', {
          method: 'GET'
        })
        .then(res => res.text())
        .then(data => {
          if (data === 'success') {
            window.location.href = 'dashboard.php';
          } else {
            alert('Error deleting conversation: ' + data);
          }
        })
        .catch(err => alert('Error: ' + err));
      }
    }
    
    function editChat(id) {
      window.location.href = 'edit_chat.php?id=' + id;
    }
  </script>
</body>
</html>
