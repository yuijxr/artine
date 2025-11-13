<?php
require_once "includes/session.php";
require_once "includes/db_connect.php";

if (!is_logged_in()) {
    header("Location: login.php");
    exit();
}

$user = current_user($conn);
// prefer full name for display
$fullname = trim((($user["first_name"] ?? '') . ' ' . ($user["last_name"] ?? '')) ) ?: 'User';
$email = htmlspecialchars($user["email"] ?? "");

// small helper: produce a short friendly device string from user agent
function get_short_agent($ua)
{
    if (!$ua) return 'Unknown device';
    $ua = strtolower($ua);
    $browser = 'Browser';
    if (strpos($ua, 'chrome') !== false && strpos($ua, 'edg/') === false && strpos($ua,'opr/') === false) $browser = 'Chrome';
    elseif (strpos($ua, 'firefox') !== false) $browser = 'Firefox';
    elseif (strpos($ua, 'safari') !== false && strpos($ua, 'chrome') === false) $browser = 'Safari';
    elseif (strpos($ua, 'edg/') !== false || strpos($ua, 'edge') !== false) $browser = 'Edge';
    elseif (strpos($ua, 'opr/') !== false || strpos($ua, 'opera') !== false) $browser = 'Opera';

    $os = 'Device';
    if (strpos($ua, 'windows') !== false) $os = 'Windows';
    elseif (strpos($ua, 'mac os x') !== false || strpos($ua, 'macintosh') !== false) $os = 'Mac';
    elseif (strpos($ua, 'iphone') !== false || strpos($ua, 'ipad') !== false) $os = 'iPhone';
    elseif (strpos($ua, 'android') !== false) $os = 'Android';
    return $os . ' - ' . $browser;
}
?>
<!doctype html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/profile.css">
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="stylesheet" href="assets/css/components.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Dekko&family=Devonshire&family=Montserrat:ital,wght@0,100..900;1,100..900&family=Outfit:wght@100..900&display=swap" rel="stylesheet">
    <title>FitCheck</title>
</head>
<body> 
    <?php include "includes/header.php"; ?> 
    <main class="profile-content">
        <div class="profile-header">
            <div class="profile-avatar">
                <img src="assets/default-avatar.svg" alt="Profile Picture">
            </div>
            <div class="profile-info">
                <h1 id="user-fullname">
                    <?php echo htmlspecialchars($fullname); ?>
                            <?php if (!empty($user['email_verified'])): ?>
                        <i class="fa fa-check-circle text-success" title="Email verified" aria-hidden="true" style="margin-left: 5px; color: green; font-size:14px;"></i>
                    <?php else: ?>
                        <a href="/artine3/auth/resend_verification.php" style="margin-left:10px;font-size:14px;color:#0095FF;text-decoration:underline;">Verify</a>
                    <?php endif; ?>
                </h1>
                <?php
                    $created = !empty($user['created_at']) ? date_create_from_format('Y-m-d H:i:s', $user['created_at']) : null;
                    $memberSinceText = $created ? 'Artine member since ' . date_format($created, 'F Y') : 'Artine member since —';
                ?>
                <p class="member-since" id="member-since"><?php echo htmlspecialchars($memberSinceText); ?></p>
            </div>
        </div>
        <div class="profile-tabs">
            <a href="#" class="tab">Account</a>
            <a href="#" class="tab">Orders</a>
            <a href="#" class="tab">Mannequin</a>
            <a href="#" class="tab">Settings</a>
        </div>
        <div class="profile-section">
            <div class="tab-panel account-panel" style="display:none;">
                <div class="section-content1" style="display: grid;">
                    <div>
                        <h2>Personal information</h2>
                        <form id="account-form">
                            <div class="form-row">
                                <label>Email</label>
                                <input class="input-form" type="email" id="acct-email" value="<?php echo $email; ?>" readonly>
                            </div>
                            <div class="form-row">
                                <label>Full name</label>
                                <input class="input-form" type="text" id="acct-name" value="<?php echo htmlspecialchars(
                        $user["first_name"] . " " . $user["last_name"],
                    ); ?>" readonly>
                            </div>
                            <div class="form-row">
                                <label>Phone</label>
                                <input class="input-form" type="text" id="acct-phone" value="<?php echo htmlspecialchars(
                        $user["phone"] ?? "",
                    ); ?>" readonly>
                            </div>
                        </form>
                    </div>
                    <!-- Address management (right) -->
                    <aside>
                        <div class="address-container">
                            <h3>Addresses</h3>
                            <button id="open-address-manager" class="add-address-btn">Manage</button>
                        </div>
                        <div id="account-addresses" class="addresses-grid">
                            <!-- populated by JS: shows up to 3 addresses as preview cards, click Manage to open full manager -->
                        </div>
                        <p>Click Manage to add, edit, delete, or choose your default shipping address.</p>
                    </aside>
                </div>
            </div>
            <div class="tab-panel orders-panel" style="display:none;">
                <div class="section-content">
                    <div class="orders-controls">
                        <div> <?php
                // Server-render the user's orders and per-user counts so the Orders tab appears instantly
                $uid = intval($user["user_id"] ?? ($_SESSION["user_id"] ?? 0));
                $counts = [
                    "all" => 0,
                    "pending" => 0,
                    "paid" => 0,
                    "confirmed" => 0,
                    "shipped" => 0,
                    "delivered" => 0,
                    "cancelled" => 0,
                    "returned" => 0,
                ];
                $orders_html = "";
                if ($uid > 0) {
                    $stmt = $conn->prepare(
                        'SELECT o.order_id,o.total_amount,o.status,o.created_at,o.updated_at,
                        (SELECT COALESCE(p.image_url, NULL) FROM order_items oi JOIN products p ON oi.product_id = p.product_id WHERE oi.order_id = o.order_id LIMIT 1) AS thumbnail,
                        (SELECT c.name FROM order_items oi JOIN products p ON oi.product_id = p.product_id JOIN categories c ON p.category_id = c.category_id WHERE oi.order_id = o.order_id LIMIT 1) AS category_name,
                        (SELECT pm.name FROM payment_methods pm WHERE pm.method_id = o.payment_method_id LIMIT 1) AS payment_method
                    FROM orders o WHERE o.user_id = ? ORDER BY o.created_at DESC',
                    );
                    $stmt->bind_param("i", $uid);
                    $stmt->execute();
                    $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    foreach ($orders as $ord) {
                        $s = strtolower($ord["status"] ?? "");
                        $counts["all"] += 1;
                        if ($s === "pending") {
                            $counts["pending"] += 1;
                        }
                        if ($s === "paid") {
                            $counts["paid"] += 1;
                        }
                        if ($s === "shipped") {
                            $counts["shipped"] += 1;
                        }
                        if ($s === "confirmed") {
                            $counts["confirmed"] += 1;
                        }
                        if ($s === "delivered" || $s === "complete") {
                            $counts["delivered"] += 1;
                        }
                        if ($s === "cancelled") {
                            $counts["cancelled"] += 1;
                        }
                        if ($s === "returned") {
                            $counts["returned"] += 1;
                        }

                        // fetch items for this order
                        $itstmt = $conn->prepare(
                            "SELECT oi.*, p.image_url, p.thumbnail_images, c.name AS category_name FROM order_items oi LEFT JOIN products p ON oi.product_id = p.product_id LEFT JOIN categories c ON p.category_id = c.category_id WHERE oi.order_id = ?",
                        );
                        $itstmt->bind_param("i", $ord["order_id"]);
                        $itstmt->execute();
                        $items = $itstmt->get_result()->fetch_all(MYSQLI_ASSOC);
                        $itstmt->close();

                        // compute thumbnail path
                        $thumb = $ord["thumbnail"] ?? null;
                        $cat = strtolower($ord["category_name"] ?? "");
                        $folder = "";
                        if (strpos($cat, "shirt") !== false) {
                            $folder = "shirts/";
                        } elseif (strpos($cat, "cap") !== false) {
                            $folder = "caps/";
                        } elseif (strpos($cat, "perfume") !== false) {
                            $folder = "perfumes/";
                        }
                        // Resolve header thumbnail path via helper: prefer main product image when available
                        require_once __DIR__ . '/includes/helpers.php';
                        if ($thumb) {
                            // the 'thumbnail' field is populated from the products.image_url subquery in the orders query
                            $thumb_path = resolve_image_path($thumb, $ord['category_name']);
                        } else {
                            // use thumbnail resolver for a sensible uploads-based default
                            $thumb_path = resolve_thumbnail_path(null, $ord['category_name'] ?? '');
                        }

                        // Build status/date display according to status mapping:
                        // Pending = Placed on
                        // Paid = Paid via (payment method) on
                        // Shipped = Arrives on (3-5 days after placed)
                        // Delivered = Delivered on
                        // Cancelled = Cancelled on
                        // Returned = Returned on
                        $dateInfoHtml = "";
                        $statusLower = $s;
                        // helpers for dates
                        $placedDateHtml = '';
                        if (!empty($ord['created_at'])) {
                            $dCreated = date_create_from_format('Y-m-d H:i:s', $ord['created_at']);
                            if ($dCreated) {
                                // include 12-hour time without space before am/pm (e.g. 11:11pm)
                                $placedDateHtml = date_format($dCreated, 'M j, Y | g:ia');
                            }
                        }

                        $updatedDateHtml = '';
                        if (!empty($ord['updated_at'])) {
                            $dUpd = date_create_from_format('Y-m-d H:i:s', $ord['updated_at']);
                            if ($dUpd) { $updatedDateHtml = date_format($dUpd, 'M j, Y | g:ia'); }
                        }

                        if ($statusLower === 'pending') {
                            // Pending = Placed on
                            if ($placedDateHtml) $dateInfoHtml = '<p class="order-arrival">Placed on ' . $placedDateHtml . '</p>';
                        } elseif ($statusLower === 'paid') {
                            // Paid = Paid via (payment method = Gcash and Credit card only) on
                            $pmRaw = $ord['payment_method'] ?? '';
                            $pm = htmlspecialchars($pmRaw);
                            $when = $updatedDateHtml ?: $placedDateHtml;
                            $showVia = in_array(strtolower(trim($pmRaw)), array_map('strtolower', ['Gcash', 'Credit card']));
                            $dateInfoHtml = '<p class="order-arrival">Paid' . ($showVia && $pm ? ' via ' . $pm : '') . ($when ? ' on ' . $when : '') . '</p>';
                        } elseif ($statusLower === 'shipped') {
                            // Shipped = Arrives on (3-5 days after placed). We show date range (no time).
                            if (!empty($ord['created_at'])) {
                                $d = date_create_from_format('Y-m-d H:i:s', $ord['created_at']);
                                if ($d) {
                                    $start = clone $d; date_modify($start, '+3 days');
                                    $end = clone $d; date_modify($end, '+5 days');
                                    // show month/day range; include month for both when different
                                    $startFmt = date_format($start, 'M j');
                                    $endFmt = date_format($end, 'M j');
                                    if ($startFmt === $endFmt) {
                                        $dateInfoHtml = '<p class="order-arrival">Arrives on ' . $startFmt . '</p>';
                                    } else {
                                        $dateInfoHtml = '<p class="order-arrival">Arrives on ' . $startFmt . ' - ' . $endFmt . '</p>';
                                    }
                                }
                            }
                        } elseif ($statusLower === 'confirmed') {
                            // Confirmed = Confirmed on (use updated_at preferred)
                            if ($updatedDateHtml) $dateInfoHtml = '<p class="order-arrival">Confirmed on ' . $updatedDateHtml . '</p>';
                        } elseif ($statusLower === 'delivered') {
                            if ($updatedDateHtml) $dateInfoHtml = '<p class="order-arrival">Delivered on ' . $updatedDateHtml . '</p>';
                        } elseif ($statusLower === 'cancelled') {
                            if ($updatedDateHtml) $dateInfoHtml = '<p class="order-arrival">Cancelled on ' . $updatedDateHtml . '</p>';
                        } elseif ($statusLower === 'returned') {
                            if ($updatedDateHtml) $dateInfoHtml = '<p class="order-arrival">Returned on ' . $updatedDateHtml . '</p>';
                        } else {
                            // fallback: show placed on and arrival range
                            if ($placedDateHtml) $dateInfoHtml = '<p class="order-arrival">Placed on ' . $placedDateHtml . '</p>';
                            if (!empty($ord['created_at'])) {
                                $d = date_create_from_format('Y-m-d H:i:s', $ord['created_at']);
                                if ($d) {
                                    $start = clone $d; date_modify($start, '+3 days');
                                    $end = clone $d; date_modify($end, '+5 days');
                                    $dateInfoHtml .= '<p class="order-arrival">Arrives on ' . date_format($start, 'M j') . '-' . date_format($end, 'j') . '</p>';
                                }
                            }
                        }

                        // build items HTML
                        $items_html = "";
                        foreach ($items as $it) {
                            // Prefer the product's main image for order item thumbnails; fall back to the product's thumbnail images
                            $itthumb = null;
                            $thumb_from_main = false;
                            if (!empty($it['image_url'])) {
                                $itthumb = $it['image_url'];
                                $thumb_from_main = true;
                            }
                            if (empty($itthumb) && !empty($it['thumbnail_images'])) {
                                $tmp = json_decode($it['thumbnail_images'], true);
                                if (is_array($tmp) && count($tmp) > 0) {
                                    $itthumb = $tmp[0];
                                }
                            }
                            if ($itthumb) {
                                if ($thumb_from_main) {
                                    $itthumb_path = resolve_image_path($itthumb, $it['category_name']);
                                } else {
                                    $itthumb_path = resolve_thumbnail_path($itthumb, $it['category_name']);
                                }
                            } else {
                                $itthumb_path = resolve_thumbnail_path(null, $it['category_name'] ?? '');
                            }
                            $items_html .= '<div class="order-product">';
                            $items_html .=
                                '<div class="order-product-image"><img src="' .
                                htmlspecialchars($itthumb_path) .
                                '" alt=""></div>';
                            $items_html .=
                                '<div class="order-product-details"><p class="order-product-name">' .
                                htmlspecialchars($it["product_name"] ?? "") .
                                "</p>";
                            $items_html .=
                                '<p class="order-product-variant">Size: ' .
                                htmlspecialchars($it["size"] ?? "—") .
                                "</p>";
                            $items_html .=
                                '<p class="product-quantity">Quantity: ' .
                                intval($it["quantity"] ?? 0) .
                                "</p></div>";
                            $items_html .=
                                '<div class="order-product-price">₱' .
                                number_format(
                                    floatval($it["product_price"] ?? 0),
                                    2,
                                ) .
                                "</div>";
                            $items_html .= "</div>";
                        }

                        // actions
                        $actions_html = "";
                        if (in_array($statusLower, ["pending", "paid"])) {
                            $actions_html .=
                                '<button class="cancel-order-btn btn danger" data-id="' .
                                intval($ord["order_id"]) .
                                '">Cancel Order</button>';
                        } elseif (
                            in_array($statusLower, [
                                "delivered",
                                // accept legacy 'complete' value
                                "complete",
                            ])
                        ) {
                            $actions_html .=
                                '<button class="return-product-btn btn danger" data-id="' .
                                intval($ord["order_id"]) .
                                '">Return Order</button>';
                        }

                        $orders_html .=
                            '<div class="order-item" data-status="' .
                            htmlspecialchars($statusLower) .
                            '">';
                        $orders_html .=
                            '<div class="order-header"><div class="order-left"><span class="order-status ' .
                            htmlspecialchars($statusLower) .
                            '">' .
                            htmlspecialchars($ord["status"]) .
                            '</span></div><div class="order-right">' .
                            $dateInfoHtml .
                            "</div></div>";
                        $orders_html .=
                            '<div class="order-content">' .
                            $items_html .
                            "</div>";
                        $orders_html .=
                            '<div class="order-footer"><div class="order-total"><p class="order-total-label">Total</p><p class="order-total-amount">₱' .
                            number_format(
                                floatval($ord["total_amount"] ?? 0),
                                2,
                            ) .
                            '</p></div><div class="order-footer-actions">' .
                            $actions_html .
                            "</div></div>";
                        $orders_html .= "</div>";
                    }
                    $stmt->close();
                }

            // render filter buttons with counts filled in
            ?> <div id="orders-filter" class="orders-filter-tabs" role="tablist" aria-label="Order filters">
                                <button class="orders-filter-btn" data-value="all" role="tab">All (<?php echo intval(
                        $counts["all"],
                    ); ?>)</button>
                                <button class="orders-filter-btn" data-value="pending" role="tab">Pending (<?php echo intval(
                        $counts["pending"],
                    ); ?>)</button>
                                <button class="orders-filter-btn" data-value="paid" role="tab">Paid (<?php echo intval(
                        $counts["paid"],
                    ); ?>)</button>
                                <button class="orders-filter-btn" data-value="confirmed" role="tab">Confirmed (<?php echo intval(
                        $counts["confirmed"],
                    ); ?>)</button>
                                <button class="orders-filter-btn" data-value="shipped" role="tab">Shipped (<?php echo intval(
                        $counts["shipped"],
                    ); ?>)</button>
                                <button class="orders-filter-btn" data-value="delivered" role="tab">Delivered (<?php echo intval(
                        $counts["delivered"],
                    ); ?>)</button>
                                <button class="orders-filter-btn" data-value="cancelled" role="tab">Cancelled (<?php echo intval(
                        $counts["cancelled"],
                    ); ?>)</button>
                                <button class="orders-filter-btn" data-value="returned" role="tab">Returned (<?php echo intval(
                        $counts["returned"],
                    ); ?>)</button>
                            </div>
                        </div>
                    </div>
                    <div id="orders-list" class="orders-list" data-hydrated="true"><?php echo $orders_html ?: '<div class="empty-orders"><div class="empty-orders-content"><i class="fa fa-box"></i><h2>No orders yet</h2><p>Looks like you haven\'t placed any orders yet.</p><a href="index.php" class="shop-now-btn">Shop Now</a></div></div>'; ?></div>
                    <div id="order-details" class="order-details"></div>
                </div>
            </div>
            <div class="tab-panel settings-panel" style="display:none;">
                <div class="section-content">
                    <div class="settings-layout">
                        <nav class="settings-nav">
                            <ul>
                                <li><button class="settings-nav-item active" data-panel="panel-account"> <i class="fa fa-user"></i> Account Details</button></li>
                                <li><button class="settings-nav-item" data-panel="panel-payments"> <i class="fa fa-credit-card"></i> Payment Methods</button></li>
                                <li><button class="settings-nav-item" data-panel="panel-addresses"> <i class="fa fa-box"></i> Delivery Addresses</button></li>
                                <li><button class="settings-nav-item" data-panel="panel-privacy"> <i class="fa fa-shield-alt"></i> Privacy and Data Control</button></li>
                                <li><button class="settings-nav-item" data-panel="panel-security"> <i class="fa fa-lock"></i> Security Settings</button></li>
                                <li><button class="settings-nav-item" id="settings-logout-nav"> <i class="fa fa-sign-out-alt"></i> Logout</button></li>
                            </ul>
                        </nav>

                        <div class="settings-content" style="flex:1;">
                            <!-- Account Details panel -->
                            <section id="panel-account" class="settings-panel-content">
                                <h3>Account Details</h3>
                                <form id="settings-account-form" class="auth-form">
                                    <div class="form-group">
                                        <label>Email</label>
                                        <input class="input-form" type="email" id="settings-email" value="<?php echo $email; ?>" readonly>
                                    </div>
                                    <div class="form-group">
                                        <label>Full name</label>
                                        <input class="input-form" type="text" id="settings-fullname" value="<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Phone</label>
                                        <input class="input-form" type="tel" id="settings-phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                    </div>
                                    <div style="display: flex; justify-content: space-between; align-items: center; gap:10px; margin-top: 10px;">
                                        <div><button id="settings-save-account" class="btn primary">Save</button></div>
                                        <div><button id="settings-delete-account" class="btn danger">Delete account</button></div>
                                    </div>
                                </form>
                            </section>

                            <!-- Payment Methods panel -->
                            <section id="panel-payments" class="settings-panel-content" style="display:none;">
                                <div class="settings-panel-header">
                                    <h3>Payment Methods</h3>
                                    <a href="#" id="settings-manage-payments" class="manage-link">Manage</a>
                                </div>
                                <p>Below are the payment methods available on Artine. Click a method to select it for quick checkout.</p>
                                <div id="payments-list" style="margin-top: 10px; display: flex; flex-direction: column; gap: 10px;">
                                    <?php
                                    // Render payment methods (available methods in the system) as pm-cards
                                    $pmRes = $conn->query('SELECT method_id, name FROM payment_methods');
                                    if ($pmRes && $pmRes->num_rows > 0) {
                                        $first = true;
                                        while ($pm = $pmRes->fetch_assoc()) {
                                            $cls = $first ? 'pm-card active' : 'pm-card';
                                            $first = false;
                                            echo '<div class="' . $cls . '" data-method-id="' . intval($pm['method_id']) . '">';
                                            echo '<div class="pm-left"><div class="pm-info"><div class="pm-name">' . htmlspecialchars($pm['name']) . '</div></div></div>';
                                            echo '</div>';
                                        }
                                    } else {
                                        echo '<div style="color:#666">No payment methods configured.</div>';
                                    }
                                    ?>
                                </div>
                            </section>

                            <!-- Delivery Addresses panel -->
                            <section id="panel-addresses" class="settings-panel-content" style="display:none;">
                                <div class="settings-panel-header">
                                    <h3>Delivery Addresses</h3>
                                    <a href="#" id="settings-manage-addresses" class="manage-link">Manage</a>
                                </div>
                                <p>All addresses saved on your account.</p>
                                <div id="addresses-list" class="addresses-grid" s>
                                    <?php
                                    // Render addresses using the same card markup/classes used elsewhere for consistency
                                    $addrStmt = $conn->prepare('SELECT address_id, full_name, phone, house_number, street, barangay, city, province, postal_code, country, is_default FROM addresses WHERE user_id = ? ORDER BY is_default DESC, created_at DESC');
                                    if ($addrStmt) {
                                        $uid2 = intval($user['user_id'] ?? ($_SESSION['user_id'] ?? 0));
                                        $addrStmt->bind_param('i', $uid2);
                                        $addrStmt->execute();
                                        $res = $addrStmt->get_result();
                                        if ($res && $res->num_rows > 0) {
                                            while ($a = $res->fetch_assoc()) {
                                                $country = (!$a['country'] || $a['country'] === '0') ? 'Philippines' : $a['country'];
                                                echo '<div class="address-card">';
                                                echo '<div class="addr-main">';
                                                echo '<strong>' . htmlspecialchars($a['full_name']) . '</strong> ';
                                                echo '<span style="color:#64748b; font-size:14px">(' . htmlspecialchars($a['phone']) . ')</span>';
                                                echo '<div style="margin-top:5px; color:#64748b; font-size:14px">' . htmlspecialchars(($a['house_number'] ? $a['house_number'] . ', ' : '') . $a['street']) . '</div>';
                                                echo '<div style="margin-top:5px; color:#64748b; font-size:14px">' . htmlspecialchars($a['city'] . ($a['barangay'] ? (', Barangay ' . $a['barangay']) : '')) . '</div>';
                                                echo '<div style="margin-top:5px; color:#64748b; font-size:14px">' . htmlspecialchars($a['province'] . ', ' . ($a['postal_code'] ?? '') . ', ' . $country) . '</div>';
                                                echo '</div>'; // addr-main
                                                echo '<div class="addr-actions">';
                                                if (intval($a['is_default']) === 1) {
                                                    echo '<span class="default-badge">Default</span>';
                                                }
                                                echo '</div>'; // addr-actions
                                                echo '</div>'; // address-card
                                            }
                                        } else {
                                            echo '<div style="color:#666">No saved addresses.</div>';
                                        }
                                        $addrStmt->close();
                                    } else {
                                        echo '<div style="color:#666">No saved addresses.</div>';
                                    }
                                    ?>
                                </div>
                            </section>

                            <!-- Privacy panel -->
                            <section id="panel-privacy" class="settings-panel-content" style="display:none;">
                                <h3>Privacy and Data Control</h3>
                                <div class="privacy-text" style="margin-top: 10px; max-width: 820px; color: #444; line-height: 1.5;">
                                    <p><strong>Artine Clothing Privacy Notice</strong></p>
                                    <p>Artine Clothing (“we”, “our”, “us”) respects your privacy and is committed to protecting your personal data. This notice explains how we collect, use, disclose, and safeguard your information when you visit our website or make purchases through our services.</p>
                                    <p>We collect information you provide directly (such as your name, email, phone, delivery address, and payment details), information collected automatically (such as device and usage data), and information from third parties when you authorize it. We use this information to process orders, communicate with you, personalize your experience, improve our products and services, prevent fraud, and comply with legal obligations.</p>
                                    <p>We do not sell your personal data. We may share data with service providers who help us operate our website, fulfill orders, and send communications. We retain personal data only as long as necessary for the purposes described in this notice or as required by law.</p>
                                    <p>You have rights under applicable law to access, correct, delete, or restrict processing of your personal data. To exercise any rights, please contact our support team at support@artine.example (replace with your support email) or visit the Data Control section in our Privacy & Settings.</p>
                                    <p>For a full version of our privacy policy, please contact our data protection officer or visit our site-wide privacy page.</p>
                                </div>
                            </section>

                            <!-- Security panel -->
                            <section id="panel-security" class="settings-panel-content" style="display:none;">
                                <h3>Security Settings</h3>
                                <p>Manage your password, two-factor authentication, and active sessions.</p>

                                <div class="security-row" style="margin-top: 10px; max-width: 720px;">
                                    <label>Password</label>
                                    <div class="input-with-action" style="margin-top:5px;">
                                        <input class="input-form" type="password" id="settings-password-placeholder" value="********" readonly aria-label="Password placeholder">
                                        <a id="settings-edit-password" class="inline-action" href="/artine3/auth/password.php?action=change">Edit</a>
                                    </div>
                                </div>

                                <div class="security-row" style="margin-top: 10px; max-width: 720px; display: flex; align-items: center; justify-content: space-between;">
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <label for="settings-2fa-toggle">Enable Email 2FA</label>
                                        <label class="toggle-switch" title="Enable or disable email two-factor">
                                            <input type="checkbox" id="settings-2fa-toggle" <?php echo (!empty($user['email_2fa_enabled']) ? 'checked' : ''); ?> />
                                        </label>
                                    </div>
                                    <div style="color: #666; font-size: 14px;">Two-factor via email adds an extra step at login.</div>
                                </div>

                                <?php
                                // Recent activity and active sessions: pull from users.last_login and sessions table
                                $lastLogin = !empty($user['last_login']) ? $user['last_login'] : null;
                                $fmt = '';
                                if ($lastLogin) {
                                    $dt = date_create_from_format('Y-m-d H:i:s', $lastLogin);
                                    if ($dt) $fmt = date_format($dt, 'M j, Y, g:i A');
                                }

                                // Try to determine a friendly location from the user's default address (best-effort)
                                $locationLabel = '';
                                $addrStmt2 = $conn->prepare('SELECT city,country FROM addresses WHERE user_id = ? AND is_default = 1 LIMIT 1');
                                if ($addrStmt2) {
                                    $uid3 = intval($user['user_id'] ?? ($_SESSION['user_id'] ?? 0));
                                    $addrStmt2->bind_param('i', $uid3);
                                    if ($addrStmt2->execute()) {
                                        $r2 = $addrStmt2->get_result()->fetch_assoc();
                                        if ($r2) {
                                            $locationLabel = trim((($r2['city'] ?? '') . ', ' . ($r2['country'] ?? '')) , ', ');
                                        }
                                    }
                                    $addrStmt2->close();
                                }

                                // Fetch sessions for this user
                                $sessions = [];
                                // include status and logout_time so we can render logged-out sessions correctly
                                $sessStmt = $conn->prepare('SELECT session_id, ip, user_agent, last_seen, created_at, `status`, logout_time FROM sessions WHERE user_id = ? ORDER BY last_seen DESC');
                                if ($sessStmt) {
                                    $uid4 = intval($user['user_id'] ?? ($_SESSION['user_id'] ?? 0));
                                    $sessStmt->bind_param('i', $uid4);
                                    if ($sessStmt->execute()) {
                                        $resS = $sessStmt->get_result();
                                        while ($sr = $resS->fetch_assoc()) { $sessions[] = $sr; }
                                    }
                                    $sessStmt->close();
                                }

                                // Deduplicate and prune sessions by (user_id, ip): keep at most two entries per ip.
                                // Preference when keeping up to two per ip:
                                // 1) keep current session if present
                                // 2) keep active sessions (status = 'active') by recency
                                // 3) if still space, keep most recent sessions regardless of status
                                // If there are 3+ sessions for the same user and ip, delete the older ones from DB (excluding current session).
                                // For display, only show up to two entries per ip (most recent/preferred)
                                $currentSid = session_id();
                                $byIp = [];
                                foreach ($sessions as $s) {
                                    $key = ($s['ip'] ?? 'unknown');
                                    if (!isset($byIp[$key])) $byIp[$key] = [];
                                    $byIp[$key][] = $s;
                                }

                                $displaySessions = [];
                                foreach ($byIp as $ip => $group) {
                                    // ensure group is sorted by last_seen desc (recent first)
                                    usort($group, function($a, $b){
                                        $ta = strtotime($a['last_seen'] ?? $a['created_at'] ?? '1970-01-01');
                                        $tb = strtotime($b['last_seen'] ?? $b['created_at'] ?? '1970-01-01');
                                        return $tb <=> $ta;
                                    });

                                    // decide which session_ids to keep (max 2)
                                    $keep = [];
                                    // prefer to keep current session if present
                                    foreach ($group as $g) {
                                        if (!empty($currentSid) && ($g['session_id'] ?? '') === $currentSid) {
                                            $keep[] = $g['session_id'];
                                            break;
                                        }
                                    }
                                    // fill remaining keeps from most recent (avoid duplicates)
                                    foreach ($group as $g) {
                                        if (count($keep) >= 2) break;
                                        if (in_array($g['session_id'], $keep)) continue;
                                        $keep[] = $g['session_id'];
                                    }

                                    // delete any extras (3rd, 4th, ...) from DB
                                    if (count($group) > count($keep)) {
                                        try {
                                            $delStmt = $conn->prepare('DELETE FROM sessions WHERE session_id = ?');
                                            if ($delStmt) {
                                                foreach ($group as $g) {
                                                    $sid = $g['session_id'] ?? null;
                                                    if (!$sid) continue;
                                                    if (in_array($sid, $keep)) continue;
                                                    // don't delete the current session (defensive)
                                                    if (!empty($currentSid) && $sid === $currentSid) continue;
                                                    $delStmt->bind_param('s', $sid);
                                                    $delStmt->execute();
                                                }
                                                $delStmt->close();
                                            }
                                        } catch (Exception $e) {
                                            // ignore deletion errors; continue to build display list
                                        }
                                    }

                                    // Add up to two sessions from this group to the display list (match keep ordering)
                                    $added = 0;
                                    foreach ($group as $g) {
                                        if ($added >= 2) break;
                                        if (!in_array($g['session_id'], $keep)) continue; // only show kept ones
                                        $displaySessions[] = $g;
                                        $added++;
                                    }
                                }

                                // Replace sessions with displaySessions for rendering
                                $sessions = $displaySessions;

                                // Current PHP session id to mark active session
                                $currentSid = session_id();
                                ?>

                                <div class="security-activity card">
                                    <h4 style="margin:0 0 8px 0;">Recent activity</h4>
                                    <div class="activity-line">Last Login: <?php echo $fmt ?: '—'; ?></div>
                                    <div class="activity-line">Current Device: <?php echo htmlspecialchars(get_short_agent($_SERVER['HTTP_USER_AGENT'] ?? '')); ?> (IP: <?php echo htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? 'Unknown'); ?>)</div>
                                    <div class="activity-line">Location: <?php echo $locationLabel ?: '—'; ?></div>
                                </div>

                                <div class="active-sessions card" style="margin-top: 15px; max-width: 720px;">
                                    <h4 style="margin:0 0 8px 0;">Active sessions</h4>
                                    <?php if (empty($sessions)): ?>
                                        <div style="color:#666">No active sessions found.</div>
                                    <?php else: ?>
                                        <ul class="sessions-list">
                                            <?php foreach ($sessions as $s):
                                                // Use status and logout_time when present to show Active vs Logged out
                                                $status = strtolower(trim($s['status'] ?? 'active'));
                                                $lastSeen = $s['last_seen'] ?? $s['created_at'] ?? null;
                                                $logoutTime = $s['logout_time'] ?? null;
                                                $label = '—';

                                                if ($status !== 'active') {
                                                    // session is logged out
                                                    if ($logoutTime) {
                                                        $diff = time() - strtotime($logoutTime);
                                                        if ($diff < 300) $label = 'Logged out just now';
                                                        elseif ($diff < 3600) $label = 'Logged out ' . floor($diff/60) . ' minutes ago';
                                                        elseif ($diff < 86400) $label = 'Logged out ' . floor($diff/3600) . ' hours ago';
                                                        else $label = 'Logged out ' . floor($diff/86400) . ' days ago';
                                                    } else {
                                                        // fallback to last seen timestamp when logout_time unavailable
                                                        if ($lastSeen) {
                                                            $diff = time() - strtotime($lastSeen);
                                                            if ($diff < 300) $label = 'Recently active';
                                                            elseif ($diff < 3600) $label = 'Active ' . floor($diff/60) . ' minutes ago';
                                                            elseif ($diff < 86400) $label = 'Active ' . floor($diff/3600) . ' hours ago';
                                                            else $label = 'Last seen ' . floor($diff/86400) . ' days ago';
                                                        }
                                                    }
                                                } else {
                                                    // active session: use last seen
                                                    if ($lastSeen) {
                                                        $diff = time() - strtotime($lastSeen);
                                                        if ($diff < 300) $label = 'Active now';
                                                        elseif ($diff < 3600) $label = 'Logged in ' . floor($diff/60) . ' minutes ago';
                                                        elseif ($diff < 86400) $label = 'Logged in ' . floor($diff/3600) . ' hours ago';
                                                        else $label = 'Last seen ' . floor($diff/86400) . ' days ago';
                                                    }
                                                }

                                                $isCurrent = ($currentSid && $currentSid === ($s['session_id'] ?? ''));
                                            ?>
                                                <?php
                                                    // normalize timestamps to ISO 8601 for reliable client-side parsing
                                                    $lastSeenIso = '';
                                                    $logoutTimeIso = '';
                                                    if (!empty($lastSeen)) {
                                                        $ts = strtotime($lastSeen);
                                                        if ($ts !== false) $lastSeenIso = date('c', $ts);
                                                    }
                                                    if (!empty($logoutTime)) {
                                                        $ts2 = strtotime($logoutTime);
                                                        if ($ts2 !== false) $logoutTimeIso = date('c', $ts2);
                                                    }
                                                    // Determine attributes: do not emit status attribute for the current active session
                                                    $attrs = 'data-session-id="' . htmlspecialchars($s['session_id'] ?? '') . '"';
                                                    if (!($isCurrent && $status === 'active')) {
                                                        $attrs .= ' data-status="' . htmlspecialchars($status) . '"';
                                                    }
                                                    if ($lastSeenIso) $attrs .= ' data-last-seen="' . htmlspecialchars($lastSeenIso) . '"';
                                                    if ($logoutTimeIso) $attrs .= ' data-logout-time="' . htmlspecialchars($logoutTimeIso) . '"';
                                                    if ($isCurrent) $attrs .= ' data-current="1"';
                                                ?>
                                                <li <?php echo $attrs; ?>>
                                                    <strong><?php echo htmlspecialchars(get_short_agent($s['user_agent'] ?? '')); ?></strong>
                                                    — <span class="session-label"><?php echo $label; ?></span>
                                                    <div style="color: #666; font-size: 14px;">IP: <?php echo htmlspecialchars($s['ip'] ?? 'Unknown'); ?><?php if ($locationLabel) echo ' — ' . htmlspecialchars($locationLabel); ?></div>
                                                    <?php if ($isCurrent) echo '<div style="color:green;font-size:13px;">This device</div>'; ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                    <div style="margin-top: 10px;"><button id="settings-logout-all" class="btn danger">Logout from all devices</button></div>
                                </div>
                            </section>

                            <!-- Logout panel -->
                            <section id="panel-logout" class="settings-panel-content" style="display:none;">
                                <h3>Logout</h3>
                                <p>Sign out of this device.</p>
                                <div style="margin-top: 10px;"><button id="panel-logout-btn" class="btn">Logout</button></div>
                            </section>
                        </div>
                    </div>

                    <!-- change-password modal is rendered near the end of the page as a modal overlay -->
                </div>
            </div>
            <div class="mannequin-content" style="display:none;">
                <?php if (empty($user['email_verified'])): ?>
                    <div class="mannequin-locked" style="padding: 40px; text-align:center;">
                 h2     <h2>Verify your emailh2irst</h2>
                        <p>Verify your email first before accessing this feature.</p>
                 buttonioa href="/artine3/auth/verify.php" target="_blaVerifyorar">buttonbutton class="btn">Resend verification</button></a>
                    </div>
                <?php else: ?>
                <div class="mannequin-controls">
                    <div class="mannequin-tabs">
                        <a href="#" class="tab body-measurement active" id="bodyTabBtn">Body Measurements</a>
                        <a href="#" class="tab other-pref" id="otherPrefBtn">Skin, Face, Body Shape</a>
                    </div>
                    <div id="bodyTab" style="display:none;">
                        <form class="measurements">
                            <div class="slider-row">
                                <label for="shoulders" class="label">Shoulder width</label>
                                <div class="slider-group">
                                    <input type="range" id="shoulders" min="40" max="55" value="48" step="0.5" class="slider">
                                    <input type="text" class="value-display" value="48">
                                    <select class="metric-select">
                                        <option value="cm" selected>cm</option>
                                        <option value="inch">inch</option>
                                    </select>
                                </div>
                            </div>
                            <div class="slider-row">
                                <label for="chest" class="label">Chest/Bust</label>
                                <div class="slider-group">
                                    <input type="range" id="chest" min="80" max="110" value="94" step="0.5" class="slider">
                                    <input type="text" class="value-display" value="94">
                                    <select class="metric-select">
                                        <option value="cm" selected>cm</option>
                                        <option value="inch">inch</option>
                                    </select>
                                </div>
                            </div>
                            <div class="slider-row">
                                <label for="waist" class="label">Waist</label>
                                <div class="slider-group">
                                    <input type="range" id="waist" min="70" max="100" value="84" step="0.5" class="slider">
                                    <input type="text" class="value-display" value="84">
                                    <select class="metric-select">
                                        <option value="cm" selected>cm</option>
                                        <option value="inch">inch</option>
                                    </select>
                                </div>
                            </div>
                            <div class="slider-row">
                                <label for="arms" class="label">Arms</label>
                                <div class="slider-group">
                                    <input type="range" id="arms" min="150" max="200" value="175" step="0.5" class="slider">
                                    <input type="text" class="value-display" value="175">
                                    <select class="metric-select">
                                        <option value="cm" selected>cm</option>
                                        <option value="inch">inch</option>
                                    </select>
                                </div>
                            </div>
                            <div class="slider-row">
                                <label for="torso" class="label">Torso length</label>
                                <div class="slider-group">
                                    <input type="range" id="torso" min="50" max="80" value="64" step="0.5" class="slider">
                                    <input type="text" class="value-display" value="64">
                                    <select class="metric-select">
                                        <option value="cm" selected>cm</option>
                                        <option value="inch">inch</option>
                                    </select>
                                </div>
                            </div>
                            <div class="btn-row">
                                <button id="save-measurements" class="btn primary save-mannequin">Save</button>
                            </div>
                        </form>
                    </div>
                    <div id="otherPrefTab" style="display:none;">
                        <div class="other-pref-group">
                            <div class="other-pref-section">
                                <div class="other-pref-label">Skin Tone</div>
                                <button type="button" class="skin-btn" data-skin="#FFDFC4" aria-label="Light skin tone" aria-pressed="true"><span class="swatch" style="background:#FFDFC4"></span></button>
                                <button type="button" class="skin-btn" data-skin="#e0b899" aria-label="Medium skin tone"><span class="swatch" style="background:#e0b899"></span></button>
                                <button type="button" class="skin-btn" data-skin="#c68642" aria-label="Tan skin tone"><span class="swatch" style="background:#c68642"></span></button>
                                <button type="button" class="skin-btn" data-skin="#a97c50" aria-label="Dark skin tone"><span class="swatch" style="background:#a97c50"></span></button>
                            </div>
                            <div class="other-pref-section">
                                <div class="other-pref-label">Face Shape</div>
                                <button type="button" class="face-btn active" data-morph="Oval Face Shape">Oval</button>
                                <button type="button" class="face-btn" data-morph="Square Face Shape">Square</button>
                                <button type="button" class="face-btn" data-morph="Diamond Face Shape">Diamond</button>
                                <button type="button" class="face-btn" data-morph="Rectangular Face Shape">Rectangular</button>
                                <button type="button" class="face-btn" data-morph="Heart Face Shape">Heart</button>
                            </div>
                            <div class="other-pref-section">
                                <div class="other-pref-label">Body Shape</div>
                                <button type="button" class="bodyshape-btn" data-morph="Triangle Body">Triangle</button>
                                <button type="button" class="bodyshape-btn active" data-morph="Straight Body">Straight</button>
                                <button type="button" class="bodyshape-btn" data-morph="Curvy Body">Curvy</button>
                                <button type="button" class="bodyshape-btn" data-morph="Body (to Fat)">To Fat</button>
                                <button type="button" class="bodyshape-btn" data-morph="Thin">Thin</button>
                                <button type="button" class="bodyshape-btn" data-morph="Sitting">Sitting</button>
                            </div>
                            <div class="other-pref-section">
                                <div class="other-pref-label">Pose</div>
                                <button type="button" class="pose-btn" data-morph="'T' Pose">T Pose</button>
                                <button type="button" class="pose-btn" data-morph="'A' Pose">A Pose</button>
                                <button type="button" class="pose-btn" data-morph="'Hi' Pose">Hi Pose</button>
                                <button type="button" class="pose-btn" data-morph="'Peace' Pose">Peace Pose</button>
                                </div>
                            <div class="btn-row">
                                <button id="save-preferences" class="btn primary save-mannequin">Save</button>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- ✅ Viewer -->
                <div class="mannequin-viewer-container" id="mannequinViewer"></div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    <!-- Address manager modal (hidden) -->
    <div id="address-modal" class="modal-overlay" style="display:none;">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="addr-modal-title">
            <button id="modal_close_icon" class="modal-close" aria-label="Close"><i class="fa fa-times"></i></button>
            <h3 id="addr-modal-title">Manage Addresses</h3>
            <!-- Manager list view -->
            <div id="modal_list">
                <div class="modal-content">
                    <strong>Your saved addresses</strong>
                    <a href="#" id="modal_add_new" class="add-address-btn">Add address</a>
                </div>
                <div id="modal_addresses_list"></div>
                <p class="modal-instruction">Click an address to select it as default, then click Save changes</p>
                <div class="modal-btn">
                    <button id="modal_cancel_changes" class="btn">Cancel</button>
                    <button id="modal_save_changes" class="btn primary">Save changes</button>
                </div>
                <!-- Add address link moved to header for compact layout -->
            </div>
            <!-- Edit/Add form (hidden by default) -->
            <form id="addr-modal-form" class="auth-form" style="display:none;">
                <input type="hidden" id="modal_address_id">
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
                        <label>City / Municipality</label>
                        <input class="input-form" id="modal_city" type="text" required>
                    </div>
                    <div class="form-group">
                        <label>Barangay</label>
                        <input class="input-form" id="modal_barangay" type="text" required>
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
                    <button type="submit" id="modal-save" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
    <!-- Change password now uses a dedicated page at auth/password.php?action=change -->
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
    <?php include "includes/footer.php"; ?>
    <script src="assets/js/account-tabs.js"></script>
    <script src="assets/js/account.js"></script>
    <?php if (!empty($user['email_verified'])): ?>
        <script type="module" src="assets/js/mannequin-viewer.js"></script>
    <?php endif; ?>
    <script async src="https://unpkg.com/es-module-shims@1.6.3/dist/es-module-shims.js"></script>
    <script type="importmap"> {
        "imports": {
            "three": "https://cdn.jsdelivr.net/npm/three@0.163.0/build/three.module.min.js",
            "three/addons/": "https://cdn.jsdelivr.net/npm/three@0.163.0/examples/jsm/"
        }
        }
    </script>
    <script>
        (function(){
            // 2FA toggle handled by assets/js/account.js (frontend module)

            // show messages from query params (password change results)
            function qp(name) { var m = new RegExp('[?&]'+name+'=([^&#]*)').exec(window.location.search); return m ? decodeURIComponent(m[1]) : null; }
            var ok = qp('change_password_ok');
            var err = qp('change_password_error');
            if (ok) try { if (typeof showNotification === 'function') showNotification('Password updated', 'success'); else alert('Password updated successfully.'); } catch(e){}
            if (err) try { if (typeof showNotification === 'function') showNotification(err, 'error'); else alert(err); } catch(e){}
        })();
    </script>
</body>

</html>