<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Verify Code - AI News Bias Analysis</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
  <div class="auth-container">
    <div class="auth-card">
      <div style="text-align: center; margin-bottom: 2rem;">
        <h1 style="margin-bottom: 0.5rem;">News AI</h1>
        <p style="margin: 0; color: var(--highlight);">Verify Your Code</p>
      </div>
      
      <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-error">
          <?php 
          switch($_GET['error']) {
            case 'invalid_code': echo 'Invalid or expired verification code'; break;
            case 'code_expired': echo 'Verification code has expired'; break;
            case 'code_mismatch': echo 'Code does not match'; break;
            default: echo 'An error occurred';
          }
          ?>
        </div>
      <?php endif; ?>

      <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
          Verification code sent to your email. Please enter it below to continue.
        </div>
      <?php endif; ?>
      
      <h2>Verify Code</h2>
      <p style="color: var(--highlight); margin-bottom: 1.5rem;">Enter the verification code sent to your email.</p>
      
      <form method="GET" action="../controllers/AuthController.php">
        <input type="hidden" name="action" value="verify_reset_code">
        <input type="hidden" name="email" value="<?php echo htmlspecialchars($_GET['email'] ?? ''); ?>">
        
        <div class="form-group">
          <label for="verification_code">Verification Code</label>
          <input type="text" id="verification_code" name="verification_code" placeholder="Enter 6-digit code" maxlength="6" pattern="[0-9]{6}" required>
        </div>
        
        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">Verify Code</button>
      </form>
      
      <p style="text-align: center; margin-top: 1rem;"><a href="forgot_password.php">Send New Code</a> | <a href="login.php">Back to Login</a></p>
    </div>
  </div>
</body>
</html>
