<?php
require_once 'config.php';
requireLogin();

$username = getCurrentUser();

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

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $external_link = $_POST['external_link'] ?? '';
    $price = $_POST['price'] ?? '';
    
    // Validate price
    if ($price !== '' && (!is_numeric($price) || floatval($price) < 0)) {
        $error = "Price must be a positive number";
    }
    
    // Handle image upload
    $image_url = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '_wishlist.' . $file_extension;
        $target_path = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
            $image_url = $target_path;
        } else {
            $error = "Failed to upload image. Please try again.";
        }
    }
    
    // Validate URL if provided
    if (!empty($external_link) && !filter_var($external_link, FILTER_VALIDATE_URL)) {
        $error = "Please enter a valid URL";
    }
    
    // Insert into database if no error
    if (!$error) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO wishlist_items (title, external_link, image_url, price, wishlist_id, post_id)
                VALUES (?, ?, ?, ?, ?, NULL)
            ");
            
            $stmt->execute([
                $title,
                $external_link ?: null,
                $image_url,
                $price ? floatval($price) : null,
                $wishlist_id
            ]);
            
            $success = "Item added to your wishlist successfully!";
            
            // Clear form or redirect
            if ($success) {
                header("Location: wishlist.php");
                exit();
            }
            
        } catch (PDOException $e) {
            $error = "Error adding item: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Wishlist Item - WOD</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .create-wishlist-container {
            max-width: 600px;
            margin: 40px auto;
            padding: 30px;
            background-color: #FFFCFC;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            margin-right: calc(auto + 80px);
        }

        .create-wishlist-container h1 {
            color: #430000;
            text-align: center;
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #430000;
            font-weight: 600;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #f0f0f0;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            border-color: #B77E7E;
            outline: none;
        }

        .submit-btn {
            background-color: #B77E7E;
            color: #FFFCFC;
            border: none;
            padding: 15px 30px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 700;
            font-size: 1.1rem;
            transition: background-color 0.3s ease;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .submit-btn:hover {
            background-color: #650000;
        }

        .image-preview {
            max-width: 200px;
            max-height: 200px;
            margin-top: 10px;
            border-radius: 10px;
            display: none;
        }

        .price-error {
            color: red;
            font-size: 0.875rem;
            margin-top: 5px;
            display: none;
        }

        .message {
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }

        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
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
            <a href="wishlist.php" class="sidebar-icon">
                <img src="images/icons/groups.png?v=2" alt="Wishlist" class="sidebar-icon-img" />
            </a>
            <a href="logout.php" class="sidebar-icon" onclick="return confirm('Are you sure you want to log out?')">
                <img src="images/icons/logout.png" alt="Logout" class="sidebar-icon-img" />
            </a>
        </div>
    </div>

    <main class="create-wishlist-container">
        <h1><i class="fas fa-plus-circle" style="color:#B77E7E; margin-right: 10px;"></i> Add Wishlist Item</h1>
        
        <?php if ($error): ?>
            <div class="message error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="message success">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" onsubmit="return validateForm()">
            <div class="form-group">
                <label for="title">Item Title *</label>
                <input type="text" id="title" name="title" placeholder="What item do you wish for?" required>
            </div>

            <div class="form-group">
                <label for="description">Description (Optional)</label>
                <textarea id="description" name="description" placeholder="Describe your wishlist item..." rows="3"></textarea>
            </div>

            <div class="form-group">
                <label for="external_link">Product Link (Optional)</label>
                <input type="url" id="external_link" name="external_link" placeholder="https://example.com/product">
            </div>

            <div class="form-group">
                <label for="price">Price (Optional)</label>
                <input type="number" id="price" name="price" placeholder="0.00" step="0.01" min="0" oninput="validatePrice(this)">
                <div id="priceError" class="price-error">Price must be a positive number</div>
            </div>

            <div class="form-group">
                <label for="image">Item Image (Optional but recommended)</label>
                <input 
                    type="file" 
                    id="image" 
                    name="image" 
                    accept="image/*" 
                    onchange="previewImage(this)">
                <img id="imagePreview" class="image-preview" alt="Image preview">
            </div>

            <button type="submit" class="submit-btn">
                <i class="fas fa-heart"></i> Add to My Wishlist
            </button>
            
            <div style="text-align: center; margin-top: 20px;">
                <a href="wishlist.php" style="color: #650000; text-decoration: none;">
                    <i class="fas fa-arrow-left"></i> Back to My Wishlist
                </a>
            </div>
        </form>
    </main>

    <script>
        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.style.display = 'none';
            }
        }

        function validatePrice(input) {
            const errorElement = document.getElementById('priceError');
            if (input.value && (isNaN(input.value) || parseFloat(input.value) < 0)) {
                errorElement.style.display = 'block';
                return false;
            } else {
                errorElement.style.display = 'none';
                return true;
            }
        }

        function validateForm() {
            const priceInput = document.getElementById('price');
            if (priceInput.value && !validatePrice(priceInput)) {
                return false;
            }
            
            const titleInput = document.getElementById('title');
            if (!titleInput.value.trim()) {
                alert('Please enter a title for your wishlist item.');
                titleInput.focus();
                return false;
            }
            
            return true;
        }
    </script>
</body>
</html>