<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password - AI News Bias Analysis</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
  <div class="auth-container">
    <div class="auth-card">
      <div style="text-align: center; margin-bottom: 2rem;">
        <h1 style="margin-bottom: 0.5rem;">News AI</h1>
        <p style="margin: 0; color: var(--highlight);">Set New Password</p>
      </div>
      
      <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-error">
          <?php 
          switch($_GET['error']) {
            case 'passwords_not_match': echo 'Passwords do not match'; break;
            case 'password_too_short': echo 'Password must be at least 6 characters'; break;
            case 'update_failed': echo 'Failed to update password'; break;
            default: echo 'An error occurred';
          }
          ?>
        </div>
      <?php endif; ?>
      
      <h2>Reset Password</h2>
      <p style="color: var(--highlight); margin-bottom: 1.5rem;">Enter your new password.</p>
      
      <form method="GET" action="../controllers/AuthController.php">
        <input type="hidden" name="action" value="reset_password">
        <input type="hidden" name="email" value="<?php echo htmlspecialchars($_GET['email'] ?? ''); ?>">
        
        <div class="form-group">
          <label for="new_password">New Password</label>
          <input type="password" id="new_password" name="new_password" placeholder="Enter new password" minlength="6" required>
        </div>
        
        <div class="form-group">
          <label for="confirm_password">Confirm Password</label>
          <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" minlength="6" required>
        </div>
        
        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">Reset Password</button>
      </form>
      
      <p style="text-align: center; margin-top: 1rem;"><a href="login.php">Back to Login</a></p>
    </div>
  </div>
</body>
</html>
