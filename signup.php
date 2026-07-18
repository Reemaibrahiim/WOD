<?php
require_once 'auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $user_type = $_POST['user_type'] ?? 'user'; 

    if ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } else {
// In signup.php, after the registration logic
        if ($auth->register($username, $name, $email, $password, $user_type)) {
            // Set default profile picture for new users
            $default_photo = 'images/Default.png';
            $stmt = $pdo->prepare("UPDATE users SET photo_url = ? WHERE username = ?");
            $stmt->execute([$default_photo, $username]);

            header("Location: login.php");
            exit();
        } else {
            $error = "Registration failed. Username or email may already exist.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Sign Up</title>
        <link rel="stylesheet" href="style.css">
    </head>
    <body class="auth-body">
        <div class="auth-container">
            <div class="auth-logo">
                <img src="images/logo.png" alt="WOD Logo">
                <h2>Create Your Account</h2>
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
                    <label>Full Name</label>
                    <input type="text" name="name" placeholder="Enter your name" required>
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" placeholder="example@email.com" required>
                </div>

                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Enter password" required>
                </div>

                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" placeholder="Re-enter password" required>
                </div>

                <div class="form-group">
                    <label>Account Type</label>
                    <div style="display:flex; gap:15px; margin-top:8px;">
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
                
                <button type="submit" class="auth-btn">Sign Up</button>

                <p class="auth-link">
                    Already have an account? <a href="login.php">Login</a>
                </p>
            </form>
        </div>
    </body>
</html>