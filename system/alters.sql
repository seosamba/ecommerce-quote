-- version: 2.2.0

-- version: 2.2.3
ALTER TABLE `shopping_quote` ADD COLUMN `internal_note` text COLLATE utf8_unicode_ci AFTER `disclaimer`;


-- 12/11/2015
-- version: 2.2.4
INSERT INTO `page_types` (`page_type_id`, `page_type_name`) VALUES ('4', 'quote');

-- These alters are always the latest and updated version of the database
UPDATE `plugin` SET `version`='2.2.5' WHERE `name`='quote';
SELECT version FROM `plugin` WHERE `name` = 'quote';
