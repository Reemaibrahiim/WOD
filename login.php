<?php
require_once 'auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username  = $_POST['username'] ?? '';
    $password  = $_POST['password'] ?? '';
    $user_type = $_POST['user_type'] ?? null; 

    if ($auth->loginWithUsername($username, $password, $user_type)) { 
        header("Location: posts.php");
        exit();
    } else {
        $error = "Invalid username or password";
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login</title>
  <link rel="stylesheet" href="style.css">
</head>
<body class="auth-body">
  <div class="auth-container">
    <div class="auth-logo">
      <img src="images/logo.png" alt="WOD Logo">
      <h2>Welcome Back</h2>
    </div>

    <?php if (isset($error)): ?>
      <div style="color: red; margin-bottom: 15px;"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

<form class="auth-form" method="POST">
    <div class="form-group">
        <label>Username</label>
        <input type="text" name="username" placeholder="Enter your username" required>
    </div>

    <div class="form-group">
        <label>Password</label>
        <input type="password" name="password" placeholder="Enter your password" required>
    </div>

    
    <div class="form-group">
        <label>Login as</label>
        <div style="display: flex; gap: 15px; margin-top: 8px;">
            <label>
                <input type="radio" name="user_type" value="user" checked>
                User
            </label>
            <label>
                <input type="radio" name="user_type" value="store">
                Store
            </label>
        </div>
    </div>

    <button type="submit" class="auth-btn">Log in</button>

    <p class="auth-link">
        Don't have an account? <a href="signup.php">Sign Up</a>
    </p>
</form>

  </div>
</body>
</html>