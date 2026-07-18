<?php
require_once 'config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = getCurrentUser();
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    // Limit description 
    $wordLimit = 40;
    $wordCount = str_word_count($description);

    if ($wordCount > $wordLimit) {
        $error = "Description is too long. Maximum allowed is $wordLimit words.";
    }

    $external_link = $_POST['external_link'] ?? '';

    // Handle price - convert empty string to NULL
    $price = $_POST['price'] ?? '';
    if ($price === '') {
        $price = null;
    } else {
        // Validate it's a positive number
        $price = floatval($price);
        if ($price < 0) {
            $error = "Price cannot be negative.";
        }
    }

    // Handle image upload (REQUIRED)
    if (!isset($_FILES['image']) || $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE) {
        $error = "Please upload an image for your post.";
    } else {
        if ($_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '.' . $file_extension;
            $target_path = $upload_dir . $filename;

            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                $image_url = $target_path;
            } else {
                $error = "Failed to upload the image. Please try again.";
            }
        } else {
            $error = "Error uploading image. Please try again.";
        }
    }

    // Validate external link (optional)
    $external_link = $_POST['external_link'] ?? '';
    if (!empty($external_link)) {
        if (filter_var($external_link, FILTER_VALIDATE_URL) === false) {
            $error = "Please enter a valid URL";
        }
    }

    // Only insert if there is NO error
    if (!isset($error)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO posts (username, title, description, image_url, external_link, price)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$username, $title, $description, $image_url, $external_link, $price]);

            header("Location: posts.php");
            exit();
        } catch (PDOException $e) {
            $error = "Error creating post: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Create New Post - WOD</title>
        <link rel="stylesheet" href="style.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
        <style>
            .create-post-container {
                max-width: 600px;
                margin: 40px auto;
                padding: 30px;
                background-color: #fcfcfc;
                border-radius: 20px;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
                margin-right: calc(auto + 80px);
            }

            .create-post-container h1 {
                color: #4a202a;
                text-align: center;
                margin-bottom: 30px;
            }

            .form-group {
                margin-bottom: 20px;
            }

            .form-group label {
                display: block;
                margin-bottom: 8px;
                color: #4a202a;
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
                border-color: #f5c0d3;
                outline: none;
            }

            .submit-btn {
                background-color: #f5c0d3;
                color: white;
                border: none;
                padding: 15px 30px;
                border-radius: 10px;
                cursor: pointer;
                font-weight: 700;
                font-size: 1.1rem;
                transition: background-color 0.3s ease;
                width: 100%;
            }

            .submit-btn:hover {
                background-color: #e8a8c5;
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


            .create-post-container {
                max-width: 600px;
                margin: 40px auto;
                padding: 30px;
                background-color: #FFFCFC;
                border-radius: 20px;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
                margin-right: calc(auto + 80px);
            }

            .create-post-container h1 {
                color: #430000;
                text-align: center;
                margin-bottom: 30px;
            }

            .form-group label {
                color: #430000;
                font-weight: 600;
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
            }

            .submit-btn:hover {
                background-color: #650000;
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

        <main class="create-post-container">

            <h1><i class="fas fa-plus-circle" style="color:#B77E7E; margin-right: 10px;"></i> Create New Post</h1>
            
            <?php if (isset($error)): ?>
                <div style="color: red; margin-bottom: 20px; padding: 10px; background: #ffe6e6; border-radius: 5px;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" onsubmit="return validateForm()">
                <div class="form-group">
                    <label for="title">Post Title</label>
                    <input type="text" id="title" name="title" placeholder="Enter a title for your post" required>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" placeholder="Describe your post..." rows="4"></textarea>
                    <small style="color: #666;">Maximum 40 words</small>
                </div>

                <div class="form-group">
                    <label for="external_link">External Link (Optional)</label>
                    <input type="url" id="external_link" name="external_link" placeholder="https://example.com">
                </div>

                <div class="form-group">
                    <label for="price">Price (Optional)</label>
                    <input type="number" id="price" name="price" placeholder="0.00" step="0.01" min="0" oninput="validatePrice(this)">
                    <div id="priceError" class="price-error">Price must be a positive number</div>
                </div>

                <div class="form-group">
                    <label for="image">Upload Image</label>
                    <input 
                        type="file" 
                        id="image" 
                        name="image" 
                        accept="image/*" 
                        onchange="previewImage(this)" 
                        required> 
                    <img id="imagePreview" class="image-preview" alt="Image preview">
                </div>

                <button type="submit" class="submit-btn">
                    <i class="fas fa-share" style="margin-right: 10px;"></i> Share Post
                </button>
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

                const description = document.getElementById('description').value;
                const wordCount = description.trim() ? description.trim().split(/\s+/).length : 0;
                if (wordCount > 40) {
                    alert('Description is too long. Maximum allowed is 40 words.');
                    return false;
                }

                return true;
            }
        </script>
    </body>
</html>