<?php
require_once 'config.php';
requireLogin();

// Initialize posts array to prevent undefined variable error
$posts = [];

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
        header("Location: posts.php");
        exit();
    } elseif ($_POST['action'] === 'add_to_wishlist' && isset($_POST['post_id'])) {
        // Add post to user's wishlist
        $post_id = (int) $_POST['post_id'];
        $username = getCurrentUser();

        // Get post details
        $stmt = $pdo->prepare("SELECT * FROM posts WHERE post_id = ?");
        $stmt->execute([$post_id]);
        $post = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($post) {
            // Get user's wishlist (or create if not exists)
            $stmt = $pdo->prepare("SELECT wishlist_id FROM wishlists WHERE username = ?");
            $stmt->execute([$username]);
            $wishlist = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$wishlist) {
                // Create wishlist for this user if missing
                $stmt = $pdo->prepare("INSERT INTO wishlists (username, visibility) VALUES (?, 0)");
                $stmt->execute([$username]);
                $wishlist_id = $pdo->lastInsertId();
            } else {
                $wishlist_id = $wishlist['wishlist_id'];
            }

            // Check if item already exists in wishlist by post_id
            $stmt = $pdo->prepare("SELECT * FROM wishlist_items WHERE post_id = ? AND wishlist_id = ?");
            $stmt->execute([$post_id, $wishlist_id]);

            if ($stmt->rowCount() === 0) {
                // Add to wishlist - FIXED VERSION
                $stmt = $pdo->prepare("
                    INSERT INTO wishlist_items 
                    (title, external_link, image_url, price, wishlist_id, post_id) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $post['title'],
                    $post['external_link'] ?? '', // Use empty string if null
                    $post['image_url'],
                    $post['price'],
                    $wishlist_id,
                    $post_id
                ]);

                // Success message (optional)
                $_SESSION['success'] = "Item added to wishlist!";
            } else {
                // Item already in wishlist
                $_SESSION['error'] = "Item is already in your wishlist!";
            }
        }

        header("Location: " . ($_SERVER['HTTP_REFERER'] ?? 'posts.php'));
        exit();
    }
}

// Fetch posts from database with error handling
try {
    $stmt = $pdo->prepare("
        SELECT p.*, u.name, u.photo_url, 
               COUNT(l.like_id) as like_count,
               EXISTS(SELECT 1 FROM likes WHERE username = ? AND post_id = p.post_id) as user_liked
        FROM posts p 
        LEFT JOIN users u ON p.username = u.username 
        LEFT JOIN likes l ON p.post_id = l.post_id 
        GROUP BY p.post_id 
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([getCurrentUser()]);
    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Log error and set empty posts array
    error_log("Database error in posts.php: " . $e->getMessage());
    $posts = [];
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Posts</title>
        <link rel="stylesheet" href="style.css" />
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
        <style>
            /* Message alerts */
            .alert {
                padding: 12px 20px;
                margin: 10px 20px;
                border-radius: 8px;
                font-weight: 500;
            }

            .alert-success {
                background-color: #d4edda;
                color: #155724;
                border: 1px solid #c3e6cb;
            }

            .alert-error {
                background-color: #f8d7da;
                color: #721c24;
                border: 1px solid #f5c6cb;
            }

            /* Enhanced Posts Header Section */
            .posts-header-section {
                background: linear-gradient(135deg, rgba(255, 252, 252, 0.95) 0%, rgba(255, 249, 249, 0.98) 100%);
                backdrop-filter: blur(10px);
                -webkit-backdrop-filter: blur(10px);
                padding: 40px 60px 30px 60px;
                margin-bottom: 20px;
                border-bottom: 1px solid rgba(183, 126, 126, 0.1);
                box-shadow: 0 4px 20px rgba(101, 0, 0, 0.05);
            }

            .posts-header-content {
                display: flex;
                justify-content: flex-start;
                align-items: center;
                margin-bottom: 30px;
            }

            .posts-header-text {
                width: 100%;
            }

            .posts-header-title {
                font-size: 32px;
                font-weight: 700;
                color: #430000;
                margin: 0 0 8px 0;
                letter-spacing: -0.02em;
                display: flex;
                align-items: center;
            }

            .posts-header-subtitle {
                font-size: 16px;
                color: #666;
                margin: 0;
                font-weight: 400;
                letter-spacing: 0.01em;
            }


            .posts-search-wrapper {
                width: 100%;
                max-width: 700px;
                margin: 0 auto;
            }

            .search-section {
                display: flex;
                justify-content: center;
                align-items: center;
                width: 100%;
                padding: 20px 0;
                position: relative;
            }

            .search-container {
                position: relative;
                width: 100%;
                max-width: 700px;
                margin: 0 auto;
            }

            .search-bar {
                width: 100%;
                padding: 14px 50px 14px 22px;
                border: 2px solid rgba(183, 126, 126, 0.2);
                border-radius: 30px;
                font-size: 16px;
                font-weight: 400;
                background: rgba(255, 252, 252, 0.8);
                backdrop-filter: blur(10px);
                -webkit-backdrop-filter: blur(10px);
                box-shadow: 0 2px 10px rgba(101, 0, 0, 0.05);
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                color: #430000;
            }

            .search-bar:focus {
                outline: none;
                background: rgba(255, 252, 252, 0.95);
                border-color: rgba(183, 126, 126, 0.3);
                box-shadow: 0 4px 20px rgba(183, 126, 126, 0.15), 0 0 0 4px rgba(183, 126, 126, 0.1);
                transform: translateY(-1px);
            }

            .search-icon {
                position: absolute;
                right: 20px;
                top: 50%;
                transform: translateY(-50%);
                color: #666;
                font-size: 18px;
                background: none;
                border: none;
                cursor: pointer;
                transition: all 0.3s ease;
                padding: 8px;
                border-radius: 50%;
            }

            .search-icon:hover {
                color: #B77E7E;
                background: rgba(183, 126, 126, 0.1);
            }

            @media (max-width: 768px) {
                .posts-header-section {
                    padding: 30px 20px 20px 20px;
                }

                .posts-header-title {
                    font-size: 26px;
                }
            }

            /* Pinterest-style Post Container */
            .post-container {
                column-count: 4;
                column-gap: 15px;
                padding: 30px 60px;
                background: #fff;
                margin: 0 auto;
            }

            .post {
                break-inside: avoid;
                margin-bottom: 20px;
                border-radius: 16px;
                overflow: hidden;
                background: white;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
                transition: all 0.3s ease;
                position: relative;
            }

            .post:hover {
                transform: translateY(-5px);
                box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
            }

            .post img {
                width: 100%;
                height: auto;
                display: block;
                border-radius: 16px 16px 0 0;
                transition: transform 0.3s ease;
            }

            .post:hover img {
                transform: scale(1.03);
            }

            /* Post Stats - Always Visible */
            .post-stats {
                padding: 12px 15px;
                background: white;
                display: flex;
                justify-content: space-between;
                align-items: center;
                border-top: 1px solid #f0f0f0;
            }

            .like-count {
                color: #666;
                font-size: 0.85rem;
                display: flex;
                align-items: center;
                gap: 5px;
            }

            .like-count .fas.fa-heart {
                color: #ff6f61;
            }

            .post-price {
                font-weight: 600;
                color: #ff6f61;
                font-size: 14px;
            }

            /* Post Overlay - Shows on Hover */
            .post-overlay {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.85);
                display: flex;
                flex-direction: column;
                padding: 20px;
                opacity: 0;
                transition: opacity 0.3s ease;
                border-radius: 16px;
            }

            .post:hover .post-overlay {
                opacity: 1;
            }

            /* Post Header in Overlay */
            .post-header {
                display: flex;
                align-items: center;
                margin-bottom: 15px;
            }

            .user-avatar {
                width: 40px;
                height: 40px;
                border-radius: 50%;
                margin-right: 12px;
                object-fit: cover;
                border: 2px solid #f5c0d3;
                flex-shrink: 0;
            }

            .user-info {
                flex: 1;
                min-width: 0;
            }

            .username {
                font-weight: 600;
                color: white;
                margin: 0;
                font-size: 14px;
                line-height: 1.2;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .post-time {
                font-size: 0.7rem;
                color: #ccc;
                margin: 2px 0 0 0;
                font-weight: 400;
            }

            /* Post Content in Overlay */
            .post-content-overlay {
                flex: 1;
                display: flex;
                flex-direction: column;
                justify-content: center;
            }

            .post-title {
                font-weight: 600;
                color: white;
                margin: 0 0 10px 0;
                font-size: 16px;
                line-height: 1.3;
            }

            .post-description {
                color: #ddd;
                margin: 0 0 12px 0;
                line-height: 1.4;
                font-size: 13px;
                display: -webkit-box;
                -webkit-line-clamp: 3;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }

            .post-link {
                color: #f5c0d3;
                text-decoration: none;
                font-size: 0.8rem;
                display: inline-flex;
                align-items: center;
                gap: 5px;
                padding: 6px 12px;
                background: rgba(245, 192, 211, 0.2);
                border-radius: 15px;
                transition: all 0.3s ease;
                margin-bottom: 8px;
                border: 1px solid rgba(245, 192, 211, 0.3);
            }

            .post-link:hover {
                background: #f5c0d3;
                color: white;
                text-decoration: none;
            }

            /* Overlay Buttons */
            .overlay-buttons {
                display: flex;
                gap: 12px;
                margin-top: 15px;
            }

            .icon-btn {
                width: 44px;
                height: 44px;
                border: none;
                border-radius: 50%;
                background-color: rgba(255, 255, 255, 0.95);
                color: #4a202a;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                font-size: 1.1rem;
                transition: all 0.3s ease;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            }

            .icon-btn:hover {
                background-color: #f5c0d3;
                color: white;
                transform: scale(1.1);
            }

            .liked .fa-heart {
                color: #ff6f61 !important;
            }

            /* Create Post Button */
            .create-post-btn {
                position: fixed;
                bottom: 30px;
                left: 30px;
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

            .create-post-btn:hover {
                background: #e8a8c5;
                transform: scale(1.1);
            }

            /* Responsive Design */
            @media (max-width: 1200px) {
                .post-container {
                    column-count: 3;
                    padding: 20px 40px;
                }
            }

            @media (max-width: 900px) {
                .post-container {
                    column-count: 2;
                    padding: 20px;
                }
            }

            @media (max-width: 600px) {
                .post-container {
                    column-count: 1;
                    padding: 15px;
                }

                .search-bar {
                    width: 100%;
                }
            }

            /* Add to the existing style in posts.php */
            .post-title {
                font-weight: 600;
                color: white;
                margin: 0 0 10px 0;
                font-size: 16px;
                line-height: 1.3;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .post-description {
                color: #ddd;
                margin: 0 0 12px 0;
                line-height: 1.4;
                font-size: 13px;
                display: -webkit-box;
                -webkit-line-clamp: 3;
                -webkit-box-orient: vertical;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .post-content-overlay {
                flex: 1;
                display: flex;
                flex-direction: column;
                justify-content: center;
                min-height: 0; /* Important for flexbox text overflow */
            }

            /* Ensure the overlay buttons stay at bottom */
            .overlay-buttons {
                margin-top: auto;
                padding-top: 15px;
            }

            .alert-success {
                background-color: #d4edda;
                color: #155724;
                border: 1px solid #c3e6cb;
            }

            .alert-error {
                background-color: #f8d7da;
                color: #721c24;
                border: 1px solid #f5c6cb;
            }

            .post-stats {
                padding: 12px 15px;
                background: #FFFCFC;
                display: flex;
                justify-content: space-between;
                align-items: center;
                border-top: 1px solid #f0f0f0;
            }

            .like-count .fas.fa-heart {
                color: #650000;
            }

            .post-price {
                font-weight: 600;
                color: #650000;
                font-size: 14px;
            }

            .post-link {
                color: #B77E7E;
                text-decoration: none;
                font-size: 0.8rem;
                display: inline-flex;
                align-items: center;
                gap: 5px;
                padding: 6px 12px;
                background: rgba(183, 126, 126, 0.2);
                border-radius: 15px;
                transition: all 0.3s ease;
                margin-bottom: 8px;
                border: 1px solid rgba(183, 126, 126, 0.3);
            }

            .post-link:hover {
                background: #B77E7E;
                color: #FFFCFC;
                text-decoration: none;
            }

            .icon-btn:hover {
                background-color: #B77E7E;
                color: #FFFCFC;
                transform: scale(1.1);
            }

            .liked .fa-heart {
                color: #650000 !important;
            }
            
                .create-post-btn {
        position: fixed;
        bottom: 80px; 
        right: 30px;
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
        z-index: 9999;
        transition: all 0.3s ease;
    }

    .footer {
        position: relative;
        z-index: 1;
    }

    /* No Posts Message Styling */
    .no-posts-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        padding: 60px 20px;
        color: #650000;
        width: 100%;
        column-span: all;
    }

    .no-posts-icon {
        font-size: 3rem;
        color: #B77E7E;
        margin-bottom: 20px;
    }

    .no-posts-message {
        font-size: 1.2rem;
        margin-bottom: 25px;
        line-height: 1.5;
    }

    .no-posts-btn {
        text-decoration: none;
        margin-top: 15px;
        display: inline-block;
        padding: 12px 24px;
        background-color: #B77E7E;
        color: white;
        border-radius: 8px;
        transition: all 0.3s ease;
        font-weight: 500;
    }

    .no-posts-btn:hover {
        background-color: #650000;
        transform: translateY(-2px);
    }
            
        </style>
    </head>
    <body>
        <div class="page-wrapper">
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

            <!-- Create Post Button -->
            <a href="create_post.php" class="create-post-btn">
                <i class="fas fa-plus" style="font-size: 1.5rem;"></i>
            </a>

            <!-- Main Content -->
            <main>
                <!-- Enhanced Header Section -->
                <section class="posts-header-section">
                    <div class="posts-header-content">
                        <div class="posts-header-text">
                            <h1 class="posts-header-title">
                                <i class="fas fa-gift" style="margin-right: 12px; color: #B77E7E;"></i>
                                Discover Gifts
                            </h1>
                            <p class="posts-header-subtitle">Explore amazing gift ideas from your friends and community</p>
                        </div>
                    </div>
                    
                    <!-- Search Bar Section -->
                    <div class="posts-search-wrapper">
                        <form action="search_friends.php" method="GET" style="width: 100%; display: block;">
                            <div class="search-container">
                                <input
                                    type="text"
                                    name="search"
                                    class="search-bar"
                                    placeholder="Search friends by username..."
                                    value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>"
                                    />
                                <button type="submit" class="search-icon">
                                    <i class="fa-solid fa-magnifying-glass"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </section>

                <!-- Posts Grid -->
                <section class="post-container">
                    <?php if (!empty($posts)): ?>
                        <?php foreach ($posts as $post): ?>
                            <div class="post">
                                <img
                                    src="<?php echo htmlspecialchars($post['image_url']); ?>"
                                    alt="<?php echo htmlspecialchars($post['title']); ?>"
                                    >

                                <!-- Always Visible Stats -->
                                <div class="post-stats">
                                    <span class="like-count">
                                        <i class="fas fa-heart"></i>
                                        <?php echo $post['like_count']; ?> likes
                                    </span>
                                    <?php if (!empty($post['price'])): ?>
                                        <span class="post-price">
                                            <?php echo number_format($post['price'], 2); ?> SAR
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <!-- Overlay Content - Shows on Hover -->
                                <div class="post-overlay">
                                    <!-- User Info -->
                                    <div class="post-header">
                                        <div class="user-info">
                                            <a
                                                href="<?php echo 'friend_profile.php?username=' . urlencode($post['username']); ?>"
                                                class="username"
                                                style="color: white; text-decoration: none;"
                                                >
                                                @<?php echo htmlspecialchars($post['username']); ?>
                                            </a>
                                            <p class="post-time">
                                                <?php echo date('M j, Y', strtotime($post['created_at'])); ?>
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Post Content -->
                                    <div class="post-content-overlay">
                                        <h4 class="post-title">
                                            <?php echo htmlspecialchars($post['title']); ?>
                                        </h4>

                                        <?php if (!empty($post['description'])): ?>
                                            <p class="post-description">
                                                <?php echo htmlspecialchars($post['description']); ?>
                                            </p>
                                        <?php endif; ?>

                                        <?php if (!empty($post['external_link'])): ?>
                                            <a
                                                href="<?php echo htmlspecialchars($post['external_link']); ?>"
                                                target="_blank"
                                                class="post-link"
                                                >
                                                <i class="fas fa-external-link-alt"></i> View Product
                                            </a>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="overlay-buttons">
                                        <!-- Like / Unlike -->
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="like">
                                            <input type="hidden" name="post_id" value="<?php echo $post['post_id']; ?>">
                                            <button
                                                type="submit"
                                                class="icon-btn fav-btn <?php echo $post['user_liked'] ? 'liked' : ''; ?>"
                                                >
                                                <i class="fa-solid fa-heart"></i>
                                            </button>
                                        </form>

                                        <!-- Add to Wishlist -->
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="add_to_wishlist">
                                            <input type="hidden" name="post_id" value="<?php echo $post['post_id']; ?>">
                                            <button type="submit" class="icon-btn upload-btn">
                                                <i class="fa-solid fa-upload"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-posts-container">
                            <i class="fas fa-gift no-posts-icon"></i>
                            <p class="no-posts-message">No posts found.<br>Be the first to create a post!</p>
                            <a href="create_post.php" class="no-posts-btn">
                                Create Post
                            </a>
                        </div>
                    <?php endif; ?>
                </section>
            </main>
        </div>

        <!-- Footer -->
        <footer class="footer" style="background: #FFFCFC !important; color: #666 !important;">
            <p style="color: #666 !important; background: transparent !important; margin: 0;">© 2025 WOD — All Rights Reserved.</p>
        </footer>
    </body>
</html>"