DROP TABLE IF EXISTS `shopping_quote`;
CREATE TABLE `shopping_quote` (
  `id` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `title` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `status` enum('new','sent','sold','lost') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'new',
  `disclaimer` text COLLATE utf8_unicode_ci,
  `internal_note` text COLLATE utf8_unicode_ci,
  `discount_tax_rate` enum('0','1','2','3') COLLATE utf8_unicode_ci DEFAULT '1',
  `delivery_type` tinytext COLLATE utf8_unicode_ci,
  `cart_id` int(10) unsigned DEFAULT NULL,
  `edited_by` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `editor_id` int(10) DEFAULT NULL,
  `creator_id` int(10) unsigned DEFAULT '0',
  `expires_at` timestamp NULL DEFAULT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `payment_type` ENUM('full_payment','partial_payment','only_signature') DEFAULT 'full_payment',
  `is_signature_required` ENUM('0','1') DEFAULT '0',
  `pdf_template` VARCHAR(45) COLLATE utf8_unicode_ci DEFAULT '',
  `signature` LONGTEXT COLLATE utf8_unicode_ci DEFAULT '',
  `is_quote_signed` ENUM('0','1') DEFAULT '0',
  `quote_signed_at` TIMESTAMP NULL,
  PRIMARY KEY (`id`),
  KEY `title` (`title`),
  KEY `status` (`status`),
  KEY `edited_by` (`edited_by`),
  KEY `cart_id` (`cart_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `shopping_quote_ibfk_2` FOREIGN KEY (`cart_id`) REFERENCES `shopping_cart_session` (`id`) ON DELETE SET NULL,
  CONSTRAINT `shopping_quote_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

INSERT INTO `email_triggers` (`enabled`, `trigger_name`, `observer`) VALUES( '1', 'quote_created', 'Quote_Tools_QuoteMailWatchdog');
INSERT INTO `email_triggers` (`enabled`, `trigger_name`, `observer`) VALUES( '1', 'quote_updated', 'Quote_Tools_QuoteMailWatchdog');
INSERT INTO `template_type` (`id`, `title`) VALUES ('typequote', 'Quote');
INSERT INTO `page_option` (`id`, `title`, `context`, `active`) VALUES ('option_quotepage', 'Quote page', 'Quote system', 1);
INSERT INTO `page_types` (`page_type_id`, `page_type_name`) VALUES ('4', 'quote');
INSERT INTO `template_type` (`id`, `title`) VALUES ('typepdfquote', 'Quote pdf');

INSERT INTO `observers_queue` (`observable`, `observer`) VALUES ('Models_Model_CartSession', 'Quote_Tools_PurchaseWatchdog');

INSERT IGNORE INTO `shopping_config` (`name`, `value`) VALUES('quoteDraggableProducts', 0);

CREATE TABLE IF NOT EXISTS `shopping_quote_draggable` (
  `quoteId` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `data` TEXT COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`quoteId`),
  FOREIGN KEY  (`quoteId`) REFERENCES `shopping_quote` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

UPDATE `plugin` SET `tags`='ecommerce,userdeleteerror,salespermission' WHERE `name` = 'quote';
UPDATE `plugin` SET `version` = '2.3.0' WHERE `name` = 'quote';
