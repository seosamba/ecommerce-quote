DROP TABLE IF EXISTS `shopping_quote`;
CREATE TABLE IF NOT EXISTS `shopping_quote` (
  `id` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `status` enum('new','sent','sold','lost') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'new',
  `cart_id` int(10) unsigned DEFAULT NULL,
  `edited_by` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `valid_until` timestamp NULL DEFAULT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `title` (`title`),
  KEY `status` (`status`),
  KEY `edited_by` (`edited_by`),
  KEY `cart_id` (`cart_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

ALTER TABLE `shopping_quote`
  ADD CONSTRAINT `shopping_quote_ibfk_2` FOREIGN KEY (`cart_id`) REFERENCES `shopping_cart_session` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `shopping_quote_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION;