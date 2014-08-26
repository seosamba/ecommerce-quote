-- version: 2.2.3
ALTER TABLE `shopping_quote` ADD COLUMN `internal_note` text COLLATE utf8_unicode_ci AFTER `disclaimer`;

-- These alters are always the latest and updated version of the database
UPDATE `plugin` SET `version`='2.2.4' WHERE `name`='quote';
SELECT version FROM `plugin` WHERE `name` = 'quote';
