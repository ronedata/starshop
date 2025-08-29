-- schema.sql
-- DB & Tables only (no data)

CREATE DATABASE IF NOT EXISTS `sobujaco_starshop`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `sobujaco_starshop`;

-- 1) admin_users (no dependencies)
CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2) categories (parent of products)
CREATE TABLE IF NOT EXISTS `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3) products (FK -> categories)
CREATE TABLE IF NOT EXISTS `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `compare_at_price` decimal(10,2) DEFAULT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `tags` varchar(255) DEFAULT NULL,
  `specifications` text DEFAULT NULL,
  `image` varchar(400) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  KEY `idx_products_stock` (`stock`),
  CONSTRAINT `products_ibfk_1`
    FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4) orders (independent)
CREATE TABLE IF NOT EXISTS `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_code` varchar(20) DEFAULT NULL,
  `customer_name` varchar(255) NOT NULL,
  `mobile` varchar(30) NOT NULL,
  `address` text NOT NULL,
  `shipping_area` enum('dhaka','nationwide') NOT NULL DEFAULT 'dhaka',
  `shipping_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_method` enum('COD') NOT NULL DEFAULT 'COD',
  `note` text DEFAULT NULL,
  `status` enum('pending','processing','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_code` (`order_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5) order_items (FK -> orders, products)
CREATE TABLE IF NOT EXISTS `order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `qty` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `order_items_ibfk_1`
    FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_items_ibfk_2`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6) product_images (FK -> products)
CREATE TABLE IF NOT EXISTS `product_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `image` varchar(400) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `product_images_ibfk_1`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `product_videos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `type` ENUM('file','youtube') NOT NULL,
  `url` VARCHAR(500) NOT NULL,
  `sort_order` INT DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product_id` (`product_id`),
  UNIQUE KEY `u_product_type` (`product_id`,`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7) settings (no dependencies)
CREATE TABLE IF NOT EXISTS `settings` (
  `key` varchar(100) NOT NULL,
  `value` text DEFAULT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- 8) page_views
CREATE TABLE IF NOT EXISTS `page_views` (
  `page` varchar(100) NOT NULL,
  `views` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`page`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- data.sql
-- Inserts only (assumes schema.sql already ran)
USE `sobujaco_starshop`;

-- Optional: uncomment if you want to skip FK order checks temporarily
-- SET FOREIGN_KEY_CHECKS = 0;

-- categories
INSERT INTO `categories` (`id`,`name`) VALUES
(1,'পোশাক'),
(2,'ইলেকট্রনিক্স'),
(3,'অ্যাক্সেসরিজ');

-- products
INSERT INTO `products`
(`id`,`category_id`,`name`,`description`,`price`,`compare_at_price`,`stock`,`tags`,`specifications`,`image`,`active`,`created_at`) VALUES
(1,1,'ক্লাসিক টি-শার্ট','১০০% কটন, আরামদায়ক।',450.00,520.00,120,NULL,NULL,'uploads/tshirt.jpg',1,'2025-08-19 10:22:16'),
(2,2,'ওয়্যারলেস ইয়ারবাড','ব্লুটুথ ৫.০, ২০ ঘন্টা ব্যাটারি।',1650.00,NULL,45,NULL,NULL,'uploads/earbuds.jpg',1,'2025-08-19 10:22:16'),
(3,3,'স্মার্টফোন কেস','শকপ্রুফ প্রটেকশন।',250.00,300.00,200,NULL,NULL,'uploads/case.jpg',1,'2025-08-19 10:22:16'),
(4,1,'কটন হুডি','শীতের জন্য উষ্ণ, নরম।',890.00,NULL,75,NULL,NULL,'uploads/hoodie.jpg',1,'2025-08-19 10:22:16'),
(5,3,'Kelsie Flynn','Quisquam cupiditate',5000.00,NULL,1,NULL,NULL,'uploads/p_1755597731_3944.jpg',1,'2025-08-19 16:02:11'),
(6,2,'Kelsie Flynn','ট্রাম্প-মোদীর সংঘাত মোদীর পতন ঘটাবে? Zahed\'s Take । জাহেদ উর রহমান ।',1200.00,NULL,2,NULL,NULL,'uploads/p_1755597887_4421.jpg',1,'2025-08-19 16:04:47'),
(7,2,'sagar','Quis officia autem m',5600.00,NULL,2,NULL,NULL,'uploads/p_1755597929_3997.jpg',1,'2025-08-19 16:05:29'),
(8,3,'Candace Michael','Amet voluptate volu',300.00,NULL,2,NULL,NULL,NULL,1,'2025-08-19 16:05:51'),
(9,3,'Kaseem Flowers','Lorem libero quod qu',491.00,652.00,94,NULL,NULL,NULL,1,'2025-08-19 16:06:21'),
(10,2,'Justin Hansen','Eum nihil rerum aliq',508.00,526.00,66,NULL,NULL,'uploads/p_1755598021_9753.jpg',1,'2025-08-19 16:07:01'),
(11,1,'sagar','প্রিলিমিনারি, লিখিত ও মৌখিক পরীক্ষা শেষে চাকরিতে নিয়োগের জন্য প্রাথমিকভাবে নির্বাচিত হন আশরাফুল ইসলাম। তালিকার শুরুর রোল নম্বরটিই তাঁর। তবে শেষ পর্যন্ত তিনি নিয়োগপত্র পাননি।\r\n\r\nসাধারণ বীমা করপোরেশনে জুনিয়র অফিসার (গ্রেড-১০) পদে নিয়োগের জন্য প্রাথমিকভাবে নির্বাচিত মোট ৬৭ প্রার্থীর এই তালিকা গত ১৫ জানুয়ারি প্রকাশিত হয়।',2500.00,NULL,0,'','রাষ্ট্রদূত জানান, ব্রাজিল কম মূল্যে বাংলাদেশে প্রাণীজ আমিষ বিশেষ করে গরুর মাংস রফতানি করতে প্রস্তুত। তার দাবি, ব্রাজিল থেকে গরুর মাংস আমদানি করা গেলে কেজি প্রতি দাম মাত্র এক ডলার অর্থাৎ ১২০ থেকে ১২৫ টাকার মধ্যে পাওয়া যাবে। তবে এ প্রক্রিয়া এগিয়ে নিতে বাংলাদেশের পক্ষ থেকে দ্রুত হালাল সার্টিফিকেট প্রদান জরুরি। এতে শুধু রফতানি বাড়বে না, বরং ব্রাজিলের বেসরকারি উদ্যোক্তারা বাংলাদেশের উদ্যোক্তাদের সাথে অংশীদারিত্বে কাজ করতে আরও আগ্রহী হবে।','/uploads/product_11_1755673592_0.jpg',1,'2025-08-20 12:26:54'),
(12,2,'Brennan Roberson','Veniam qui consecte\r\nকপ-৩০ সম্মেলন নিয়ে আলোচনায় রাষ্ট্রদূত ফার্নান্দো বলেন, জলবায়ু পরিবর্তনের জন্য দায়ী ধনী দেশগুলো প্রতিশ্রুত অর্থ দিতে গড়িমসি করছে। তবে এজন্য বসে থাকলে চলবে না। তিনি আহ্বান জানান, সমন্বিত উদ্যোগের মাধ্যমে সবাইকে জলবায়ু পরিবর্তন মোকাবেলায় এগিয়ে আসতে হবে। এ ক্ষেত্রে সাংবাদিকদের ভূমিকা অত্যন্ত গুরুত্বপূর্ণ বলেও তিনি উল্লেখ করেন।\r\n\r\nমতবিনিময় সভায় সাকজেএফ-এর নির্বাহী সভাপতি আসাদুজ্জামান সম্রাট, সাধারণ সম্পাদক কেরামত উল্লাহ বিপ্লবসহ সংগঠনের বাংলাদেশ চ্যাপ্টারের নেতারা উপস্থিত ছিলেন।',649.00,700.00,58,'adc,gdfdgh,fgdfhgf','রাষ্ট্রদূত জানান, ব্রাজিল কম মূল্যে বাংলাদেশে প্রাণীজ আমিষ বিশেষ করে গরুর মাংস রফতানি করতে প্রস্তুত। তার দাবি, ব্রাজিল থেকে গরুর মাংস আমদানি করা গেলে কেজি প্রতি দাম মাত্র এক ডলার অর্থাৎ ১২০ থেকে ১২৫ টাকার মধ্যে পাওয়া যাবে। তবে এ প্রক্রিয়া এগিয়ে নিতে বাংলাদেশের পক্ষ থেকে দ্রুত হালাল সার্টিফিকেট প্রদান জরুরি। এতে শুধু রফতানি বাড়বে না, বরং ব্রাজিলের বেসরকারি উদ্যোক্তারা বাংলাদেশের উদ্যোক্তাদের সাথে অংশীদারিত্বে কাজ করতে আরও আগ্রহী হবে।','/uploads/product_12_1755671624_0.jpg',1,'2025-08-20 12:33:44'),
(13,3,'Inez Brewer','Ea aut magni consequ',347.00,721.00,39,'Earum, saepe, volupt','Fuga Tempore deser',NULL,0,'2025-08-20 18:37:30'),
(14,3,'Seth Frost','Sed quidem voluptate',873.00,326.00,65,'Adipisci explicabo','Rerum sit velit est','/uploads/product_14_1755693827_0.jpg',0,'2025-08-20 18:43:47'),
(15,2,'PHILIPS Air Fryer HD9200, uses up to 90% less fat, 1400W, 4.1 Liter, with Rapid Air Technology Black (Best Price)','PHILIPS Air Fryer HD9200, uses up to 90% less fat, 1400W, 4.1 Liter, with Rapid Air Technology Black\r\nBrand: PHILIPS \r\nItem code: HD9200\r\nMade in: China\r\nCord length: 0.8 m\r\nPower: 1400 W\r\nMaterial of main body: Plastic\r\nPan (liter*): 4.1 L\r\nPortions: 4\r\nColor: (Black) As given picture.\r\n\r\nSpecifications:\r\nAutomatic shut-off\r\nCool wall exterior\r\nDishwasher safe\r\nOn/off switch\r\nReady signal\r\nTemperature control\r\nPower-on light\r\nQuick Clean\r\nPatented Rapid Air\r\nTime control\r\n\r\nCooking Functions:\r\nFry (hot oil)\r\nRoast (dry heat)\r\nGrill (direct heat)\r\nBake (dry heat)\r\nOne-pot cooking (combination of dry and moist heat)\r\nStir-fry (mixture of dry and wet heat)\r\nSaute (moist heat)\r\nCook from frozen (dry or wet heat, depending on the food)\r\nReheat (dry or moist heat, depending on the food)\r\nDefrost (moist heat)\r\nDehydrate (dry heat)\r\n\r\nFeatures:\r\nRapid Air technology: This technology circulates hot air around the food to cook it evenly and quickly. It uses up to 90% less fat than traditional frying methods.\r\nBuilt-in timer and temperature control: The air fryer has a built-in timer that can be set for up to 60 minutes. It also has a temperature control that can be adjusted from 80 to 200 degrees Celsius.\r\nDishwasher-safe parts: All of the removable parts of the air fryer are dishwasher-safe, making it easy to clean.\r\nCalm wall exterior: The exterior of the air fryer stays cool to the touch, even when it is in use. This makes it safe to use and easy to handle.\r\nCompact design: The air fryer is compact and lightweight, making it easy to store and transport.\r\n\r\nWarranty:\r\n1 Year Spare Parts & 1 Years Service Warranty\r\n\r\nNote:\r\n* 100% CoD available.\r\n*Product delivery duration may vary due to product availability in stock.\r\nDisclaimer: The actual color of the physical product may slightly vary due to the deviation of lighting sources, photography or your device display settings.\r\n\r\nTerms & Conditions:\r\n-For Outside Dhaka Order Customer Have to receive the products from Courier Hub.',8557.00,19900.00,5,'Philips, Panna Electronics,','Size   43','/uploads/product_15_1755755238_0.jpg',1,'2025-08-21 11:47:18');

-- orders
INSERT INTO `orders`
(`id`,`order_code`,`customer_name`,`mobile`,`address`,`shipping_area`,`shipping_fee`,`subtotal`,`total`,`payment_method`,`note`,`status`,`created_at`) VALUES
(1,'ORD0000001','Ciara Franks','+8801708166046','Ducimus dolorem ill','dhaka',60.00,2100.00,2160.00,'COD','','processing','2025-08-19 12:13:52'),
(2,'ORD0000002','Carl Horn','01708166045','Iste cumque dolor es','nationwide',120.00,1016.00,1136.00,'COD','Qui sint voluptatibu','delivered','2025-08-19 16:16:19'),
(3,'ORD0000003','Camille Vargas','01708166045','Dolorem sunt optio','nationwide',120.00,1016.00,1136.00,'COD','Enim proident qui u','pending','2025-08-20 12:37:33'),
(4,'ORD0000004','Quon White','01708166046','Laboriosam pariatur','dhaka',60.00,40707.00,40767.00,'COD','Vitae fugit sint du','pending','2025-08-21 17:23:23');

-- order_items (depends on orders & products)
INSERT INTO `order_items` (`id`,`order_id`,`product_id`,`qty`,`price`) VALUES
(1,1,1,1,450.00),
(2,1,2,1,1650.00),
(3,2,10,2,508.00),
(4,3,10,2,508.00),
(5,4,10,4,508.00),
(6,4,11,1,2500.00),
(7,4,12,3,649.00),
(8,4,15,4,8557.00);

-- product_images (depends on products)
INSERT INTO `product_images` (`id`,`product_id`,`image`,`sort_order`) VALUES
(1,12,'/uploads/product_12_1755671624_0.jpg',0),
(2,11,'/uploads/product_11_1755673592_0.jpg',0),
(3,10,'/uploads/default.jpg',0),
(4,12,'/uploads/product_12_1755693089_0.jpg',0),
(5,13,'/uploads/product_13_1755693450_0.jpg',0),
(6,14,'/uploads/product_14_1755693827_0.jpg',0),
(7,15,'/uploads/product_15_1755755238_0.jpg',0);

-- admin_users
INSERT INTO `admin_users` (`id`,`username`,`password_hash`,`created_at`) VALUES
(1,'rone','$2y$10$ypPWxERmKE/a2r1LdlNvTeZW71149RTHp4bRl89dblJ9H1XYU7JKq','2025-08-19 11:53:39'),
(2,'admin','$2y$10$UqS9dW0vL2X3G8R0Jc6ল8ePSiU5Q7wU8e9G0Yg0m3Q0f7bXo8wH2C','2025-08-19 18:06:58');

-- settings
INSERT INTO `settings` (`key`,`value`) VALUES
('cod_note','সারা দেশে ক্যাশ অন ডেলিভারি প্রযোজ্য।'),
('delivery_dhaka','60'),
('delivery_nationwide','120'),
('home_heading','স্টার শপে স্বাগতম'),
('home_notice','বাংলাদেশের সেরা অনলাইন শপিং প্ল্যাটফর্ম। ক্যাশ অন ডেলিভারি সুবিধা সহ।'),
('maintenance_enabled','0'),
('store_about','বাংলাদেশের সেরা অনলাইন শপিং প্ল্যাটফর্ম। ক্যাশ অন ডেলিভারি সুবিধা সহ।'),
('store_email','info@starshop.com'),
('store_name','স্টার শপ'),
('store_phone','01812345678');

-- Optional: re-enable FK checks
-- SET FOREIGN_KEY_CHECKS = 1;
