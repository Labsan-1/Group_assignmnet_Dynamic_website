<?php
session_start();
include 'db.php';
require_once 'mailer.php'; // needed for sendOrderConfirmedEmail()

// Show DB errors while developing (remove these 2 lines in production)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$cart_items = [];
$error = '';
$success = '';

// Fetch all cart items for the user
$query = "SELECT c.*, p.name, p.price, p.Image 
          FROM cart c 
          JOIN products p ON c.product_id = p.product_id 
          WHERE c.user_id = ?";
if ($stmt = $con->prepare($query)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $cart_items = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_method = $_POST['payment_method'] ?? '';
    $name    = trim($_POST['name'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city    = trim($_POST['city'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    
    // Validate inputs
    if (empty($payment_method) || empty($name) || empty($address) || empty($city) || empty($phone)) {
        $error = 'All fields are required';
    } elseif (!preg_match("/^[0-9]{10}$/", $phone)) {
        $error = 'Please enter a valid 10-digit phone number';
    } elseif (empty($cart_items)) {
        $error = 'Your cart is empty.';
    } else {
        // Start transaction
        $con->begin_transaction();
        try {
            $created_order_ids = [];
            $grand_total = 0.0;

            // Insert orders for each cart item
            foreach ($cart_items as $product) {
                $line_total  = (float)$product['price'] * (int)$product['quantity'];
                $grand_total += $line_total;

                // ✅ FIX: insert only columns that exist: (user_id, product_id, total_amount)
                $insert_order_query = "INSERT INTO orders (user_id, product_id, total_amount) VALUES (?, ?, ?)";
                if ($order_stmt = $con->prepare($insert_order_query)) {
                    // types: i = int, d = double
                    $order_stmt->bind_param("iid", $user_id, $product['product_id'], $line_total);
                    $order_stmt->execute();
                    $order_id = $con->insert_id;
                    $created_order_ids[] = $order_id;
                    $order_stmt->close();

                    // Insert payment record (you already have payment_type column)
                    $insert_payment_query = "INSERT INTO payments (order_id, amount, payment_type) VALUES (?, ?, ?)";
                    if ($payment_stmt = $con->prepare($insert_payment_query)) {
                        $payment_stmt->bind_param("ids", $order_id, $line_total, $payment_method);
                        $payment_stmt->execute();
                        $payment_stmt->close();

                        // Clear cart item
                        $delete_cart_query = "DELETE FROM cart WHERE user_id = ? AND product_id = ?";
                        if ($cart_stmt = $con->prepare($delete_cart_query)) {
                            $cart_stmt->bind_param("ii", $user_id, $product['product_id']);
                            $cart_stmt->execute();
                            $cart_stmt->close();
                        }
                    }
                }
            }

            // Commit DB changes first
            $con->commit();

            // Fetch customer email + fallback name from users table
            $cust_email = '';
            $cust_name  = $name ?: 'Customer';
            if ($uStmt = $con->prepare("SELECT email, username FROM users WHERE user_id = ? LIMIT 1")) {
                $uStmt->bind_param("i", $user_id);
                $uStmt->execute();
                $uRes = $uStmt->get_result()->fetch_assoc();
                if ($uRes) {
                    $cust_email = $uRes['email'] ?? '';
                    if (!$name && !empty($uRes['username'])) $cust_name = $uRes['username'];
                }
                $uStmt->close();
            }

            // Build items for email
            $emailItems = [];
            foreach ($cart_items as $it) {
                $emailItems[] = [
                    'name'  => $it['name'],
                    'qty'   => (int)$it['quantity'],
                    // line total for each item
                    'price' => (float)$it['price'] * (int)$it['quantity'],
                ];
            }

            // Shipping block for email
            $shippingBlock = trim($name . "\n" . $address . "\n" . $city . "\nPhone: " . $phone);

            // Send confirmation email (do not block success if mail fails)
            $orderSummary = [
                'ids'            => $created_order_ids,
                'total'          => $grand_total,
                'currency'       => 'NPR',
                'payment_method' => $payment_method,
                'shipping'       => $shippingBlock
            ];

            $mailResult = true;
            if ($cust_email) {
                $mailResult = sendOrderConfirmedEmail($cust_email, $cust_name, $orderSummary, $emailItems);
            }

            if ($mailResult === true) {
                echo "<script>
                        alert('Order confirmed! A confirmation email has been sent. Your order will be delivered within few days.');
                        window.location.href = 'index.php';
                      </script>";
            } else {
                $err = htmlspecialchars($mailResult, ENT_QUOTES, 'UTF-8');
                echo "<script>
                        alert('Order confirmed, but email could not be sent: {$err}');
                        window.location.href = 'index.php';
                      </script>";
            }
            exit();

        } catch (Exception $e) {
            $con->rollback();
            error_log('Checkout error: ' . $e->getMessage());
            $error = 'Failed to save order. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout</title>
    <link rel="stylesheet" href="Styles/index.css">
    <style>
        .checkout-php,.checkout-container{max-width:800px;margin:20px auto;padding:20px}
        .product-summary{display:flex;align-items:center;gap:20px;padding:20px;border:1px solid #ddd;border-radius:8px;margin-bottom:20px}
        .product-summary img{width:100px;height:100px;object-fit:cover}
        .payment-options{margin:20px 0}
        .payment-method{display:none}
        .payment-label{display:inline-block;padding:10px 20px;margin-right:10px;background:#f0f0f0;border-radius:4px;cursor:pointer}
        .payment-method:checked + .payment-label{background:#4CAF50;color:#fff}
        .payment-details{margin-top:20px;padding:20px;border:1px solid #ddd;border-radius:8px;display:none}
        .shipping-form{margin-top:20px}
        .form-group{margin-bottom:15px}
        .form-group label{display:block;margin-bottom:5px;font-weight:bold}
        .form-group input{width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;font-size:1em}
        .confirm-btn{background:#4CAF50;color:#fff;padding:10px 20px;border:none;border-radius:4px;cursor:pointer;font-size:1.1em;margin-top:20px;width:100%}
        .error-message{color:#ff0000;padding:10px;margin:10px 0;background:#ffe6e6;border-radius:4px}
    </style>
</head>
<body>
<header>
    <div class="header-content">
        <div class="header-title"><h1>Checkout</h1></div>
        <div class="header-navigation">
            <nav>
                <ul>
                    <li><a href="cart.php">Back to Cart</a></li>
                    <li><a href="logout.php">Logout</a></li>
                </ul>
            </nav>
        </div>
    </div>
</header>

<div class="checkout-container">
    <?php if ($error): ?>
        <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if (!empty($cart_items)): ?>
        <?php foreach ($cart_items as $product): ?>
            <div class="product-summary">
                <img src="<?php echo htmlspecialchars($product['Image']); ?>" 
                     alt="<?php echo htmlspecialchars($product['name']); ?>">
                <div>
                    <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                    <p>Price: $<?php echo number_format($product['price'], 2); ?></p>
                    <p>Quantity: <?php echo (int)$product['quantity']; ?></p>
                    <p>Total: $<?php echo number_format($product['price'] * $product['quantity'], 2); ?></p>
                </div>
            </div>
        <?php endforeach; ?>

        <form method="POST" onsubmit="return validateForm()">
            <div class="payment-options">
                <h3>Select Payment Method</h3>
                <input type="radio" id="offline" name="payment_method" value="Cash on Delivery" class="payment-method" checked>
                <label for="offline" class="payment-label">Cash on Delivery</label>
            </div>

            <div id="offline-payment" class="payment-details" style="display:block;">
                <h3>Cash on Delivery</h3>
                <p>You will pay $ 
                <?php 
                    $total_amount = 0;
                    foreach ($cart_items as $item) {
                        $total_amount += $item['price'] * $item['quantity'];
                    }
                    echo number_format($total_amount, 2); 
                ?> upon delivery.</p>
            </div>

            <div class="shipping-form">
                <h3>Shipping Address</h3>
                <div class="form-group">
                    <label for="name">Full Name:</label>
                    <input type="text" id="name" name="name" required>
                </div>

                <div class="form-group">
                    <label for="address">Address:</label>
                    <input type="text" id="address" name="address" required>
                </div>

                <div class="form-group">
                    <label for="city">City:</label>
                    <input type="text" id="city" name="city" required>
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number:</label>
                    <input type="tel" id="phone" name="phone" required pattern="[0-9]{10}" title="Please enter a valid 10-digit phone number">
                </div>
            </div>

            <button type="submit" class="confirm-btn">Confirm Order</button>
        </form>
    <?php else: ?>
        <p>No items in cart. <a href="index.php">Continue shopping</a></p>
    <?php endif; ?>
</div>

<script>
function validateForm() {
    const phone = document.getElementById('phone').value;
    if (!/^[0-9]{10}$/.test(phone)) {
        alert('Please enter a valid 10-digit phone number');
        return false;
    }
    return confirm('Are you sure you want to place this order?');
}
</script>
</body>
</html>
