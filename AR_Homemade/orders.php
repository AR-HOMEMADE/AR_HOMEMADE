<?php

require_once "db.php";
require_once "auth.php";
require_once "role_check.php";

require_role(['Administrator', 'Staff']);

// Store the actual customer separately from the staff member who records the order.
// This also upgrades existing databases automatically without deleting any data.
$conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS customer_name VARCHAR(150) NULL AFTER user_id");

$message = "";
$error = "";

/* CREATE ORDER */

if (isset($_POST['create_order'])) {
    $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT) ?: 0;
    $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT) ?: 0;
    $user_id = (int)$_SESSION['user_id'];
    $customer_name = trim($_POST['customer_name'] ?? '');
    $customer_name = $customer_name === '' ? 'Walk-in Customer' : substr($customer_name, 0, 150);

    if ($product_id <= 0) {
        $error = "Please select a valid product.";
    } elseif ($quantity <= 0) {
        $error = "Quantity must be greater than zero.";
    } else {
        $conn->begin_transaction();
        try {
            // Lock the product row while checking its current availability.
            $stmt = $conn->prepare("SELECT product_name, price FROM products WHERE id = ? FOR UPDATE");
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $product = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$product) {
                throw new DomainException("Product not found.");
            }
            $price = (float)$product['price'];
            $total = $price * $quantity;
            $order = $conn->prepare("INSERT INTO orders (user_id, customer_name, total_amount, status) VALUES (?, ?, ?, 'Pending')");
            $order->bind_param("isd", $user_id, $customer_name, $total);
            $order->execute();
            $order_id = (int)$conn->insert_id;
            $order->close();

            $item = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, subtotal) VALUES (?, ?, ?, ?, ?)");
            $item->bind_param("iiidd", $order_id, $product_id, $quantity, $price, $total);
            $item->execute();
            $item->close();

            $conn->commit();
            $message = "Order created successfully. Order #" . $order_id;
        } catch (DomainException $e) {
            $conn->rollback();
            $error = $e->getMessage();
        } catch (Throwable $e) {
            $conn->rollback();
            error_log("AR Homemade order creation failed: " . $e->getMessage());
            $error = "The order could not be created. Please check the product and try again.";
        }
    }
}

/* RECORD PAYMENT */

if (isset($_POST['record_payment'])) {
    $order_id = filter_input(INPUT_POST, 'payment_order_id', FILTER_VALIDATE_INT) ?: 0;
    $amount_paid = (float)($_POST['amount_paid'] ?? 0);
    $payment_method = 'Cash';

    if ($order_id <= 0) {
        $error = "Please select a valid order.";
    } elseif ($amount_paid <= 0) {
        $error = "Amount paid must be greater than zero.";
    } else {
        $conn->begin_transaction();
        try {
            $payment_query = $conn->prepare("SELECT total_amount, status, payment_status FROM orders WHERE id = ? FOR UPDATE");
            $payment_query->bind_param("i", $order_id);
            $payment_query->execute();
            $payment_order = $payment_query->get_result()->fetch_assoc();
            $payment_query->close();

            if (!$payment_order) {
                throw new DomainException("Order not found.");
            }
            if (in_array($payment_order['status'], ['Completed', 'Cancelled'], true)) {
                throw new DomainException("Payment cannot be changed for a completed or cancelled order.");
            }
            if ($payment_order['payment_status'] === 'Paid') {
                throw new DomainException("Payment has already been recorded for this order.");
            }

            $total_amount = (float)$payment_order['total_amount'];
            if ($amount_paid < $total_amount) {
                throw new DomainException("Amount paid must be at least ₱" . number_format($total_amount, 2) . ".");
            }
            $change_amount = max(0, $amount_paid - $total_amount);
            $payment_update = $conn->prepare("UPDATE orders SET payment_method = ?, payment_status = 'Paid', amount_paid = ?, change_amount = ? WHERE id = ?");
            $payment_update->bind_param("sddi", $payment_method, $amount_paid, $change_amount, $order_id);
            $payment_update->execute();
            $payment_update->close();
            $conn->commit();
            $message = "Payment recorded successfully.";
        } catch (DomainException $e) {
            $conn->rollback();
            $error = $e->getMessage();
        } catch (Throwable $e) {
            $conn->rollback();
            error_log("AR Homemade payment recording failed: " . $e->getMessage());
            $error = "Payment could not be recorded. Please try again.";
        }
    }
}

/* UPDATE ORDER STATUS */

if (isset($_POST['update_status'])) {
    $order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT) ?: 0;
    $new_status = trim($_POST['status'] ?? '');
    $allowed_statuses = ['Pending', 'Preparing', 'Ready', 'Completed', 'Cancelled'];

    if ($order_id <= 0 || !in_array($new_status, $allowed_statuses, true)) {
        $error = "Invalid order status.";
    } else {
        $conn->begin_transaction();
        try {
            // Lock the order so two requests cannot complete it at the same time.
            $order_query = $conn->prepare("SELECT status, total_amount, payment_status FROM orders WHERE id = ? FOR UPDATE");
            $order_query->bind_param("i", $order_id);
            $order_query->execute();
            $order = $order_query->get_result()->fetch_assoc();
            $order_query->close();

            if (!$order) {
                throw new DomainException("Order not found.");
            }
            if ($order['status'] === 'Completed') {
                throw new DomainException("Completed orders cannot be changed.");
            }
            if ($order['status'] === 'Cancelled' && $new_status !== 'Cancelled') {
                throw new DomainException("Cancelled orders cannot be reopened.");
            }

            if ($new_status === 'Completed') {
                if ($order['payment_status'] !== 'Paid') {
                    throw new DomainException("Record the payment first before completing this order.");
                }

                $items_query = $conn->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
                $items_query->bind_param("i", $order_id);
                $items_query->execute();
                $items_result = $items_query->get_result();
                if ($items_result->num_rows === 0) {
                    $items_query->close();
                    throw new DomainException("This order has no items and cannot be completed.");
                }

                $orderItems = [];
                while ($item = $items_result->fetch_assoc()) {
                    $product_id = (int)$item['product_id'];
                    $quantity = (int)$item['quantity'];
                    if ($product_id <= 0 || $quantity <= 0) {
                        $items_query->close();
                        throw new DomainException("The order contains an invalid item.");
                    }
                    $orderItems[] = ['product_id' => $product_id, 'quantity' => $quantity];
                }
                $items_query->close();
                if (!$orderItems) {
                    throw new DomainException("This order has no items and cannot be completed.");
                }

                // Aggregate recipe requirements across all menu items before changing stock.
                $required = [];
                foreach ($orderItems as $orderItem) {
                    $recipe = $conn->prepare("SELECT ingredient_id, quantity_required FROM product_recipes WHERE product_id = ?");
                    $recipe->bind_param("i", $orderItem['product_id']);
                    $recipe->execute();
                    $recipeRows = $recipe->get_result();
                    if ($recipeRows->num_rows === 0) {
                        $recipe->close();
                        $missing = $conn->prepare("SELECT product_name FROM products WHERE id = ?");
                        $missing->bind_param("i", $orderItem['product_id']);
                        $missing->execute();
                        $missingName = $missing->get_result()->fetch_assoc()['product_name'] ?? 'Menu item';
                        $missing->close();
                        throw new DomainException("No ingredient recipe is configured for '" . $missingName . "'. Ask the administrator to set its recipe before completing this order.");
                    }
                    while ($recipeRow = $recipeRows->fetch_assoc()) {
                        $ingredientId = (int)$recipeRow['ingredient_id'];
                        $amount = (float)$recipeRow['quantity_required'] * $orderItem['quantity'];
                        if (!isset($required[$ingredientId])) $required[$ingredientId] = 0;
                        $required[$ingredientId] += $amount;
                    }
                    $recipe->close();
                }

                // Check all ingredient balances first, then deduct within this transaction.
                foreach ($required as $ingredientId => $amount) {
                    $ingredient = $conn->prepare("SELECT ingredient_name, quantity_in_stock, unit FROM ingredients WHERE id = ? FOR UPDATE");
                    $ingredient->bind_param("i", $ingredientId);
                    $ingredient->execute();
                    $ingredientRow = $ingredient->get_result()->fetch_assoc();
                    $ingredient->close();
                    if (!$ingredientRow) throw new DomainException("A recipe refers to an ingredient that no longer exists.");
                    if ((float)$ingredientRow['quantity_in_stock'] + 0.000001 < $amount) {
                        throw new DomainException("Not enough " . $ingredientRow['ingredient_name'] . " in stock. Required: " . rtrim(rtrim(number_format($amount, 3, '.', ''), '0'), '.') . " " . $ingredientRow['unit'] . ".");
                    }
                }
                foreach ($required as $ingredientId => $amount) {
                    $deduct = $conn->prepare("UPDATE ingredients SET quantity_in_stock = quantity_in_stock - ? WHERE id = ? AND quantity_in_stock >= ?");
                    $deduct->bind_param("did", $amount, $ingredientId, $amount);
                    $deduct->execute();
                    if ($deduct->affected_rows !== 1) {
                        $deduct->close();
                        throw new DomainException("Ingredient stock changed during order completion. Please retry.");
                    }
                    $deduct->close();
                    $move = $conn->prepare("INSERT INTO ingredient_movements (ingredient_id, movement_type, quantity, remarks) VALUES (?, 'Stock Out', ?, ?)");
                    $remarks = "Order #" . $order_id . " recipe deduction";
                    $move->bind_param("ids", $ingredientId, $amount, $remarks);
                    $move->execute();
                    $move->close();
                }


                // Prevent a duplicate sales row if data was already partially or manually recorded.
                $sale_check = $conn->prepare("SELECT id FROM sales WHERE order_id = ? LIMIT 1");
                $sale_check->bind_param("i", $order_id);
                $sale_check->execute();
                $has_sale = $sale_check->get_result()->num_rows > 0;
                $sale_check->close();
                if ($has_sale) {
                    throw new DomainException("A sales record already exists for this order. Review the records before completing it again.");
                }

                $total_amount = (float)$order['total_amount'];
                $sale = $conn->prepare("INSERT INTO sales (order_id, amount) VALUES (?, ?)");
                $sale->bind_param("id", $order_id, $total_amount);
                $sale->execute();
                $sale->close();
            }

            $update_order = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $update_order->bind_param("si", $new_status, $order_id);
            $update_order->execute();
            $update_order->close();
            $conn->commit();
            $message = $new_status === 'Completed'
                ? "Order completed successfully. Recipe ingredients and sales updated."
                : "Order status updated successfully.";
        } catch (DomainException $e) {
            $conn->rollback();
            $message = $e->getMessage();
        } catch (Throwable $e) {
            $conn->rollback();
            error_log("AR Homemade order status update failed: " . $e->getMessage());
            $error = "Order status could not be updated. Please review the order and try again.";
        }
    }
}

/* GET PRODUCTS */

$products = $conn->query(
    "SELECT id, product_name, price
     FROM products
     ORDER BY product_name ASC"
);

/* GET ORDERS */

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

if ($search != "") {

    $search_id = intval($search);
    $search_term = "%" . $search . "%";

    $stmt = $conn->prepare(
        "SELECT
            orders.id,
            orders.order_date,
            orders.total_amount,
            orders.customer_name,
            orders.status,
            orders.payment_method,
            orders.payment_status,
            orders.amount_paid,
            orders.change_amount,
            users.fullname AS created_by,
            users.username
         FROM orders
         INNER JOIN users
            ON orders.user_id = users.id
         WHERE orders.id = ?
            OR COALESCE(NULLIF(orders.customer_name, ''), 'Walk-in Customer') LIKE ?
            OR users.fullname LIKE ?
            OR users.username LIKE ?
         ORDER BY orders.id DESC"
    );

    $stmt->bind_param("isss", $search_id, $search_term, $search_term, $search_term);

    $stmt->execute();

    $orders = $stmt->get_result();

} else {

    $orders = $conn->query(
        "SELECT
            orders.id,
            orders.order_date,
            orders.total_amount,
            orders.customer_name,
            orders.status,
            orders.payment_method,
            orders.payment_status,
            orders.amount_paid,
            orders.change_amount,
            users.fullname AS created_by,
            users.username
         FROM orders
         INNER JOIN users
            ON orders.user_id = users.id
         ORDER BY orders.id DESC"
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

    <title>Orders - AR Homemade</title>

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

.orders-table th,
.orders-table td {
    vertical-align: middle;
}

.order-actions {
    min-width: 210px;
}

.payment-details summary {
    list-style: none;
    cursor: pointer;
    display: inline-block;
}

.payment-details summary::-webkit-details-marker {
    display: none;
}

.payment-box {
    padding: 12px;
    background: #fff7f4;
    border: 1px solid #eadfd9;
    border-radius: 10px;
}

.payment-box .form-control:focus,
.order-actions .form-select:focus {
    border-color: #d62828;
    box-shadow: 0 0 0 .15rem rgba(214, 40, 40, .12);
}

@media (max-width: 800px) {
    .orders-table {
        min-width: 1050px;
    }
}

</style>

    <link rel="stylesheet" href="style.css">
</head>

<body class="bg-light">

<div class="topbar">
    <div class="navbar-inner">
        <a href="index.php" class="logo"><img src="images/ar_logo.png" alt="AR Homemade"></a>
        <div class="nav-links">
            <?php if (($_SESSION['role'] ?? '') === 'Administrator'): ?>
            <a href="index.php">Dashboard</a>
            <a href="orders.php" class="active">Orders</a>
            <a href="products.php">Menu Items</a>
            <a href="inventory.php">Ingredients & Recipes</a>
            <a href="reports.php">Reports</a>
            <a href="users.php">Users</a>
            <?php else: ?>
            <a href="orders.php" class="active">Orders</a>
            <?php endif; ?>
            <a href="logout.php">Logout</a>
        </div>
    </div>
</div>

<div class="container mt-4">

    <div class="d-flex justify-content-between align-items-center">

        <div>

            <h2>Orders</h2>

            <p class="text-muted">
                Manage customer orders and order status.
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

    <!-- CREATE ORDER -->

    <div class="card shadow-sm mb-4">

        <div class="card-header">
            <strong>Create Order</strong>
        </div>

        <div class="card-body">

            <form method="POST">

                <div class="row">

                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="customer_name">Customer Name <span class="text-muted">(Optional)</span></label>
                        <input
                            type="text"
                            id="customer_name"
                            name="customer_name"
                            class="form-control"
                            maxlength="150"
                            placeholder="Leave blank for Walk-in Customer"
                        >
                        <small class="text-muted">This is the buyer's name, not the staff account.</small>
                    </div>

                    <div class="col-md-6 mb-3">

                        <label class="form-label">
                            Product
                        </label>

                        <select
                            name="product_id"
                            class="form-select"
                            required
                        >

                            <option value="">
                                Select Product
                            </option>

                            <?php while ($product = $products->fetch_assoc()): ?>

                                <option
                                    value="<?php echo $product['id']; ?>"
                                >

                                    <?php
                                    echo htmlspecialchars(
                                        $product['product_name']
                                    );
                                    ?>

                                    -
                                    ₱<?php
                                    echo number_format(
                                        $product['price'],
                                        2
                                    );
                                    ?>

                                </option>

                            <?php endwhile; ?>

                        </select>

                    </div>

                    <div class="col-md-3 mb-3">

                        <label class="form-label">
                            Quantity
                        </label>

                        <input
                            type="number"
                            name="quantity"
                            class="form-control"
                            min="1"
                            required
                        >

                    </div>

                </div>

                <button
                    type="submit"
                    name="create_order"
                    class="btn btn-primary"
                >
                    Create Order
                </button>

            </form>

        </div>

    </div>

   <!-- ORDER LIST -->

<div class="card shadow-sm">

    <div class="card-header">

        <div class="d-flex justify-content-between align-items-center">

            <strong>Order List</strong>

            <form method="GET" class="d-flex">

                <input
                    type="text"
                    name="search"
                    class="form-control form-control-sm me-2"
                    placeholder="Search Order ID or Customer..."
                    value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>"
                >

                <button type="submit" class="btn btn-primary btn-sm">
                    Search
                </button>

                <?php if (isset($_GET['search']) && $_GET['search'] != ""): ?>

                    <a href="orders.php" class="btn btn-secondary btn-sm ms-2">
                        Clear
                    </a>

                <?php endif; ?>

            </form>

        </div>

    </div>

        <div class="card-body p-0">

            <div class="table-responsive">

                <table class="table table-bordered table-hover mb-0 orders-table">

                    <thead class="table-dark">
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Created By</th>
                            <th>Date</th>
                            <th>Total</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if ($orders->num_rows > 0): ?>

                        <?php while ($row = $orders->fetch_assoc()): ?>

                            <?php
                                $total = (float)$row['total_amount'];
                                $amount_paid = (float)($row['amount_paid'] ?? 0);
                                $change_amount = (float)($row['change_amount'] ?? 0);
                                $payment_status = $row['payment_status'] ?? 'Pending';
                            ?>

                            <tr>
                                <td>#<?php echo $row['id']; ?></td>

                                <td><?php echo htmlspecialchars(trim((string)($row['customer_name'] ?? '')) !== '' ? $row['customer_name'] : 'Walk-in Customer'); ?></td>
                                <td><?php echo htmlspecialchars($row['created_by']); ?></td>

                                <td>
                                    <?php echo date('M d, Y', strtotime($row['order_date'])); ?><br>
                                    <small class="text-muted"><?php echo date('h:i A', strtotime($row['order_date'])); ?></small>
                                </td>

                                <td class="fw-bold">
                                    ₱<?php echo number_format($total, 2); ?>
                                </td>

                                <td>
                                    <strong><?php echo htmlspecialchars($row['payment_method'] ?? 'Cash'); ?></strong><br>
                                    <?php if ($payment_status === 'Paid'): ?>
                                        <span class="badge bg-success">Paid</span>
                                        <div class="small text-muted mt-1">
                                            Paid: ₱<?php echo number_format($amount_paid, 2); ?><br>
                                            <?php if ($change_amount > 0): ?>
                                                Change: ₱<?php echo number_format($change_amount, 2); ?>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Unpaid</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php if ($row['status'] == 'Pending'): ?>
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    <?php elseif ($row['status'] == 'Preparing'): ?>
                                        <span class="badge bg-primary">Preparing</span>
                                    <?php elseif ($row['status'] == 'Ready'): ?>
                                        <span class="badge bg-info text-dark">Ready</span>
                                    <?php elseif ($row['status'] == 'Completed'): ?>
                                        <span class="badge bg-success">Completed</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Cancelled</span>
                                    <?php endif; ?>
                                </td>

                                <td class="order-actions">
                                    <?php if ($payment_status !== 'Paid'): ?>
                                        <details class="payment-details mb-2">
                                            <summary class="btn btn-success btn-sm">Record Payment</summary>
                                            <div class="payment-box mt-2">
                                                <form method="POST">
                                                    <input type="hidden" name="payment_order_id" value="<?php echo $row['id']; ?>">
                                                    <input type="hidden" name="payment_method" value="Cash">

                                                    <div class="small text-muted mb-1">
                                                        Order total: <strong>₱<?php echo number_format($total, 2); ?></strong>
                                                    </div>

                                                    <label class="form-label small mb-1">Amount Paid</label>
                                                    <input
                                                        type="number"
                                                        name="amount_paid"
                                                        class="form-control form-control-sm mb-2"
                                                        min="<?php echo number_format($total, 2, '.', ''); ?>"
                                                        step="0.01"
                                                        placeholder="Enter cash received"
                                                        required
                                                    >

                                                    <button type="submit" name="record_payment" class="btn btn-success btn-sm w-100">
                                                        Save Payment
                                                    </button>
                                                </form>
                                            </div>
                                        </details>
                                    <?php else: ?>
                                        <span class="text-success small d-block mb-2">Payment complete</span>
                                    <?php endif; ?>

                                    <?php if ($row['status'] !== 'Completed' && $row['status'] !== 'Cancelled'): ?>
                                        <form method="POST" class="d-flex gap-1">
                                            <input type="hidden" name="order_id" value="<?php echo $row['id']; ?>">

                                            <select name="status" class="form-select form-select-sm">
                                                <option value="Pending" <?php echo ($row['status'] == 'Pending') ? 'selected' : ''; ?>>Pending</option>
                                                <option value="Preparing" <?php echo ($row['status'] == 'Preparing') ? 'selected' : ''; ?>>Preparing</option>
                                                <option value="Ready" <?php echo ($row['status'] == 'Ready') ? 'selected' : ''; ?>>Ready</option>
                                                <option value="Completed" <?php echo ($row['status'] == 'Completed') ? 'selected' : ''; ?>>Completed</option>
                                                <option value="Cancelled">Cancelled</option>
                                            </select>

                                            <button type="submit" name="update_status" class="btn btn-primary btn-sm">
                                                Update
                                            </button>
                                        </form>
                                    <?php elseif ($row['status'] === 'Cancelled'): ?>
                                        <span class="text-muted small">Cancelled</span>
                                    <?php else: ?>
                                        <span class="text-muted small">Completed</span>
                                    <?php endif; ?>
                                </td>
                            </tr>

                        <?php endwhile; ?>

                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center">No orders found.</td>
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
