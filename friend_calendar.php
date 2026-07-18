<?php
require_once 'config.php';
requireLogin();

if (!isset($_GET['username'])) {
    header("Location: calendar.php");
    exit();
}

$friend_username = $_GET['username'];
$current_user = getCurrentUser();

// Get friend data
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$friend_username]);
$friend = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$friend) {
    header("Location: calendar.php");
    exit();
}

// Get friend data
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$friend_username]);
$friend = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$friend) {
    header("Location: calendar.php");
    exit();
}
// Calendar parameters
$current_month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$current_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Handle month navigation
if (isset($_GET['nav'])) {
    if ($_GET['nav'] === 'prev') {
        $current_month--;
        if ($current_month < 1) {
            $current_month = 12;
            $current_year--;
        }
    } elseif ($_GET['nav'] === 'next') {
        $current_month++;
        if ($current_month > 12) {
            $current_month = 1;
            $current_year++;
        }
    }
}

// Get friend's occasions only
$stmt = $pdo->prepare("SELECT * FROM occasions WHERE username = ? ORDER BY date");
$stmt->execute([$friend_username]);
$friend_occasions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count upcoming events (within next 30 days)
$upcoming_count = 0;
$today = date('Y-m-d');
$next_month = date('Y-m-d', strtotime('+30 days'));

foreach ($friend_occasions as $occasion) {
    if ($occasion['date'] >= $today && $occasion['date'] <= $next_month) {
        $upcoming_count++;
    }
}

// Calendar calculation
$first_day = mktime(0, 0, 0, $current_month, 1, $current_year);
$month_name = date('F', $first_day);
$year = date('Y', $first_day);
$day_of_week = date('N', $first_day); // 1 (Monday) through 7 (Sunday)
$days_in_month = cal_days_in_month(CAL_GREGORIAN, $current_month, $current_year);

// Previous month days
$prev_month = $current_month - 1;
$prev_year = $current_year;
if ($prev_month < 1) {
    $prev_month = 12;
    $prev_year--;
}
$prev_month_days = cal_days_in_month(CAL_GREGORIAN, $prev_month, $prev_year);

// Generate calendar days
$calendar_days = [];

// Previous month days
for ($i = $day_of_week - 1; $i > 0; $i--) {
    $calendar_days[] = [
        'day' => $prev_month_days - $i + 1,
        'month' => 'prev',
        'events' => []
    ];
}

// Current month days
for ($day = 1; $day <= $days_in_month; $day++) {
    $current_date = sprintf('%04d-%02d-%02d', $current_year, $current_month, $day);
    $day_events = [];

    foreach ($friend_occasions as $occasion) {
        if ($occasion['date'] == $current_date) {
            $day_events[] = $occasion;
        }
    }

    $calendar_days[] = [
        'day' => $day,
        'month' => 'current',
        'events' => $day_events,
        'is_today' => ($current_date == date('Y-m-d'))
    ];
}

// Next month days
$total_cells = 42; // 6 weeks * 7 days
$remaining_days = $total_cells - count($calendar_days);
for ($day = 1; $day <= $remaining_days; $day++) {
    $calendar_days[] = [
        'day' => $day,
        'month' => 'next',
        'events' => []
    ];
}
?>
<!DOCTYPE html>
<html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($friend['username']); ?>'s Calendar - WOD</title>
        <link rel="stylesheet" href="style.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <style>
            .nav-arrow {
                background: #f5c0d3;
                border: none;
                width: 45px;
                height: 45px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                color: white;
                transition: all 0.3s ease;
                text-decoration: none;
                box-shadow: 0 3px 8px rgba(245, 192, 211, 0.4);
            }

            .nav-arrow:hover {
                background: #e8a5bb;
                transform: scale(1.1);
                box-shadow: 0 5px 12px rgba(245, 192, 211, 0.6);
            }

            .calendar-day.has-events {
                background: linear-gradient(135deg, #f5c0d320, #f8f0f5);
                border-color: #f5c0d3;
            }

            .event-indicator {
                font-size: 0.7rem;
                padding: 3px 6px;
                border-radius: 8px;
                margin-bottom: 2px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                font-weight: 500;
                cursor: pointer;
            }

            .event-birthday {
                background: linear-gradient(135deg, #ff6f61, #ff8a65);
                color: white;
            }

            .event-anniversary {
                background: linear-gradient(135deg, #4caf50, #66bb6a);
                color: white;
            }

            .event-graduation {
                background: linear-gradient(135deg, #2196f3, #42a5f5);
                color: white;
            }

            .event-other {
                background: linear-gradient(135deg, #ff9800, #ffb74d);
                color: white;
            }

            .event-personal {
                background: linear-gradient(135deg, #8451C5, #6a3fb0);
                color: white;
            }

            .back-to-profile {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                background: #f5c0d3;
                color: white;
                padding: 10px 20px;
                border-radius: 25px;
                text-decoration: none;
                font-weight: 600;
                margin-bottom: 20px;
                transition: all 0.3s ease;
            }

            .back-to-profile:hover {
                background: #e8a5bb;
                transform: translateY(-2px);
            }

            .friend-calendar-header {
                display: flex;
                align-items: center;
                gap: 15px;
                margin-bottom: 20px;
                flex-wrap: wrap;
            }

            .friend-avatar {
                width: 60px;
                height: 60px;
                border-radius: 50%;
                object-fit: cover;
                border: 3px solid #f5c0d3;
            }

            .friend-actions {
                margin-top: 30px;
                text-align: center;
            }

            .event-indicator {
                background: #8451C5 !important;
                color: white;
            }

            .event-birthday,
            .event-anniversary,
            .event-graduation,
            .event-other,
            .event-personal {
                background: #8451C5 !important;
                color: white;
            }

            .nav-arrow {
                background: #B77E7E;
                border: none;
                width: 45px;
                height: 45px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                color: #FFFCFC;
                transition: all 0.3s ease;
                text-decoration: none;
                box-shadow: 0 3px 8px rgba(183, 126, 126, 0.4);
            }

            .nav-arrow:hover {
                background: #650000;
                transform: scale(1.1);
                box-shadow: 0 5px 12px rgba(183, 126, 126, 0.6);
            }

            .calendar-day.has-events {
                background: linear-gradient(135deg, #B77E7E20, #f8f0f5);
                border-color: #B77E7E;
            }

            .event-indicator {
                background: #650000 !important;
                color: #FFFCFC;
            }

            .event-birthday,
            .event-anniversary,
            .event-graduation,
            .event-other,
            .event-personal {
                background: #650000 !important;
                color: #FFFCFC;
            }

            .back-to-profile {
                background: #B77E7E;
                color: #FFFCFC;
                padding: 10px 20px;
                border-radius: 25px;
                text-decoration: none;
                font-weight: 600;
                margin-bottom: 20px;
                transition: all 0.3s ease;
            }

            .back-to-profile:hover {
                background: #650000;
                transform: translateY(-2px);
            }

            .friend-avatar {
                border: 3px solid #B77E7E;
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
        <!-- Calendar Header -->
        <header class="wishlist-header">
            <div class="wishlist-header-content">
                <div class="friend-calendar-header">
                    <img src="<?php echo htmlspecialchars($friend['photo_url'] ?? 'images/profile-pic.jpg'); ?>" 
                         alt="<?php echo htmlspecialchars($friend['name']); ?>" class="friend-avatar">
                    <div>
                        <h1><?php echo htmlspecialchars($friend['name']); ?>'s Calendar</h1>
                        <p>@<?php echo htmlspecialchars($friend['username']); ?>'s events and occasions</p>
                        <div class="wishlist-info">Upcoming: <?php echo $upcoming_count; ?> Events</div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Calendar Container -->
        <section class="calendar-container">
            <!-- Friend Actions -->
            <div class="friend-actions">
                <a href="friend_profile.php?username=<?php echo urlencode($friend_username); ?>" class="edit-profile-btn" style="text-decoration: none; margin-right: 10px;">
                    <i class="fas fa-user"></i> Back To Profile
                </a>
                <a href="friend-wishlist.php?username=<?php echo urlencode($friend_username); ?>" class="edit-profile-btn" style="text-decoration: none;">
                    <i class="fas fa-heart"></i> Back To Wishlist
                </a>

            </div>
            <br>  <br>

            <!-- Friend's Upcoming Events -->
            <div class="upcoming-events" style="margin-bottom: 40px;">
                <div class="management-card">
                    <h2><i class="fas fa-bell" style="color:#B77E7E; margin-right: 10px;"></i> <?php echo htmlspecialchars($friend['name']); ?>'s Upcoming Events</h2>

                    <?php if (!empty($friend_occasions)): ?>
                        <div style="display: grid; gap: 15px;">
                            <?php
                            $upcoming_displayed = 0;
                            foreach ($friend_occasions as $event):
                                if ($event['date'] >= $today && $upcoming_displayed < 10):
                                    $upcoming_displayed++;
                                    ?>
                                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 15px; background: #f0f0f5; border-radius: 10px;">
                                        <div>
                                            <strong><?php echo htmlspecialchars($event['title']); ?></strong>
                                            <br>
                                            <small style="color: #666;">
                                                <?php echo date('M j, Y', strtotime($event['date'])); ?>
                                                • <?php echo ucfirst($event['type']); ?>
                                            </small>
                                            <?php if (!empty($event['description'])): ?>
                                                <br>
                                                <small style="color: #888;"><?php echo htmlspecialchars($event['description']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <span class="event-indicator event-<?php echo $event['type']; ?>" style="font-size: 0.7rem;">
                                            <?php echo ucfirst($event['type']); ?>
                                        </span>
                                    </div>
                                    <?php
                                endif;
                            endforeach;
                            ?>
                        </div>
                    <?php else: ?>
                        <p style="text-align: center; color: #888; padding: 20px;">
                            <?php echo htmlspecialchars($friend['name']); ?> hasn't added any events yet.
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Month View -->
            <div id="month-view" class="calendar-view">
                <div class="calendar-grid">
                    <div class="calendar-header">
                        <a href="?username=<?php echo urlencode($friend_username); ?>&nav=prev&month=<?php echo $current_month; ?>&year=<?php echo $current_year; ?>" class="nav-arrow">
                            <i class="fa-solid fa-chevron-left"></i>
                        </a>
                        <h2><?php echo $month_name . ' ' . $year; ?></h2>
                        <a href="?username=<?php echo urlencode($friend_username); ?>&nav=next&month=<?php echo $current_month; ?>&year=<?php echo $current_year; ?>" class="nav-arrow">
                            <i class="fa-solid fa-chevron-right"></i>
                        </a>
                    </div>

                    <div class="weekdays">
                        <div>Mon</div>
                        <div>Tue</div>
                        <div>Wed</div>
                        <div>Thu</div>
                        <div>Fri</div>
                        <div>Sat</div>
                        <div>Sun</div>
                    </div>

                    <div class="calendar-days">
                        <?php foreach ($calendar_days as $index => $day_data): ?>
                            <div class="calendar-day <?php echo $day_data['month'] !== 'current' ? 'other-month' : ''; ?> 
                            <?php echo isset($day_data['is_today']) && $day_data['is_today'] ? 'today' : ''; ?>
                                 <?php echo!empty($day_data['events']) ? 'has-events' : ''; ?>">

                                <div class="day-number"><?php echo $day_data['day']; ?></div>

                                <?php if (!empty($day_data['events'])): ?>
                                    <?php foreach (array_slice($day_data['events'], 0, 3) as $event): ?>
                                        <div class="event-indicator event-<?php echo $event['type']; ?>" 
                                             title="<?php echo htmlspecialchars($event['title'] . ' - ' . ucfirst($event['type'])); ?>">
                                                 <?php
                                                 $event_title = $event['title'];
                                                 if (strlen($event_title) > 12) {
                                                     $event_title = substr($event_title, 0, 12) . '...';
                                                 }
                                                 echo $event_title;
                                                 ?>
                                        </div>
                                    <?php endforeach; ?>

                                    <?php if (count($day_data['events']) > 3): ?>
                                        <div class="event-indicator event-other">
                                            +<?php echo count($day_data['events']) - 3; ?> more
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        </section>

        <!-- Footer -->
        <footer class="footer" style="background: #FFFCFC !important; color: #666 !important;">
            <p style="color: #666 !important; background: transparent !important; margin: 0;">© 2025 WOD — All Rights Reserved.</p>
        </footer>
    </body>
</html>