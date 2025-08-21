<?php
require_once __DIR__ . '/config.php';

// PHP 5.x/7.x/8.x–কমপ্যাটিবল
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id > 0 && isset($_SESSION['cart']) && isset($_SESSION['cart'][$id])) {
    unset($_SESSION['cart'][$id]);

    // কার্ট একেবারে খালি হয়ে গেলে সেশন কীটাও সরিয়ে দিন (ঐচ্ছিক)
    if (empty($_SESSION['cart'])) {
        unset($_SESSION['cart']);
    }
}

// সবসময় রিডাইরেক্টের পর স্ক্রিপ্ট থামান
header('Location: cart.php');
exit;
