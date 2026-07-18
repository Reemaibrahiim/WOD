<?php
require_once 'config.php';

// Fetch some random gift item images from posts or wishlist items
try {
    // Try to get images from posts first
    $stmt = $pdo->query("
        SELECT image_url 
        FROM posts 
        WHERE image_url IS NOT NULL AND image_url != '' 
        ORDER BY RAND() 
        LIMIT 10
    ");
    $gift_images = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // If not enough from posts, get from wishlist_items
    if (count($gift_images) < 8) {
        $stmt = $pdo->query("
            SELECT image_url 
            FROM wishlist_items 
            WHERE image_url IS NOT NULL AND image_url != '' 
            ORDER BY RAND() 
            LIMIT " . (8 - count($gift_images))
        );
        $wishlist_images = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $gift_images = array_merge($gift_images, $wishlist_images);
    }
    
    // If still not enough, use placeholder/default images
    while (count($gift_images) < 8) {
        $gift_images[] = 'images/Default.png';
    }
    
    // Limit to 8 items for better visual impact
    $gift_images = array_slice($gift_images, 0, 8);
} catch (PDOException $e) {
    // Fallback to default images if database query fails
    $gift_images = array_fill(0, 8, 'images/Default.png');
}
?>
<!DOCTYPE html>
<html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Welcome to WOD - Social Gifting</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

        <style>
            /* General Reset and Font */
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: 'Poppins', sans-serif;
                margin: 0;
                padding: 0;
                background: #FFFCFC;
                display: flex;
                flex-direction: column;
                min-height: 100vh;
                color: #430000;
                position: relative;
                overflow-x: hidden;
                padding-bottom: 0;
            }

            .main-content-wrapper {
                display: flex;
                justify-content: center;
                align-items: center;
                flex: 1;
                padding: 20px 0;
            }



            /* Gift items - Background decorative elements */
            .gift-column {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                pointer-events: none;
                z-index: 0;
                display: flex;
                justify-content: space-between;
                padding: 0;
                overflow: hidden;
            }

            .gift-column.left {
                justify-content: flex-start;
                align-items: flex-start;
            }

            .gift-column.right {
                justify-content: flex-end;
                align-items: flex-start;
            }

            .gift-item {
                width: 150px;
                height: 150px;
                border-radius: 15px;
                overflow: hidden;
                box-shadow: 0 4px 15px rgba(101, 0, 0, 0.15);
                background: #FFFCFC;
                padding: 4px;
                opacity: 0.75;
                position: absolute;
            }

            .gift-column.left .gift-item:nth-child(1) {
                top: 15%;
                left: 3%;
            }

            .gift-column.left .gift-item:nth-child(2) {
                top: 35%;
                left: 2%;
            }

            .gift-column.left .gift-item:nth-child(3) {
                top: 55%;
                left: 4%;
            }

            .gift-column.left .gift-item:nth-child(4) {
                top: 75%;
                left: 3%;
            }

            .gift-column.right .gift-item:nth-child(1) {
                top: 20%;
                right: 3%;
            }

            .gift-column.right .gift-item:nth-child(2) {
                top: 40%;
                right: 2%;
            }

            .gift-column.right .gift-item:nth-child(3) {
                top: 60%;
                right: 4%;
            }

            .gift-column.right .gift-item:nth-child(4) {
                top: 80%;
                right: 3%;
            }

            .gift-item img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                border-radius: 12px;
                filter: brightness(1.05) contrast(1.1) saturate(1.1);
            }

            /* Add sparkle effect on some items */
            .gift-item:nth-child(2)::after,
            .gift-item:nth-child(4)::after {
                content: '✨';
                position: absolute;
                top: -10px;
                right: -10px;
                font-size: 1.5rem;
                animation: sparkle 2s ease-in-out infinite;
                z-index: 10;
            }

            @keyframes sparkle {
                0%, 100% { 
                    transform: scale(1) rotate(0deg);
                    opacity: 0.7;
                }
                50% { 
                    transform: scale(1.3) rotate(180deg);
                    opacity: 1;
                }
            }

            /* Decorative gift icons - Reduced animations */
            .gift-icon {
                position: absolute;
                font-size: 3rem;
                color: rgba(183, 126, 126, 0.25);
                animation: floatIcon 20s ease-in-out infinite;
                pointer-events: none;
                z-index: 1;
                filter: drop-shadow(0 4px 8px rgba(101, 0, 0, 0.1));
                will-change: transform;
            }

            .gift-icon:nth-child(1) {
                top: 12%;
                left: 8%;
                animation-delay: 1s;
                font-size: 3.5rem;
            }

            .gift-icon:nth-child(2) {
                top: 25%;
                right: 10%;
                animation-delay: 4s;
                font-size: 2.8rem;
            }

            .gift-icon:nth-child(3) {
                bottom: 28%;
                left: 10%;
                animation-delay: 7s;
                font-size: 3.2rem;
            }

            .gift-icon:nth-child(4) {
                bottom: 18%;
                right: 8%;
                animation-delay: 2.5s;
                font-size: 3.4rem;
            }

            .gift-icon:nth-child(5) {
                top: 50%;
                left: 4%;
                animation-delay: 5s;
                font-size: 2.6rem;
            }

            .gift-icon:nth-child(6) {
                top: 65%;
                right: 5%;
                animation-delay: 8s;
                font-size: 3rem;
            }

            .gift-icon:nth-child(7) {
                top: 40%;
                right: 3%;
                animation-delay: 3.5s;
                font-size: 2.9rem;
            }

            .gift-icon:nth-child(8) {
                bottom: 45%;
                left: 5%;
                animation-delay: 6s;
                font-size: 3.3rem;
            }

            @keyframes floatIcon {
                0%, 100% {
                    transform: translateY(0) translateX(0) rotate(0deg);
                    opacity: 0.2;
                }
                50% {
                    transform: translateY(-20px) translateX(15px) rotate(5deg);
                    opacity: 0.3;
                }
            }

            /* Different icon colors */
            .gift-icon i.fa-gift {
                color: rgba(183, 126, 126, 0.3);
            }

            .gift-icon i.fa-heart {
                color: rgba(183, 126, 126, 0.25);
            }

            .gift-icon i.fa-star {
                color: rgba(183, 126, 126, 0.2);
            }

            .gift-icon i.fa-birthday-cake {
                color: rgba(183, 126, 126, 0.28);
            }

            /* Main Welcome Container */
            .welcome-container {
                text-align: center;
                padding: 50px 60px;
                background: rgba(255, 252, 252, 0.95);
                backdrop-filter: blur(10px);
                border-radius: 20px;
                box-shadow: 0 20px 60px rgba(101, 0, 0, 0.12), 
                            0 0 0 1px rgba(183, 126, 126, 0.08);
                max-width: 800px;
                width: 100%;
                position: relative;
                z-index: 10;
                animation: fadeInUp 0.8s ease-out;
                margin: 0 auto;
                min-height: 600px;
            }

            /* Bottom Section */
            .bottom-section {
                width: 100%;
                padding: 80px 20px 40px 20px;
                margin-top: 60px;
                background: linear-gradient(180deg, transparent 0%, rgba(183, 126, 126, 0.05) 100%);
                clear: both;
                position: relative;
                overflow: hidden;
            }

            .bottom-content {
                max-width: 1200px;
                margin: 0 auto;
                text-align: center;
                position: relative;
                z-index: 1;
            }

            .bottom-title {
                font-size: 2.5rem;
                font-weight: 700;
                color: #650000;
                margin-bottom: 15px;
                animation: fadeInUp 0.8s ease-out;
            }

            .bottom-subtitle {
                font-size: 1.2rem;
                color: #666;
                margin-bottom: 50px;
                max-width: 600px;
                margin-left: auto;
                margin-right: auto;
                animation: fadeInUp 1s ease-out;
            }

            /* Features Grid */
            .features-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap: 30px;
                margin-top: 40px;
                padding: 0 20px 20px 20px;
            }

            .feature-card {
                background: #FFFCFC;
                border-radius: 20px;
                padding: 35px 25px;
                box-shadow: 0 8px 25px rgba(101, 0, 0, 0.1);
                animation: fadeInUp 0.6s ease-out backwards;
                text-align: center;
                transition: all 0.3s ease;
            }

            .feature-card:nth-child(1) { animation-delay: 0.4s; }
            .feature-card:nth-child(2) { animation-delay: 0.5s; }
            .feature-card:nth-child(3) { animation-delay: 0.6s; }

            .feature-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 12px 35px rgba(101, 0, 0, 0.15);
            }

            .feature-card-icon {
                font-size: 3.5rem;
                color: #B77E7E;
                margin-bottom: 20px;
                display: inline-block;
            }

            .feature-card-title {
                font-size: 1.4rem;
                font-weight: 700;
                color: #650000;
                margin-bottom: 12px;
            }

            .feature-card-description {
                font-size: 1rem;
                color: #666;
                line-height: 1.6;
            }

            .stats-section {
                display: flex;
                justify-content: center;
                gap: 60px;
                margin-top: 40px;
                margin-bottom: 40px;
                flex-wrap: wrap;
            }

            .stat-box {
                text-align: center;
                padding: 30px;
                background: rgba(255, 252, 252, 0.8);
                border-radius: 20px;
                min-width: 150px;
                box-shadow: 0 5px 20px rgba(101, 0, 0, 0.08);
                animation: fadeInUp 0.8s ease-out backwards;
            }

            .stat-box:nth-child(1) { animation-delay: 0.1s; }
            .stat-box:nth-child(2) { animation-delay: 0.2s; }
            .stat-box:nth-child(3) { animation-delay: 0.3s; }

            .stat-number {
                font-size: 3rem;
                font-weight: 800;
                color: #650000;
                margin-bottom: 10px;
                display: inline-block;
                animation: textFloat 4s ease-in-out infinite;
                will-change: transform;
            }

            .stat-box:nth-child(1) .stat-number {
                animation-delay: 0s;
            }

            .stat-box:nth-child(2) .stat-number {
                animation-delay: 0.5s;
            }

            .stat-box:nth-child(3) .stat-number {
                animation-delay: 1s;
            }

            @keyframes textFloat {
                0%, 100% {
                    transform: translateY(0);
                }
                50% {
                    transform: translateY(-5px);
                }
            }

            .stat-label {
                font-size: 1rem;
                color: #666;
                font-weight: 500;
                display: inline-block;
                animation: textPulse 3s ease-in-out infinite;
                will-change: transform;
            }

            .stat-box:nth-child(1) .stat-label {
                animation-delay: 0.2s;
            }

            .stat-box:nth-child(2) .stat-label {
                animation-delay: 0.7s;
            }

            .stat-box:nth-child(3) .stat-label {
                animation-delay: 1.2s;
            }

            @keyframes textPulse {
                0%, 100% {
                    transform: scale(1);
                    opacity: 0.8;
                }
                50% {
                    transform: scale(1.03);
                    opacity: 1;
                }
            }


            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(30px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            /* Logo and Title */
            .logo-section {
                margin-bottom: 30px;
            }

            .logo-img {
                width: 180px;
                height: auto;
                margin-bottom: 20px;
                filter: drop-shadow(0 4px 8px rgba(101, 0, 0, 0.1));
                animation: logoFloat 3s ease-in-out infinite;
            }

            @keyframes logoFloat {
                0%, 100% { transform: translateY(0); }
                50% { transform: translateY(-8px); }
            }

            .welcome-container h1 {
                font-size: 2.8rem;
                font-weight: 800;
                margin: 15px 0;
                color: #650000;
                letter-spacing: -0.5px;
                background: linear-gradient(135deg, #650000 0%, #B77E7E 100%);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
            }

            .welcome-container p {
                font-size: 1.15rem;
                color: #666;
                margin-bottom: 40px;
                line-height: 1.8;
                font-weight: 400;
            }

            /* Feature highlights */
            .features {
                display: flex;
                justify-content: space-around;
                margin: 35px 0;
                padding: 25px 0;
                border-top: 1px solid rgba(183, 126, 126, 0.15);
                border-bottom: 1px solid rgba(183, 126, 126, 0.15);
            }

            .feature-item {
                flex: 1;
                padding: 0 10px;
            }

            .feature-icon {
                font-size: 1.8rem;
                color: #B77E7E;
                margin-bottom: 8px;
            }

            .feature-text {
                font-size: 0.85rem;
                color: #650000;
                font-weight: 500;
            }

            /* Buttons Section */
            .action-buttons {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 15px;
                width: 100%;
                margin-top: 10px;
            }

            .btn {
                padding: 16px 30px;
                border: none;
                border-radius: 12px;
                font-weight: 600;
                cursor: pointer;
                font-size: 1.05rem;
                transition: all 0.3s ease;
                text-decoration: none;
                display: flex;
                align-items: center;
                justify-content: center;
                width: 100%;
                max-width: 320px;
                position: relative;
                overflow: hidden;
            }

            .btn::before {
                content: '';
                position: absolute;
                top: 50%;
                left: 50%;
                width: 0;
                height: 0;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.3);
                transform: translate(-50%, -50%);
                transition: width 0.6s, height 0.6s;
            }

            .btn:hover::before {
                width: 300px;
                height: 300px;
            }

            .btn span {
                position: relative;
                z-index: 1;
            }

            /* Primary Button: Sign Up */
            .btn-signup {
                background: linear-gradient(135deg, #B77E7E 0%, #650000 100%);
                color: #FFFCFC;
                box-shadow: 0 6px 20px rgba(183, 126, 126, 0.4);
            }

            .btn-signup:hover {
                transform: translateY(-3px);
                box-shadow: 0 8px 25px rgba(183, 126, 126, 0.5);
            }

            /* Secondary Button: Login */
            .btn-login {
                background-color: transparent;
                color: #650000;
                border: 2px solid #650000;
                position: relative;
            }

            .btn-login:hover {
                background-color: #650000;
                color: #FFFCFC;
                transform: translateY(-3px);
                box-shadow: 0 6px 20px rgba(101, 0, 0, 0.3);
            }

            /* Responsive Design */
            @media (max-width: 600px) {
                .welcome-container {
                    padding: 40px 30px;
                }

                .welcome-container h1 {
                    font-size: 2.2rem;
                }

                .logo-img {
                    width: 150px;
                }

                .features {
                    flex-direction: column;
                    gap: 20px;
                }

                .main-content-wrapper {
                    grid-template-columns: 1fr;
                }

                .gift-column {
                    display: none;
                }

                .welcome-container {
                    width: 90%;
                }

                .gift-icon {
                    font-size: 2rem !important;
                    opacity: 0.15;
                }

                .bottom-section {
                    padding: 40px 15px;
                }

                .features-grid {
                    grid-template-columns: 1fr;
                    gap: 25px;
                }

                .stats-section {
                    gap: 30px;
                }

                .stat-box {
                    min-width: 120px;
                    padding: 20px;
                }

                .stat-number {
                    font-size: 2rem;
                }
            }
        </style>
    </head>

    <body>
        <!-- Decorative gift icons -->
        <div class="gift-icon">
            <i class="fas fa-gift"></i>
        </div>
        <div class="gift-icon">
            <i class="fas fa-heart"></i>
        </div>
        <div class="gift-icon">
            <i class="fas fa-gift"></i>
        </div>
        <div class="gift-icon">
            <i class="fas fa-star"></i>
        </div>
        <div class="gift-icon">
            <i class="fas fa-birthday-cake"></i>
        </div>
        <div class="gift-icon">
            <i class="fas fa-gift"></i>
        </div>
        <div class="gift-icon">
            <i class="fas fa-heart"></i>
        </div>
        <div class="gift-icon">
            <i class="fas fa-star"></i>
        </div>

        <div class="main-content-wrapper">
            <!-- Left column gift items -->
            <div class="gift-column left">
                <?php 
                $left_images = array_slice($gift_images, 0, 4);
                foreach ($left_images as $image_url): ?>
                    <div class="gift-item">
                        <img src="<?php echo htmlspecialchars($image_url); ?>" alt="Gift item" />
                    </div>
                <?php endforeach; ?>
            </div>

        <div class="welcome-container">
            <header class="logo-section">
                <img src="images/logo.png" alt="WOD Logo" class="logo-img">
                <h1>Welcome to WOD</h1>
                <p>Your interactive social platform for coordinated and thoughtful gift-giving.</p>
            </header>

            <div class="features">
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-gift"></i>
                    </div>
                    <div class="feature-text">Group Gifts</div>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="feature-text">Event Calendar</div>
                </div>
                <div class="feature-item">
                    <div class="feature-icon">
                        <i class="fas fa-heart"></i>
                    </div>
                    <div class="feature-text">Wishlists</div>
                </div>
            </div>

            <section class="action-buttons">
                <a href="signup.php" class="btn btn-signup">
                    <span>
                    <i class="fas fa-user-plus" style="margin-right: 8px;"></i> Sign Up
                    </span>
                </a>

                <a href="login.php" class="btn btn-login">
                    <span>
                    <i class="fas fa-sign-in-alt" style="margin-right: 8px;"></i> Login
                    </span>
                </a>
            </section>
        </div>

            <!-- Right column gift items -->
            <div class="gift-column right">
                <?php 
                $right_images = array_slice($gift_images, 4, 4);
                foreach ($right_images as $image_url): ?>
                    <div class="gift-item">
                        <img src="<?php echo htmlspecialchars($image_url); ?>" alt="Gift item" />
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        </div>

        <!-- Bottom Section -->
        <section class="bottom-section">
            <div class="bottom-content">
                <h2 class="bottom-title">Discover Amazing Gifts</h2>
                <p class="bottom-subtitle">Explore curated collections and find the perfect gift for your loved ones</p>
                
                <div class="stats-section">
                    <div class="stat-box">
                        <div class="stat-number">1000+</div>
                        <div class="stat-label">Gift Ideas</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number">500+</div>
                        <div class="stat-label">Happy Users</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number">50+</div>
                        <div class="stat-label">Group Gifts</div>
                    </div>
                </div>

                <div class="features-grid">
                    <div class="feature-card">
                        <div class="feature-card-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3 class="feature-card-title">Group Gifting</h3>
                        <p class="feature-card-description">Coordinate with friends and family to give the perfect gift together. Split costs and make dreams come true!</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-card-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <h3 class="feature-card-title">Event Calendar</h3>
                        <p class="feature-card-description">Never miss a special occasion. Track birthdays, anniversaries, and important dates all in one place.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-card-icon">
                            <i class="fas fa-heart-circle-check"></i>
                        </div>
                        <h3 class="feature-card-title">Smart Wishlists</h3>
                        <p class="feature-card-description">Create and share wishlists with your loved ones. Make gift-giving thoughtful and effortless.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Footer -->
        <footer class="footer" style="background: #FFFCFC; color: #666; padding: 20px; text-align: center; margin-top: auto;">
            <p style="margin: 0; color: #666;">© 2025 WOD — All Rights Reserved.</p>
        </footer>

    </body>

</html>