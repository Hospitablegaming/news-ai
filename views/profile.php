<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once '../config/database.php';
require_once '../models/User.php';
require_once '../models/Subscription.php';
require_once '../models/Analytics.php';

$db = new Database();
$conn = $db->connect();
$userModel = new User($conn);
$subscriptionModel = new Subscription($conn);
$analyticsModel = new Analytics($conn);

$user = $userModel->getById($_SESSION['user_id']);
$currentPlan = $subscriptionModel->getById($user['subscription_id'] ?? 1);
$userStats = $analyticsModel->getUserStats($_SESSION['user_id']);

if (!$user) {
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Account Settings - AI News Bias Analysis</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
  <nav class="navbar">
    <div class="navbar-brand">
      <a href="dashboard.php">
        <img src="../assets/img/news-ai-logo.png" alt="News AI logo" style="width: 34px; height: auto;">
      </a>
    </div>
    <div class="navbar-menu">
      <span class="user-greeting">Welcome, <?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?></span>
      <a href="profile.php" title="Account Settings" style="display:inline-flex; align-items:center; justify-content:center; width:32px; height:32px; border-radius:50%; overflow:hidden; background:#2b2f3f; text-decoration:none;">
  <?php if (!empty($user['profile_pic'])): ?>
    <img src="../uploads/<?= htmlspecialchars($user['profile_pic']) ?>" alt="Profile" style="width:32px; height:32px; object-fit:cover;">
  <?php else: ?>
    <span style="color:#fff; font-weight:700;"><?= strtoupper(substr($user['username'], 0, 1)) ?></span>
  <?php endif; ?>
</a>
      <a href="../logout.php">Logout</a>
    </div>
  </nav>

  <div class="main-layout">
    <aside class="sidebar">
      <ul class="sidebar-menu">
        <li><a href="dashboard.php">Create Prompt</a></li>
        <li><a href="public_prompts.php">Prompt Feed</a></li>
        <li><a href="dashboard.php">Create or Continue Conversation</a></li>
        <li><a href="subscription.php">Subscription</a></li>
        <li><a href="profile.php" class="active">Account</a></li>
        <?php if (($_SESSION['role'] ?? 'member') === 'admin'): ?>
          <li><a href="admin/dashboard.php">Admin Panel</a></li>
        <?php endif; ?>
      </ul>
    </aside>

    <main class="content">
      <div class="container-centered">
        <h1>Account Settings</h1>

        <?php if (isset($_GET['updated'])): ?>
          <div class="alert alert-success">Account updated successfully.</div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
          <div class="alert alert-error">Error: <?= htmlspecialchars($_GET['error']) ?></div>
        <?php endif; ?>

        <div class="card">
          <div style="display:flex; align-items:center; gap:1rem; margin-bottom:1.5rem;">
            <?php if (!empty($user['profile_pic'])): ?>
              <img src="../uploads/<?= htmlspecialchars($user['profile_pic']) ?>" alt="Profile picture" style="width:72px; height:72px; border-radius:50%; object-fit:cover;">
            <?php else: ?>
              <div style="width:72px; height:72px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:2rem; background:#1e293b; color:#fff; font-weight:700;">
                <?= strtoupper(substr($user['username'], 0, 1)) ?>
              </div>
            <?php endif; ?>
            <div>
              <h2 style="margin:0;"><?= htmlspecialchars($user['first_name']) ?> (<?= htmlspecialchars($user['username']) ?>)</h2>
              <p style="margin:0.35rem 0 0; color: var(--highlight);"><?= htmlspecialchars($user['email']) ?></p>
              <p style="margin:0.35rem 0 0; color: var(--highlight);">Plan: <?= htmlspecialchars($currentPlan['plan_name'] ?? 'Free') ?></p>
            </div>
          </div>

          <form method="POST" action="../controllers/AuthController.php" enctype="multipart/form-data">
            <input type="hidden" name="action" value="update_profile">

            <div class="form-group">
              <label for="first_name">First Name</label>
              <input type="text" id="first_name" name="first_name" class="form-control" value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" required>
            </div>

            <div class="form-group">
              <label for="last_name">Last Name</label>
              <input type="text" id="last_name" name="last_name" class="form-control" value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" required>
            </div>

            <div class="form-group">
              <label for="username">Username</label>
              <input type="text" id="username" name="username" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" required>
            </div>

            <div class="form-group">
              <label for="email">Email</label>
              <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
            </div>

            <div class="form-group">
              <label for="profile_pic">Profile Picture</label>
              <input type="file" id="profile_pic" name="profile_pic" class="form-control" accept="image/*">
            </div>

            <button type="submit" class="btn btn-primary">Save Profile</button>
          </form>
        </div>

        <div class="card" style="margin-top: 2rem;">
          <h3>Change Password</h3>
          <form method="GET" action="../controllers/AuthController.php">
            <input type="hidden" name="action" value="change_password">

            <div class="form-group">
              <label for="current_password">Current Password</label>
              <input type="password" id="current_password" name="current_password" class="form-control" required>
            </div>

            <div class="form-group">
              <label for="new_password">New Password</label>
              <input type="password" id="new_password" name="new_password" class="form-control" required>
            </div>

            <div class="form-group">
              <label for="confirm_password">Confirm New Password</label>
              <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
            </div>

            <button type="submit" class="btn btn-secondary">Change Password</button>
          </form>
        </div>

        <div class="card" style="margin-top: 2rem;">
          <h3>Usage Analytics</h3>
          <div class="stats-grid">
            <div class="stat-item">
              <p class="stat-number"><?= number_format((int) ($userStats['prompts'] ?? 0)) ?></p>
              <p class="stat-label">Total Prompts</p>
            </div>
            <div class="stat-item">
              <p class="stat-number"><?= number_format((int) ($userStats['tokens'] ?? 0)) ?></p>
              <p class="stat-label">Tokens Used</p>
            </div>
            <div class="stat-item">
              <p class="stat-number"><?= $currentPlan ? $currentPlan['prompt_limit'] : 'Unlimited' ?></p>
              <p class="stat-label">Monthly Limit</p>
            </div>
          </div>
          <p style="margin-top: 1rem; font-size: 0.9rem; color: var(--text); opacity: 0.8;">
            Token usage is estimated based on input/output text length. 1 token ≈ 4 characters.
          </p>
        </div>

        <div class="card" style="margin-top: 2rem; border-color: #ff9999;">
          <h3 style="color:#ff9999;">Delete Account</h3>
          <p>This permanently removes your chats, analytics, and account.</p>
          <button class="btn btn-secondary" style="color:#ff9999;" onclick="deleteAccount()">Delete Account</button>
        </div>
      </div>
    </main>
  </div>

  <script>
    function deleteAccount() {
      if (!confirm('Are you sure you want to permanently delete your account?')) {
        return;
      }

      fetch('../controllers/AuthController.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=delete_account'
      })
      .then(response => response.text())
      .then(data => {
        if (data.trim() === 'success') {
          window.location.href = '../logout.php';
        } else {
          alert('Error deleting account.');
        }
      })
      .catch(error => alert('Error: ' + error));
    }
  </script>
</body>
</html>
