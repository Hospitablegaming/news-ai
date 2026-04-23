<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

require_once '../../config/database.php';
require_once '../../models/Analytics.php';
require_once '../../models/User.php';
require_once '../../models/Prompt.php';
require_once '../../models/Subscription.php';

$db = new Database();
$conn = $db->connect();
$analyticsModel = new Analytics($conn);
$userModel = new User($conn);
$promptModel = new Prompt($conn);
$subscriptionModel = new Subscription($conn);

if (!$userModel->isAdmin($_SESSION['user_id'])) {
    header('Location: ../dashboard.php');
    exit();
}

$stats = $analyticsModel->stats();
$users = $userModel->getAllUsers();
$publicPrompts = $promptModel->getPublic();
$plans = $subscriptionModel->getAll();
$user = $userModel->getById($_SESSION['user_id']);

$planMap = [];
foreach ($plans as $plan) {
    $planMap[$plan['id']] = $plan['plan_name'];
}

$totalUsers = count($users);
$totalPublicPrompts = count($publicPrompts);
$adminUsers = count(array_filter($users, function ($user) use ($userModel) {
    return $userModel->getUserRole($user['id']) === 'admin';
}));
$paidUsers = count(array_filter($users, function ($user) {
    return (int) ($user['subscription_id'] ?? 1) > 1;
}));
$recentUsers = array_slice($users, 0, 8);
$recentActivity = $promptModel->getRecentPrompts(20);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard - AI News Bias Analysis</title>
  <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body>
  <nav class="navbar">
    <div class="navbar-brand">
      <a href="../../dashboard.php">
        <img src="../../assets/img/news-ai-logo.png" alt="News AI logo">
      </a>
    </div>
    <div class="navbar-menu">
      <span class="user-greeting">Admin: <?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?></span>
      <a href="../profile.php" title="Account Settings"></a>
      <a href="../../logout.php">Logout</a>
    </div>
  </nav>

  <div class="main-layout">
    <aside class="sidebar">
      <ul class="sidebar-menu">
        <li><a href="dashboard.php" class="active">Overview</a></li>
        <li><a href="#users">Users</a></li>
        <li><a href="#activity">Activity</a></li>
        <li><a href="../dashboard.php">Back</a></li>
      </ul>
    </aside>

    <main class="content">
      <div class="container-centered">
        <h1>Admin Dashboard</h1>
        <p style="color: var(--highlight); margin-bottom: 1.5rem;">Platform overview and recent user activity.</p>

        <div class="admin-stats-grid">
          <div class="card stat-card">
            <p class="stat-label">Total Users</p>
            <p class="stat-number"><?= $totalUsers ?></p>
          </div>
          <div class="card stat-card">
            <p class="stat-label">Admin Users</p>
            <p class="stat-number"><?= $adminUsers ?></p>
          </div>
          <div class="card stat-card">
            <p class="stat-label">Paid Subscribers</p>
            <p class="stat-number"><?= $paidUsers ?></p>
          </div>
          <div class="card stat-card">
            <p class="stat-label">Public Prompts</p>
            <p class="stat-number"><?= $totalPublicPrompts ?></p>
          </div>
          <div class="card stat-card">
            <p class="stat-label">Analytics Events</p>
            <p class="stat-number"><?= (int) ($stats['prompts'] ?? 0) ?></p>
          </div>
          <div class="card stat-card">
            <p class="stat-label">Tokens Used</p>
            <p class="stat-number"><?= number_format((int) ($stats['tokens'] ?? 0)) ?></p>
          </div>
        </div>

        <div class="card" id="users" style="margin-top: 2rem;">
          <h3 class="card-title">Users</h3>
          <?php if (empty($users)): ?>
            <p>No users found.</p>
          <?php else: ?>
            <div style="overflow-x:auto;">
              <table class="admin-table">
                <thead>
                  <tr>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Plan</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($users as $user): ?>
                    <?php $role = $userModel->getUserRole($user['id']); ?>
                    <tr>
                      <td><?= htmlspecialchars($user['username']) ?></td>
                      <td><?= htmlspecialchars($user['email']) ?></td>
                      <td><?= htmlspecialchars(ucfirst($role)) ?></td>
                      <td><?= htmlspecialchars($planMap[$user['subscription_id'] ?? 1] ?? 'Free') ?></td>
                      <td><?= $role === 'banned' ? '<span style="color:#ff7373;font-weight:700;">Banned</span>' : 'Active' ?></td>
                      <td><?= !empty($user['created_at']) ? date('M d, Y', strtotime($user['created_at'])) : '—' ?></td>
                      <td>
                        <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                          <?php if ($role === 'banned'): ?>
                            <button class="btn btn-secondary" onclick="adminAction('unban_user', <?= (int)$user['id'] ?>)">Unban</button>
                          <?php else: ?>
                            <button class="btn btn-danger" onclick="adminAction('ban_user', <?= (int)$user['id'] ?>)">Ban</button>
                          <?php endif; ?>
                          <button class="btn btn-warning" onclick="adminAction('revoke_subscription', <?= (int)$user['id'] ?>)">Revoke Plan</button>
                          <button class="btn btn-danger" onclick="adminAction('delete_user', <?= (int)$user['id'] ?>)">Kick</button>
                        <?php else: ?>
                          <span style="color:rgba(255,255,255,0.65);">Self</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

        <div class="card" id="activity" style="margin-top: 2rem;">
          <h3 class="card-title">Activity</h3>
          <p style="margin-bottom: 1rem;">Recent activity from other users (recent prompts and usage).</p>
          <?php $filteredActivity = array_filter($recentActivity, function ($row) { return $row['user_id'] !== $_SESSION['user_id']; }); ?>
          <?php if (empty($filteredActivity)): ?>
            <p>No recent activity found from other users.</p>
          <?php else: ?>
            <div style="overflow-x:auto;">
              <table class="admin-table">
                <thead>
                  <tr>
                    <th>User</th>
                    <th>Prompt</th>
                    <th>Created</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($filteredActivity as $activity): ?>
                    <tr>
                      <td><?= htmlspecialchars($activity['username']) ?></td>
                      <td><?= htmlspecialchars(strlen($activity['prompt']) > 100 ? substr($activity['prompt'], 0, 100) . '...' : $activity['prompt']) ?></td>
                      <td><?= !empty($activity['created_at']) ? date('M d, Y H:i', strtotime($activity['created_at'])) : '—' ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </main>
  </div>

  <style>
    .admin-stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 1rem;
    }
    .stat-card {
      text-align: center;
    }
    .stat-label {
      color: var(--highlight);
      font-size: 0.8rem;
      text-transform: uppercase;
      margin-bottom: 0.5rem;
    }
    .stat-number {
      font-size: 2rem;
      font-weight: 700;
      color: var(--accent);
      margin: 0;
    }
    .admin-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 1rem;
    }
    .admin-table th,
    .admin-table td {
      text-align: left;
      padding: 0.85rem;
      border-bottom: 1px solid rgba(255,255,255,0.08);
    }
    .admin-table th {
      color: var(--highlight);
      font-size: 0.8rem;
      text-transform: uppercase;
    }
    .admin-table td button {
      margin-right: 0.35rem;
      margin-top: 0.15rem;
    }
  </style>
  <script>
    function adminAction(action, userId) {
      if (!confirm('Are you sure you want to perform this action?')) {
        return;
      }

      const url = '../../controllers/AdminController.php?action=' + encodeURIComponent(action) + '&user_id=' + encodeURIComponent(userId);

      fetch(url, {
        method: 'GET'
      })
      .then(res => res.json())
      .then(data => {
        if (data && data.success) {
          alert(data.message || 'Action completed');
          window.location.reload();
        } else {
          alert(data.message || 'Unable to complete action');
        }
      })
      .catch(() => {
        alert('An error occurred while performing the action.');
      });
    }
  </script>
</body>
</html>