<?php
require_once 'config.php';
requireLogin();

if (!isset($_GET['username'])) {
    header("Location: posts.php");
    exit();
}

$friend_username = $_GET['username'];
$current_user = getCurrentUser();

// Get friend data
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$friend_username]);
$friend = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$friend) {
    header("Location: posts.php");
    exit();
}

// Get friend's wishlist
$stmt = $pdo->prepare("SELECT * FROM wishlists WHERE username = ?");
$stmt->execute([$friend_username]);
$wishlist = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if current user is following this friend
$stmt = $pdo->prepare("SELECT * FROM friends WHERE (username_1 = ? AND username_2 = ?) OR (username_1 = ? AND username_2 = ?)");
$stmt->execute([$current_user, $friend_username, $friend_username, $current_user]);
$is_following = $stmt->rowCount() > 0;

// Get wishlist items - ONLY if wishlist is public (visibility = 1)
$wishlist_items = [];
$can_view_wishlist = false;

if ($wishlist && $wishlist['visibility'] == 1) {
    // Only show wishlist if it's public
    $can_view_wishlist = true;
    $stmt = $pdo->prepare("
        SELECT wi.*, 
               (SELECT COUNT(*) FROM group_gifts gg WHERE gg.item_id = wi.item_id) as group_gift_count
        FROM wishlist_items wi 
        WHERE wi.wishlist_id = ? 
        ORDER BY wi.created_at DESC
    ");
    $stmt->execute([$wishlist['wishlist_id']]);
    $wishlist_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get friend's posts count
$stmt = $pdo->prepare("SELECT COUNT(*) as post_count FROM posts WHERE username = ?");
$stmt->execute([$friend_username]);
$post_count = $stmt->fetch(PDO::FETCH_ASSOC)['post_count'];

// Get friend's followers count
$stmt = $pdo->prepare("SELECT COUNT(*) as follower_count FROM friends WHERE username_2 = ?");
$stmt->execute([$friend_username]);
$follower_count = $stmt->fetch(PDO::FETCH_ASSOC)['follower_count'];

// Handle follow/unfollow
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'follow') {
        $stmt = $pdo->prepare("INSERT INTO friends (username_1, username_2) VALUES (?, ?)");
        $stmt->execute([$current_user, $friend_username]);
        $is_following = true;
    } elseif ($_POST['action'] === 'unfollow') {
        $stmt = $pdo->prepare("DELETE FROM friends WHERE (username_1 = ? AND username_2 = ?) OR (username_1 = ? AND username_2 = ?)");
        $stmt->execute([$current_user, $friend_username, $friend_username, $current_user]);
        $is_following = false;
    }
    header("Location: friend-wishlist.php?username=" . urlencode($friend_username));
    exit();
}

// Handle reserving items (only if user can view the wishlist)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reserve_item']) && $can_view_wishlist) {
    $item_id = $_POST['item_id'];

    // Check if item is already reserved or has group gift
    $stmt = $pdo->prepare("
        SELECT wi.is_reserved, 
               (SELECT COUNT(*) FROM group_gifts gg WHERE gg.item_id = wi.item_id) as group_gift_count
        FROM wishlist_items wi 
        WHERE wi.item_id = ?
    ");
    $stmt->execute([$item_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item['is_reserved'] && $item['group_gift_count'] == 0) {
        $stmt = $pdo->prepare("UPDATE wishlist_items SET is_reserved = 1, reserved_by = ? WHERE item_id = ?");
        $stmt->execute([$current_user, $item_id]);
    }
    header("Location: friend-wishlist.php?username=" . urlencode($friend_username));
    exit();
}

// Handle group gift creation - FIXED REDIRECT AND VALIDATION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_group_gift']) && $can_view_wishlist) {
    $item_id = $_POST['item_id'];
    $participant_count = intval($_POST['participant_count']);
    $current_user = getCurrentUser();

    // Validate participant count
    if ($participant_count < 2) {
        $_SESSION['error'] = "Group gift must have at least 2 participants including yourself.";
        header("Location: friend-wishlist.php?username=" . urlencode($friend_username));
        exit();
    }

    // Get item price and check if already reserved or has group gift
    $stmt = $pdo->prepare("
        SELECT wi.price, wi.is_reserved,
               (SELECT COUNT(*) FROM group_gifts gg WHERE gg.item_id = wi.item_id) as group_gift_count
        FROM wishlist_items wi 
        WHERE wi.item_id = ?
    ");
    $stmt->execute([$item_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($item && $item['price'] > 0 && !$item['is_reserved'] && $item['group_gift_count'] == 0) {
        $share_amount = $item['price'] / $participant_count;

        // Get participant usernames from individual inputs and validate them
        $participants = [];
        $invalid_users = [];

        for ($i = 1; $i <= $participant_count - 1; $i++) {
            $username_key = 'username_' . $i;
            if (!empty($_POST[$username_key])) {
                $username = trim($_POST[$username_key]);

                // Remove @ symbol if present
                if (strpos($username, '@') === 0) {
                    $username = substr($username, 1);
                }

                // Check if user exists
                $user_check = $pdo->prepare("SELECT username FROM users WHERE username = ?");
                $user_check->execute([$username]);
                $user_exists = $user_check->fetch(PDO::FETCH_ASSOC);

                if ($user_exists) {
                    $participants[] = $username;
                } else {
                    $invalid_users[] = $username;
                }
            }
        }

        // Check if there are invalid users
        if (!empty($invalid_users)) {
            $_SESSION['error'] = "The following users do not exist: " . implode(', ', $invalid_users);
            header("Location: friend-wishlist.php?username=" . urlencode($friend_username));
            exit();
        }

        // Check if we have enough participants (current user + valid participants)
        if (count($participants) < ($participant_count - 1)) {
            $_SESSION['error'] = "Please provide valid usernames for all participants.";
            header("Location: friend-wishlist.php?username=" . urlencode($friend_username));
            exit();
        }

        // Create group gift
        $stmt = $pdo->prepare("INSERT INTO group_gifts (group_size, collected_amount, item_id, created_by) VALUES (?, 0, ?, ?)");
        $stmt->execute([$participant_count, $item_id, $current_user]);
        $group_gift_id = $pdo->lastInsertId();

        // Add creator as first contributor
        $stmt = $pdo->prepare("INSERT INTO contributions (amount, username, group_gift_id) VALUES (?, ?, ?)");
        $stmt->execute([$share_amount, $current_user, $group_gift_id]);

        // Add other participants - AUTOMATICALLY ADD THEM (NO INVITATIONS)
        foreach ($participants as $username) {
            if (!empty($username) && $username !== $current_user) {
                $stmt = $pdo->prepare("INSERT INTO contributions (amount, username, group_gift_id, is_paid) VALUES (?, ?, ?, 0)");
                $stmt->execute([$share_amount, $username, $group_gift_id]);
            }
        }

        $_SESSION['success'] = "Group gift created successfully! All participants have been added automatically!";
        // FIX: Proper redirect without JavaScript
        header("Location: friend-wishlist.php?username=" . urlencode($friend_username));
        exit();
    } else {
        $_SESSION['error'] = "Unable to create group gift. Item may be reserved or already has a group gift.";
        header("Location: friend-wishlist.php?username=" . urlencode($friend_username));
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($friend['username']); ?>'s Wishlist - WOD</title>
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

            .reserve-btn {
                background: #28a745;
                color: white;
                border: none;
                padding: 10px 20px;
                border-radius: 25px;
                cursor: pointer;
                font-weight: 600;
                transition: all 0.3s ease;
                width: 100%;
                margin-top: 10px;
            }

            .reserve-btn:hover:not(:disabled) {
                background: #218838;
                transform: translateY(-2px);
            }

            .reserve-btn:disabled {
                background: #6c757d;
                cursor: not-allowed;
            }

            .reserved-badge {
                background: #ff6f61;
                color: white;
                padding: 5px 10px;
                border-radius: 15px;
                font-size: 0.8rem;
                font-weight: 600;
                margin-top: 10px;
                display: inline-block;
            }

            .group-gift-badge {
                background: #8451C5;
                color: white;
                padding: 5px 10px;
                border-radius: 15px;
                font-size: 0.8rem;
                font-weight: 600;
                margin-top: 10px;
                display: inline-block;
            }

            .private-wishlist-message {
                text-align: center;
                padding: 60px 20px;
                color: #666;
                background: white;
                border-radius: 15px;
                margin: 40px 20px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.05);
                width: 100%;
            }

            .private-wishlist-message i {
                font-size: 4rem;
                color: #f5c0d3;
                margin-bottom: 20px;
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

            .wishlist-private-tab {
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

            .no-wishlist-message {
                text-align: center;
                padding: 60px 20px;
                color: #666;
                background: white;
                border-radius: 15px;
                margin: 40px 20px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.05);
                width: 100%;
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

            /* FIXED: No Overlapping Items - Flexbox Layout */
            .profile-posts {
                padding-top: 20px;
                width: 100%;
            }

            .posts-grid {
                display: flex;
                flex-wrap: wrap;
                gap: 25px;
                width: 100%;
                justify-content: flex-start;
                align-items: stretch;
            }

            .profile-post {
                position: relative;
                border-radius: 16px;
                overflow: hidden;
                background-color: #fff;
                box-shadow: 0 3px 12px rgba(0, 0, 0, 0.1);
                transition: all 0.3s ease;
                height: 380px;
                display: flex;
                flex-direction: column;
                width: calc(25% - 19px); /* 4 items per row with gap */
                min-width: 280px;
                flex-grow: 0;
                flex-shrink: 0;
                margin: 0;
            }

            .profile-post img {
                width: 100%;
                height: 220px;
                object-fit: cover;
                transition: transform 0.3s ease;
                display: block;
            }

            .profile-post:hover img {
                transform: scale(1.05);
            }

            /* Static item info - always visible */
            .wishlist-item-info {
                padding: 15px;
                background: white;
                flex: 1;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                min-height: 160px;
            }

            .wishlist-item-info h3 {
                font-weight: 600;
                color: #4a202a;
                margin: 0 0 8px 0;
                font-size: 16px;
                line-height: 1.3;
                display: block;
                overflow: visible;
                white-space: normal;
                height: auto;
                word-wrap: break-word;
            }

            .wishlist-item-info p {
                margin: 0;
                color: #666;
                font-size: 14px;
            }

            /* Improved overlay for buttons only */
            .post-overlay {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                height: 220px;
                background: rgba(0, 0, 0, 0.85);
                display: flex;
                align-items: center;
                justify-content: center;
                opacity: 0;
                transition: opacity 0.3s ease;
                z-index: 2;
            }

            .profile-post:hover .post-overlay {
                opacity: 1;
            }

            .post-actions {
                display: flex;
                flex-direction: column;
                gap: 12px;
                align-items: center;
                width: 80%;
            }

            .action-btn {
                display: flex;
                align-items: center;
                gap: 8px;
                padding: 12px 20px;
                background: rgba(255, 255, 255, 0.95);
                border: none;
                border-radius: 25px;
                color: #333;
                font-weight: 600;
                font-size: 14px;
                cursor: pointer;
                transition: all 0.3s ease;
                width: 100%;
                justify-content: center;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
                text-decoration: none;
            }

            .action-btn:hover {
                background: #f5c0d3;
                color: white;
                transform: translateY(-2px);
            }

            /* Ensure buttons are always accessible */
            .reserve-btn, .contribution-btn {
                background: #28a745;
                color: white;
            }

            .contribution-btn {
                background: #f5c0d3;
            }

            .reserve-btn:hover {
                background: #218838;
            }

            .contribution-btn:hover {
                background: #e8a8c5;
            }

            /* Badge styling */
            .reserved-badge, .group-gift-badge {
                background: #ff6f61;
                color: white;
                padding: 4px 8px;
                border-radius: 12px;
                font-size: 0.7rem;
                font-weight: 600;
                margin-top: 5px;
                display: inline-block;
            }

            .group-gift-badge {
                background: #8451C5;
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

            /* Button Styles */
            .action-buttons {
                display: flex;
                flex-direction: column;
                gap: 8px;
                margin-top: 10px;
            }

            .reserve-btn {
                background: #28a745;
                color: white;
                border: none;
                padding: 10px;
                border-radius: 8px;
                cursor: pointer;
                font-weight: 600;
                font-size: 0.8rem;
                transition: all 0.3s ease;
                text-align: center;
            }

            .reserve-btn:hover:not(:disabled) {
                background: #218838;
                transform: translateY(-2px);
            }

            .reserve-btn:disabled {
                background: #6c757d;
                cursor: not-allowed;
            }

            .contribution-btn {
                background: #f5c0d3;
                color: white;
                border: none;
                padding: 10px;
                border-radius: 8px;
                cursor: pointer;
                font-weight: 600;
                font-size: 0.8rem;
                transition: all 0.3s ease;
                text-align: center;
            }

            .contribution-btn:hover:not(:disabled) {
                background: #e8a8c5;
                transform: translateY(-2px);
            }

            .contribution-btn:disabled {
                background: #6c757d;
                cursor: not-allowed;
            }

            /* Modal Styles */
            .modal {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                z-index: 10000;
                align-items: center;
                justify-content: center;
            }

            .modal-content {
                background: white;
                padding: 30px;
                border-radius: 15px;
                width: 90%;
                max-width: 500px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                max-height: 80vh;
                overflow-y: auto;
            }

            .modal-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
            }

            .close-modal {
                cursor: pointer;
                font-size: 1.5rem;
                color: #888;
            }

            .participant-inputs {
                max-height: 200px;
                overflow-y: auto;
                margin-bottom: 15px;
                border: 1px solid #f0f0f0;
                border-radius: 8px;
                padding: 10px;
            }

            .participant-input {
                margin-bottom: 8px;
            }

            .participant-input:last-child {
                margin-bottom: 0;
            }

            /* Success/Error Messages */
            .alert-success {
                background: #d4edda;
                color: #155724;
                padding: 12px 15px;
                border-radius: 8px;
                margin-bottom: 20px;
                border: 1px solid #c3e6cb;
                width: 100%;
            }

            .alert-error {
                background: #f8d7da;
                color: #721c24;
                padding: 12px 15px;
                border-radius: 8px;
                margin-bottom: 20px;
                border: 1px solid #f5c6cb;
                width: 100%;
            }

            /* Responsive fixes */
            @media (max-width: 1200px) {
                .profile-post {
                    width: calc(33.333% - 17px); /* 3 items per row */
                }
            }

            @media (max-width: 900px) {
                .profile-post {
                    width: calc(50% - 13px); /* 2 items per row */
                }
            }

            @media (max-width: 600px) {
                .profile-post {
                    width: 100%; /* 1 item per row */
                    min-width: auto;
                }

                .posts-grid {
                    gap: 20px;
                }
            }

            @media (max-width: 480px) {
                .posts-grid {
                    gap: 15px;
                }

                .profile-post {
                    height: 350px;
                }
            }

            /* Ensure no overlapping */
            .profile-post {
                box-sizing: border-box;
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

            .reserve-btn {
                background: #650000;
                color: #FFFCFC;
            }

            .reserve-btn:hover:not(:disabled) {
                background: #430000;
                transform: translateY(-2px);
            }

            .reserved-badge {
                background: #B77E7E;
                color: #FFFCFC;
            }

            .group-gift-badge {
                background: #650000;
                color: #FFFCFC;
            }

            .calendar-link-btn {
                background: #650000;
                color: #FFFCFC;
            }

            .calendar-link-btn:hover {
                background: #430000;
                transform: translateY(-2px);
            }

            .contribution-btn {
                background: #B77E7E;
                color: #FFFCFC;
            }

            .contribution-btn:hover:not(:disabled) {
                background: #650000;
                transform: translateY(-2px);
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

            .tab.active {
                color: #B77E7E;
                border-bottom: 3px solid #B77E7E;
            }

            .tab:hover {
                color: #B77E7E;
                background-color: #fdf2f8;
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
                    <a href="friend_profile.php?username=<?php echo urlencode($friend_username); ?>" class="tab" data-tab="posts">
                        <i class="fas fa-gift"></i> Posts
                    </a>
                    <?php if ($wishlist && $wishlist['visibility'] == 1): ?>
                        <a href="friend-wishlist.php?username=<?php echo urlencode($friend_username); ?>" class="tab active" data-tab="wishlist">
                            <i class="fas fa-heart"></i> Wishlist
                        </a>
                    <?php else: ?>
                        <span class="wishlist-private-tab" title="<?php echo $wishlist ? 'Wishlist is private' : 'No wishlist'; ?>">
                            <i class="fas fa-heart"></i> Wishlist 
                            <?php if ($wishlist && $wishlist['visibility'] == 0): ?>
                                <i class="fas fa-lock" style="margin-left: 5px; font-size: 0.8em;"></i>
                            <?php endif; ?>
                        </span>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Wishlist Content -->
            <section class="profile-posts" id="wishlist-tab">
                <!-- Display Success/Error Messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; ?>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert-error">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; ?>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <div class="posts-grid">
                    <?php if (!$wishlist): ?>
                        <div class="no-wishlist-message">
                            <i class="fas fa-exclamation-circle"></i>
                            <h3>No wishlist found</h3>
                            <p><?php echo htmlspecialchars($friend['username']); ?> hasn't created a wishlist yet.</p>
                        </div>
                    <?php elseif (!$can_view_wishlist): ?>
                        <div class="private-wishlist-message">
                            <i class="fas fa-lock"></i>
                            <h3>This wishlist is private</h3>
                            <p><?php echo htmlspecialchars($friend['username']); ?> has set their wishlist to private.</p>
                            <p>Only they can see their wishlist items.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($wishlist_items as $item): ?>
                            <!-- Wishlist Item -->
                            <div class="profile-post">
                                <img src="<?php echo htmlspecialchars($item['image_url']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>">
                                <div class="post-overlay">
                                    <div class="post-actions">
                                        <!-- Reserve Button -->
                                        <?php if (!$item['is_reserved'] && $item['group_gift_count'] == 0): ?>
                                            <form method="POST" style="width: 100%;">
                                                <input type="hidden" name="reserve_item" value="1">
                                                <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                                <button type="submit" class="action-btn reserve-btn" 
                                                        onclick="return confirm('Are you sure you want to reserve this item? This will let <?php echo htmlspecialchars($friend['username']); ?> know you\\'re getting it for them.')">
                                                    <i class="fas fa-lock" style="margin-right: 5px;"></i>
                                                    <span>Reserve Gift</span>
                                                </button>
                                            </form>
                                        <?php elseif ($item['is_reserved']): ?>
                                            <div class="action-btn" style="background: #6c757d; color: white; cursor: not-allowed;">
                                                <i class="fas fa-lock" style="margin-right: 5px;"></i>
                                                <span>Already Reserved</span>
                                            </div>
                                        <?php else: ?>
                                            <div class="action-btn" style="background: #6c757d; color: white; cursor: not-allowed;">
                                                <i class="fas fa-lock" style="margin-right: 5px;"></i>
                                                <span>Cannot Reserve - Group Gift Active</span>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Contribution Button -->
                                        <?php if (!$item['is_reserved'] && $item['group_gift_count'] == 0): ?>
                                            <button type="button" class="action-btn contribution-btn" 
                                                    onclick="openContributionModal(<?php echo $item['item_id']; ?>, <?php echo $item['price'] ?: 0; ?>)">
                                                <i class="fas fa-users" style="margin-right: 5px;"></i>
                                                <span>Start Group Gift</span>
                                            </button>
                                        <?php elseif ($item['group_gift_count'] > 0): ?>
                                            <div class="action-btn" style="background: #6c757d; color: white; cursor: not-allowed;">
                                                <i class="fas fa-users" style="margin-right: 5px;"></i>
                                                <span>Group Gift Already Started</span>
                                            </div>
                                        <?php else: ?>
                                            <div class="action-btn" style="background: #6c757d; color: white; cursor: not-allowed;">
                                                <i class="fas fa-users" style="margin-right: 5px;"></i>
                                                <span>Cannot Start Gift - Item Reserved</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="wishlist-item-info">
                                    <h3><?php echo htmlspecialchars($item['title']); ?></h3>
                                    <?php if (!empty($item['price'])): ?>
                                        <p style="font-weight: 600; color: #ff6f61;"><?php echo number_format($item['price'], 2); ?> SAR</p>
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
                                        <div class="reserved-badge">
                                            <i class="fas fa-check"></i> Reserved by <?php echo htmlspecialchars($item['reserved_by']); ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($item['group_gift_count'] > 0): ?>
                                        <div class="group-gift-badge">
                                            <i class="fas fa-users"></i> Group Gift Active
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php if (count($wishlist_items) === 0): ?>
                            <div class="no-wishlist-message">
                                <i class="fas fa-heart" style="font-size: 3rem; color: #430000 ; margin-bottom: 15px;"></i>
                                <p>No items in this wishlist yet.</p>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </section>
        </main>

        <!-- Contribution Modal -->
        <div id="contributionModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 style="color: #4a202a; margin: 0;">Start Group Contribution</h3>
                    <span class="close-modal" onclick="closeContributionModal()">&times;</span>
                </div>

                <form method="POST" id="groupGiftForm">
                    <div class="modal-body">
                        <input type="hidden" name="create_group_gift" value="1">
                        <input type="hidden" id="modalItemId" name="item_id">

                        <div class="form-group" style="margin-bottom: 20px;">
                            <label style="display: block; margin-bottom: 8px; color: #B77E7E; font-weight: 600;">Number of Participants (Including You)</label>
                            <input type="number" id="participantCount" name="participant_count" min="2" max="20" value="2" 
                                   style="width: 100%; padding: 10px; border: 2px solid #f0f0f0; border-radius: 8px; font-size: 1rem;"
                                   onchange="updateParticipantInputs()">
                        </div>

                        <div class="form-group" style="margin-bottom: 20px;">
                            <label style="display: block; margin-bottom: 8px; color: #B77E7E; font-weight: 600;">Add Friends (Enter usernames with or without @)</label>
                            <div class="participant-inputs" id="participantInputsContainer">
                                <!-- Participant inputs will be generated here -->
                            </div>
                        </div>

                        <div class="cost-summary" style="background: #f8f9fa; padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                            <h4 style="margin: 0 0 10px 0; color: #4a202a;">Cost Summary</h4>
                            <div style="display: flex; justify-content: space-between;">
                                <span>Item Price:</span>
                                <span id="modalItemPrice" style="font-weight: 600;">0 SAR</span>
                            </div>
                            <div style="display: flex; justify-content: space-between; margin-top: 5px;">
                                <span>Share per person:</span>
                                <span id="modalSharePrice" style="font-weight: 600; color: #650000;">0 SAR</span>
                            </div>
                        </div>
                    </div>

                    <div class="modal-actions" style="display: flex; gap: 10px;">
                        <button type="button" onclick="closeContributionModal()" 
                                style="flex: 1; padding: 12px; border: 2px solid #B77E7E; background: white; color: #B77E7E; border-radius: 8px; font-weight: 600; cursor: pointer;">
                            Cancel
                        </button>
                        <button type="submit" 
                                style="flex: 1; padding: 12px; border: none; background: #B77E7E; color: white; border-radius: 8px; font-weight: 600; cursor: pointer;">
                            <i class="fas fa-users" style="margin-right: 5px;"></i> Create Group
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Footer -->
        <footer class="footer" style="background: #FFFCFC !important; color: #666 !important;">
            <p style="color: #666 !important; background: transparent !important; margin: 0;">© 2025 WOD — All Rights Reserved.</p>
        </footer>

        <script>
            let currentItemPrice = 0;

            function openContributionModal(itemId, itemPrice) {
                currentItemPrice = itemPrice;
                document.getElementById('modalItemId').value = itemId;
                document.getElementById('modalItemPrice').textContent = itemPrice.toFixed(2) + ' SAR';
                updateShareCalculation();
                updateParticipantInputs();
                document.getElementById('contributionModal').style.display = 'flex';
            }

            function closeContributionModal() {
                document.getElementById('contributionModal').style.display = 'none';
            }

            function updateShareCalculation() {
                const participantCount = parseInt(document.getElementById('participantCount').value);
                const sharePrice = currentItemPrice / participantCount;
                document.getElementById('modalSharePrice').textContent = sharePrice.toFixed(2) + ' SAR';
            }

            function updateParticipantInputs() {
                const participantCount = parseInt(document.getElementById('participantCount').value);
                const container = document.getElementById('participantInputsContainer');
                container.innerHTML = '';

                for (let i = 1; i <= participantCount - 1; i++) {
                    const inputDiv = document.createElement('div');
                    inputDiv.className = 'participant-input';
                    inputDiv.innerHTML = `
                        <input type="text" name="username_${i}" 
                               placeholder="username${i} (with or without @)"
                               style="width: 100%; padding: 10px; border: 2px solid #f0f0f0; border-radius: 8px; font-size: 1rem;">
                    `;
                    container.appendChild(inputDiv);
                }

                updateShareCalculation();
            }

            // Close modal when clicking outside
            document.getElementById('contributionModal').addEventListener('click', function (e) {
                if (e.target === this) {
                    closeContributionModal();
                }
            });

            // Form validation
            document.getElementById('groupGiftForm').addEventListener('submit', function (e) {
                const participantCount = parseInt(document.getElementById('participantCount').value);
                if (participantCount < 2) {
                    e.preventDefault();
                    alert('Group gift must have at least 2 participants including yourself');
                    return false;
                }

                // Check if all required participant fields are filled
                const requiredParticipants = participantCount - 1;
                let filledParticipants = 0;

                for (let i = 1; i <= requiredParticipants; i++) {
                    const input = document.querySelector(`input[name="username_${i}"]`);
                    if (input && input.value.trim() !== '') {
                        filledParticipants++;
                    }
                }

                if (filledParticipants < requiredParticipants) {
                    e.preventDefault();
                    alert('Please provide usernames for all participants');
                    return false;
                }
            });

            // Initialize participant inputs on page load
            document.addEventListener('DOMContentLoaded', function () {
                updateParticipantInputs();
            });
        </script>
    </body>
</html>