<?php
require_once 'config.php';
requireLogin();

if (!isset($_GET['username'])) {
    header("Location: posts.php");
    exit();
}

$friend_username = $_GET['username'];
$current_user = getCurrentUser();

// Handle like/unlike and wishlist actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'like' && isset($_POST['post_id'])) {
        $post_id = $_POST['post_id'];

        // Check if already liked
        $stmt = $pdo->prepare("SELECT * FROM likes WHERE username = ? AND post_id = ?");
        $stmt->execute([$current_user, $post_id]);

        if ($stmt->rowCount() === 0) {
            // Add like
            $stmt = $pdo->prepare("INSERT INTO likes (username, post_id) VALUES (?, ?)");
            $stmt->execute([$current_user, $post_id]);
        } else {
            // Remove like
            $stmt = $pdo->prepare("DELETE FROM likes WHERE username = ? AND post_id = ?");
            $stmt->execute([$current_user, $post_id]);
        }
        header("Location: friend_profile.php?username=" . urlencode($friend_username));
        exit();
    } elseif ($_POST['action'] === 'add_to_wishlist' && isset($_POST['post_id'])) {
        // Add post to user's wishlist
        $post_id = $_POST['post_id'];

        // Get post details
        $stmt = $pdo->prepare("SELECT * FROM posts WHERE post_id = ?");
        $stmt->execute([$post_id]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($post) {
            // Get user's wishlist
            $stmt = $pdo->prepare("SELECT wishlist_id FROM wishlists WHERE username = ?");
            $stmt->execute([$current_user]);
            $wishlist = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($wishlist) {
                // Check if item already exists in wishlist
                $stmt = $pdo->prepare("SELECT * FROM wishlist_items WHERE title = ? AND wishlist_id = ?");
                $stmt->execute([$post['title'], $wishlist['wishlist_id']]);

                if ($stmt->rowCount() === 0) {
                    // Add to wishlist
                    $stmt = $pdo->prepare("INSERT INTO wishlist_items (title, external_link, image_url, price, wishlist_id) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$post['title'], $post['external_link'], $post['image_url'], $post['price'], $wishlist['wishlist_id']]);
                    $_SESSION['success'] = "Post added to wishlist!";
                } else {
                    $_SESSION['error'] = "Item already in wishlist!";
                }
            }
        }
        header("Location: friend_profile.php?username=" . urlencode($friend_username));
        exit();
    }
}

// Get friend data
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$friend_username]);
$friend = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$friend) {
    header("Location: posts.php");
    exit();
}

// Check if current user is following this friend
$stmt = $pdo->prepare("SELECT * FROM friends WHERE username_1 = ? AND username_2 = ?");
$stmt->execute([$current_user, $friend_username]);
$is_following = $stmt->rowCount() > 0;

// Get friend's posts count
$stmt = $pdo->prepare("SELECT COUNT(*) as post_count FROM posts WHERE username = ?");
$stmt->execute([$friend_username]);
$post_count_result = $stmt->fetch(PDO::FETCH_ASSOC);
$post_count = $post_count_result ? $post_count_result['post_count'] : 0;

// Get friend's followers count
$stmt = $pdo->prepare("SELECT COUNT(*) as follower_count FROM friends WHERE username_2 = ?");
$stmt->execute([$friend_username]);
$follower_count_result = $stmt->fetch(PDO::FETCH_ASSOC);
$follower_count = $follower_count_result ? $follower_count_result['follower_count'] : 0;

// Get friend's posts with like counts
$stmt = $pdo->prepare("
    SELECT p.*, 
           COUNT(l.like_id) as like_count,
           EXISTS(SELECT 1 FROM likes WHERE username = ? AND post_id = p.post_id) as user_liked
    FROM posts p 
    LEFT JOIN likes l ON p.post_id = l.post_id 
    WHERE p.username = ?
    GROUP BY p.post_id 
    ORDER BY p.created_at DESC
");
$stmt->execute([$current_user, $friend_username]);
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check wishlist visibility
$stmt = $pdo->prepare("SELECT visibility FROM wishlists WHERE username = ?");
$stmt->execute([$friend_username]);
$wishlist_result = $stmt->fetch(PDO::FETCH_ASSOC);
$wishlist_visibility = $wishlist_result ? $wishlist_result['visibility'] : 1;

// Handle follow/unfollow
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'follow') {
        $stmt = $pdo->prepare("INSERT INTO friends (username_1, username_2) VALUES (?, ?)");
        $stmt->execute([$current_user, $friend_username]);
        $is_following = true;
    } elseif ($_POST['action'] === 'unfollow') {
        $stmt = $pdo->prepare("DELETE FROM friends WHERE username_1 = ? AND username_2 = ?");
        $stmt->execute([$current_user, $friend_username]);
        $is_following = false;
    }
    header("Location: friend_profile.php?username=" . urlencode($friend_username));
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($friend['username']); ?>'s Profile - WOD</title>
        <link rel="stylesheet" href="style.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

        <style>
            .friend-action-btn {
                background: #f5c0d3;
                border: none;
                border-radius: 25px;
                color: white;
                padding: 8px 20px;
                font-weight: 600;
                cursor: pointer;
                font-size: 14px;
                transition: all 0.3s ease;
                text-decoration: none;
                display: inline-block;
            }

            .friend-action-btn:hover {
                background: #e8a8c5;
                transform: translateY(-2px);
            }

            .unfollow-btn {
                background: #6c757d;
            }

            .unfollow-btn:hover {
                background: #5a6268;
            }

            .wishlist-private {
                color: #888;
                cursor: not-allowed;
            }

            .calendar-link-btn {
                background: #8451C5;
                color: white;
                border: none;
                border-radius: 25px;
                padding: 8px 20px;
                font-weight: 600;
                cursor: pointer;
                font-size: 14px;
                transition: all 0.3s ease;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                gap: 5px;
                margin-left: 10px;
            }

            .calendar-link-btn:hover {
                background: #6a3fb0;
                transform: translateY(-2px);
            }

            .tab {
                display: flex;
                align-items: center;
                justify-content: center;
                height: 60px;
                text-decoration: none;
                color: #888;
                font-size: 16px;
                font-weight: 600;
                padding: 0 30px;
                border: none;
                background: none;
                cursor: pointer;
                transition: all 0.3s ease;
                border-bottom: 3px solid transparent;
            }

            .tab.active {
                color: #f5c0d3;
                border-bottom: 3px solid #f5c0d3;
            }

            .tab:hover {
                color: #f5c0d3;
                background-color: #fdf2f8;
            }

            .wishlist-private {
                display: flex;
                align-items: center;
                justify-content: center;
                height: 60px;
                color: #888;
                font-size: 16px;
                font-weight: 600;
                padding: 0 30px;
                border-bottom: 3px solid transparent;
                cursor: not-allowed;
            }

            .calendar-link-disabled {
                background: #6c757d !important;
                cursor: not-allowed;
                opacity: 0.6;
            }

            .calendar-link-disabled:hover {
                background: #6c757d !important;
                transform: none !important;
            }

            .friend-action-btn {
                background: #B77E7E;
                border: none;
                border-radius: 25px;
                color: #FFFCFC;
                padding: 8px 20px;
                font-weight: 600;
                cursor: pointer;
                font-size: 14px;
                transition: all 0.3s ease;
                text-decoration: none;
                display: inline-block;
            }

            .friend-action-btn:hover {
                background: #650000;
                transform: translateY(-2px);
            }

            .unfollow-btn {
                background: #650000;
            }

            .unfollow-btn:hover {
                background: #430000;
            }

            .calendar-link-btn {
                background: #650000;
                color: #FFFCFC;
                border: none;
                border-radius: 25px;
                padding: 8px 20px;
                font-weight: 600;
                cursor: pointer;
                font-size: 14px;
                transition: all 0.3s ease;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                gap: 5px;
                margin-left: 10px;
            }

            .calendar-link-btn:hover {
                background: #430000;
                transform: translateY(-2px);
            }

            .tab.active {
                color: #B77E7E;
                border-bottom: 3px solid #B77E7E;
            }

            .tab:hover {
                color: #B77E7E;
                background-color: #fdf2f8;
            }

            .post-link {
                background: #f8f0f5;
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

        <!-- Profile Content -->
        <main class="profile-container">
            <!-- Profile Header -->
            <section class="profile-header">
                <div class="profile-pic-container">
                    <img src="<?php echo htmlspecialchars($friend['photo_url'] ?? 'images/Default.jpg'); ?>" alt="Profile Picture" class="profile-pic">
                </div>

                <div class="profile-info">
                    <div class="profile-name-section">
                        <h1 class="username">@<?php echo htmlspecialchars($friend['username']); ?></h1>
                        <form method="POST" style="display: inline;">
                            <?php if ($is_following): ?>
                                <button type="submit" name="action" value="unfollow" class="friend-action-btn unfollow-btn">
                                    <i class="fas fa-user-minus" style="margin-right: 5px;"></i> Unfollow
                                </button>
                            <?php else: ?>
                                <button type="submit" name="action" value="follow" class="friend-action-btn">
                                    <i class="fas fa-user-plus" style="margin-right: 5px;"></i> Follow
                                </button>
                            <?php endif; ?>
                        </form>
                        <!-- Always show View Calendar button - works for everyone -->
                        <a href="friend_calendar.php?username=<?php echo urlencode($friend_username); ?>" class="calendar-link-btn">
                            <i class="fas fa-calendar-alt"></i> View Calendar
                        </a>
                    </div>

                    <h2 class="full-name"><?php echo htmlspecialchars($friend['name']); ?></h2>
                    <p class="bio"><?php echo htmlspecialchars($friend['bio'] ?? 'No bio yet.'); ?></p>

                    <div class="profile-stats">
                        <div class="stat">
                            <span class="stat-number"><?php echo $post_count; ?></span>
                            <span class="stat-label">Posts</span>
                        </div>
                        <div class="stat">
                            <span class="stat-number"><?php echo $follower_count; ?></span>
                            <span class="stat-label">Followers</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Profile Tabs -->
            <section class="profile-tabs">
                <div class="tabs-container">
                    <a href="friend_profile.php?username=<?php echo urlencode($friend_username); ?>" class="tab active" data-tab="posts">
                        <i class="fas fa-gift"></i> Posts
                    </a>
                    <?php if ($wishlist_result && $wishlist_visibility == 1): ?>
                        <a href="friend-wishlist.php?username=<?php echo urlencode($friend_username); ?>" class="tab" data-tab="wishlist">
                            <i class="fas fa-heart"></i> Wishlist
                        </a>
                    <?php elseif ($wishlist_result && $wishlist_visibility == 0 && $is_following): ?>
                        <a href="friend-wishlist.php?username=<?php echo urlencode($friend_username); ?>" class="tab" data-tab="wishlist">
                            <i class="fas fa-heart"></i> Wishlist <i class="fas fa-lock" style="margin-left: 5px; font-size: 0.8em;"></i>
                        </a>
                    <?php else: ?>
                        <span class="tab wishlist-private" title="<?php echo $wishlist_result ? 'Wishlist is private' : 'No wishlist'; ?>">
                            <i class="fas fa-heart"></i> Wishlist 
                            <?php if ($wishlist_result && $wishlist_visibility == 0): ?>
                                <i class="fas fa-lock" style="margin-left: 5px; font-size: 0.8em;"></i>
                            <?php endif; ?>
                        </span>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Posts Grid -->
            <section class="profile-posts" id="posts-tab">


                <div class="posts-grid">
                    <?php foreach ($posts as $post): ?>
                        <div class="profile-post">
                            <img src="<?php echo htmlspecialchars($post['image_url']); ?>" alt="<?php echo htmlspecialchars($post['title']); ?>">
                            <div class="post-overlay">
                                <div class="post-actions">
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="like">
                                        <input type="hidden" name="post_id" value="<?php echo $post['post_id']; ?>">
                                        <button type="submit" class="action-btn like-btn">
                                            <i class="fas fa-heart" style="color: <?php echo $post['user_liked'] ? '#650000' : 'inherit'; ?>"></i>
                                            <span><?php echo $post['like_count']; ?> Likes</span>
                                        </button>
                                    </form>


                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="add_to_wishlist">
                                        <input type="hidden" name="post_id" value="<?php echo $post['post_id']; ?>">
                                        <button type="submit" class="action-btn wishlist-btn">
                                            <i class="fas fa-bookmark"></i>
                                            <span>Add to Wishlist</span>
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <div style="padding: 10px;">
                                <h4><?php echo htmlspecialchars($post['title']); ?></h4>
                                <p><?php echo htmlspecialchars($post['description']); ?></p>
                                <?php if (!empty($post['external_link'])): ?>
                                    <a href="<?php echo htmlspecialchars($post['external_link']); ?>" target="_blank" class="post-link" 
                                       style="display: inline-block; margin: 5px 0; padding: 5px 10px; background: #f8f0f5; border-radius: 5px; color: #8451C5; text-decoration: none;">
                                        <i class="fas fa-external-link-alt"></i> View Product
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($post['price'])): ?>
                                    <p style="font-weight: 600; color: #ff6f61;"><?php echo number_format($post['price'], 2); ?> SAR</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if (count($posts) === 0): ?>
                        <div style="text-align: center; padding: 40px; color: #888; grid-column: 1 / -1;">
                            <i class="fas fa-gift" style="font-size: 3rem; color: #B77E7E; margin-bottom: 15px;"></i>
                            <p>No posts yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </main>

        <!-- Footer -->
        <footer class="footer" style="background: #FFFCFC !important; color: #666 !important;">
            <p style="color: #666 !important; background: transparent !important; margin: 0;">© 2025 WOD — All Rights Reserved.</p>
        </footer>
    </body>
</html>