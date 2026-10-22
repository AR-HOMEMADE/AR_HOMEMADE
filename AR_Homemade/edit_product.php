<?php
require_once "db.php";
require_once "auth.php";
require_once "role_check.php";

require_role(['Administrator']);

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    header("Location: products.php");
    exit;
}

$error = "";
$success = "";
$stmt = $conn->prepare("SELECT id, product_name, description, image, price, stock, low_stock_level FROM products WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    header("Location: products.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
    $product_name = trim($_POST['product_name'] ?? "");
    $description = trim($_POST['description'] ?? "");
    $price_raw = trim($_POST['price'] ?? "");
    $image = $product['image'] ?? "";
    $new_image_path = "";

    if ($product_name === "" || $price_raw === "") {
        $error = "Please fill in the product name and price.";
    } elseif (!is_numeric($price_raw) || (float)$price_raw < 0) {
        $error = "Enter a valid price of 0 or more.";
    } else {
        $price = (float)$price_raw;
    }

    if ($error === "" && isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $error = "There was a problem uploading the image.";
        } elseif ($_FILES['image']['size'] > 5 * 1024 * 1024) {
            $error = "Image must be 5MB or smaller.";
        } else {
            $allowed_types = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/gif' => 'gif'
            ];
            $mime = mime_content_type($_FILES['image']['tmp_name']);
            if (!isset($allowed_types[$mime])) {
                $error = "Only JPG, PNG, WEBP, and GIF images are allowed.";
            } else {
                $upload_dir = __DIR__ . '/uploads/';
                if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true)) {
                    $error = "Could not create the uploads folder.";
                } else {
                    $new_image = uniqid('product_', true) . '.' . $allowed_types[$mime];
                    $new_image_path = $upload_dir . $new_image;
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $new_image_path)) {
                        $image = 'uploads/' . $new_image;
                    } else {
                        $error = "Failed to save the uploaded image.";
                    }
                }
            }
        }
    }

    if ($error === "") {
        // Keep stock and low-stock settings unchanged; these are managed in inventory.
        $stmt = $conn->prepare("UPDATE products SET product_name = ?, description = ?, image = ?, price = ? WHERE id = ?");
        $stmt->bind_param("sssdi", $product_name, $description, $image, $price, $id);

        if ($stmt->execute()) {
            $stmt->close();

            if ($new_image_path !== "" && !empty($product['image'])) {
                $old_image = basename($product['image']);
                $old_image_path = __DIR__ . '/uploads/' . $old_image;
                if (is_file($old_image_path) && realpath($old_image_path) !== realpath($new_image_path)) {
                    unlink($old_image_path);
                }
            }
            header("Location: products.php?updated=1");
            exit;
        } else {
            $error = "Failed to update the product. Please try again.";
            $stmt->close();
            if ($new_image_path !== "" && is_file($new_image_path)) {
                unlink($new_image_path);
            }
        }
    }

    // Preserve submitted values when validation or upload fails.
    $product['product_name'] = $product_name;
    $product['description'] = $description;
    $product['price'] = $price_raw;
    $product['image'] = $image;
}
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
$image_src = '';
if (!empty($product['image'])) {
    $candidate = str_replace('\\\\', '/', $product['image']);
    if (strpos($candidate, 'uploads/') === 0 || strpos($candidate, 'images/') === 0) {
        $image_src = $candidate;
    } else {
        $image_src = 'uploads/' . basename($candidate);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Product - AR Homemade</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        /* Keep the edit page navbar consistent with the other admin pages. */
        .topbar {
            position: sticky;
            top: 0;
            z-index: 1000;
            width: 100%;
        }
        .navbar-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            margin: 0 auto;
            width: 100%;
        }
        .nav-links {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
        }
        .nav-links a {
            white-space: nowrap;
        }
        @media (max-width: 900px) {
            .navbar-inner {
                flex-direction: column;
                align-items: flex-start;
            }
            .nav-links {
                width: 100%;
                justify-content: flex-start;
                overflow-x: auto;
                flex-wrap: nowrap;
                padding-bottom: 5px;
            }
        }
        .edit-product-wrap { max-width: 900px; margin: 32px auto; padding: 0 15px 35px; }
        .edit-product-card { border: 0; border-radius: 16px; overflow: hidden; box-shadow: 0 12px 35px rgba(143,29,29,.10); }
        .edit-product-head { background: #fff; padding: 22px 26px; border-bottom: 1px solid #eadfd9; }
        .edit-product-body { padding: 26px; }
        .product-preview { width: 150px; height: 130px; object-fit: contain; background: #faf7f4; border: 1px solid #eadfd9; border-radius: 12px; padding: 8px; }
        .btn-ar { background: #d62828; border-color: #d62828; color: #fff; }
        .btn-ar:hover { background: #8f1d1d; border-color: #8f1d1d; color: #fff; }
        .form-label { font-weight: 600; }
        @media(max-width:576px) { .edit-product-body { padding: 18px; } .edit-product-head { padding: 18px; } }
    </style>
</head>
<body>
<header class="topbar">
    <div class="navbar-inner">
        <a href="index.php" class="logo"><img src="images/ar_logo.png" alt="AR Homemade"></a>
        <nav class="nav-links" aria-label="Main navigation">
            <a href="index.php">Dashboard</a>
            <a href="orders.php">Orders</a>
            <a href="products.php" class="active">Menu Items</a>
            <a href="inventory.php">Ingredients &amp; Recipes</a>
            <a href="reports.php">Reports</a>
            <a href="users.php">Users</a>
            <a href="logout.php">Logout</a>
        </nav>
    </div>
</header>

<main class="edit-product-wrap">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <div>
            <h1 class="h3 mb-1">Edit Product</h1>
            <p class="text-muted mb-0">Update the product details and menu image.</p>
        </div>
        <a href="products.php" class="btn btn-outline-secondary">← Back to Menu Items</a>
    </div>

    <section class="card edit-product-card">
        <div class="edit-product-head">
            <strong>Product Information</strong>
        </div>
        <div class="edit-product-body">
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger" role="alert"><?php echo e($error); ?></div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="row g-4">
                    <div class="col-12">
                        <label for="product_name" class="form-label">Product Name <span class="text-danger">*</span></label>
                        <input id="product_name" type="text" name="product_name" class="form-control" maxlength="255" value="<?php echo e($product['product_name']); ?>" required>
                    </div>
                    <div class="col-12">
                        <label for="description" class="form-label">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="3"><?php echo e($product['description'] ?? ''); ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label for="price" class="form-label">Price (₱) <span class="text-danger">*</span></label>
                        <input id="price" type="number" name="price" class="form-control" min="0" step="0.01" value="<?php echo e($product['price']); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Current Stock</label>
                        <input type="text" class="form-control" value="<?php echo e($product['stock']); ?>" readonly>
                        <div class="form-text">Stock is managed in Inventory and will not be changed here.</div>
                    </div>
                    <div class="col-12">
                        <label for="image" class="form-label">Product Image</label>
                        <div class="d-flex align-items-center gap-3 flex-wrap mb-2">
                            <?php if ($image_src !== ''): ?>
                                <img class="product-preview" src="<?php echo e($image_src); ?>" alt="Current product image">
                            <?php else: ?>
                                <div class="product-preview d-flex align-items-center justify-content-center text-muted">No image</div>
                            <?php endif; ?>
                            <div class="flex-grow-1">
                                <input id="image" type="file" name="image" class="form-control" accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif">
                                <div class="form-text">Optional. JPG, PNG, WEBP, or GIF; maximum 5 MB. Leave empty to keep the current image.</div>
                            </div>
                        </div>
                    </div>
                </div>
                <hr class="my-4">
                <div class="d-flex flex-wrap gap-2 justify-content-end">
                    <a href="products.php" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" name="update_product" value="1" class="btn btn-ar">Save Changes</button>
                </div>
            </form>
        </div>
    </section>
</main>
</body>
</html>
