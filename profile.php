<?php
require_once 'config.php';
requireLogin();

$username = getCurrentUser();

// Handle post deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_post'])) {
    $post_id = $_POST['post_id'];

    // Verify the post belongs to the current user
    $stmt = $pdo->prepare("SELECT * FROM posts WHERE post_id = ? AND username = ?");
    $stmt->execute([$post_id, $username]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($post) {
        // Delete associated likes first
        $stmt = $pdo->prepare("DELETE FROM likes WHERE post_id = ?");
        $stmt->execute([$post_id]);

        // Delete from wishlist_items (Remove from all wishlists)
        $stmt = $pdo->prepare("DELETE FROM wishlist_items WHERE post_id = ?");
        $stmt->execute([$post_id]);

        // Delete the post
        $stmt = $pdo->prepare("DELETE FROM posts WHERE post_id = ?");
        $stmt->execute([$post_id]);

        header("Location: profile.php");
        exit();
    }
}

// Handle like/unlike
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'like' && isset($_POST['post_id'])) {
        $post_id = $_POST['post_id'];
        $username = getCurrentUser();

        // Check if already liked
        $stmt = $pdo->prepare("SELECT * FROM likes WHERE username = ? AND post_id = ?");
        $stmt->execute([$username, $post_id]);

        if ($stmt->rowCount() === 0) {
            // Add like
            $stmt = $pdo->prepare("INSERT INTO likes (username, post_id) VALUES (?, ?)");
            $stmt->execute([$username, $post_id]);
        } else {
            // Remove like
            $stmt = $pdo->prepare("DELETE FROM likes WHERE username = ? AND post_id = ?");
            $stmt->execute([$username, $post_id]);
        }
        header("Location: profile.php");
        exit();
    } elseif ($_POST['action'] === 'add_to_wishlist' && isset($_POST['post_id'])) {
        // Add post to user's wishlist
        $post_id = $_POST['post_id'];
        $username = getCurrentUser();

        // Get post details
        $stmt = $pdo->prepare("SELECT * FROM posts WHERE post_id = ?");
        $stmt->execute([$post_id]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($post) {
            // Get user's wishlist
            $stmt = $pdo->prepare("SELECT wishlist_id FROM wishlists WHERE username = ?");
            $stmt->execute([$username]);
            $wishlist = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($wishlist) {
                // Check if item already exists in wishlist by post_id
                $stmt = $pdo->prepare("SELECT * FROM wishlist_items WHERE post_id = ? AND wishlist_id = ?");
                $stmt->execute([$post_id, $wishlist['wishlist_id']]);

                if ($stmt->rowCount() === 0) {
                    // Add to wishlist
                    $stmt = $pdo->prepare("INSERT INTO wishlist_items (title, external_link, image_url, price, wishlist_id, post_id) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$post['title'], $post['external_link'], $post['image_url'], $post['price'], $wishlist['wishlist_id'], $post_id]);
                }
            }
        }
        header("Location: profile.php");
        exit();
    }
}

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get user's posts count 
$stmt = $pdo->prepare("SELECT COUNT(*) as post_count FROM posts WHERE username = ?");
$stmt->execute([$username]);
$post_count_result = $stmt->fetch(PDO::FETCH_ASSOC);
$post_count = $post_count_result ? $post_count_result['post_count'] : 0;

// Get followers count (people who follow the current user)
$stmt = $pdo->prepare("SELECT COUNT(*) as follower_count FROM friends WHERE username_2 = ?");
$stmt->execute([$username]);
$follower_count_result = $stmt->fetch(PDO::FETCH_ASSOC);
$follower_count = $follower_count_result ? $follower_count_result['follower_count'] : 0;

// Get following count (people the current user follows)
$stmt = $pdo->prepare("SELECT COUNT(*) as following_count FROM friends WHERE username_1 = ?");
$stmt->execute([$username]);
$following_count_result = $stmt->fetch(PDO::FETCH_ASSOC);
$following_count = $following_count_result ? $following_count_result['following_count'] : 0;

// Get user's posts with like counts
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
$stmt->execute([$username, $username]);
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle wishlist visibility toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_visibility'])) {
    $visibility = $_POST['visibility'] === 'true' ? 1 : 0;
    $stmt = $pdo->prepare("UPDATE wishlists SET visibility = ? WHERE username = ?");
    $stmt->execute([$visibility, $username]);
    header("Location: profile.php");
    exit();
}

// Get current wishlist visibility
$stmt = $pdo->prepare("SELECT visibility FROM wishlists WHERE username = ?");
$stmt->execute([$username]);
$wishlist_result = $stmt->fetch(PDO::FETCH_ASSOC);
$wishlist_visibility = $wishlist_result ? $wishlist_result['visibility'] : 1;
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Profile</title>
        <link rel="stylesheet" href="style.css" />
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

        <style>
            .profile-post .post-details {
                padding: 10px;
                background: white;
            }

            .profile-post .post-title {
                font-weight: 600;
                color: #4a202a;
                margin: 5px 0;
                font-size: 14px;
            }

            .profile-post .post-price {
                font-weight: 600;
                color: #ff6f61;
                margin: 0;
                font-size: 12px;
            }

            .create-post-btn-profile {
                position: fixed;
                bottom: 30px;
                right: 100px;
                background: #f5c0d3;
                color: white;
                width: 60px;
                height: 60px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                text-decoration: none;
                box-shadow: 0 4px 15px rgba(245, 192, 211, 0.5);
                z-index: 1000;
                transition: all 0.3s ease;
            }

            .create-post-btn-profile:hover {
                background: #e8a8c5;
                transform: scale(1.1);
            }

            .calendar-quick-link {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                background: #8451C5;
                color: white;
                padding: 8px 16px;
                border-radius: 20px;
                text-decoration: none;
                font-weight: 600;
                font-size: 14px;
                transition: all 0.3s ease;
                margin-left: 15px;
            }

            .calendar-quick-link:hover {
                background: #6a3fb0;
                transform: translateY(-2px);
            }

            .delete-btn {
                background: rgba(255, 255, 255, 0.95);
                color: #ff6b6b;
            }

            .delete-btn:hover {
                background: #ff6b6b;
                color: white;
            }
            .visibility-slider {
                position: relative;
                width: 60px;
                height: 30px;
                background: #ccc;
                border-radius: 25px;
                transition: all 0.3s ease;
                display: flex;
                align-items: center;
            }

            .slider-knob {
                position: absolute;
                width: 24px;
                height: 24px;
                background: white;
                border-radius: 50%;
                top: 3px;
                left: 3px;
                transition: all 0.3s ease;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            }

            .visibility-checkbox:checked + .visibility-label .visibility-slider {
                background: #f5c0d3;
            }

            .visibility-checkbox:checked + .visibility-label .visibility-slider .slider-knob {
                transform: translateX(30px);
            }

            .visibility-label {
                display: flex;
                align-items: center;
                gap: 15px;
                cursor: pointer;
                padding: 12px 20px;
                border-radius: 25px;
                background: #f8f0f5;
                transition: all 0.3s ease;
                min-width: 250px;
                justify-content: space-between;
            }

            .visibility-label:hover {
                background: #f5c0d320;
            }

            .visibility-text {
                font-weight: 600;
                color: #4a202a;
                font-size: 14px;
                display: flex;
                align-items: center;
            }
            .profile-post .post-title {
                color: #430000;
            }

            .profile-post .post-price {
                color: #650000;
            }

            .create-post-btn-profile {
                background: #B77E7E;
                color: #FFFCFC;
            }

            .create-post-btn-profile:hover {
                background: #650000;
                transform: scale(1.1);
            }

            .calendar-quick-link {
                background: #650000;
                color: #FFFCFC;
            }

            .calendar-quick-link:hover {
                background: #430000;
                transform: translateY(-2px);
            }

            .delete-btn {
                background: rgba(255, 252, 252, 0.95);
                color: #650000;
            }

            .delete-btn:hover {
                background: #650000;
                color: #FFFCFC;
            }

            .post-link {
                background: #f8f0f5;
                color: #650000;
            }

            .visibility-checkbox:checked + .visibility-label .visibility-slider {
                background: #B77E7E;
            }

            .visibility-checkbox:checked + .visibility-label .visibility-text {
                color: #B77E7E;
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
                    <img src="<?php echo htmlspecialchars($user['photo_url'] ?? 'images/Default.jpg'); ?>" alt="Profile Picture" class="profile-pic">
                </div>

                <div class="profile-info">
                    <div class="profile-name-section">
                        <h1 class="username">@<?php echo htmlspecialchars($user['username']); ?></h1>
                        <a href="edit_profile.php" class="edit-profile-btn" style="text-decoration: none;">Edit Profile</a>
                        <a href="calendar.php" class="calendar-quick-link">
                            <i class="fas fa-calendar-alt"></i> My Calendar
                        </a>
                    </div>

                    <h2 class="full-name"><?php echo htmlspecialchars($user['name']); ?></h2>
                    <p class="bio"><?php echo htmlspecialchars($user['bio'] ?? 'No bio yet.'); ?></p>

                    <div class="profile-stats">
                        <div class="stat">
                            <span class="stat-number"><?php echo $post_count; ?></span>
                            <span class="stat-label">Posts</span>
                        </div>
                        <div class="stat">
                            <span class="stat-number"><?php echo $follower_count; ?></span>
                            <span class="stat-label">Followers</span>
                        </div>
                        <div class="stat">
                            <a href="following.php" style="text-decoration: none; color: inherit;">
                                <span class="stat-number"><?php echo $following_count; ?></span>
                                <span class="stat-label">Following</span>
                            </a>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Profile Tabs -->
            <section class="profile-tabs">
                <div class="tabs-container">
                    <button class="tab active" data-tab="posts">
                        <i class="fas fa-gift"></i> My Posts
                    </button>
                    <a href="wishlist.php" class="tab" data-tab="wishlist">
                        <i class="fas fa-heart"></i> My Wishlist
                    </a>
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
                                            <i class="fas fa-heart" style="color: <?php echo $post['user_liked'] ? '#ff6f61' : 'inherit'; ?>"></i>
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
                                    <!-- Delete Post Button (only for user's own posts) -->
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="delete_post" value="1">
                                        <input type="hidden" name="post_id" value="<?php echo $post['post_id']; ?>">
                                        <button type="submit" class="action-btn delete-btn" 
                                                onclick="return confirm('Are you sure you want to delete this post?')">
                                            <i class="fas fa-trash"></i>
                                            <span>Delete</span>
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
                                    <p style="font-weight: 600; color: #650000;"><?php echo number_format($post['price'], 2); ?> SAR</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if (count($posts) === 0): ?>
                        <div style="text-align: center; padding: 40px; color: #888; grid-column: 1 / -1;">
                            <i class="fas fa-gift" style="font-size: 3rem; color: #430000; margin-bottom: 15px;"></i>
                            <p>No posts yet.</p>
                            <a href="create_post.php" class="edit-profile-btn" style="text-decoration: none; margin-top: 15px; display: inline-block;">
                                Create Your First Post
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </main>


        <!-- Footer -->
        <footer class="footer" style="background: #FFFCFC !important; color: #666 !important;">
            <p style="color: #666 !important; background: transparent !important; margin: 0;">© 2025 WOD — All Rights Reserved.</p>
        </footer>

        <script>
            function toggleVisibility(checkbox) {
                const isPublic = checkbox.checked;
                const statusElement = document.getElementById('visibility-status');
                const helpElement = document.getElementById('visibility-help');
                const iconElement = checkbox.parentElement.querySelector('.fa');

                // Update visual state immediately
                if (isPublic) {
                    statusElement.textContent = 'Public';
                    helpElement.textContent = 'Everyone can see your wishlist';
                    iconElement.className = 'fas fa-eye';
                } else {
                    statusElement.textContent = 'Private';
                    helpElement.textContent = 'Only you can see your wishlist';
                    iconElement.className = 'fas fa-eye-slash';
                }

                // Update the hidden input value
                document.getElementById('visibility-value').value = isPublic ? 'false' : 'true';

                // Submit the form
                document.getElementById('visibility-form').submit();
            }
        </script>

    </body>
</html>
