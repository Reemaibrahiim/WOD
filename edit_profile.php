<?php
require_once 'config.php';
requireLogin();

$username = getCurrentUser();

// Get current user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $bio = $_POST['bio'] ?? '';
    $email = $_POST['email'] ?? '';

    // Handle photo upload
    $photo_url = $user['photo_url'];
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '_profile.' . $file_extension;
        $target_path = $upload_dir . $filename;

        if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_path)) {
            $photo_url = $target_path;
            // Delete old photo if it exists and is not default
            if ($user['photo_url'] && $user['photo_url'] !== 'images/profile-pic.jpg') {
                @unlink($user['photo_url']);
            }
        }
    }

    try {
        $stmt = $pdo->prepare("UPDATE users SET name = ?, bio = ?, email = ?, photo_url = ? WHERE username = ?");
        $stmt->execute([$name, $bio, $email, $photo_url, $username]);

        $_SESSION['name'] = $name;
        header("Location: profile.php");
        exit();
    } catch (PDOException $e) {
        $error = "Error updating profile: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Edit Profile - WOD</title>
        <link rel="stylesheet" href="style.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
        <style>
            .edit-profile-container {
                max-width: 600px;
                margin: 40px auto;
                padding: 30px;
                background-color: #fcfcfc;
                border-radius: 20px;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
                margin-right: calc(auto + 80px);
            }

            .edit-profile-container h1 {
                color: #4a202a;
                text-align: center;
                margin-bottom: 30px;
            }

            .form-group {
                margin-bottom: 20px;
            }

            .form-group label {
                display: block;
                margin-bottom: 8px;
                color: #4a202a;
                font-weight: 600;
            }

            .form-group input,
            .form-group textarea {
                width: 100%;
                padding: 12px;
                border: 2px solid #f0f0f0;
                border-radius: 10px;
                font-size: 1rem;
                transition: border-color 0.3s;
            }

            .form-group input:focus,
            .form-group textarea:focus {
                border-color: #f5c0d3;
                outline: none;
            }

            .form-group textarea {
                resize: vertical;
                min-height: 100px;
            }

            .photo-preview {
                width: 150px;
                height: 150px;
                border-radius: 50%;
                object-fit: cover;
                border: 4px solid #f5c0d3;
                margin: 10px auto;
                display: block;
            }

            .submit-btn {
                background-color: #f5c0d3;
                color: white;
                border: none;
                padding: 15px 30px;
                border-radius: 10px;
                cursor: pointer;
                font-weight: 700;
                font-size: 1.1rem;
                transition: background-color 0.3s ease;
                width: 100%;
                margin-top: 20px;
            }

            .submit-btn:hover {
                background-color: #e8a8c5;
            }

            .file-input-wrapper {
                text-align: center;
                margin: 20px 0;
            }

            .file-input-wrapper input[type="file"] {
                display: none;
            }

            .file-input-label {
                background-color: #f5c0d3;
                color: white;
                padding: 10px 20px;
                border-radius: 25px;
                cursor: pointer;
                display: inline-block;
                transition: background-color 0.3s ease;
            }

            .file-input-label:hover {
                background-color: #e8a8c5;
            }

            .edit-profile-container {
                max-width: 600px;
                margin: 40px auto;
                padding: 30px;
                background-color: #FFFCFC;
                border-radius: 20px;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
                margin-right: calc(auto + 80px);
            }

            .edit-profile-container h1 {
                color: #430000;
                text-align: center;
                margin-bottom: 30px;
            }

            .form-group label {
                color: #430000;
                font-weight: 600;
            }

            .form-group input:focus,
            .form-group textarea:focus {
                border-color: #B77E7E;
                outline: none;
            }

            .photo-preview {
                border: 4px solid #B77E7E;
            }

            .submit-btn {
                background-color: #B77E7E;
                color: #FFFCFC;
                border: none;
                padding: 15px 30px;
                border-radius: 10px;
                cursor: pointer;
                font-weight: 700;
                font-size: 1.1rem;
                transition: background-color 0.3s ease;
                width: 100%;
                margin-top: 20px;
            }

            .submit-btn:hover {
                background-color: #650000;
            }

            .file-input-label {
                background-color: #B77E7E;
                color: #FFFCFC;
                padding: 10px 20px;
                border-radius: 25px;
                cursor: pointer;
                display: inline-block;
                transition: background-color 0.3s ease;
            }

            .file-input-label:hover {
                background-color: #650000;
            }
        </style>
    </head>
    <body>
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-icons">
                <a href="#" class="sidebar-icon logo-icon">
                    <img src="images/logo.png" alt="WOD Logo" class="sidebar-logo" />
                </a>
                <a href="posts.php" class="sidebar-icon">
                    <img src="images/icons/home.svg" alt="Home" class="sidebar-icon-img" />
                </a>
                <a href="calendar.php" class="sidebar-icon">
                    <img src="images/icons/calender.svg" alt="calendar" class="sidebar-icon-img" />
                </a>
                <a href="profile.php" class="sidebar-icon">
                    <img src="images/icons/profile.svg" alt="Profile" class="sidebar-icon-img" />
                </a>
                <a href="group_dashboard.php" class="sidebar-icon">
                    <img src="images/icons/groups.png?v=2" alt="Groups" class="sidebar-icon-img" />
                </a>
                <a href="logout.php" class="sidebar-icon" onclick="return confirm('Are you sure you want to log out?')">
                    <img src="images/icons/logout.png" alt="Logout" class="sidebar-icon-img" />
                </a>
            </div>
        </div>

        <main class="edit-profile-container">
            <h1><i class="fas fa-user-edit" style="color:#B77E7E; margin-right: 10px;"></i> Edit Profile</h1>

            <?php if (isset($error)): ?>
                <div style="color: red; margin-bottom: 20px; padding: 10px; background: #ffe6e6; border-radius: 5px;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="file-input-wrapper">
                    <img id="photoPreview" src="<?php echo htmlspecialchars($user['photo_url'] ?? 'images/profile-pic.jpg'); ?>" alt="Profile Photo" class="photo-preview">
                    <input type="file" id="photo" name="photo" accept="image/*" onchange="previewPhoto(this)">
                    <label for="photo" class="file-input-label">
                        <i class="fas fa-camera" style="margin-right: 8px;"></i> Change Photo
                    </label>
                </div>

                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                    <small style="color: #888;">Username cannot be changed</small>
                </div>

                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="bio">Bio</label>
                    <textarea id="bio" name="bio" placeholder="Tell us about yourself..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fas fa-save" style="margin-right: 10px;"></i> Save Changes
                </button>
            </form>
        </main>

        <script>
            function previewPhoto(input) {
                const preview = document.getElementById('photoPreview');
                if (input.files && input.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        preview.src = e.target.result;
                    }
                    reader.readAsDataURL(input.files[0]);
                }
            }
        </script>
    </body>
</html>