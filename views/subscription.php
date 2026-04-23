<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once '../config/database.php';
require_once '../models/User.php';
require_once '../models/Subscription.php';

$db = new Database();
$conn = $db->connect();
$userModel = new User($conn);
$subscriptionModel = new Subscription($conn);

$user = $userModel->getById($_SESSION['user_id']);
$subscriptions = $subscriptionModel->getAll();
$currentPlan = $subscriptionModel->getById($user['subscription_id'] ?? 1);
$monthlyUsage = $userModel->getMonthlyUsage($_SESSION['user_id']);
$planLimit = max(1, (int) ($currentPlan['prompt_limit'] ?? 1));
$usagePercent = min(100, ($monthlyUsage / $planLimit) * 100);

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
    <title>Subscription - AI News Bias Analysis</title>
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
                <li><a href="public_prompts.php">Prompt Feed</a></li>
                <li><a href="dashboard.php">Create or Continue Conversation</a></li>
                <li><a href="subscription.php" class="active">Subscription</a></li>
                <li><a href="profile.php">Account</a></li>
            </ul>
        </aside>

        <main class="content">
            <div class="container-centered">
                <h1>Choose Your Plan</h1>

                <?php if (isset($_GET['updated'])): ?>
                    <div class="alert alert-success">Subscription updated successfully.</div>
                <?php endif; ?>

                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-error">Error: <?= htmlspecialchars($_GET['error']) ?></div>
                <?php endif; ?>

                <div class="subscription-plans">
                    <?php foreach ($subscriptions as $plan): ?>
                        <div class="card subscription-card <?= (int) $user['subscription_id'] === (int) $plan['id'] ? 'current-plan' : '' ?>">
                            <h3><?= htmlspecialchars($plan['plan_name']) ?> Plan</h3>
                            <div class="price">
                                <span class="amount">£<?= htmlspecialchars($plan['monthly_price']) ?></span>
                                <span class="period">/month</span>
                            </div>
                            <ul class="features">
                                <li><?= htmlspecialchars($plan['prompt_limit']) ?> prompts per month</li>
                                <li>Access to NewsAPI integration</li>
                                <li>AI-powered analysis</li>
                                <li>Save and edit chats</li>
                            </ul>

                            <?php if ((int) $user['subscription_id'] === (int) $plan['id']): ?>
                                <div class="current-badge">Current Plan</div>
                            <?php else: ?>
                                <form method="GET" action="../controllers/SubscriptionController.php">
                                    <input type="hidden" name="action" value="update_plan">
                                    <input type="hidden" name="plan_id" value="<?= (int) $plan['id'] ?>">
                                    <button type="submit" class="btn btn-primary">Switch to <?= htmlspecialchars($plan['plan_name']) ?></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="card" style="margin-top: 2rem;">
                    <h2>Current Usage</h2>
                    <p><strong>Monthly Prompts Used:</strong> <?= $monthlyUsage ?> / <?= $planLimit ?></p>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?= $usagePercent ?>%"></div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <style>
        .subscription-plans {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }
        .subscription-card { text-align: center; position: relative; }
        .subscription-card.current-plan { border-color: #007bff; background-color: #f8f9ff; }
        .current-badge {
            background: #007bff; color: white; padding: 0.5rem 1rem; border-radius: 20px;
            font-size: 0.875rem; margin-bottom: 1rem; display: inline-block;
        }
        .price { font-size: 2rem; font-weight: bold; margin: 1rem 0; }
        .amount { color: #007bff; }
        .period { font-size: 1rem; color: #666; }
        .features { list-style: none; padding: 0; margin: 1.25rem 0; }
        .features li { margin: 0.5rem 0; color: #555; }
        .progress-bar { background: #e9ecef; height: 10px; border-radius: 5px; margin-top: 0.5rem; overflow: hidden; }
        .progress-fill { background: #007bff; height: 100%; border-radius: 5px; transition: width 0.3s ease; }
    </style>
</body>
</html>