DROP TABLE IF EXISTS `shopping_quote`;
DELETE FROM `email_triggers` WHERE `trigger_name` = 'quote_created';
DELETE FROM `email_triggers` WHERE `trigger_name` = 'quote_updated';
DELETE FROM `template_type` WHERE `id` = 'typequote';
DELETE FROM `page_option` WHERE `id` = 'option_quotepage';

DROP TABLE IF EXISTS `shopping_quote_draggable`;
DROP TABLE IF EXISTS `quote_custom_fields_config`;
DROP TABLE IF EXISTS `quote_custom_params_data`;
DROP TABLE IF EXISTS `quote_custom_params_options_data`;
