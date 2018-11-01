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
UPDATE `plugin` SET `tags`='userdeleteerror' WHERE `name` = 'quote';

-- These alters are always the latest and updated version of the database
UPDATE `plugin` SET `version`='2.2.8' WHERE `name`='quote';
SELECT version FROM `plugin` WHERE `name` = 'quote';
