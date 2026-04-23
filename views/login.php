<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - AI News Bias Analysis</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
  <div class="auth-container">
    <div class="auth-card">
      <div class="auth-brand">
        <div class="app-logo" style="justify-content: center;">
          <span class="logo-mark"></span>
          <div class="brand-copy">
            <div class="brand-name">News AI</div>
            <div class="brand-tag">AI News Bias & Contradiction Analysis</div>
          </div>
        </div>
      </div>
      
      <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-error">
          Invalid email or password
        </div>
      <?php endif; ?>
      
      <?php if (isset($_GET['msg'])): ?>
        <div class="alert alert-success">
          Account created! Please log in.
        </div>
      <?php endif; ?>
      
      <h2>Login</h2>
      
      <form method="GET" action="../controllers/AuthController.php">
        <input type="hidden" name="action" value="login">
        
        <div class="form-group">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" placeholder="Enter your email" required>
        </div>
        
        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" placeholder="Enter your password" required>
        </div>
        
        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">Login</button>
      </form>
      
      <p>Don't have an account? <a href="register.php">Register here</a></p>
      <p style="text-align: center; margin-top: 1rem;"><a href="forgot_password.php">Forgot your password?</a></p>
    </div>
  </div>
</body>
</html>
