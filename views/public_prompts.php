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
$model = new Prompt($conn);
$userModel = new User($conn);
$prompts = $model->getPublic();
$user = $userModel->getById($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Public Prompts - AI News Bias Analysis</title>
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
      <a href="profile.php" title="Account Settings"></a>
      <a href="../logout.php">Logout</a>
    </div>
  </nav>
  
  <div class="main-layout">
    <aside class="sidebar">
      <ul class="sidebar-menu">
        <li><a href="dashboard.php">Create Prompt</a></li>
        <li><a href="public_prompts.php" class="active">Prompt Feed</a></li>
      </ul>
    </aside>
    
    <main class="content">
      <div class="container-centered">
        <h1>Prompt Feed</h1>
        
        <?php if (empty($prompts)): ?>
          <div class="card" style="text-align: center; padding: 3rem;">
            <p>No public prompts yet. Be the first to analyze and share!</p>
            <a href="dashboard.php" class="btn btn-primary mt-3">Create Prompt</a>
          </div>
        <?php else: ?>
          <?php foreach($prompts as $p): ?>
            <div class="card">
              <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                <div>
                  <span style="color: var(--accent);">📝 <?= htmlspecialchars($p['username']) ?></span>
                  <div class="prompt-meta" style="margin-top: 0.25rem;"><?= date('M d, Y H:i', strtotime($p['created_at'])) ?></div>
                </div>
              </div>
              
              <p style="color: var(--highlight); margin: 1rem 0 0.5rem 0; text-transform: uppercase; font-size: 0.75rem;">Article/Text:</p>
              <div style="background-color: rgba(27, 27, 58, 0.5); padding: 1rem; border-radius: 4px; margin-bottom: 1rem;">
                <p><?= htmlspecialchars(substr($p['prompt'], 0, 300)) ?><?= strlen($p['prompt']) > 300 ? '...' : '' ?></p>
              </div>
              
              <hr>
              
              <p style="color: var(--highlight); margin: 1rem 0 0.5rem 0; text-transform: uppercase; font-size: 0.75rem;">Analysis:</p>
              <div class="analysis-block">
                <p class="analysis-text"><?= nl2br(htmlspecialchars(trim(substr(strip_tags($p['response']), 0, 400)))) ?><?= strlen(strip_tags($p['response'])) > 400 ? '...' : '' ?></p>
              </div>
            </div>
          <?php endforeach ?>
        <?php endif ?>
      </div>
    </main>
  </div>
</body>
</html>