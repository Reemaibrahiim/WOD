
<?php
require_once 'config.php';
requireLogin();

if (isset($_GET['search'])) {
    $search_term = '%' . $_GET['search'] . '%';
    $current_user = getCurrentUser();

    $stmt = $pdo->prepare("
        SELECT username, name, photo_url, bio 
        FROM users 
        WHERE username LIKE ? AND username != ?
        LIMIT 10
    ");
    $stmt->execute([$search_term, $current_user]);
    $search_results = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle follow/unfollow
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $target_username = $_POST['username'];
    $current_user = getCurrentUser();

    if ($_POST['action'] === 'follow') {
        // Check if not already following (directional check)
        $stmt = $pdo->prepare("SELECT * FROM friends WHERE username_1 = ? AND username_2 = ?");
        $stmt->execute([$current_user, $target_username]);

        if ($stmt->rowCount() === 0) {
            $stmt = $pdo->prepare("INSERT INTO friends (username_1, username_2) VALUES (?, ?)");
            $stmt->execute([$current_user, $target_username]);
        }
    } elseif ($_POST['action'] === 'unfollow') {
        // Remove only the directional follow relationship
        $stmt = $pdo->prepare("DELETE FROM friends WHERE username_1 = ? AND username_2 = ?");
        $stmt->execute([$current_user, $target_username]);
    }

    header("Location: search_friends.php?search=" . urlencode($_GET['search'] ?? ''));
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Search Friends - WOD</title>
        <link rel="stylesheet" href="style.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
        <style>
            .search-container-custom {
                max-width: 800px;
                margin: 40px auto;
                padding: 30px;
                background-color: #fcfcfc;
                border-radius: 20px;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
                margin-right: calc(auto + 80px);
            }

            .search-form {
                display: flex;
                gap: 10px;
                margin-bottom: 30px;
            }

            .search-input {
                flex: 1;
                padding: 12px 20px;
                border: 2px solid #f0f0f0;
                border-radius: 25px;
                font-size: 1rem;
            }

            .search-input:focus {
                border-color: #f5c0d3;
                outline: none;
            }

            .search-btn {
                background-color: #f5c0d3;
                color: white;
                border: none;
                padding: 12px 25px;
                border-radius: 25px;
                cursor: pointer;
                font-weight: 600;
            }

            .search-btn:hover {
                background-color: #e8a8c5;
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
                background-color: transparent;
                color: #8451C5;
                border: 2px solid #8451C5;
                padding: 8px 16px;
                border-radius: 20px;
                cursor: pointer;
                font-weight: 600;
                text-decoration: none;
                margin-left: 10px;
                transition: all 0.3s ease;
            }

            .view-profile-btn:hover {
                background-color: #8451C5;
                color: white;
            }

            .no-results {
                text-align: center;
                color: #888;
                padding: 40px;
            }

            .search-container-custom {
                max-width: 800px;
                margin: 40px auto;
                padding: 30px;
                background-color: #FFFCFC;
                border-radius: 20px;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
                margin-right: calc(auto + 80px);
            }

            .search-btn {
                background-color: #B77E7E;
                color: #FFFCFC;
                border: none;
                padding: 12px 25px;
                border-radius: 25px;
                cursor: pointer;
                font-weight: 600;
            }

            .search-btn:hover {
                background-color: #650000;
            }

            .username {
                font-weight: 600;
                color: #430000;
                margin: 0;
                font-size: 1.1rem;
            }

            .follow-btn {
                background-color: #B77E7E;
                color: #FFFCFC;
                border: none;
                padding: 8px 16px;
                border-radius: 20px;
                cursor: pointer;
                font-weight: 600;
                transition: background-color 0.3s ease;
            }

            .follow-btn:hover {
                background-color: #650000;
            }

            .unfollow-btn {
                background-color: #650000;
                color: #FFFCFC;
                border: none;
                padding: 8px 16px;
                border-radius: 20px;
                cursor: pointer;
                font-weight: 600;
                transition: background-color 0.3s ease;
            }

            .unfollow-btn:hover {
                background-color: #430000;
            }

            .view-profile-btn {
                background-color: transparent;
                color: #650000;
                border: 2px solid #650000;
                padding: 8px 16px;
                border-radius: 20px;
                cursor: pointer;
                font-weight: 600;
                text-decoration: none;
                margin-left: 10px;
                transition: all 0.3s ease;
            }

            .view-profile-btn:hover {
                background-color: #650000;
                color: #FFFCFC;
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
        <main class="search-container-custom">
            <h1 style="color: #430000; text-align: center; margin-bottom: 30px;">
                <i class="fas fa-search" style="color:#B77E7E; margin-right: 10px;"></i> Search Friends
            </h1>

            <form method="GET" class="search-form">
                <input type="text" name="search" class="search-input" 
                       placeholder="Search by username..." 
                       value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                <button type="submit" class="search-btn">
                    <i class="fas fa-search" style="margin-right: 8px;"></i> Search
                </button>
            </form>

            <?php if (isset($search_results)): ?>
                <div class="search-results">
                    <?php if (count($search_results) > 0): ?>
                        <?php foreach ($search_results as $user): ?>
                            <?php
                            // Check if current user is following this user (directional check)
                            $current_user = getCurrentUser();
                            $stmt = $pdo->prepare("SELECT * FROM friends WHERE username_1 = ? AND username_2 = ?");
                            $stmt->execute([$current_user, $user['username']]);
                            $is_following = $stmt->rowCount() > 0;
                            ?>

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
                                <div>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="username" value="<?php echo htmlspecialchars($user['username']); ?>">
                                        <?php if ($is_following): ?>
                                            <button type="submit" name="action" value="unfollow" class="unfollow-btn">
                                                <i class="fas fa-user-minus" style="margin-right: 5px;"></i> Unfollow
                                            </button>
                                        <?php else: ?>
                                            <button type="submit" name="action" value="follow" class="follow-btn">
                                                <i class="fas fa-user-plus" style="margin-right: 5px;"></i> Follow
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                    <a href="friend_profile.php?username=<?php echo urlencode($user['username']); ?>" class="view-profile-btn">
                                        View Profile
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-results">
                            <i class="fas fa-user-slash" style="font-size: 3rem; color: #B77E7E; margin-bottom: 15px;"></i>
                            <p>No users found matching your search.</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </body>
</html>
