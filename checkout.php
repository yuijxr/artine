<?php
require_once 'includes/db_connect.php';
require_once 'includes/session.php';
require_login();
$user = current_user($conn);
?>
<!doctype html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/cart.css">
    <link rel="stylesheet" href="assets/css/checkout.css">
    <link rel="stylesheet" href="assets/css/profile.css">
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Dekko&family=Devonshire&family=Montserrat:ital,wght@0,100..900;1,100..900&family=Outfit:wght@100..900&display=swap" rel="stylesheet">
    <title>FitCheck</title>
</head>
<body data-loading="true">
    <?php include 'includes/header.php'; ?>
    <main class="checkout-page main-content">
        <h1>Checkout</h1>
        <div class="checkout-container">
            <section>
                <div class="addr-pay-row">
                    <div id="addresses-list"></div>
                    <div class="checkout-payment">
                        <div id="payment-methods"></div>
                    </div>
                </div>
                <h3 class="checkout-section-title">Items</h3>
                <div class="checkout-items" id="checkout-items"></div>
            </section>
            <aside class="checkout-summary" id="summary">
                <div>
                    <h3>Order Summary</h3>
                    <div class="checkout-summary-row">
                        <span>Items</span>
                        <span id="items-count">0</span>
                    </div>
                    <div class="checkout-summary-row">
                        <span>Subtotal</span>
                        <span>₱<span id="subtotal">0.00</span></span>
                    </div>
                    <div class="checkout-summary-row">
                        <span>Shipping Fee</span>
                        <span>₱49.00</span>
                    </div>
                    <div class="checkout-summary-row">
                        <span>Tax (12% VAT)</span>
                        <span>₱<span id="tax">0.00</span></span>
                    </div>
                    <div class="checkout-summary-row total">
                        <span>Total</span>
                        <span>₱<span id="total">0.00</span></span>
                    </div>
                    <button class="checkout-btn" id="place-order">Place Order</button>
                </div>
                <!-- payment methods moved to the left column (addresses) -->
            </aside>
        </div>
    </main>

    <!-- Payment info modal (used for GCash / Credit Card extra info) - styled like the address modal -->
    <div id="payment-modal" class="modal-overlay" aria-hidden="true">
        <div class="modal pm-modal-panel" role="dialog" aria-modal="true">
            <button class="pm-modal-close modal-close" aria-label="Close"><i class="fa fa-times"></i></button>
            <h3 id="pm-modal-title">Payment Info</h3>
            <div id="pm-modal-body"></div>
            <div class="modal-btn pm-modal-actions">
                <button id="pm-modal-cancel" class="btn">Cancel</button>
                <button id="pm-modal-confirm" class="btn primary">Confirm</button>
            </div>
        </div>
    </div>
    <?php
    // Provide initial checkout data server-side to avoid client-side flash.
    // Fetch addresses, payment methods, and cart items for the current logged-in user.
    $init = [
        'addresses' => [],
        'payment_methods' => [],
        'items' => []
    ];
    // Addresses
    $stmt = $conn->prepare(
        'SELECT address_id,user_id,full_name,phone,house_number,barangay,street,city,province,postal_code,country,is_default FROM addresses WHERE user_id = ? ORDER BY is_default DESC, created_at DESC'
    );
    $uid = intval($_SESSION['user_id']);
    $stmt->bind_param('i', $uid);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $init['addresses'][] = $r;
        }
    }
    $stmt->close();
    // Payment methods
    $res = $conn->query('SELECT method_id, name FROM payment_methods');
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $init['payment_methods'][] = $r;
        }
    }
    // Cart items (same shape as api/fetch_cart_items.php)
    $sql = "SELECT c.cart_id, c.product_id, c.quantity, c.size, p.name, p.price, p.image_url, cat.name AS category_name 
             FROM cart c 
             JOIN products p ON p.product_id = c.product_id 
             JOIN categories cat ON p.category_id = cat.category_id 
             WHERE c.user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $uid);
    if ($stmt->execute()) {
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            // map the DB image value to the uploads path
            require_once __DIR__ . '/includes/helpers.php';
            $img = $r['image_url'] ?: '';
            $resolved = resolve_image_path($img, $r['category_name']);
            $init['items'][] = [
                'cart_id' => $r['cart_id'],
                'product_id' => $r['product_id'],
                'name' => $r['name'],
                'price' => $r['price'],
                'quantity' => $r['quantity'],
                'size' => $r['size'],
                // include both keys the JS might expect
                'image' => $resolved,
                'image_url' => $resolved
            ];
        }
    }
    $stmt->close();
    // Output the init data as a JS variable so checkout.js can use it synchronously
    $json = json_encode($init, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    echo "<script>window.INIT_CHECKOUT_DATA = $json;</script>";
    ?>

    <script src="assets/js/index.js"></script>
    <script src="assets/js/checkout.js"></script>
    
    <!-- Include account address modal and logic so checkout can open the same manager inline -->
    <!-- Modal markup is the same as in account.php and relies on assets/js/account.js -->
    <div id="address-modal" class="modal-overlay" style="display:none;">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="addr-modal-title">
            <button id="modal_close_icon" class="modal-close" aria-label="Close">
                <i class="fa fa-times"></i>
            </button>
            <h3 id="addr-modal-title">Manage Addresses</h3>
            <div id="modal_list">
                <div class="modal-head">
                    <strong>Your saved addresses</strong>
                    <a href="#" id="modal_add_new" class="add-address-btn">Add address</a>
                </div>
                <div id="modal_addresses_list"></div>
                <p class="modal-instruction">
                    Click an address to select it as default, then click Save changes
                </p>
                <div class="modal-btn">
                    <button id="modal_save_changes" class="btn primary">Save changes</button>
                    <button id="modal_cancel_changes" class="btn">Cancel</button>
                </div>
                <!-- Add address link moved to header for compact layout -->
            </div>
            <form id="addr-modal-form" style="display:none;">
                <input class="input-form" type="hidden" id="modal_address_id">
                <?php
                $pref_full_name = htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
                $pref_phone = htmlspecialchars($user['phone'] ?? '');
                $pref_country = htmlspecialchars($user['country'] ?? 'Philippines');
                ?>
                <div class="name-row">
                    <div class="form-group">
                        <label>Full name</label>
                        <input class="input-form" id="modal_full_name" type="text" required value="<?php echo $pref_full_name; ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input class="input-form" id="modal_phone" type="tel" required value="<?php echo $pref_phone; ?>" readonly>
                    </div>
                </div>

                <div class="info-row">
                    <div class="form-group">
                        <label>House number</label>
                        <input class="input-form" id="modal_house_number" type="text" required>
                    </div>
                    <div class="form-group">
                        <label>Street</label>
                        <input class="input-form" id="modal_street" type="text" required>
                    </div>
                </div>

                <div class="info-row">
                    <div class="form-group">
                        <label>Barangay</label>
                        <input class="input-form" id="modal_barangay" type="text" required>
                    </div>
                    <div class="form-group">
                        <label>City / Municipality</label>
                        <input class="input-form" id="modal_city" type="text" required>
                    </div>
                </div>

                <div class="info-row">
                    <div class="form-group">
                        <label>Province</label>
                        <input class="input-form" id="modal_province" type="text" required>
                    </div>
                    <div class="form-group">
                        <label>Postal code</label>
                        <input class="input-form" id="modal_postal_code" type="text" required>
                    </div>
                    <div class="form-group">
                        <label>Country</label>
                        <input class="input-form" id="modal_country" type="text" value="<?php echo $pref_country; ?>" readonly required>
                    </div>
                </div>

                <div class="modal-btn">
                    <button type="button" id="modal_back" class="btn">Back to list</button>
                    <button type="submit" id="modal-save" class="btn primary">Save</button>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/js/account.js"></script>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
