<?php

require_once "db.php";
require_once "auth.php";
require_once "role_check.php";

require_role(['Administrator']);

$message = "";
$error = "";

/* ADD PRODUCT */

if (isset($_POST['add_product'])) {

   $product_name = trim($_POST['product_name'] ?? "");
    $description = trim($_POST['description'] ?? "");
    $price = $_POST['price'] ?? "";
    $stock = 0;
    $low_stock_level = 0;
    $image = "";

    // Upload product image (optional)
    if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {

        if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $error = "There was a problem uploading the image.";
        } elseif ($_FILES['image']['size'] > 5 * 1024 * 1024) {
            $error = "Image must be 5MB or smaller.";
        } else {
            $allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $image_type = mime_content_type($_FILES['image']['tmp_name']);

            if (!in_array($image_type, $allowed_types, true)) {
                $error = "Only JPG, PNG, WEBP, and GIF images are allowed.";
            } else {
                $extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                $image = uniqid('product_', true) . '.' . $extension;
                $upload_dir = __DIR__ . '/uploads/';

                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                if (!move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $image)) {
                    $error = "Failed to save the uploaded image.";
                    $image = "";
                }
            }
        }
    }
    if ($product_name == "" || $price == "") {

        $error = "Please fill in all required fields.";

    } elseif (!is_numeric($price) || (float)$price < 0) {

        $error = "Price must be a valid non-negative amount.";

    } elseif ($error != "") {

        // Keep the upload error message and do not insert the product.

    } else {

       $stmt = $conn->prepare(
    "INSERT INTO products
    (product_name, description, image, price, stock, low_stock_level)
    VALUES (?, ?, ?, ?, ?, ?)"
);

$stmt->bind_param(
    "sssdii",
    $product_name,
    $description,
    $image,
    $price,
    $stock,
    $low_stock_level
);

        if ($stmt->execute()) {
            $message = "Product added successfully.";
        } else {
            $error = "Failed to add product.";
        }

        $stmt->close();
    }
}

/* DELETE PRODUCT */

if (isset($_GET['delete'])) {

    $id = intval($_GET['delete']);

    // Get the image filename before deleting the database row.
    $image_stmt = $conn->prepare("SELECT image FROM products WHERE id = ?");
    $image_stmt->bind_param("i", $id);
    $image_stmt->execute();
    $product_to_delete = $image_stmt->get_result()->fetch_assoc();
    $image_stmt->close();

    $stmt = $conn->prepare(
        "DELETE FROM products WHERE id = ?"
    );

    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        $message = "Product deleted successfully.";

        if (!empty($product_to_delete['image'])) {
            $image_path = __DIR__ . '/uploads/' . basename($product_to_delete['image']);
            if (is_file($image_path)) {
                unlink($image_path);
            }
        }
    } else {
        $error = "Unable to delete product.";
    }

    $stmt->close();
}

/* UPDATE PRODUCT */

if (isset($_POST['update_product'])) {

    $id = intval($_POST['id']);
    $product_name = trim($_POST['product_name'] ?? "");
    $description = trim($_POST['description'] ?? "");
    $price = $_POST['price'] ?? "";
    $stock = 0;
    $low_stock_level = 0;

    // Get the current image first.
    $current_stmt = $conn->prepare("SELECT image FROM products WHERE id = ?");
    $current_stmt->bind_param("i", $id);
    $current_stmt->execute();
    $current_product = $current_stmt->get_result()->fetch_assoc();
    $current_stmt->close();

    $image = $current_product['image'] ?? "";
    $new_image_uploaded = false;

    // Upload a replacement image only when the user selected one.
    if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {

        if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $error = "There was a problem uploading the image.";
        } elseif ($_FILES['image']['size'] > 5 * 1024 * 1024) {
            $error = "Image must be 5MB or smaller.";
        } else {
            $allowed_types = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $image_type = mime_content_type($_FILES['image']['tmp_name']);

            if (!in_array($image_type, $allowed_types, true)) {
                $error = "Only JPG, PNG, WEBP, and GIF images are allowed.";
            } else {
                $extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                $new_image = uniqid('product_', true) . '.' . $extension;
                $upload_dir = __DIR__ . '/uploads/';

                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $new_image)) {
                    $image = $new_image;
                    $new_image_uploaded = true;
                } else {
                    $error = "Failed to save the uploaded image.";
                }
            }
        }
    }

    if ($product_name == "" || $price == "") {
        $error = "Please fill in all required fields.";
    } elseif (!is_numeric($price) || (float)$price < 0) {
        $error = "Price must be a valid non-negative amount.";
    } elseif (!is_numeric($stock) || (int)$stock < 0) {
        $error = "Stock must be a valid non-negative whole number.";
    } elseif (!is_numeric($low_stock_level) || (int)$low_stock_level < 0) {
        $error = "Low-stock level must be a valid non-negative whole number.";
    }

    if ($error == "") {
        $stmt = $conn->prepare(
            "UPDATE products
             SET product_name = ?,
                 description = ?,
                 image = ?,
                 price = ?,
                 stock = ?,
                 low_stock_level = ?
             WHERE id = ?"
        );

        $stmt->bind_param(
            "sssdiii",
            $product_name,
            $description,
            $image,
            $price,
            $stock,
            $low_stock_level,
            $id
        );

        if ($stmt->execute()) {
            $message = "Product updated successfully.";

            // Remove the previous image only after the database update succeeds.
            if ($new_image_uploaded && !empty($current_product['image'])) {
                $old_image_path = __DIR__ . '/uploads/' . basename($current_product['image']);
                if (is_file($old_image_path)) {
                    unlink($old_image_path);
                }
            }
        } else {
            $error = "Failed to update product.";

            // If the new image was uploaded but the DB update failed, remove it.
            if ($new_image_uploaded) {
                $new_image_path = __DIR__ . '/uploads/' . basename($image);
                if (is_file($new_image_path)) {
                    unlink($new_image_path);
                }
            }
        }

        $stmt->close();
    }
}

/* GET PRODUCTS */

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

if ($search != "") {

    $search_term = "%" . $search . "%";

    $stmt = $conn->prepare(
        "SELECT * FROM products
         WHERE product_name LIKE ?
         OR description LIKE ?
         ORDER BY id DESC"
    );

    $stmt->bind_param("sss", $search_term, $search_term, $search_term);

    $stmt->execute();

    $result = $stmt->get_result();

} else {

    $result = $conn->query(
        "SELECT * FROM products ORDER BY id DESC"
    );

}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Products - AR Homemade</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

<style>
/* Shared AR Homemade navigation styling */
.topbar {
    background: white;
    border-bottom: 1px solid #e5e5e5;
    position: sticky;
    top: 0;
    z-index: 1000;
}
.navbar-inner {
    max-width: 1200px;
    margin: auto;
    padding: 15px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.logo {
    font-size: 25px;
    font-weight: 800;
    color: #d62828;
    text-decoration: none;
}
.nav-links {
    display: flex;
    align-items: center;
    gap: 25px;
}
.nav-links a {
    text-decoration: none;
    color: #333;
    font-weight: 600;
}
.nav-links a:hover {
    color: #d62828;
}
.nav-links a.active {
    background: #d62828;
    color: white !important;
    padding: 9px 18px;
    border-radius: 25px;
}

.cart-link {
    padding: 9px 18px;
}
.navbar-user {
    color: #333;
    font-weight: 600;
    white-space: nowrap;
}
@media (max-width: 800px) {
    .navbar-inner { padding: 12px 15px; }
    .logo { font-size: 21px; }
    .nav-links { gap: 10px; }
    .nav-links a:not(.active) { display: none; }
}
</style>

    <link rel="stylesheet" href="style.css">
</head>

<body class="bg-light">

<div class="topbar">
    <div class="navbar-inner">
        <a href="index.php" class="logo"><img src="images/ar_logo.png" alt="AR Homemade"></a>
        <div class="nav-links">
            <a href="index.php">Dashboard</a>
            <a href="orders.php">Orders</a>
            <a href="products.php" class="active">Menu Items</a>
            <a href="inventory.php">Ingredients & Recipes</a>
            <a href="reports.php">Reports</a>
            <a href="users.php">Users</a>
            <a href="logout.php">Logout</a>
        </div>
    </div>
</div>

<!-- NAVBAR -->
<!-- CONTENT -->

<div class="container mt-4">

    <div class="d-flex justify-content-between align-items-center">

        <div>

            <h2>Products</h2>

            <p class="text-muted">
                Manage AR Homemade products.
            </p>

        </div>

        <a
            href="index.php"
            class="btn btn-secondary"
        >
            Dashboard
        </a>

    </div>

    <!-- MESSAGES -->

    <?php if ($message != ""): ?>

        <div class="alert alert-success">

            <?php echo htmlspecialchars($message); ?>

        </div>

    <?php endif; ?>

    <?php if ($error != ""): ?>

        <div class="alert alert-danger">

            <?php echo htmlspecialchars($error); ?>

        </div>

    <?php endif; ?>

    <!-- ADD PRODUCT -->

    <div class="card shadow-sm mb-4">

        <div class="card-header">

            <strong>Add Product</strong>

        </div>

        <div class="card-body">

            <form method="POST" enctype="multipart/form-data">

                <div class="row">

                    <div class="col-md-4 mb-3">

                        <label class="form-label">
                            Product Name
                        </label>

                        <input
                            type="text"
                            name="product_name"
                            class="form-control"
                            required
                        >

                    </div>

                    <div class="col-md-4 mb-3">

                        <label class="form-label">
                            Description
                        </label>

                        <input
                            type="text"
                            name="description"
                            class="form-control"
                        >

                    </div>

                    <div class="col-md-4 mb-3">

                        <label class="form-label">
                            Product Image
                        </label>

                        <input
                            type="file"
                            name="image"
                            class="form-control"
                            accept="image/jpeg,image/png,image/webp,image/gif"
                        >

                        <small class="text-muted">
                            JPG, PNG, WEBP, or GIF (max 5MB)
                        </small>

                    </div>

                    <div class="col-md-2 mb-3">

                        <label class="form-label">
                            Price
                        </label>

                        <input
                            type="number"
                            name="price"
                            class="form-control"
                            step="0.01"
                            min="0"
                            required
                        >

                    </div>
</div>

                <button
                    type="submit"
                    name="add_product"
                    class="btn btn-primary"
                >
                    Add Product
                </button>

            </form>

        </div>

    </div>

   <!-- PRODUCT LIST -->

<div class="card shadow-sm">

    <div class="card-header">

        <div class="d-flex justify-content-between align-items-center">

            <strong>Product List</strong>

            <form method="GET" class="d-flex">

                <input
                    type="text"
                    name="search"
                    class="form-control form-control-sm me-2"
                    placeholder="Search product..."
                    value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
                >

                <button type="submit" class="btn btn-primary btn-sm">
                    Search
                </button>

                <?php if (isset($_GET['search']) && $_GET['search'] != ""): ?>

    <a href="products.php" class="btn btn-secondary btn-sm">
        Clear
    </a>

<?php endif; ?>

            </form>

        </div>

    </div>

        <div class="card-body p-0">

            <div class="table-responsive">

                <table class="table table-bordered table-hover mb-0">

                    <thead class="table-dark">

                        <tr>

                            <th>ID</th>

                            <th>Image</th>

                            <th>Product</th>

                            <th>Description</th>


                            <th>Price</th>


                            <th>Action</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php if ($result->num_rows > 0): ?>

                        <?php while ($row = $result->fetch_assoc()): ?>

                            <tr>

                                <td>
                                    <?php echo $row['id']; ?>
                                </td>

                                <td>
                                    <?php if (!empty($row['image'])): ?>
                                        <img
                                            src="uploads/<?php echo htmlspecialchars(basename($row['image'])); ?>"
                                            alt="<?php echo htmlspecialchars($row['product_name']); ?>"
                                            style="width:70px;height:70px;object-fit:cover;border-radius:10px;"
                                        >
                                    <?php else: ?>
                                        <span class="text-muted">No image</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php
                                    echo htmlspecialchars(
                                        $row['product_name']
                                    );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo htmlspecialchars(
                                        $row['description']
                                    );
                                    ?>
                                </td>

                                <td>
                                    ₱<?php
                                    echo number_format(
                                        $row['price'],
                                        2
                                    );
                                    ?>
                                </td>

                                <td>

                                    <a
    href="edit_product.php?id=<?php echo $row['id']; ?>"
    class="btn btn-warning btn-sm"
>
    Edit
</a>

<a
    href="products.php?delete=<?php echo $row['id']; ?>"
    class="btn btn-danger btn-sm"
    onclick="return confirm('Are you sure you want to delete this product?');"
>
    Delete
</a>

                                </td>

                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>

                        <tr>

                            <td
                                colspan="8"
                                class="text-center"
                            >
                                No products found.
                            </td>

                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

</body>

</html>
