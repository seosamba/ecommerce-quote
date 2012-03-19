<?php
/**
 * Toaster quote magic space. Works similar to the Toastercart magic space
 * in fact - depends on it. Renders magic space using quote template
 *
 * @author iamne Eugene I. Nezhuta <eugene@seotoaster.com>
 */

class MagicSpaces_Toasterquote_Toasterquote extends MagicSpaces_Toastercart_Toastercart {

	protected $_parser = null;

	protected function _run() {
		$content         = '';

		$tmpPageContent  = $this->_content;
		$this->_content  = $this->_findQuoteTemplateContent();
		$spaceContent    = $this->_parse();
		$this->_content  = $tmpPageContent;

		$cartContent = $this->_getCartContent();
		$cartSize    = sizeof($cartContent);

		if($cartSize) {
			foreach($cartContent as $key => $item) {
				$content .= preg_replace_callback('~{\$quote:(.+)}~U', function($matches) use($key) {
					$options = array_merge(explode(':', $matches[1]), array($key, 'quotemspace'));
					return Tools_Factory_WidgetFactory::createWidget('Quote', $options)->render();
				}, $spaceContent);
			}

			$parser   = new Tools_Content_Parser($content, $this->_toasterData, array());
			$content  = $parser->parseSimple();
		}
		return $content;
	}

	protected function _getCartContent() {
		$pageHelper  = Zend_Controller_Action_HelperBroker::getStaticHelper('page');
		$front       = Zend_Controller_Front::getInstance();
		$quote       = Quote_Models_Mapper_QuoteMapper::getInstance()->find($pageHelper->clean($front->getRequest()->getParams('page')));
		$cart        = Models_Mapper_CartSessionMapper::getInstance()->find($quote->getCartId());
		$cartContent = $cart->getCartContent();
		if(!$cartContent) {
			return null;
		}
		return array_map(function($itemData) {
			$product = Models_Mapper_ProductMapper::getInstance()->find($itemData['product_id']);
			if($product instanceof Models_Model_Product) {
				$itemData['name']  = $product->getName();
				$itemData['photo'] = '';
				$itemData['note']  = 'GFY';
				return $itemData;
			}
		}, $cart->getCartContent());

	}

	protected function _findQuoteTemplateContent() {
		$templateMapper = Application_Model_Mappers_TemplateMapper::getInstance();
		$shoppingConfig = Models_Mapper_ShoppingConfig::getInstance()->getConfigParams();
		if(!isset($shoppingConfig['quoteTemplate']) || !$shoppingConfig['quoteTemplate']) {
			//if quote template not secified in configs, get first template by type 'quote'
			$quoteTemplates = $templateMapper->findByType(Application_Model_Models_Template::TYPE_QUOTE);
			$quoteTemplate  = reset($quoteTemplates);
			unset($quoteTemplates);
		} else {
			$quoteTemplate = $templateMapper->findByName($this->_shoppingConfig['quoteTemplate']);
		}
	    return $quoteTemplate->getContent();
	}

}
