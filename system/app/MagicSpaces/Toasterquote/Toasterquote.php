<?php
/**
 * MAGICSPACE: toasterquote
 * Renders magic space using quote template
 * Here you can put quote item widgets
 * <tr>
 *   <td class="product-img"> {$quote:item:photo} </td>
 *     <td class="product-info"><p class="item-name">{$quote:item:name}</p>
 *     <p>{$quote:item:shortDescription}</p>
 *     <p class="itemID"><span>Item ID: </span>{$quote:item:sku}</p>
 *    <div class="product-options">{$quote:item:options}</div>
 *  </td>
 *  <td class="product-qty">{$quote:item:qty}</td>
 *  <td class="product-unit-price">{$quote:item:price:unit}</td>
 *  <td class="product-total">{$quote:item:price}</td>
 *  <td class="product-remove">{$quote:item:remove}</td>
 * </tr>
 * @author iamne Eugene I. Nezhuta <eugene@seotoaster.com>
 */

class MagicSpaces_Toasterquote_Toasterquote extends MagicSpaces_Toastercart_Toastercart {

	protected $_parser = null;

	protected function _run() {
		$content         = '';

		$tmpPageContent  = $this->_content;
        try {
		    $this->_content  = $this->_findQuoteTemplateContent();
        } catch (Exceptions_SeotoasterMagicSpaceException $smse) {
            return $smse->getMessage();
        }
		$spaceContent    = $this->_parse();
		$this->_content  = $tmpPageContent;

		$cartContent = $this->_getCartContent();

		if(sizeof($cartContent)) {
			foreach($cartContent as $key => $item) {
				$content .= preg_replace_callback('~{\$quote:(.+)}~U', function($matches) use($key) {
					$options = array_merge(explode(':', $matches[1]), array($key, 'quotemspace'));
					return Tools_Factory_WidgetFactory::createWidget('Quote', $options)->render();
				}, $spaceContent);
			}

			$parser   = new Tools_Content_Parser($content, $this->_toasterData, array());
			$content  = $parser->parseSimple();
		} else {
            $translator = Zend_Controller_Action_HelperBroker::getStaticHelper('language');
            return '<tr><td colspan="7" class="empty-quote-content">' . $translator->translate('This quote is empty.') . '</td></tr>';
        }
		return $content;
	}

	protected function _getCartContent() {
		$pageHelper  = Zend_Controller_Action_HelperBroker::getStaticHelper('page');
		$front       = Zend_Controller_Front::getInstance();
		$quote       = Quote_Models_Mapper_QuoteMapper::getInstance()->find($pageHelper->clean($front->getRequest()->getParams('page')));
        if (!$quote instanceof Quote_Models_Model_Quote) {
            error_log(__CLASS__.': Quote not found.');
            return null;
        }
		$cart        = Models_Mapper_CartSessionMapper::getInstance()->find($quote->getCartId());
        if (!$cart instanceof Models_Model_CartSession) {
            error_log(__CLASS__.': Cart not found.');
            return null;
        }
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
			$quoteTemplates = $templateMapper->findByType(Quote::QUOTE_TEPMPLATE_TYPE);
			$quoteTemplate  = reset($quoteTemplates);
			unset($quoteTemplates);
		} else {
			$quoteTemplate = $templateMapper->find($shoppingConfig['quoteTemplate']);
		}
        if(!$quoteTemplate) {
            error_log('Quote template not found! Cannot parse MagicSpace');
            throw new Exceptions_SeotoasterMagicSpaceException('Sorry, but we can not render the quote page at this moment. Please, try again later.');
        }
	    return $quoteTemplate->getContent();
	}

}
