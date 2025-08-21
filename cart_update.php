<?php
require_once __DIR__.'/config.php'; // এখানে session_start() থাকে ধরে নিচ্ছি

// POST থেকে qty নিয়ে সেশন কার্ট আপডেট
if (!empty($_POST['qty']) && is_array($_POST['qty'])) {
    $new = [];
    foreach ($_POST['qty'] as $pid => $qty) {
        $pid = (int)$pid;
        $q   = max(1, (int)$qty);
        if ($pid > 0) $new[$pid] = $q;
    }
    // অন্তত একটি আইটেম থাকলে রিফ্রেশ করুন; নাহলে খালি কার্ট রাখুন
    if ($new) {
        $_SESSION['cart'] = $new;
    } else {
        unset($_SESSION['cart']);
    }
}

// রিডাইরেক্ট টার্গেট (শুধু লোকাল পাথ অনুমোদন)
$next = $_POST['next'] ?? 'cart.php';
$host = parse_url($next, PHP_URL_HOST);
$scheme = parse_url($next, PHP_URL_SCHEME);
if ($host || $scheme) { // এক্সটার্নাল ব্লক
    $next = 'cart.php';
}

header("Location: {$next}");
exit;
