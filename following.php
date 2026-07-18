<?php
require_once 'config.php';
requireLogin();

$username = getCurrentUser();

// Get users that the current user is following
$stmt = $pdo->prepare("
    SELECT u.username, u.name, u.photo_url, u.bio 
    FROM friends f 
    JOIN users u ON f.username_2 = u.username 
    WHERE f.username_1 = ? 
    ORDER BY u.username
");
$stmt->execute([$username]);
$following = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Following - WOD</title>
        <link rel="stylesheet" href="style.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
        <style>
            .following-container {
                max-width: 800px;
                margin: 40px auto;
                padding: 30px;
                background-color: #fcfcfc;
                border-radius: 20px;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
                margin-right: calc(auto + 80px);
            }

            .user-card {
                display: flex;
                align-items: center;
                padding: 20px;
                background: white;
                border-radius: 15px;
                margin-bottom: 15px;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            }

            .user-avatar {
                width: 60px;
                height: 60px;
                border-radius: 50%;
                margin-right: 15px;
                object-fit: cover;
            }

            .user-info {
                flex: 1;
            }

            .username {
                font-weight: 600;
                color: #4a202a;
                margin: 0;
                font-size: 1.1rem;
            }

            .user-name {
                color: #666;
                margin: 5px 0;
            }

            .user-bio {
                color: #888;
                margin: 5px 0;
                font-size: 0.9rem;
            }

            .view-profile-btn {
                background-color: #f5c0d3;
                color: white;
                border: none;
                padding: 8px 16px;
                border-radius: 20px;
                cursor: pointer;
                font-weight: 600;
                text-decoration: none;
                transition: background-color 0.3s ease;
            }

            .view-profile-btn:hover {
                background-color: #e8a8c5;
            }

            .no-following {
                text-align: center;
                color: #888;
                padding: 40px;
            }

            .back-link {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                color: #f5c0d3;
                text-decoration: none;
                font-weight: 600;
                margin-bottom: 20px;
            }
            .following-container {
                max-width: 800px;
                margin: 40px auto;
                padding: 30px;
                background-color: #FFFCFC;
                border-radius: 20px;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
                margin-right: calc(auto + 80px);
            }

            .username {
                font-weight: 600;
                color: #430000;
                margin: 0;
                font-size: 1.1rem;
            }

            .view-profile-btn {
                background-color: #B77E7E;
                color: #FFFCFC;
                border: none;
                padding: 8px 16px;
                border-radius: 20px;
                cursor: pointer;
                font-weight: 600;
                text-decoration: none;
                transition: background-color 0.3s ease;
            }

            .view-profile-btn:hover {
                background-color: #650000;
            }

            .back-link {
                color: #B77E7E;
                text-decoration: none;
                font-weight: 600;
                margin-bottom: 20px;
            }

            .back-link:hover {
                color: #650000;
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

        <main class="following-container">
            <a href="profile.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Profile
            </a>

            <h1 style="color: #430000; margin-bottom: 30px;">
    <i class="fas fa-users" style="color:#B77E7E; margin-right: 10px;"></i> People You Follow
</h1>

            <?php if (count($following) > 0): ?>
                <div class="following-list">
                    <?php foreach ($following as $user): ?>
                        <div class="user-card">
                            <img src="<?php echo htmlspecialchars($user['photo_url'] ?? 'images/profile-pic.jpg'); ?>" 
                                 alt="<?php echo htmlspecialchars($user['name']); ?>" class="user-avatar">
                            <div class="user-info">
                                <p class="username">@<?php echo htmlspecialchars($user['username']); ?></p>
                                <p class="user-name"><?php echo htmlspecialchars($user['name']); ?></p>
                                <?php if (!empty($user['bio'])): ?>
                                    <p class="user-bio"><?php echo htmlspecialchars($user['bio']); ?></p>
                                <?php endif; ?>
                            </div>
                            <a href="friend_profile.php?username=<?php echo urlencode($user['username']); ?>" class="view-profile-btn">
                                View Profile
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-following">
                    <i class="fas fa-user-plus" style="font-size: 3rem; color: #430000 ; margin-bottom: 15px;"></i>
                    <p>You're not following anyone yet.</p>
                    <p>Search for friends to follow their posts and wishlists!</p>
                    <a href="search_friends.php" class="view-profile-btn" style="margin-top: 15px; display: inline-block;">
                        Search Friends
                    </a>
                </div>
            <?php endif; ?>
        </main>
    </body>
</html>