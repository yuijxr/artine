<?php
require_once __DIR__ . '/session.php';

// Ensure DB connection for cart count
if (!isset($conn)) {
	require_once __DIR__ . '/db_connect.php';
}

$initialCartCount = 0;
if (is_logged_in()) {
	$uid = intval($_SESSION['user_id'] ?? 0);
	if ($uid > 0) {
		$stmt = $conn->prepare('SELECT SUM(quantity) AS cnt FROM cart WHERE user_id = ?');
		if ($stmt) {
			$stmt->bind_param('i', $uid);
			$stmt->execute();
			$r = $stmt->get_result()->fetch_assoc();
			$initialCartCount = intval($r['cnt'] ?? 0);
			$stmt->close();
		}
	}
}
?>

<header class="header-fixed">
	<div class="header-container">
		<!-- LEFT SIDE -->
		<div class="header-left">
		<?php
		// Make the header active state page-aware: only highlight category links on the shop (index.php).
		$curScript = basename($_SERVER['PHP_SELF'] ?? '');
		$catParam = $_GET['category'] ?? null;
		?>
			<a href="/artine3/index.php" class="<?= ($curScript === 'index.php' && !$catParam ? 'active' : '') ?>">All</a>
			<a href="/artine3/index.php?category=shirts" class="<?= ($curScript === 'index.php' && ($catParam === 'shirts') ? 'active' : '') ?>">Shirts</a>
			<a href="/artine3/index.php?category=caps" class="<?= ($curScript === 'index.php' && ($catParam === 'caps') ? 'active' : '') ?>">Caps</a>
			<a href="/artine3/index.php?category=perfumes" class="<?= ($curScript === 'index.php' && ($catParam === 'perfumes') ? 'active' : '') ?>">Perfumes</a>
		</div>

		<!-- CENTER (LOGO) -->
		<div class="header-center">
			<a href="/artine3/index.php" class="logo">
				<img src="assets/img/logo.png" alt="artine3 Logo">
			</a>
		</div>

		<!-- RIGHT SIDE -->
		<div class="header-right">
			<form class="header-search" action="#" method="get" autocomplete="off" onsubmit="return false;">
                <input type="text" placeholder="Search" name="q" />
                <i class="fa fa-search"></i>
            </form>
			<?php if (is_logged_in()):
				$user = current_user($GLOBALS['conn'] ?? null);
				$name = trim($user['first_name'] ?? '') ?: 'User';
				// mark user link active when on the account page
				$curScript = basename($_SERVER['PHP_SELF'] ?? '');
			?>
				<a href="account.php#account" class="user-link <?= ($curScript === 'account.php' ? 'active' : '') ?>" title="View account">
					<i class="fa fa-user"></i>
					<span class="welcome-user">Hi, <?= htmlspecialchars($name) ?></span>
				</a>
			<?php else: ?>
				<a href="login.php" class="auth-link">Login</a>
				<a href="register.php" class="auth-link">Sign Up</a>
			<?php endif; ?>
			<a href="cart.php" class="cart-link cart-icon" aria-label="Shopping Cart">
				<i class="fa fa-shopping-cart"></i>
				<span id="cart-count" class="cart-badge" style="display: <?= $initialCartCount > 0 ? 'flex' : 'none' ?>"><?= $initialCartCount ?></span>
			</a>
		</div>
	</div>
</header>

	<script src="assets/js/lib/utils.js"></script>

	<script>
		// initial values available synchronously to JS to avoid flash of 0
		window.INIT_CART_COUNT = <?= intval($initialCartCount) ?>;
		window.IS_LOGGED = <?= is_logged_in() ? 'true' : 'false' ?>;
		window.IS_VERIFIED = <?= (is_logged_in() && (!empty($user['email_verified']))) ? 'true' : 'false' ?>;
	</script>
