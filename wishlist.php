<?php
require_once 'config.php';
requireLogin();

$username = getCurrentUser();

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$isStore = isset($user['user_type']) && $user['user_type'] === 'store';

// Get user's wishlist
$stmt = $pdo->prepare("SELECT * FROM wishlists WHERE username = ?");
$stmt->execute([$username]);
$wishlist = $stmt->fetch(PDO::FETCH_ASSOC);

// Safe visibility value even if wishlist does not exist yet
$wishlist_visibility = 0; // default: private

if ($wishlist && isset($wishlist['visibility'])) {
    $wishlist_visibility = (int) $wishlist['visibility'];
}


// Get wishlist items
$wishlist_items = [];

if ($wishlist && isset($wishlist['wishlist_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM wishlist_items WHERE wishlist_id = ? ORDER BY created_at DESC");
    $stmt->execute([$wishlist['wishlist_id']]);
    $wishlist_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// Get user's posts count
$stmt = $pdo->prepare("SELECT COUNT(*) as post_count FROM posts WHERE username = ?");
$stmt->execute([$username]);
$post_count = $stmt->fetch(PDO::FETCH_ASSOC)['post_count'];

// Get friends count (followers) - FIXED
$stmt = $pdo->prepare("SELECT COUNT(*) as follower_count FROM friends WHERE username_2 = ?");
$stmt->execute([$username]);
$follower_count_result = $stmt->fetch(PDO::FETCH_ASSOC);
$follower_count = $follower_count_result ? $follower_count_result['follower_count'] : 0;

// Get following count - CORRECT (already fine)
$stmt = $pdo->prepare("SELECT COUNT(*) as following_count FROM friends WHERE username_1 = ?");
$stmt->execute([$username]);
$following_count_result = $stmt->fetch(PDO::FETCH_ASSOC);
$following_count = $following_count_result ? $following_count_result['following_count'] : 0;

// Handle wishlist visibility toggle
if (!$isStore && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_visibility'])) {
    $visibility = $_POST['visibility'] === 'true' ? 1 : 0;
    $stmt = $pdo->prepare("UPDATE wishlists SET visibility = ? WHERE username = ?");
    $stmt->execute([$visibility, $username]);
    header("Location: wishlist.php");
    exit();
}


// Handle item removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_item']) && $wishlist && isset($wishlist['wishlist_id'])) {
    $item_id = $_POST['item_id'];
    $stmt = $pdo->prepare("DELETE FROM wishlist_items WHERE item_id = ? AND wishlist_id = ?");
    $stmt->execute([$item_id, $wishlist['wishlist_id']]);
    header("Location: wishlist.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>My Wishlist</title>
        <link rel="stylesheet" href="style.css">
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

            .wishlist-item-info {
                padding: 15px;
                background: white;
            }

            .wishlist-item-info h3 {
                font-weight: 600;
                color: #4a202a;
                margin: 0 0 8px 0;
                font-size: 16px;
            }

            .wishlist-item-info p {
                margin: 0;
                color: #666;
                font-size: 14px;
            }

            .wishlist-link {
                display: inline-block;
                margin: 8px 0;
                padding: 6px 12px;
                background: rgba(132, 81, 197, 0.1);
                border-radius: 6px;
                color: #8451C5;
                text-decoration: none;
                font-size: 0.8rem;
                word-break: break-all;
                max-width: 100%;
                border: 1px solid rgba(132, 81, 197, 0.2);
                transition: all 0.3s ease;
            }

            .wishlist-link:hover {
                background: #8451C5;
                color: white;
                text-decoration: none;
            }

            .profile-post .post-details {
                background: #FFFCFC;
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

            .wishlist-item-info h3 {
                color: #430000;
            }

            .wishlist-link {
                background: rgba(101, 0, 0, 0.1);
                color: #650000;
                border: 1px solid rgba(101, 0, 0, 0.2);
            }

            .wishlist-link:hover {
                background: #650000;
                color: #FFFCFC;
            }

            .visibility-checkbox:checked + .visibility-label .visibility-slider {
                background: #B77E7E;
            }

            .visibility-checkbox:checked + .visibility-label .visibility-text {
                color: #B77E7E;
            }

            .create-wishlist-item-btn:hover {
                background: linear-gradient(135deg, #A66D6D 0%, #650000 100%);
                transform: translateY(-3px);
                box-shadow: 0 8px 25px rgba(183, 126, 126, 0.4);
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
                    <a href="profile.php" class="tab" data-tab="posts">
                        <i class="fas fa-gift"></i> My Posts
                    </a>
                    <a href="wishlist.php" class="tab active" data-tab="wishlist">
                        <i class="fas fa-heart"></i> My Wishlist
                    </a>
                </div>
            </section>

            <?php if (!$isStore): ?>
                <!-- Visibility Control -->
                <section class="wishlist-visibility-control">
                    <form method="POST">
                        <div class="visibility-toggle">

                            <!-- MUST BE FIRST and must directly precede label -->
                            <input 
                                type="checkbox" 
                                id="wishlist-visibility-page" 
                                class="visibility-checkbox"
                                <?php echo $wishlist_visibility ? 'checked' : ''; ?> 
                                onchange="this.form.submit()"
                                >


                            <!-- THIS MUST COME RIGHT AFTER INPUT -->
                            <label for="wishlist-visibility-page" class="visibility-label">
                                <span class="visibility-text">
                                    My Wishlist is <?php echo $wishlist_visibility ? 'Public' : 'Private'; ?>
                                </span>
                                <span class="visibility-slider"></span>
                            </label>

                            <!-- Hidden fields MUST come AFTER label -->
                            <input type="hidden" name="toggle_visibility" value="1">
                            <input type="hidden" name="visibility" value="<?php echo $wishlist_visibility ? 'false' : 'true'; ?>">

                        </div>
                    </form>

                    <p class="visibility-help">When off, only you can see your wishlist items</p>
                </section>



                <!-- Add Wishlist Item Button -->
                <section class="add-wishlist-item-section" style="text-align: center; margin: 30px 0;">
                    <a href="create_wishlist_item.php" class="create-wishlist-item-btn" style="
                       background: linear-gradient(135deg, #B77E7E 0%, #A66D6D 100%);
                       color: #FFFCFC;
                       border: none;
                       padding: 14px 28px;
                       border-radius: 30px;
                       font-weight: 600;
                       cursor: pointer;
                       transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                       display: inline-flex;
                       align-items: center;
                       gap: 10px;
                       text-decoration: none;
                       font-size: 15px;
                       letter-spacing: 0.3px;
                       box-shadow: 0 4px 15px rgba(183, 126, 126, 0.3);
                       ">
                        <i class="fas fa-plus-circle"></i> Add New Wishlist Item
                    </a>


                    <!-- Wishlist Grid -->
                    <section class="profile-posts" id="wishlist-tab">
                        <div class="posts-grid">
                            <?php foreach ($wishlist_items as $item): ?>
                                <!-- Wishlist Item -->
                                <div class="profile-post">
                                    <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>">
                                    <div class="post-overlay">
                                        <div class="post-actions">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="remove_item" value="1">
                                                <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                                <button type="submit" class="action-btn remove-btn" onclick="return confirm('Are you sure you want to remove this item from your wishlist?')">
                                                    <i class="far fa-trash-alt"></i>
                                                    <span>Remove</span>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                    <div class="wishlist-item-info">
                                        <h3><?php echo htmlspecialchars($item['title']); ?></h3>
                                        <?php if (!empty($item['price'])): ?>
                                            <p style="font-weight: 600; color: #650000;"><?php echo number_format($item['price'], 2); ?> SAR</p>
                                        <?php endif; ?>

                                        <?php if (!empty($item['external_link'])): ?>
                                            <a href="<?php echo htmlspecialchars($item['external_link']); ?>" target="_blank" class="wishlist-link">
                                                <i class="fas fa-external-link-alt" style="margin-right: 5px;"></i> 
                                                <?php
                                                $url = $item['external_link'];
                                                $domain = parse_url($url, PHP_URL_HOST);
                                                if ($domain) {
                                                    echo htmlspecialchars($domain);
                                                } else {
                                                    echo 'View Product';
                                                }
                                                ?>
                                            </a>
                                        <?php endif; ?>

                                        <?php if ($item['is_reserved']): ?>
                                            <p style="color: #ff6f61; font-weight: 600; margin-top: 8px;">
                                                <i class="fas fa-lock"></i> Reserved
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <?php if (count($wishlist_items) === 0): ?>
                                <div style="text-align: center; padding: 40px; color: #888; grid-column: 1 / -1;">
                                    <i class="fas fa-heart" style="font-size: 3rem; color: #430000 ; margin-bottom: 15px;"></i>
                                    <p>Your wishlist is empty. Start adding items from posts!</p>
                                    <a href="posts.php" class="edit-profile-btn" style="text-decoration: none; margin-top: 15px; display: inline-block;">
                                        Browse Posts
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>
                <?php else: ?>
                    <section class="profile-posts" id="wishlist-tab">
                        <p style="padding: 20px; font-weight: 500;">
                            Store accounts do not have wishlists.
                        </p>
                    </section>

                <?php endif; ?>

        </main>

        <!-- Footer -->
        <footer class="footer" style="background: #FFFCFC !important; color: #666 !important;">
            <p style="color: #666 !important; background: transparent !important; margin: 0;">© 2025 WOD — All Rights Reserved.</p>
        </footer>

    </body>

</html>