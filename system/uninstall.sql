DROP TABLE IF EXISTS `shopping_quote`;
DELETE FROM `email_triggers` WHERE `trigger_name` = 'quote_newquote';
DELETE FROM `template_type` WHERE `id` = 'typequote';