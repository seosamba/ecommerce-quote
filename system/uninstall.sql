DROP TABLE IF EXISTS `shopping_quote`;
DELETE FROM `email_triggers` WHERE `trigger_name` = 'quote_created';
DELETE FROM `email_triggers` WHERE `trigger_name` = 'quote_updated';
DELETE FROM `template_type` WHERE `id` = 'typequote';
DELETE FROM `page_option` WHERE `id` = 'option_quotepage';