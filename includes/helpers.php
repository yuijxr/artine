<?php
/**
 * Common helper functions for the application.
 */

if (!function_exists('resolve_image_path')) {
    function resolve_image_path($image, $category = '') {
    // If no image provided, return a sensible default within uploads
    if (!$image) return 'uploads/thumbnail_img/noimg.png';

        // If it's a remote URL, return as-is
        if (preg_match('#^https?://#i', $image)) return $image;

        // Normalize leading slashes
        $trimmed = preg_replace('#^/+#', '', $image);

        // If it's already stored under uploads/product_img, return normalized path
        if (preg_match('#^uploads/product_img/#i', $trimmed)) {
            return $trimmed;
        }

        // If value contains a slash, it may be in the form category/filename; prefer uploads/product_img/<that path>
        if (strpos($trimmed, '/') !== false) {
            if (file_exists(__DIR__ . '/../uploads/product_img/' . $trimmed)) {
                return 'uploads/product_img/' . $trimmed;
            }
            // otherwise, try basename search in common folders
            $base = basename($trimmed);
            $common = ['products', 'shirts', 'caps', 'perfumes'];
            foreach ($common as $c) {
                $p = __DIR__ . '/../uploads/product_img/' . $c . '/' . $base;
                if (file_exists($p)) {
                    return 'uploads/product_img/' . $c . '/' . $base;
                }
            }
            // last resort: return the trimmed value under uploads so admin can populate later
            return 'uploads/product_img/' . $trimmed;
        }

        // It's a bare filename. Prefer category folder first (if category provided), else 'products'
        $cat = strtolower($category ?? '');
        $folder = '';
        if (strpos($cat, 'shirt') !== false) {
            $folder = 'shirts';
        } elseif (strpos($cat, 'cap') !== false) {
            $folder = 'caps';
        } elseif (strpos($cat, 'perfume') !== false) {
            $folder = 'perfumes';
        }
        if ($folder) {
            $p = __DIR__ . '/../uploads/product_img/' . $folder . '/' . $trimmed;
            if (file_exists($p)) return 'uploads/product_img/' . $folder . '/' . $trimmed;
            // otherwise return the intended path under uploads so admin can populate later
            return 'uploads/product_img/' . $folder . '/' . $trimmed;
        }

        // Default: prefer uploads/product_img/products/<filename>
        $p = __DIR__ . '/../uploads/product_img/products/' . $trimmed;
        if (file_exists($p)) return 'uploads/product_img/products/' . $trimmed;
        return 'uploads/product_img/products/' . $trimmed;
    }
}

if (!function_exists('resolve_thumbnail_path')) {
    function resolve_thumbnail_path($thumb, $category = '') {
        if (!$thumb) return 'uploads/thumbnail_img/noimg.png';
        if (preg_match('#^https?://#i', $thumb)) return $thumb;
        $trimmed = preg_replace('#^/+#', '', $thumb);
        if (preg_match('#^uploads/thumbnail_img/#i', $trimmed)) return $trimmed;

        if (strpos($trimmed, '/') !== false) {
            if (file_exists(__DIR__ . '/../uploads/thumbnail_img/' . $trimmed)) {
                return 'uploads/thumbnail_img/' . $trimmed;
            }
            $base = basename($trimmed);
            $common = ['products', 'shirts', 'caps', 'perfumes'];
            foreach ($common as $c) {
                $p = __DIR__ . '/../uploads/thumbnail_img/' . $c . '/' . $base;
                if (file_exists($p)) return 'uploads/thumbnail_img/' . $c . '/' . $base;
            }
            return 'uploads/thumbnail_img/' . $trimmed;
        }

        $cat = strtolower($category ?? '');
        $folder = '';
        if (strpos($cat, 'shirt') !== false) $folder = 'shirts';
        elseif (strpos($cat, 'cap') !== false) $folder = 'caps';
        elseif (strpos($cat, 'perfume') !== false) $folder = 'perfumes';
        if ($folder) {
            $p = __DIR__ . '/../uploads/thumbnail_img/' . $folder . '/' . $trimmed;
            if (file_exists($p)) return 'uploads/thumbnail_img/' . $folder . '/' . $trimmed;
            return 'uploads/thumbnail_img/' . $folder . '/' . $trimmed;
        }
        $p = __DIR__ . '/../uploads/thumbnail_img/products/' . $trimmed;
        if (file_exists($p)) return 'uploads/thumbnail_img/products/' . $trimmed;
        return 'uploads/thumbnail_img/products/' . $trimmed;
    }
}
