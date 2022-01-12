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

-- 05/08/2020
-- version: 2.2.9
ALTER TABLE `shopping_quote` ADD COLUMN `payment_type` ENUM('full_payment','partial_payment','only_signature') DEFAULT 'full_payment';
ALTER TABLE `shopping_quote` ADD COLUMN `is_signature_required` ENUM('0','1') DEFAULT '0';
ALTER TABLE `shopping_quote` ADD COLUMN `pdf_template` VARCHAR(45) COLLATE utf8_unicode_ci DEFAULT '';
ALTER TABLE `shopping_quote` ADD COLUMN `signature` LONGTEXT COLLATE utf8_unicode_ci DEFAULT '';
ALTER TABLE `shopping_quote` ADD COLUMN `is_quote_signed` ENUM('0','1') DEFAULT '0';
ALTER TABLE `shopping_quote` ADD COLUMN `quote_signed_at` TIMESTAMP NULL;
INSERT INTO `template_type` (`id`, `title`) VALUES ('typepdfquote', 'Quote pdf');
UPDATE `plugin` SET `tags`='ecommerce,userdeleteerror,salespermission' WHERE `name` = 'quote';
INSERT INTO `email_triggers` (`enabled`, `trigger_name`, `observer`) VALUES( '1', 'quote_signed', 'Quote_Tools_QuoteMailWatchdog');

-- 11/05/2021
-- version: 2.3.0
ALTER TABLE `shopping_quote` MODIFY COLUMN `status` ENUM('new','sent','signature_only_signed','sold','lost') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'new';

-- 27/05/2021
-- version: 2.3.1
CREATE TABLE IF NOT EXISTS `quote_custom_fields_config` (
    `id` INT(10) UNSIGNED AUTO_INCREMENT NOT NULL,
    `param_type` ENUM('text','select','radio','textarea','checkbox') DEFAULT 'text',
    `param_name` VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL,
    `label` VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL,
    PRIMARY KEY(`id`),
    UNIQUE(`param_type`, `param_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `quote_custom_params_options_data` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `custom_param_id` INT UNSIGNED NOT NULL,
    `option_value` VARCHAR(255) NULL,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`custom_param_id`) REFERENCES `quote_custom_fields_config` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
    ) ENGINE = InnoDB DEFAULT CHARSET = utf8 COLLATE = utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `quote_custom_params_data` (
    `id` INT(10) UNSIGNED AUTO_INCREMENT NOT NULL,
    `cart_id` INT(10) UNSIGNED NOT NULL,
    `param_id` INT(10) UNSIGNED NOT NULL,
    `param_value` VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL,
    `params_option_id` INT(10) DEFAULT NULL,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`param_id`) REFERENCES `quote_custom_fields_config` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
    FOREIGN KEY (`cart_id`) REFERENCES `shopping_cart_session` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- 18/09/2021
-- version: 2.3.2
INSERT IGNORE INTO `shopping_config` (`name`, `value`) VALUES
('allowAutosave', 1),
('disableAutosaveEmail', 0);

-- 13/08/2021
-- version: 2.3.3
UPDATE `plugin` SET `tags`='ecommerce,userdeleteerror,userdelete,salespermission' WHERE `name` = 'quote';
ALTER TABLE `shopping_quote` DROP FOREIGN KEY `shopping_quote_ibfk_3`;
ALTER TABLE `shopping_quote` ADD CONSTRAINT `shopping_quote_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE SET NULL ON UPDATE NO ACTION;

-- 10/12/2021
-- version: 2.3.4
ALTER TABLE `shopping_quote` ADD COLUMN `is_quote_restricted_control` ENUM('0','1') DEFAULT '0';

-- These alters are always the latest and updated version of the database
UPDATE `plugin` SET `version`='2.3.5' WHERE `name`='quote';
SELECT version FROM `plugin` WHERE `name` = 'quote';
