
<?php
require_once 'config.php';
requireLogin();

$username = getCurrentUser();
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

// Handle adding new event
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_event'])) {
    $title = $_POST['title'] ?? '';
    $date = $_POST['date'] ?? '';
    $type = $_POST['type'] ?? 'personal';
    $description = $_POST['description'] ?? '';

    if (!empty($title) && !empty($date)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO occasions (title, date, type, description, username) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$title, $date, $type, $description, $username]);
            header("Location: calendar.php");
            exit();
        } catch (PDOException $e) {
            $error = "Error adding event: " . $e->getMessage();
        }
    } else {
        $error = "Please fill in all required fields";
    }
}

// Get user's own occasions
$stmt = $pdo->prepare("SELECT * FROM occasions WHERE username = ? ORDER BY date");
$stmt->execute([$username]);
$user_occasions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get friends' occasions
$stmt = $pdo->prepare("
    SELECT o.*, u.name as friend_name, u.username as friend_username
    FROM occasions o 
    JOIN friends f ON (f.username_1 = ? AND f.username_2 = o.username) OR (f.username_2 = ? AND f.username_1 = o.username)
    JOIN users u ON o.username = u.username 
    WHERE o.username != ?
    ORDER BY o.date
");
$stmt->execute([$username, $username, $username]);
$friend_occasions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Combine all occasions
$all_occasions = array_merge($user_occasions, $friend_occasions);

// Count upcoming events (within next 30 days)
$upcoming_count = 0;
$today = date('Y-m-d');
$next_month = date('Y-m-d', strtotime('+30 days'));

foreach ($all_occasions as $occasion) {
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

    foreach ($all_occasions as $occasion) {
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
        <title>My Calendar - WOD</title>
        <link rel="stylesheet" href="style.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
        <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <style>
            .add-event-btn {
                background: #f5c0d3;
                color: white;
                border: none;
                padding: 12px 24px;
                border-radius: 25px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
                display: flex;
                align-items: center;
                gap: 8px;
                text-decoration: none;
                font-size: 14px;
                box-shadow: 0 4px 12px rgba(245, 192, 211, 0.3);
            }

            .add-event-btn:hover {
                background: #e8a5bb;
                transform: translateY(-2px);
                box-shadow: 0 6px 16px rgba(245, 192, 211, 0.4);
            }

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

            .event-personal {
                background: linear-gradient(135deg, #f5c0d3, #e8a5bb);
                color: white;
            }

            .event-user {
                background: #f5c0d3;
                color: white;
            }

            .event-friend {
                background: #8451C5;
                color: white;
            }

            .event-tooltip {
                position: absolute;
                background: rgba(0, 0, 0, 0.9);
                color: white;
                padding: 8px 12px;
                border-radius: 8px;
                font-size: 0.8rem;
                z-index: 1000;
                max-width: 200px;
                display: none;
            }

            .calendar-day {
                position: relative;
            }

            .calendar-day:hover .event-tooltip {
                display: block;
            }

            .calendar-filter {
                display: flex;
                gap: 10px;
                margin-bottom: 20px;
                flex-wrap: wrap;
            }

            .filter-btn {
                background: #f0f0f0;
                border: none;
                padding: 8px 16px;
                border-radius: 20px;
                cursor: pointer;
                font-weight: 500;
                transition: all 0.3s ease;
                text-decoration: none;
                display: inline-block;
            }

            .filter-btn.active {
                background: #f5c0d3;
                color: white;
            }

            .filter-btn:hover {
                background: #e8a5bb;
                color: white;
            }

            .event-legend {
                display: flex;
                gap: 15px;
                margin-top: 20px;
                flex-wrap: wrap;
                justify-content: center;
            }

            .legend-item {
                display: flex;
                align-items: center;
                gap: 5px;
                font-size: 0.8rem;
            }

            .legend-color {
                width: 12px;
                height: 12px;
                border-radius: 50%;
            }


            .add-event-btn {
                background: #B77E7E;
                color: #FFFCFC;
                border: none;
                padding: 12px 24px;
                border-radius: 25px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s ease;
                display: flex;
                align-items: center;
                gap: 8px;
                text-decoration: none;
                font-size: 14px;
                box-shadow: 0 4px 12px rgba(183, 126, 126, 0.3);
            }

            .add-event-btn:hover {
                background: #650000;
                transform: translateY(-2px);
                box-shadow: 0 6px 16px rgba(183, 126, 126, 0.4);
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

            .event-personal {
                background: #B77E7E;
                color: #FFFCFC;
            }

            .event-user {
                background: #B77E7E;
                color: #FFFCFC;
            }

            .event-friend {
                background: #650000;
                color: #FFFCFC;
            }

            .filter-btn {
                background: #f0f0f0;
                border: none;
                padding: 8px 16px;
                border-radius: 20px;
                cursor: pointer;
                font-weight: 500;
                transition: all 0.3s ease;
                text-decoration: none;
                display: inline-block;
            }

            .filter-btn.active {
                background: #B77E7E;
                color: #FFFCFC;
            }

            .filter-btn:hover {
                background: #650000;
                color: #FFFCFC;
            }

            .legend-color {
                width: 12px;
                height: 12px;
                border-radius: 50%;
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
                <h1>My Calendar</h1>
                <p>
                    <?php
                    $filter = $_GET['filter'] ?? 'all';
                    if ($filter === 'personal') {
                        echo 'My Events';
                    } elseif ($filter === 'friends') {
                        echo "Friends' Events";
                    } else {
                        echo 'All Events';
                    }
                    ?>
                </p>
                <div class="wishlist-info">Upcoming: <?php echo $upcoming_count; ?> Events</div>
            </div>
        </header>


        <!-- Calendar Container -->
        <section class="calendar-container">
            <div class="calendar-actions">
                <a href="#add-event" class="add-event-btn">
                    <i class="fa-solid fa-plus"></i> Add New Event
                </a>

                <!-- Calendar Filters -->
                <div class="calendar-filter">
                    <a href="?filter=all&month=<?php echo $current_month; ?>&year=<?php echo $current_year; ?>" 
                       class="filter-btn <?php echo ($_GET['filter'] ?? 'all') === 'all' ? 'active' : ''; ?>" 
                       data-filter="all">All Events</a>
                    <a href="?filter=personal&month=<?php echo $current_month; ?>&year=<?php echo $current_year; ?>" 
                       class="filter-btn <?php echo ($_GET['filter'] ?? '') === 'personal' ? 'active' : ''; ?>" 
                       data-filter="personal">My Events</a>
                    <a href="?filter=friends&month=<?php echo $current_month; ?>&year=<?php echo $current_year; ?>" 
                       class="filter-btn <?php echo ($_GET['filter'] ?? '') === 'friends' ? 'active' : ''; ?>" 
                       data-filter="friends">Friends' Events</a>
                </div>
            </div>

            <!-- Upcoming Events List -->
            <div class="upcoming-events" style="margin-bottom: 40px;">
                <div class="management-card">
                    <h2><i class="fas fa-bell" style="color:#B77E7E; margin-right: 10px;"></i> Upcoming Events</h2>

                    <?php
                    // Filter events based on selection
                    $filter = $_GET['filter'] ?? 'all';
                    $filtered_events = array_filter($all_occasions, function ($event) use ($filter, $username) {
                        if ($filter === 'personal') {
                            return $event['username'] === $username;
                        } elseif ($filter === 'friends') {
                            return $event['username'] !== $username;
                        }
                        return true; // Show all for 'all' filter
                    });
                    ?>

                    <?php if (!empty($filtered_events)): ?>
                        <div style="display: grid; gap: 15px;">
                            <?php
                            $upcoming_displayed = 0;
                            foreach ($filtered_events as $event):
                                if ($event['date'] >= $today && $upcoming_displayed < 10):
                                    $upcoming_displayed++;
                                    ?>
                                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 15px; background: #f8f0f5; border-radius: 10px;">
                                        <div>
                                            <strong><?php echo htmlspecialchars($event['title']); ?></strong>
                                            <br>
                                            <small style="color: #666;">
                                                <?php echo date('M j, Y', strtotime($event['date'])); ?>
                                                • <?php echo ucfirst($event['type']); ?>
                                                <?php if (isset($event['friend_name'])): ?>
                                                    - <a href="friend_calendar.php?username=<?php echo urlencode($event['friend_username']); ?>" style="color: #8451C5; text-decoration: none;">
                                                        <?php echo htmlspecialchars($event['friend_name']); ?>'s Event
                                                    </a>
                                                <?php else: ?>
                                                    - Your Event
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                        <span class="event-indicator <?php echo isset($event['friend_name']) ? 'event-friend' : 'event-user'; ?>" style="font-size: 0.7rem;">
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
                            No upcoming events. Add some events to get started!
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Month View -->
            <div id="month-view" class="calendar-view">
                <div class="calendar-grid">
                    <div class="calendar-header">
                        <a href="?filter=<?php echo $filter; ?>&nav=prev&month=<?php echo $current_month; ?>&year=<?php echo $current_year; ?>" class="nav-arrow">
                            <i class="fa-solid fa-chevron-left"></i>
                        </a>
                        <h2><?php echo $month_name . ' ' . $year; ?></h2>
                        <a href="?filter=<?php echo $filter; ?>&nav=next&month=<?php echo $current_month; ?>&year=<?php echo $current_year; ?>" class="nav-arrow">
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
                        <?php
                        foreach ($calendar_days as $index => $day_data):
                            // Filter day events based on current filter
                            $filtered_day_events = array_filter($day_data['events'], function ($event) use ($filter, $username) {
                                if ($filter === 'personal') {
                                    return $event['username'] === $username;
                                } elseif ($filter === 'friends') {
                                    return $event['username'] !== $username;
                                }
                                return true;
                            });
                            ?>
                            <div class="calendar-day <?php echo $day_data['month'] !== 'current' ? 'other-month' : ''; ?> 
                            <?php echo isset($day_data['is_today']) && $day_data['is_today'] ? 'today' : ''; ?>
                                 <?php echo!empty($filtered_day_events) ? 'has-events' : ''; ?>"
                                 data-date="<?php echo $day_data['month'] === 'current' ? sprintf('%04d-%02d-%02d', $current_year, $current_month, $day_data['day']) : ''; ?>">

                                <div class="day-number"><?php echo $day_data['day']; ?></div>

                                <?php if (!empty($filtered_day_events)): ?>
                                    <?php foreach (array_slice($filtered_day_events, 0, 2) as $event): ?>
                                        <?php
                                        $event_class = isset($event['friend_name']) ? 'event-friend' : 'event-user';
                                        ?>
                                        <div class="event-indicator <?php echo $event_class; ?>" 
                                             title="<?php echo htmlspecialchars($event['title'] . (isset($event['friend_name']) ? ' (' . $event['friend_name'] . ')' : '') . ' - ' . ucfirst($event['type'])); ?>">
                                                 <?php
                                                 $event_title = $event['title'];
                                                 if (strlen($event_title) > 10) {
                                                     $event_title = substr($event_title, 0, 10) . '...';
                                                 }
                                                 echo $event_title;
                                                 ?>
                                        </div>
                                    <?php endforeach; ?>

                                    <?php if (count($filtered_day_events) > 2): ?>
                                        <div class="event-indicator event-user">
                                            +<?php echo count($filtered_day_events) - 2; ?> more
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Event Legend -->
<div class="event-legend">
    <div class="legend-item">
        <div class="legend-color" style="background: #B77E7E;"></div>
        <span>My Events</span>
    </div>
    <div class="legend-item">
        <div class="legend-color" style="background: #650000;"></div>
        <span>Friends' Events</span>
    </div>
</div>


            <!-- Add Event Form -->
            <div id="add-event" class="add-event-section">
                <div class="form-container">
                    <h3>Add New Event</h3>

                    <?php if (isset($error)): ?>
                        <div style="color: red; margin-bottom: 15px; padding: 10px; background: #ffe6e6; border-radius: 5px;">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <form class="auth-form" method="POST">
                        <input type="hidden" name="add_event" value="1">

                        <div class="form-group">
                            <label>Event Title *</label>
                            <input type="text" name="title" placeholder="Enter event title" required>
                        </div>

                        <div class="form-group">
                            <label>Event Date *</label>
                            <input type="date" name="date" required>
                        </div>

                        <div class="form-group">
                            <label>Event Type</label>
                            <select name="type">
                                <option value="personal">Personal Event</option>
                                <option value="birthday">Birthday</option>
                                <option value="anniversary">Anniversary</option>
                                <option value="graduation">Graduation</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Description (Optional)</label>
                            <textarea name="description" placeholder="Add event details"></textarea>
                        </div>

                        <button type="submit" class="auth-btn">Add Event</button>
                        <a href="#month-view" class="auth-link" style="display: block; text-align: center; margin-top: 15px;">Cancel</a>
                    </form>
                </div>
            </div>

        </section>

        <!-- Footer -->
        <footer class="footer" style="background: #FFFCFC !important; color: #666 !important;">
            <p style="color: #666 !important; background: transparent !important; margin: 0;">© 2025 WOD — All Rights Reserved.</p>
        </footer>

        <script>
            // Set default date to today
            document.querySelector('input[name="date"]').valueAsDate = new Date();

            // Smooth scroll for add event form
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });

            // Filter buttons functionality
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.addEventListener('click', function () {
                    // Remove active class from all buttons
                    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                    // Add active class to clicked button
                    this.classList.add('active');

                    const filter = this.dataset.filter;
                    // Here you would implement the filtering logic
                    // For now, we'll just show a message
                    console.log('Filter:', filter);
                });
            });
        </script>
    </body>
</html>
