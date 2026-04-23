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

$promptId = intval($_GET['id'] ?? 0);
$prompt = $promptModel->getById($promptId);
if (!$prompt || $prompt['user_id'] != $_SESSION['user_id']) {
    header('Location: your_chats.php');
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Chat - NewsAI</title>
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
        <li><a href="dashboard.php">Create or Continue Conversation</a></li>
      </ul>
    </aside>

    <main class="content">
      <div class="container-centered">
        <h1>Edit Chat</h1>

        <?php if (!empty($error)): ?>
          <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
          <div class="alert alert-error">Error: <?= htmlspecialchars($_GET['error']) ?></div>
        <?php endif; ?>

        <?php if (isset($_GET['success'])): ?>
          <div class="alert alert-success">Changes saved successfully!</div>
        <?php endif; ?>

        <div class="card">
          <form method="GET" action="../controllers/PromptController.php">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?= $promptId ?>">

            <div class="form-group">
              <label for="prompt">Prompt</label>
              <textarea id="prompt" name="prompt" class="form-control" rows="6"><?= htmlspecialchars($prompt['prompt']) ?></textarea>
            </div>

            <div class="form-group">
              <label for="response">Response (will be regenerated)</label>
              <textarea id="response" class="form-control" rows="8" disabled><?= htmlspecialchars($prompt['response']) ?></textarea>
            </div>

            <div class="form-group">
              <label>
                <input type="checkbox" name="public" <?= $prompt['is_public'] ? 'checked' : '' ?>>
                Keep public visibility
              </label>
            </div>

            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="dashboard.php" class="btn btn-secondary" style="margin-left: 1rem;">Cancel</a>
          </form>
        </div>
      </div>
    </main>
  </div>
</body>
</html>
