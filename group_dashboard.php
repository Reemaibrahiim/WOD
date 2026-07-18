<?php
require_once 'config.php';
requireLogin();

$username = getCurrentUser();

// Get user's active contributions (where user is a participant)
$stmt = $pdo->prepare("
    SELECT gg.*, wi.title, wi.image_url, wi.price, 
           u.name as target_name, u.username as target_username,
           (SELECT COUNT(*) FROM contributions cnt1 WHERE cnt1.group_gift_id = gg.group_gift_id AND cnt1.is_paid = 1) as paid_count,
           gg.group_size as total_count,
           c.amount as user_share,
           c.is_paid as user_paid
    FROM group_gifts gg
    JOIN wishlist_items wi ON gg.item_id = wi.item_id
    JOIN users u ON wi.wishlist_id = (SELECT wishlist_id FROM wishlists WHERE username = u.username)
    JOIN contributions c ON gg.group_gift_id = c.group_gift_id
    WHERE c.username = ?
    ORDER BY gg.created_at DESC
");
$stmt->execute([$username]);
$contributions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle mark as paid
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_paid'])) {
    $group_gift_id = $_POST['group_gift_id'];

    $stmt = $pdo->prepare("UPDATE contributions SET is_paid = 1 WHERE group_gift_id = ? AND username = ?");
    $stmt->execute([$group_gift_id, $username]);

    // Update collected amount
    $stmt = $pdo->prepare("
        UPDATE group_gifts gg
        JOIN contributions c ON gg.group_gift_id = c.group_gift_id
        SET gg.collected_amount = gg.collected_amount + c.amount
        WHERE gg.group_gift_id = ? AND c.username = ? AND c.is_paid = 1
    ");
    $stmt->execute([$group_gift_id, $username]);

    header("Location: group_dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Group Dashboard - WOD</title>
        <link rel="stylesheet" href="style.css"> 
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <style>
            .page-container {
                max-width: 1200px;
                margin: 40px auto;
                padding: 40px;
                background: rgba(255, 252, 252, 0.95);
                backdrop-filter: blur(10px);
                -webkit-backdrop-filter: blur(10px);
                border-radius: 25px;
                box-shadow: 0 12px 40px rgba(101, 0, 0, 0.08), 0 0 0 1px rgba(183, 126, 126, 0.1);
                margin-right: calc(auto + 80px);
                border: 1px solid rgba(183, 126, 126, 0.1);
            }

            .dashboard-header {
                margin-bottom: 40px;
                border-bottom: 2px solid rgba(183, 126, 126, 0.15);
                padding-bottom: 25px;
            }

            .dashboard-header h1 {
                color: #430000;
                font-size: 2.5rem;
                font-weight: 700;
                letter-spacing: -0.02em;
                margin-bottom: 8px;
            }

            .dashboard-header p {
                color: #666;
                font-size: 1.1rem;
                margin: 0;
            }

            .dashboard-section {
                margin-bottom: 40px;
                padding: 30px;
                border-radius: 20px;
                background: rgba(255, 252, 252, 0.5);
            }

            .dashboard-section h2 {
                color: #430000;
                font-size: 1.8rem;
                font-weight: 600;
                margin-bottom: 25px;
                padding-bottom: 15px;
                border-bottom: 2px solid rgba(183, 126, 126, 0.15);
                letter-spacing: -0.01em;
                display: flex;
                align-items: center;
                gap: 12px;
            }

            .group-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
                gap: 30px;
            }

            .group-card {
                background: rgba(255, 252, 252, 0.95);
                backdrop-filter: blur(10px);
                -webkit-backdrop-filter: blur(10px);
                border-radius: 20px;
                padding: 0;
                box-shadow: 0 4px 20px rgba(101, 0, 0, 0.08);
                border: 1px solid rgba(183, 126, 126, 0.1);
                transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
                overflow: hidden;
            }

            .group-card:hover {
                transform: translateY(-8px);
                box-shadow: 0 12px 40px rgba(101, 0, 0, 0.15);
                border-color: rgba(183, 126, 126, 0.2);
            }

            .card-content {
                padding: 25px;
            }

            .card-title {
                font-size: 1.3rem;
                color: #430000;
                font-weight: 700;
                margin: 0 0 8px 0;
                line-height: 1.3;
                letter-spacing: -0.01em;
            }

            .card-username {
                color: #B77E7E;
                font-weight: 600;
                margin: 0 0 18px 0;
                font-size: 0.95rem;
            }

            .card-image {
                width: 100%;
                height: 220px;
                object-fit: cover;
                border-radius: 15px;
                margin-bottom: 20px;
                box-shadow: 0 4px 15px rgba(101, 0, 0, 0.1);
                transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            }

            .group-card:hover .card-image {
                transform: scale(1.03);
            }

            .cost-info {
                display: flex;
                justify-content: space-between;
                margin-bottom: 20px;
                padding: 18px;
                background: rgba(255, 252, 252, 0.8);
                backdrop-filter: blur(10px);
                -webkit-backdrop-filter: blur(10px);
                border-radius: 15px;
                border: 1px solid rgba(183, 126, 126, 0.1);
                box-shadow: 0 2px 10px rgba(101, 0, 0, 0.05);
            }

            .cost-item {
                text-align: center;
                flex: 1;
            }

            .cost-label {
                font-size: 0.85rem;
                color: #666;
                margin-bottom: 8px;
                font-weight: 500;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .cost-value {
                font-size: 1.2rem;
                font-weight: 700;
                color: #650000;
                letter-spacing: -0.01em;
            }

            .progress-section {
                margin: 20px 0;
            }

            .progress-info {
                display: flex;
                justify-content: space-between;
                margin-bottom: 12px;
                font-size: 0.9rem;
                color: #430000;
                font-weight: 600;
            }

            .progress-bar-container {
                height: 12px;
                background: rgba(183, 126, 126, 0.1);
                border-radius: 10px;
                overflow: hidden;
                box-shadow: inset 0 2px 4px rgba(101, 0, 0, 0.05);
            }

            .progress-bar {
                height: 100%;
                background: linear-gradient(90deg, #B77E7E 0%, #A66D6D 50%, #650000 100%);
                border-radius: 10px;
                transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
                box-shadow: 0 2px 8px rgba(183, 126, 126, 0.3);
            }

            .card-actions {
                display: flex;
                gap: 12px;
                margin-top: 20px;
            }

            .action-btn {
                flex: 1;
                padding: 14px 20px;
                border: none;
                border-radius: 12px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                font-size: 0.95rem;
                text-align: center;
                letter-spacing: 0.3px;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
            }

            .pay-btn {
                background: linear-gradient(135deg, #B77E7E 0%, #A66D6D 100%);
                color: #FFFCFC;
                box-shadow: 0 4px 15px rgba(183, 126, 126, 0.3);
            }

            .pay-btn:hover {
                background: linear-gradient(135deg, #A66D6D 0%, #650000 100%);
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(183, 126, 126, 0.4);
            }

            .pay-btn:active {
                transform: translateY(0);
            }

            .pay-btn:disabled {
                background: linear-gradient(135deg, #9e9e9e 0%, #757575 100%);
                cursor: not-allowed;
                opacity: 0.6;
                transform: none;
                box-shadow: none;
            }

            .contributions-section {
                background: rgba(255, 252, 252, 0.3);
            }

            .no-items {
                text-align: center;
                padding: 60px 40px;
                color: #666;
                grid-column: 1 / -1;
                background: rgba(255, 252, 252, 0.5);
                border-radius: 20px;
                border: 2px dashed rgba(183, 126, 126, 0.2);
            }

            .no-items i {
                font-size: 4rem;
                color: #B77E7E;
                margin-bottom: 20px;
                opacity: 0.6;
            }

            .no-items p {
                font-size: 1.1rem;
                margin: 10px 0;
                color: #430000;
            }

            .create-group-btn {
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
                margin-top: 20px;
            }

            .create-group-btn:hover {
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

        <main class="page-container">
            <header class="dashboard-header">
                <h1>Group Gift Dashboard</h1>
                <p style="color: #666;">Manage your group contributions.</p>

            </header>

            <!-- Active Contributions Section -->
            <section class="dashboard-section contributions-section">
                <h2><i class="fas fa-hand-holding-usd" style="color: #B77E7E ; margin-left: 10px;"></i> My Group Contributions</h2>
                <div class="group-grid">
                    <?php if (count($contributions) > 0): ?>
                        <?php foreach ($contributions as $contribution): ?>
                            <?php
                            $progress = $contribution['price'] > 0 ? ($contribution['collected_amount'] / $contribution['price']) * 100 : 0;
                            $progress = min($progress, 100);
                            ?>
                            <div class="group-card">
                                <div class="card-content">
                                    <h3 class="card-title"><?php echo htmlspecialchars($contribution['title']); ?></h3>
                                    <p class="card-username">for @<?php echo htmlspecialchars($contribution['target_username']); ?></p>
                                    <img src="<?php echo htmlspecialchars($contribution['image_url']); ?>" alt="<?php echo htmlspecialchars($contribution['title']); ?>" class="card-image">

                                    <div class="cost-info">
                                        <div class="cost-item">
                                            <div class="cost-label">Total Cost</div>
                                            <div class="cost-value"><?php echo number_format($contribution['price'], 2); ?> SAR</div>
                                        </div>
                                        <div class="cost-item">
                                            <div class="cost-label">Your Share</div>
                                            <div class="cost-value"><?php echo number_format($contribution['user_share'], 2); ?> SAR</div>
                                        </div>
                                    </div>

                                    <div class="progress-section">
                                        <div class="progress-info">
                                            <span>Progress</span>
                                            <span><?php echo number_format($progress, 0); ?>%</span>
                                        </div>
                                        <div class="progress-bar-container">
                                            <div class="progress-bar" style="width: <?php echo $progress; ?>%;"></div>
                                        </div>
                                        <div style="text-align: center; font-size: 0.8rem; color: #666; margin-top: 5px;">
                                            <?php echo $contribution['paid_count']; ?> of <?php echo $contribution['total_count']; ?> paid
                                        </div>
                                    </div>

                                    <div class="card-actions">
                                        <?php if (!$contribution['user_paid']): ?>
                                            <form method="POST" style="flex: 1;">
                                                <input type="hidden" name="mark_paid" value="1">
                                                <input type="hidden" name="group_gift_id" value="<?php echo $contribution['group_gift_id']; ?>">
                                                <button type="submit" class="action-btn pay-btn">
                                                    <i class="fas fa-check-circle"></i> Mark as Paid
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <button class="action-btn pay-btn" disabled>
                                                <i class="fas fa-check"></i> Paid
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-items">
                            <i class="fas fa-hand-holding-usd" style="color: #B77E7E ;" ></i>
                            <p>No active contributions yet.</p>
                            <p>Start a group gift from a friend's wishlist to get started!</p>
                            <a href="posts.php" class="create-group-btn" style="margin-top: 15px;">
                                <i class="fas fa-search" style="color: #430000 ;" ></i> Browse Friends' Posts
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </main>

        <footer class="footer" style="background: #FFFCFC !important; color: #666 !important;">
            <p style="color: #666 !important; background: transparent !important; margin: 0;">© 2025 WOD — All Rights Reserved.</p>
        </footer>
    </body>
</html>