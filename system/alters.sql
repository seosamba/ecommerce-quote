-- version: 2.2.0

-- version: 2.2.3
ALTER TABLE `shopping_quote` ADD COLUMN `internal_note` text COLLATE utf8_unicode_ci AFTER `disclaimer`;


-- 12/11/2015
-- version: 2.2.4
INSERT INTO `page_types` (`page_type_id`, `page_type_name`) VALUES ('4', 'quote');

-- 28/04/2017
-- version: 2.2.5
ALTER TABLE `shopping_quote`
ADD `editor_id` int NULL AFTER `edited_by`;

-- 07/02/2018
-- version: 2.2.6
INSERT INTO `observers_queue` (`observable`, `observer`) VALUES ('Models_Model_CartSession', 'Quote_Tools_PurchaseWatchdog');

-- 31/10/2018
-- version: 2.2.7
UPDATE `plugin` SET `tags`='ecommerce,userdeleteerror' WHERE `name` = 'quote';

-- 06/08/2020
-- version: 2.2.8
INSERT IGNORE INTO `shopping_config` (`name`, `value`) VALUES('quoteDraggableProducts', 0);

CREATE TABLE IF NOT EXISTS `shopping_quote_draggable` (
  `quoteId` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `data` TEXT COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`quoteId`),
  FOREIGN KEY  (`quoteId`) REFERENCES `shopping_quote` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- These alters are always the latest and updated version of the database
UPDATE `plugin` SET `version`='2.2.9' WHERE `name`='quote';
SELECT version FROM `plugin` WHERE `name` = 'quote';
