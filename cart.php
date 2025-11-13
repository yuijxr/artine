<?php
require_once 'includes/db_connect.php';
require_once 'includes/session.php';

// If user logged in, we'll fetch items from DB; otherwise the page will be driven by localStorage via JS
$is_logged = is_logged_in();
$cart_items = [];

if ($is_logged) {
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare('SELECT c.*, p.name, p.price, p.image_url, cat.name as category_name 
                           FROM cart c 
                           JOIN products p ON c.product_id = p.product_id 
                           JOIN categories cat ON p.category_id = cat.category_id 
                           WHERE c.user_id = ?');
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    require_once __DIR__ . '/includes/helpers.php';
    while ($row = $result->fetch_assoc()) {
        // Determine the correct image folder based on category
        $image_folder = '';
        $category = strtolower($row['category_name']);
        if (strpos($category, 'shirt') !== false) {
            $image_folder = 'shirts/';
        } elseif (strpos($category, 'cap') !== false) {
            $image_folder = 'caps/';
        } elseif (strpos($category, 'perfume') !== false) {
            $image_folder = 'perfumes/';
        }
        // Use resolve_image_path to map DB value to the new uploads/product_img structure
        $image_path = resolve_image_path($row['image_url'] ?? '', $row['category_name'] ?? '');
        
        $cart_items[] = [
            'id' => $row['product_id'],
            'name' => $row['name'],
            'price' => $row['price'],
            'quantity' => $row['quantity'],
            'size' => $row['size'],
            'image_url' => $image_path,
            'category_name' => $row['category_name']
        ];
    }
    $stmt->close();
}
?>
<!doctype html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/cart.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Dekko&family=Devonshire&family=Montserrat:ital,wght@0,100..900;1,100..900&family=Outfit:wght@100..900&display=swap" rel="stylesheet">
    <title>FitCheck</title>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    <main class="main-content cart-page">
        <h1 class="cart-title">Shopping Cart</h1>
        <div class="cart-container" style="display: none;">
            <div class="cart-items" id="cart-items">
                <!-- JS will populate cart items from localStorage or AJAX -->
            </div>

            <aside class="cart-summary" style="display: none;">
                <div class="cart-summary-content">
                    <h3>Order Summary</h3>
                    <div class="summary-row"><span>Items</span><span id="items-count">0</span></div>
                    <div class="summary-row"><span>Subtotal</span><span>₱<span id="subtotal">0.00</span></span></div>
                    <div class="summary-row"><span>Shipping Fee</span><span>₱49.00</span></div>
                    <div class="summary-row"><span>Tax (12% VAT)</span><span>₱<span id="tax">0.00</span></span></div>
                    <div class="summary-row total"><span>Grand Total</span><span>₱<span id="grand">0.00</span></span></div>
                    <button id="checkout-btn" class="checkout-btn" onclick="location.href='checkout.php'">Proceed to Checkout</button>
                    <a href="index.php" class="continue-shopping-btn">Continue Shopping</a>
                </div>
            </aside>
        </div>
        
        <div class="empty-cart" id="emptyCart" style="display: none;">
            <div class="empty-cart-content">
                <i class="fa fa-shopping-cart"></i>
                <h2>Your cart is empty</h2>
                <p>Looks like you haven't added any items yet.</p>
                <a href="index.php" class="shop-now-btn">Shop Now</a>
            </div>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>

    <script>
        // Pre-load cart data for logged in users
        <?php if ($is_logged): ?>
        window.INITIAL_CART_DATA = <?php echo json_encode($cart_items); ?>;
        <?php endif; ?>
    </script>
    <script src="assets/js/index.js"></script>
    <script src="assets/js/cart.js"></script>
</body>
</html>
