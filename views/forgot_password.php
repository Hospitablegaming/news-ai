<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password - AI News Bias Analysis</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
  <div class="auth-container">
    <div class="auth-card">
      <div class="auth-brand">
        <div class="app-logo" style="justify-content: center;">
          <span class="logo-mark">N</span>
          <div class="brand-copy">
            <div class="brand-name">News AI</div>
            <div class="brand-tag">Password Recovery</div>
          </div>
        </div>
      </div>
      
      <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-error">
          <?php 
          switch($_GET['error']) {
            case 'email_not_found': echo 'Email address not found'; break;
            case 'email_failed': echo 'Unable to send password reset email right now. Please try again later.'; break;
            default: echo 'An error occurred. Please try again.';
          }
          ?>
        </div>
      <?php endif; ?>
      
      <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
          Verification code sent! Check your email for the code.
        </div>
      <?php endif; ?>
      
      <h2>Forgot Password</h2>
      <p style="color: var(--highlight); margin-bottom: 1.5rem;">Enter your email address and we'll send you a verification code.</p>
      
      <form method="GET" action="../controllers/AuthController.php">
        <input type="hidden" name="action" value="forgot_password">
        
        <div class="form-group">
          <label for="email">Email Address</label>
          <input type="email" id="email" name="email" placeholder="Enter your email" required>
        </div>
        
        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">Send Verification Code</button>
      </form>
      
      <p style="text-align: center; margin-top: 1rem;"><a href="login.php">Back to Login</a></p>
    </div>
  </div>
</body>
</html>
