<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register - AI News Bias Analysis</title>
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
      
      <h2>Create Account</h2>
      
      <form method="GET" action="../controllers/AuthController.php">
        <input type="hidden" name="action" value="register">
        
        <div class="form-group">
          <label for="first_name">First Name</label>
          <input type="text" id="first_name" name="first_name" placeholder="Enter your first name" required>
        </div>
        
        <div class="form-group">
          <label for="last_name">Last Name</label>
          <input type="text" id="last_name" name="last_name" placeholder="Enter your last name" required>
        </div>
        
        <div class="form-group">
          <label for="username">Username</label>
          <input type="text" id="username" name="username" placeholder="Choose a username" required>
        </div>
        
        <div class="form-group">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" placeholder="Enter your email" required>
        </div>
        
        <div class="form-group">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" placeholder="Minimum 6 characters" minlength="6" required>
        </div>
        
        <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">Register</button>
      </form>
      
      <p>Already have an account? <a href="login.php">Login here</a></p>
    </div>
  </div>
</body>
</html>