<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
require_once '../config/database.php';
require_once '../models/Prompt.php';
require_once '../models/User.php';
require_once '../models/Subscription.php';
$db = new Database();
$conn = $db->connect();
$promptModel = new Prompt($conn);
$userModel = new User($conn);
$subscriptionModel = new Subscription($conn);
$userConversations = $promptModel->getUserConversations($_SESSION['user_id']);
$user = $userModel->getById($_SESSION['user_id']);
$subscription = $subscriptionModel->getById($user['subscription_id'] ?? 1);
$monthlyUsage = $userModel->getMonthlyUsage($_SESSION['user_id']);
$promptsRemaining = ($subscription['prompt_limit'] ?? 10) - $monthlyUsage;
$hasReachedLimit = $promptsRemaining <= 0;

function summarizeHeadline($text) {
    $text = trim(preg_replace('/\s+/', ' ', strip_tags($text)));
    $text = preg_replace('/\?$/', '', $text);
    $text = preg_replace('/^\s*(?:what|why|how|does|do|is|are|can|could|should|would|will|have|has|had|were|was|did|may|might|must)\b\s*/i', '', $text);
    $text = preg_replace('/\b(?:please|thanks|thank you)\b/i', '', $text);
    $text = trim(preg_replace('/\s+/', ' ', $text));
    if ($text === '') {
        return 'Conversation';
    }
    if (strlen($text) > 50) {
        $text = substr($text, 0, 47) . '...';
    }
    return ucfirst($text);
}

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

$selectedConversationId = intval($_GET['conversation_id'] ?? 0);
$conversationMessages = [];
if ($selectedConversationId > 0) {
    $conversationMessages = $promptModel->getConversation($selectedConversationId);
    // Verify the conversation belongs to the user
    if (!empty($conversationMessages) && $conversationMessages[0]['user_id'] != $_SESSION['user_id']) {
        $selectedConversationId = 0;
        $conversationMessages = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard - AI News Bias Analysis</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
  <nav class="navbar">
    <div class="navbar-brand">
      <a href="dashboard.php">
        <img src="../assets/img/news-ai-logo.png" alt="News AI logo" style="width: 56px; height: auto;">
      </a>
    </div>
    <div class="navbar-menu">
      <span class="user-greeting">Welcome, <?= htmlspecialchars(($user['first_name'] ?? '')) ?></span>
      <a href="profile.php" title="Account Settings">
        <?php if (!empty($user['profile_pic'])): ?>
          <img src="../uploads/<?= htmlspecialchars($user['profile_pic']) ?>" alt="Profile" style="width:32px; height:32px; border-radius:50%; object-fit:cover;">
        <?php else: ?>
          
        <?php endif; ?>
      </a>
      <a href="../logout.php">Logout</a>
    </div>
  </nav>
  
  <div class="main-layout">
    <aside class="sidebar">
      <ul class="sidebar-menu">
        <li><a href="dashboard.php" class="active">Create Prompt</a></li>
        <li><a href="public_prompts.php">Prompt Feed</a></li>
        <li class="sidebar-heading">Your Conversations</li>
        <?php if (!empty($userConversations)): ?>
          <?php foreach ($userConversations as $conversation): ?>
            <?php $headline = htmlspecialchars(substr(trim($conversation['prompt']), 0, 40)); ?>
            <li class="sidebar-subitem" style="display: flex; justify-content: space-between; align-items: center; padding: 0.25rem 0;">
              <a href="dashboard.php?conversation_id=<?= intval($conversation['conversation_id']) ?>"<?= intval($conversation['conversation_id']) === $selectedConversationId ? ' class="active"' : '' ?> style="flex: 1;"><?= summarizeHeadline($conversation['prompt']) ?></a>
              <button type="button" class="btn-icon" title="Delete conversation" onclick="deleteConversation(<?= intval($conversation['conversation_id']) ?>)" style="margin-left: 0.5rem; padding: 0.25rem 0.5rem; font-size: 0.875rem;">🗑️</button>
            </li>
          <?php endforeach; ?>
        <?php else: ?>
          <li class="sidebar-subitem"><span class="sidebar-note">No conversations yet</span></li>
        <?php endif; ?>
      </ul>
    </aside>
    
    <main class="content">
      <div class="container-centered">
        <div class="dashboard-header">
          <h1><?php echo $selectedConversationId > 0 ? 'Continue Conversation' : 'Send a Prompt'; ?></h1>
        </div>

        <?php if (!empty($conversationMessages)): ?>
        <!-- Conversation History -->
        <div class="card">
          <h3>Conversation History <small style="font-weight: normal; color: var(--text); opacity: 0.7;">(Previous messages in this conversation)</small></h3>
          <div class="conversation-history">
            <?php foreach ($conversationMessages as $message): ?>
              <div class="conversation-entry">
                <div class="entry-block user-entry">
                  <div class="entry-label">Prompt</div>
                  <div class="entry-text"><?= nl2br(htmlspecialchars($message['prompt'])) ?></div>
                </div>
                <div class="entry-block assistant-entry">
                  <div class="entry-label">Response</div>
                  <div class="entry-text"><?= nl2br(safeHtmlWithLinks($message['response'])) ?></div>
                </div>
                <div class="entry-meta" style="display: flex; justify-content: space-between; align-items: center;">
                  <span><?= date('M d, Y H:i', strtotime($message['created_at'])) ?></span>
                  <button type="button" class="btn btn-sm btn-danger" onclick="deletePrompt(<?= intval($message['id']) ?>)" style="padding: 0.25rem 0.75rem; font-size: 0.875rem;">Delete</button>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Analysis Form -->
        <div class="card analysis-card">
          <form id="promptForm">
            <div class="prompt-panel">
              <div class="prompt-header">
                <div>
                  <h2>Analyze text like a conversation</h2>
                  <p class="prompt-subtitle">Paste an article, claim, or briefing and receive a clear bias and reliability analysis.</p>
                </div>
                <span class="shortcut-chip">Ctrl+Enter</span>
              </div>

              <div class="form-group">
                <label for="prompt">Article or text to analyze</label>
                <textarea id="prompt" class="form-control prompt-input" rows="8" placeholder="Paste news article, text, or claim to analyze for bias, contradictions, and reliability..."></textarea>
              </div>
              
              <input type="hidden" id="conversation_id" name="conversation_id" value="<?= $selectedConversationId ?>">
              <?php if ($selectedConversationId > 0): ?>
              <div class="form-group">
              </div>
              <?php endif; ?>

              <?php if ($hasReachedLimit): ?>
              <div class="form-group">
                <div class="alert alert-error">
                  ⚠️ You have reached your monthly prompt limit of <?= $subscription['prompt_limit'] ?>. Please upgrade your plan or wait until next month to send more prompts.
                </div>
              </div>
              <?php endif; ?>
              
              <div class="form-row">
                <label class="checkbox-label">
                  <input type="checkbox" id="public" name="public"<?= $hasReachedLimit ? ' disabled' : '' ?>>
                  Share publicly in prompt feed
                </label>
                <button type="button" id="analyseBtn" class="btn btn-primary"<?= $hasReachedLimit ? ' disabled' : '' ?> <?= $hasReachedLimit ? 'title="You have reached your monthly prompt limit"' : '' ?>>Analyse</button>
              </div>
            </div>
          </form>
        </div>

        <!-- Results Display -->
        <div id="result" class="card analysis-result" style="display: none;">
          <h3 class="section-title">Analysis Results</h3>
          <div id="resultContent" class="analysis-output"></div>
        </div>
      </div>
    </main>
  </div>
  
  <!-- Custom Delete Confirmation Modal -->
  <div id="deleteModal" class="modal" style="display: none;">
    <div class="modal-content">
      <h2>Delete Conversation</h2>
      <p>Are you sure you want to delete this conversation? This action cannot be undone.</p>
      <div class="modal-buttons">
        <button type="button" class="btn btn-danger" onclick="confirmDelete()">Yes, Delete</button>
        <button type="button" class="btn btn-secondary" onclick="cancelDelete()">Cancel</button>
      </div>
    </div>
  </div>
  
  <script src="../assets/js/app.js"></script>
</body>
</html>