<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/session.php';

// require admin access
require_admin();


 $errors = [];
 $notice = '';

// helper: verify admin password
function verify_admin_password($conn, $admin_id, $password) {
    if (empty($admin_id) || empty($password)) return false;
    $stmt = $conn->prepare('SELECT password_hash FROM admins WHERE admin_id = ? LIMIT 1');
    if (!$stmt) return false;
    $stmt->bind_param('i', $admin_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row || empty($row['password_hash'])) return false;
    if (!function_exists('password_verify')) return false;
    return password_verify($password, $row['password_hash']);
}

// load categories for dropdown
$categories = [];
$catStmt = $conn->prepare('SELECT category_id, name FROM categories ORDER BY name ASC');
if ($catStmt) { $catStmt->execute(); $res = $catStmt->get_result(); while ($r = $res->fetch_assoc()) { $categories[] = $r; } $catStmt->close(); }

// Handle actions: create, update, delete (soft), restore
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        // require admin password confirmation for create
        $admin_password = $_POST['admin_password'] ?? '';
        if (!verify_admin_password($conn, $_SESSION['admin_id'] ?? null, $admin_password)) {
            $errors[] = 'Invalid admin password. Please confirm with your admin password to create a product.';
        }
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
    $category_id = intval($_POST['category_id'] ?? 0);
    // determine folder name from selected category so it's available for thumbnails even if main image not uploaded
    $catName = '';
    foreach ($categories as $c) { if ($c['category_id'] == $category_id) { $catName = $c['name']; break; } }
    $c = strtolower($catName ?: '');
    if (strpos($c, 'shirt') !== false) $folder = 'shirts';
    elseif (strpos($c, 'cap') !== false) $folder = 'caps';
    elseif (strpos($c, 'perfume') !== false) $folder = 'perfumes';
    else $folder = 'products';
        $stock = intval($_POST['stock'] ?? 0);
        $image_url = trim($_POST['image_url'] ?? '');
        // handle uploaded image file (optional)
    if (!empty($_FILES['image_file']) && is_uploaded_file($_FILES['image_file']['tmp_name'])) {
            $f = $_FILES['image_file'];
            $maxSize = 5 * 1024 * 1024; // 5MB
            if ($f['size'] > $maxSize) {
                $errors[] = 'Image is too large (max 5MB).';
            } else {
                $info = @getimagesize($f['tmp_name']);
                $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg'];
                if ($info === false || !isset($allowed[$info['mime']])) {
                    $errors[] = 'Only PNG and JPG images are allowed.';
                } else {
                    $ext = $allowed[$info['mime']];
                    // determine folder based on selected category
                    $catName = '';
                    foreach ($categories as $c) { if ($c['category_id'] == $category_id) { $catName = $c['name']; break; } }
                    $c = strtolower($catName ?: '');
                    if (strpos($c, 'shirt') !== false) $folder = 'shirts';
                    elseif (strpos($c, 'cap') !== false) $folder = 'caps';
                    elseif (strpos($c, 'perfume') !== false) $folder = 'perfumes';
                    else $folder = 'products';
                    $uploadDir = __DIR__ . '/../uploads/product_img/' . $folder;
                    if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0755, true); }
                    // sanitize original filename (keep base name, replace unsafe chars)
                    $origName = trim((string)($f['name'] ?? ''));
                    $base = pathinfo($origName, PATHINFO_FILENAME);
                    // allow spaces in filenames; remove only unsafe characters
                    $base = preg_replace('/[^A-Za-z0-9 _\-]/', '', $base);
                    if ($base === '') { $base = 'image'; }
                    $filename = $base . '.' . $ext;
                    $dest = $uploadDir . '/' . $filename;
                    // avoid collisions by appending a counter
                    $counter = 1;
                    while (file_exists($dest)) {
                        $filename = $base . '-' . $counter . '.' . $ext;
                        $dest = $uploadDir . '/' . $filename;
                        $counter++;
                    }
                    if (move_uploaded_file($f['tmp_name'], $dest)) {
                        // store category/filename in DB (e.g. "shirts/breezy v2.png")
                        $image_url = $folder . '/' . $filename;
                    } else {
                        $errors[] = 'Failed to move uploaded image.';
                    }
                }
            }
        }
        // handle thumbnail uploads (optional, up to 5 files)
        $thumbnail_list = [];
        if (!empty($_FILES['thumbnail_files']) && is_array($_FILES['thumbnail_files']['tmp_name'])) {
            $files = $_FILES['thumbnail_files'];
            $countFiles = count($files['tmp_name']);
            for ($i = 0; $i < $countFiles && count($thumbnail_list) < 5; $i++) {
                if (!is_uploaded_file($files['tmp_name'][$i])) continue;
                $ftmp = $files['tmp_name'][$i];
                $fname = $files['name'][$i];
                $fsize = $files['size'][$i];
                if ($fsize > 5 * 1024 * 1024) { $errors[] = 'One of the thumbnails is too large (max 5MB).'; continue; }
                $info = @getimagesize($ftmp);
                $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg'];
                if ($info === false || !isset($allowed[$info['mime']])) { $errors[] = 'Thumbnail must be PNG or JPG.'; continue; }
                $ext = $allowed[$info['mime']];
                $thumbUploadDir = __DIR__ . '/../uploads/thumbnail_img/' . $folder;
                if (!is_dir($thumbUploadDir)) { @mkdir($thumbUploadDir, 0755, true); }
                $base = pathinfo(trim((string)$fname), PATHINFO_FILENAME);
                $base = preg_replace('/[^A-Za-z0-9 _\-]/', '', $base);
                if ($base === '') $base = 'thumb';
                $filename = $base . '.' . $ext;
                $dest = $thumbUploadDir . '/' . $filename;
                $counter = 1;
                while (file_exists($dest)) { $filename = $base . '-' . $counter . '.' . $ext; $dest = $thumbUploadDir . '/' . $filename; $counter++; }
                if (move_uploaded_file($ftmp, $dest)) {
                    $thumbnail_list[] = $folder . '/' . $filename;
                }
            }
        }

        if ($name === '') { $errors[] = 'Product name is required.'; }
        if ($price <= 0) { $errors[] = 'Price must be greater than zero.'; }
        if ($category_id <= 0) { $errors[] = 'Please select a category.'; }

        if (empty($errors)) {
                $stmt = $conn->prepare('INSERT INTO products (name, description, price, category_id, stock, image_url, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
            if ($stmt) {
                // types: name(s), description(s), price(d), category_id(i), stock(i), image_url(s)
                if (!empty($thumbnail_list)) {
                    $thumb_json = json_encode($thumbnail_list);
                    // include thumbnail_images in insert
                    $stmt = $conn->prepare('INSERT INTO products (name, description, price, category_id, stock, image_url, thumbnail_images, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
                    if ($stmt) {
                        $stmt->bind_param('ssdiiss', $name, $description, $price, $category_id, $stock, $image_url, $thumb_json);
                    }
                } else {
                    $stmt->bind_param('ssdiis', $name, $description, $price, $category_id, $stock, $image_url);
                }
                if ($stmt->execute()) {
                    $new_id = $conn->insert_id;
                    $notice = 'Product created.';
                    // log create action
                    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
                    $details = json_encode(['product_id' => $new_id, 'name' => $name, 'price' => $price, 'category_id' => $category_id]);
                    $log = $conn->prepare('INSERT INTO admin_actions (admin_id, action_type, target_table, target_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)');
                    if ($log) { $atype = 'create_product'; $tt = 'products'; $tid = $new_id; $log->bind_param('ississ', $_SESSION['admin_id'], $atype, $tt, $tid, $details, $ip); $log->execute(); $log->close(); }
                } else { $errors[] = 'DB error: ' . $stmt->error; }
                $stmt->close();
            } else { $errors[] = 'DB prepare error.'; }
        }
    } elseif ($action === 'update') {
        // require admin password confirmation for update
        $admin_password = $_POST['admin_password'] ?? '';
        if (!verify_admin_password($conn, $_SESSION['admin_id'] ?? null, $admin_password)) {
            $errors[] = 'Invalid admin password. Please confirm with your admin password to update a product.';
        }
        $product_id = intval($_POST['product_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
    $category_id = intval($_POST['category_id'] ?? 0);
    // decide folder from category selection so thumbnail uploads have a folder even if no main image provided
    $newCatName = '';
    foreach ($categories as $c) { if ($c['category_id'] == $category_id) { $newCatName = $c['name']; break; } }
    $c = strtolower($newCatName ?: '');
    if (strpos($c, 'shirt') !== false) $folder = 'shirts';
    elseif (strpos($c, 'cap') !== false) $folder = 'caps';
    elseif (strpos($c, 'perfume') !== false) $folder = 'perfumes';
    else $folder = 'products';
        $stock = intval($_POST['stock'] ?? 0);
        $image_url = trim($_POST['image_url'] ?? '');
        // handle uploaded image file (optional)
    $uploaded_image_url = null;
        if (!empty($_FILES['image_file']) && is_uploaded_file($_FILES['image_file']['tmp_name'])) {
            $f = $_FILES['image_file'];
            $maxSize = 5 * 1024 * 1024; // 5MB
            if ($f['size'] > $maxSize) {
                $errors[] = 'Image is too large (max 5MB).';
            } else {
                $info = @getimagesize($f['tmp_name']);
                $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg'];
                if ($info === false || !isset($allowed[$info['mime']])) {
                    $errors[] = 'Only PNG and JPG images are allowed.';
                } else {
                    $ext = $allowed[$info['mime']];
                    // decide folder based on (new) category selection if provided, else use previous product category
                    $newCatName = '';
                    foreach ($categories as $c) { if ($c['category_id'] == $category_id) { $newCatName = $c['name']; break; } }
                    $c = strtolower($newCatName ?: '');
                    if (strpos($c, 'shirt') !== false) $folder = 'shirts';
                    elseif (strpos($c, 'cap') !== false) $folder = 'caps';
                    elseif (strpos($c, 'perfume') !== false) $folder = 'perfumes';
                    else $folder = 'products';
                    $uploadDir = __DIR__ . '/../uploads/product_img/' . $folder;
                    if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0755, true); }
                    // use sanitized original filename and avoid collisions
                    $origName = trim((string)($f['name'] ?? ''));
                    $base = pathinfo($origName, PATHINFO_FILENAME);
                    // allow spaces in filenames; remove only unsafe characters (preserve spaces)
                    $base = preg_replace('/[^A-Za-z0-9 _\-]/', '', $base);
                    if ($base === '') { $base = 'image'; }
                    $filename = $base . '.' . $ext;
                    $dest = $uploadDir . '/' . $filename;
                    $counter = 1;
                    while (file_exists($dest)) {
                        $filename = $base . '-' . $counter . '.' . $ext;
                        $dest = $uploadDir . '/' . $filename;
                        $counter++;
                    }
                    if (move_uploaded_file($f['tmp_name'], $dest)) {
                        $uploaded_image_url = $folder . '/' . $filename;
                    } else {
                        $errors[] = 'Failed to move uploaded image.';
                    }
                }
            }
        }

        // handle thumbnail uploads on update (replace thumbnails if uploaded)
        $uploaded_thumbs = [];
        if (!empty($_FILES['thumbnail_files']) && is_array($_FILES['thumbnail_files']['tmp_name'])) {
            $files = $_FILES['thumbnail_files'];
            $countFiles = count($files['tmp_name']);
            for ($i = 0; $i < $countFiles && count($uploaded_thumbs) < 5; $i++) {
                if (!is_uploaded_file($files['tmp_name'][$i])) continue;
                $ftmp = $files['tmp_name'][$i];
                $fname = $files['name'][$i];
                $fsize = $files['size'][$i];
                if ($fsize > 5 * 1024 * 1024) { $errors[] = 'One of the thumbnails is too large (max 5MB).'; continue; }
                $info = @getimagesize($ftmp);
                $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg'];
                if ($info === false || !isset($allowed[$info['mime']])) { $errors[] = 'Thumbnail must be PNG or JPG.'; continue; }
                $ext = $allowed[$info['mime']];
                $thumbUploadDir = __DIR__ . '/../uploads/thumbnail_img/' . $folder;
                if (!is_dir($thumbUploadDir)) { @mkdir($thumbUploadDir, 0755, true); }
                $base = pathinfo(trim((string)$fname), PATHINFO_FILENAME);
                $base = preg_replace('/[^A-Za-z0-9 _\-]/', '', $base);
                if ($base === '') $base = 'thumb';
                $filename = $base . '.' . $ext;
                $dest = $thumbUploadDir . '/' . $filename;
                $counter = 1;
                while (file_exists($dest)) { $filename = $base . '-' . $counter . '.' . $ext; $dest = $thumbUploadDir . '/' . $filename; $counter++; }
                if (move_uploaded_file($ftmp, $dest)) {
                    $uploaded_thumbs[] = $folder . '/' . $filename;
                }
            }
        }

        if ($product_id <= 0) { $errors[] = 'Invalid product id.'; }
        if ($name === '') { $errors[] = 'Product name is required.'; }
        if ($price <= 0) { $errors[] = 'Price must be greater than zero.'; }

        if (empty($errors)) {
            // Determine update strategy depending on uploaded main image and/or thumbnails
            try {
                if ($uploaded_image_url !== null && !empty($uploaded_thumbs)) {
                    // update both main image and thumbnails
                    $thumb_json = json_encode($uploaded_thumbs);
                    $stmt = $conn->prepare('UPDATE products SET name = ?, description = ?, price = ?, category_id = ?, stock = ?, image_url = ?, thumbnail_images = ?, updated_at = NOW() WHERE product_id = ?');
                    if ($stmt) {
                        $stmt->bind_param('ssdiissi', $name, $description, $price, $category_id, $stock, $uploaded_image_url, $thumb_json, $product_id);
                        if ($stmt->execute()) {
                            $notice = 'Product updated.';
                            // log update
                            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
                            $details = json_encode(['product_id' => $product_id, 'name' => $name, 'price' => $price]);
                            $log = $conn->prepare('INSERT INTO admin_actions (admin_id, action_type, target_table, target_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)');
                            if ($log) { $atype = 'update_product'; $tt = 'products'; $tid = $product_id; $log->bind_param('ississ', $_SESSION['admin_id'], $atype, $tt, $tid, $details, $ip); $log->execute(); $log->close(); }
                        } else { $errors[] = 'DB error: ' . $stmt->error; }
                        $stmt->close();
                    } else { $errors[] = 'DB prepare error.'; }
                } elseif ($uploaded_image_url !== null) {
                    // update only main image
                    $stmt = $conn->prepare('UPDATE products SET name = ?, description = ?, price = ?, category_id = ?, stock = ?, image_url = ?, updated_at = NOW() WHERE product_id = ?');
                    if ($stmt) {
                        $stmt->bind_param('ssdiisi', $name, $description, $price, $category_id, $stock, $uploaded_image_url, $product_id);
                        if ($stmt->execute()) {
                            $notice = 'Product updated.';
                            // log update
                            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
                            $details = json_encode(['product_id' => $product_id, 'name' => $name, 'price' => $price]);
                            $log = $conn->prepare('INSERT INTO admin_actions (admin_id, action_type, target_table, target_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)');
                            if ($log) { $atype = 'update_product'; $tt = 'products'; $tid = $product_id; $log->bind_param('ississ', $_SESSION['admin_id'], $atype, $tt, $tid, $details, $ip); $log->execute(); $log->close(); }
                        } else { $errors[] = 'DB error: ' . $stmt->error; }
                        $stmt->close();
                    } else { $errors[] = 'DB prepare error.'; }
                } elseif (!empty($uploaded_thumbs)) {
                    // update only thumbnails (replace existing)
                    $thumb_json = json_encode($uploaded_thumbs);
                    $stmt = $conn->prepare('UPDATE products SET name = ?, description = ?, price = ?, category_id = ?, stock = ?, thumbnail_images = ?, updated_at = NOW() WHERE product_id = ?');
                    if ($stmt) {
                        $stmt->bind_param('ssdiisi', $name, $description, $price, $category_id, $stock, $thumb_json, $product_id);
                        if ($stmt->execute()) {
                            $notice = 'Product updated.';
                            // log update
                            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
                            $details = json_encode(['product_id' => $product_id, 'name' => $name]);
                            $log = $conn->prepare('INSERT INTO admin_actions (admin_id, action_type, target_table, target_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)');
                            if ($log) { $atype = 'update_product'; $tt = 'products'; $tid = $product_id; $log->bind_param('ississ', $_SESSION['admin_id'], $atype, $tt, $tid, $details, $ip); $log->execute(); $log->close(); }
                        } else { $errors[] = 'DB error: ' . $stmt->error; }
                        $stmt->close();
                    } else { $errors[] = 'DB prepare error.'; }
                } else {
                    // update metadata only
                        $stmt = $conn->prepare('UPDATE products SET name = ?, description = ?, price = ?, category_id = ?, stock = ?, updated_at = NOW() WHERE product_id = ?');
                        if ($stmt) {
                            $stmt->bind_param('ssdiii', $name, $description, $price, $category_id, $stock, $product_id);
                            if ($stmt->execute()) {
                                $notice = 'Product updated.';
                                // log update
                                $ip = $_SERVER['REMOTE_ADDR'] ?? null;
                                $details = json_encode(['product_id' => $product_id, 'name' => $name]);
                                $log = $conn->prepare('INSERT INTO admin_actions (admin_id, action_type, target_table, target_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)');
                                if ($log) { $atype = 'update_product'; $tt = 'products'; $tid = $product_id; $log->bind_param('ississ', $_SESSION['admin_id'], $atype, $tt, $tid, $details, $ip); $log->execute(); $log->close(); }
                            } else { $errors[] = 'DB error: ' . $stmt->error; }
                            $stmt->close();
                        } else { $errors[] = 'DB prepare error.'; }
                }
            } catch (Exception $e) {
                $errors[] = 'DB error: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete') {
        // require admin password confirmation for delete
        $admin_password = $_POST['admin_password'] ?? '';
        if (!verify_admin_password($conn, $_SESSION['admin_id'] ?? null, $admin_password)) {
            $errors[] = 'Invalid admin password. Please confirm with your admin password to delete a product.';
        }
        $product_id = intval($_POST['product_id'] ?? 0);
        if ($product_id > 0) {
            // Hard delete: remove dependent rows then delete product and image file
            $conn->begin_transaction();
            try {
                // delete order_items referencing this product
                $d1 = $conn->prepare('DELETE FROM order_items WHERE product_id = ?');
                if ($d1) { $d1->bind_param('i', $product_id); $d1->execute(); $d1->close(); }

                // delete cart entries (FK may cascade, but remove explicitly)
                $d2 = $conn->prepare('DELETE FROM cart WHERE product_id = ?');
                if ($d2) { $d2->bind_param('i', $product_id); $d2->execute(); $d2->close(); }

                // read image filename and thumbnails before deleting
                $img = null;
                $thumbs = null;
                $s = $conn->prepare('SELECT image_url, thumbnail_images FROM products WHERE product_id = ? LIMIT 1');
                if ($s) { $s->bind_param('i', $product_id); $s->execute(); $res = $s->get_result(); $r = $res->fetch_assoc(); if ($r) { $img = $r['image_url']; $thumbs = $r['thumbnail_images']; } $s->close(); }

                // delete product row
                $d3 = $conn->prepare('DELETE FROM products WHERE product_id = ?');
                if ($d3) { $d3->bind_param('i', $product_id); $d3->execute(); $d3->close(); }

                $conn->commit();
                // log delete action
                $ip = $_SERVER['REMOTE_ADDR'] ?? null;
                $details = json_encode(['product_id' => $product_id, 'image' => $img]);
                $log = $conn->prepare('INSERT INTO admin_actions (admin_id, action_type, target_table, target_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)');
                if ($log) { $atype = 'delete_product'; $tt = 'products'; $tid = $product_id; $log->bind_param('ississ', $_SESSION['admin_id'], $atype, $tt, $tid, $details, $ip); $log->execute(); $log->close(); }

                // remove image file from disk (uploads path only)
                if (!empty($img)) {
                    $path1 = __DIR__ . '/../uploads/product_img/' . $img;
                    if (file_exists($path1)) { @unlink($path1); }
                }
                // remove thumbnails if present
                if (!empty($thumbs)) {
                    $decoded = json_decode($thumbs, true);
                    if (is_array($decoded)) {
                        foreach ($decoded as $t) {
                            $pathT = __DIR__ . '/../uploads/thumbnail_img/' . $t;
                            if (file_exists($pathT)) { @unlink($pathT); continue; }
                        }
                    }
                }

                $notice = 'Product deleted.';
            } catch (Exception $e) {
                $conn->rollback();
                $errors[] = 'Failed to delete product: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'restore') {
        // require admin password confirmation for restore
        $admin_password = $_POST['admin_password'] ?? '';
        if (!verify_admin_password($conn, $_SESSION['admin_id'] ?? null, $admin_password)) {
            $errors[] = 'Invalid admin password. Please confirm with your admin password to restore a product.';
        }
        $product_id = intval($_POST['product_id'] ?? 0);
        if ($product_id > 0) {
            $stmt = $conn->prepare('UPDATE products SET deleted_at = NULL WHERE product_id = ?');
            if ($stmt) {
                if ($stmt->execute()) {
                    $notice = 'Product restored.';
                    // log restore
                    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
                    $details = json_encode(['product_id' => $product_id]);
                    $log = $conn->prepare('INSERT INTO admin_actions (admin_id, action_type, target_table, target_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)');
                    if ($log) { $atype = 'restore_product'; $tt = 'products'; $tid = $product_id; $log->bind_param('ississ', $_SESSION['admin_id'], $atype, $tt, $tid, $details, $ip); $log->execute(); $log->close(); }
                } else {
                    $errors[] = 'DB error: ' . $stmt->error;
                }
                $stmt->close();
            }
        }
    }
}

// fetch products
$products = [];
$stmt = $conn->prepare('SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.category_id ORDER BY p.created_at DESC LIMIT 1000');
if ($stmt) { $stmt->execute(); $res = $stmt->get_result(); while ($r = $res->fetch_assoc()) { $products[] = $r; } $stmt->close(); }

// if editing selected product
 $editing = null;
 $creating = false;
 if (!empty($_GET['id'])) {
     $pid = intval($_GET['id']);
     $stmt = $conn->prepare('SELECT * FROM products WHERE product_id = ? LIMIT 1');
     if ($stmt) { $stmt->bind_param('i', $pid); $stmt->execute(); $editing = $stmt->get_result()->fetch_assoc(); $stmt->close(); }
 }
 if (!empty($_GET['action']) && $_GET['action'] === 'new') {
     $creating = true;
 }
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <link rel="stylesheet" href="/artine3/assets/css/style.css">
    <title>Admin - Products</title>
</head>
<body>
    <header style="padding:16px;border-bottom:1px solid #eee;display:flex;justify-content:space-between;align-items:center;">
        <h2>Admin - Products</h2>
        <div>
            <a href="/artine3/admin/logout.php">Logout</a>
        </div>
    </header>
    <main style="padding:20px;display:flex;gap:20px;">
        <nav style="width:220px;flex:0 0 220px;background:#fff;border:1px solid #eee;padding:12px;border-radius:6px;">
            <ul style="list-style:none;padding:0;margin:0;font-size:14px;">
                <li style="margin-bottom:8px;"><a href="/artine3/admin/index.php" style="text-decoration:none;color:#333;">Dashboard</a></li>
                <li style="margin-bottom:8px;"><a href="/artine3/admin/users.php" style="text-decoration:none;color:#333;">Users</a></li>
                <li style="margin-bottom:8px;"><a href="/artine3/admin/products.php" style="text-decoration:none;color:#333;font-weight:700;">Products</a></li>
                <li style="margin-bottom:8px;"><a href="/artine3/admin/orders.php" style="text-decoration:none;color:#333;">Orders</a></li>
                <li style="margin-bottom:8px;"><a href="/artine3/admin/settings.php" style="text-decoration:none;color:#333;">Settings</a></li>
            </ul>
        </nav>
        <section style="flex:1;">
            <h3>Products</h3>

            <?php if ($notice): ?>
                <div style="padding:8px;background:#e6ffed;border:1px solid #d4f5d8;margin-bottom:12px;"><?php echo htmlspecialchars($notice); ?></div>
            <?php endif; ?>
            <?php if (!empty($errors)): ?>
                <div style="padding:8px;background:#fff1f0;border:1px solid #ffd6d6;margin-bottom:12px;color:#900;">
                    <ul style="margin:0;padding-left:18px;">
                        <?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div style="margin-bottom:18px;">
                <a href="products.php?action=new" style="display:inline-block;margin-bottom:8px;">+ Add New Product</a>
                <?php if ($editing): ?>
                    <h4>Editing: <?php echo htmlspecialchars($editing['name']); ?></h4>
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="product_id" value="<?php echo intval($editing['product_id']); ?>">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                            <div>
                                <label>Name<br><input type="text" name="name" value="<?php echo htmlspecialchars($editing['name']); ?>" style="width:100%"></label>
                            </div>
                            <div>
                                <label>Price<br><input type="number" step="0.01" name="price" value="<?php echo htmlspecialchars($editing['price']); ?>" style="width:100%"></label>
                            </div>
                            <div>
                                <label>Category<br>
                                    <select name="category_id" style="width:100%">
                                        <option value="0">-- select --</option>
                                        <?php foreach ($categories as $c): ?>
                                            <option value="<?php echo intval($c['category_id']); ?>" <?php if ($c['category_id']==$editing['category_id']) echo 'selected'; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                            </div>
                            <div>
                                <label>Stock<br><input type="number" name="stock" value="<?php echo intval($editing['stock']); ?>" style="width:100%"></label>
                            </div>
                                    <div style="grid-column:1 / -1">
                                        <label>Image filename<br><input type="text" name="image_url" value="<?php echo htmlspecialchars($editing['image_url']); ?>" style="width:100%"></label>
                                    </div>
                                    <div style="grid-column:1 / -1;margin-top:6px;">
                                        <div style="margin-top:6px;"><label>Replace image (PNG/JPG)<br><input type="file" name="image_file" accept="image/png,image/jpeg"></label></div>
                                    </div>
                                    <div style="grid-column:1 / -1;margin-top:6px;">
                                        <div style="margin-top:6px;"><label>Thumbnail images (up to 5, PNG/JPG)<br><input type="file" name="thumbnail_files[]" accept="image/png,image/jpeg" multiple></label></div>
                                    </div>
                            <div style="grid-column:1 / -1">
                                <label>Description<br><textarea name="description" style="width:100%" rows="6"><?php echo htmlspecialchars($editing['description']); ?></textarea></label>
                            </div>
                        </div>
                        <div style="margin-top:10px;">
                            <div style="margin-bottom:8px;">
                                <label>Confirm admin password<br><input type="password" name="admin_password" required style="width:100%;padding:6px;" /></label>
                            </div>
                            <button type="submit">Save changes</button>
                            <a href="products.php" style="margin-left:10px;">Cancel</a>
                        </div>
                    </form>
                    <form method="post" style="margin-top:10px;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="product_id" value="<?php echo intval($editing['product_id']); ?>">
                        <div style="margin-bottom:8px;">
                            <label>Confirm admin password<br><input type="password" name="admin_password" required style="width:100%;padding:6px;" /></label>
                        </div>
                        <button type="submit" style="background:#f8d7da;border:1px solid #f5c6cb;padding:6px;color:#721c24;">Delete (soft)</button>
                    </form>
                <?php elseif ($creating): ?>
                    <h4>Add New Product</h4>
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="create">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                            <div>
                                <label>Name<br><input type="text" name="name" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" style="width:100%"></label>
                            </div>
                            <div>
                                <label>Price<br><input type="number" step="0.01" name="price" value="<?php echo htmlspecialchars($_POST['price'] ?? '0.00'); ?>" style="width:100%"></label>
                            </div>
                            <div>
                                <label>Category<br>
                                    <select name="category_id" style="width:100%">
                                        <option value="0">-- select --</option>
                                        <?php foreach ($categories as $c): ?>
                                            <option value="<?php echo intval($c['category_id']); ?>" <?php if (isset($_POST['category_id']) && $_POST['category_id']==$c['category_id']) echo 'selected'; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                            </div>
                            <div>
                                <label>Stock<br><input type="number" name="stock" value="<?php echo intval($_POST['stock'] ?? 0); ?>" style="width:100%"></label>
                            </div>
                            <div style="grid-column:1 / -1">
                                <label>Image File (PNG/JPG)<br><input type="file" name="image_file" accept="image/png,image/jpeg" style="width:100%"></label>
                            </div>
                            <div style="grid-column:1 / -1">
                                <label>Thumbnail images (up to 5, PNG/JPG)<br><input type="file" name="thumbnail_files[]" accept="image/png,image/jpeg" multiple style="width:100%"></label>
                            </div>
                            <div style="grid-column:1 / -1">
                                <label>Description<br><textarea name="description" style="width:100%" rows="6"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea></label>
                            </div>
                        </div>
                        <div style="margin-top:10px;">
                            <div style="margin-bottom:8px;">
                                <label>Confirm admin password<br><input type="password" name="admin_password" required style="width:100%;padding:6px;" /></label>
                            </div>
                            <button type="submit">Create product</button>
                            <a href="products.php" style="margin-left:10px;">Cancel</a>
                        </div>
                    </form>
                <?php else: ?>
                    <h4>All products</h4>
                    <table style="width:100%;border-collapse:collapse;margin-top:6px;">
                        <thead>
                            <tr>
                                <th style="text-align:left;padding:8px;border-bottom:1px solid #eee">ID</th>
                                <th style="text-align:left;padding:8px;border-bottom:1px solid #eee">Name</th>
                                <th style="text-align:left;padding:8px;border-bottom:1px solid #eee">Category</th>
                                <th style="text-align:right;padding:8px;border-bottom:1px solid #eee">Price</th>
                                <th style="text-align:right;padding:8px;border-bottom:1px solid #eee">Stock</th>
                                <th style="text-align:left;padding:8px;border-bottom:1px solid #eee">Status</th>
                                <th style="text-align:left;padding:8px;border-bottom:1px solid #eee">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $p): ?>
                                <tr>
                                    <td style="padding:8px;border-bottom:1px solid #fafafa"><?php echo intval($p['product_id']); ?></td>
                                    <td style="padding:8px;border-bottom:1px solid #fafafa"><?php echo htmlspecialchars($p['name']); ?></td>
                                    <td style="padding:8px;border-bottom:1px solid #fafafa"><?php echo htmlspecialchars($p['category_name']); ?></td>
                                    <td style="padding:8px;border-bottom:1px solid #fafafa;text-align:right">₱ <?php echo number_format($p['price'],2); ?></td>
                                    <td style="padding:8px;border-bottom:1px solid #fafafa;text-align:right"><?php echo intval($p['stock']); ?></td>
                                    <td style="padding:8px;border-bottom:1px solid #fafafa"><?php echo $p['deleted_at'] ? '<span style="color:#888">Deleted</span>' : 'Active'; ?></td>
                                    <td style="padding:8px;border-bottom:1px solid #fafafa">
                                        <?php if (!$p['deleted_at']): ?>
                                            <a href="products.php?id=<?php echo intval($p['product_id']); ?>">Edit</a>
                                            <?php else: ?>
                                            <form method="post" style="display:inline">
                                                <input type="hidden" name="action" value="restore">
                                                <input type="hidden" name="product_id" value="<?php echo intval($p['product_id']); ?>">
                                                <input type="password" name="admin_password" placeholder="admin password" required style="padding:6px;margin-right:6px;" />
                                                <button type="submit">Restore</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </section>
    </main>
</body>
</html>
