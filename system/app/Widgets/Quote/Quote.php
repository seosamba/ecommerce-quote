<?php
/**
 * @author Eugene I. Nezhuta <eugene@seotoaster.com>
 * Date: 2/24/12
 * Time: 5:19 PM
 */

class Widgets_Quote_Quote extends Widgets_Abstract {

	const INFO_TYPE_SHIPPING = 'shipping';

	const INFO_TYPE_BILLING  = 'billing';

	protected $_sessionHelper  = null;

	protected $_quote          = null;

	protected $_cartItem       = null;

	protected $_websiteHelper  = null;

	protected $_view           = null;

	protected $_shoppingConfig = array();

	protected $_currency       = null;

	protected function _init() {
		$this->_view = new Zend_View(array(
			'scriptPath' => __DIR__ . '/views'
		));
		$this->_view->setHelperPath(APPLICATION_PATH . '/views/helpers/');
		$this->_shoppingConfig = Models_Mapper_ShoppingConfig::getInstance()->getConfigParams();
		$this->_sessionHelper  = Zend_Controller_Action_HelperBroker::getStaticHelper('session');
		$this->_websiteHelper  = Zend_Controller_Action_HelperBroker::getStaticHelper('website');
		$this->_currency       = Zend_Registry::get('Zend_Currency');
		$this->_quote          = $this->_findQuote();
	}

	protected function _findQuote() {
		$pageHelper   = Zend_Controller_Action_HelperBroker::getStaticHelper('page');
		$requestedUri = isset($this->_toasterOptions['url']) ? $this->_toasterOptions['url'] : Tools_System_Tools::getRequestUri();
		return Quote_Models_Mapper_QuoteMapper::getInstance()->find($pageHelper->clean($requestedUri));
	}

	protected function _load() {
		if(empty($this->_options)) {
			throw new Exceptions_SeotoasterWidgetException('Not enough parameters');
		}
		$rendererName = '_render' . ucfirst(array_shift($this->_options));
		if(method_exists($this, $rendererName)) {
			return $this->$rendererName();
		}
	}

	protected function _renderTitle() {
		return $this->_quote->getTitle();
	}

	protected function _renderSubtotal() {
		$cart = $this->_invokeCart();
		return $this->_currency->toCurrency(array_reduce($cart->getCartContent(), function($result, $item) {
			return ($result += ($item['tax_price'] * $item['qty']));
		}, 0));
	}

	protected function _renderTotaltax() {
		$cart = $this->_invokeCart();
		return $this->_currency->toCurrency(array_reduce($cart->getCartContent(), function($result, $item) {
			return ($result += $item['tax']);
		}, 0));
	}

	protected function _renderShipping() {
		$cart = $this->_invokeCart();
		return $this->_currency->toCurrency($cart->getShippingPrice());
	}

	protected function _renderDiscount() {
		return $this->_currency->toCurrency($this->_quote->getDiscount());
	}

	protected function _renderTotal() {
		$cart = $this->_invokeCart();
		$subTotal = array_reduce($cart->getCartContent(), function($result, $item) {
			return ($result += ($item['tax_price'] * $item['qty']));
		}, 0);
		$tax      = array_reduce($cart->getCartContent(), function($result, $item) {
			return ($result += $item['tax']);
		}, 0);
		$shipping = $cart->getShippingPrice();
		$discount = $this->_quote->getDiscount();
		return $this->_currency->toCurrency($subTotal + $tax + $shipping + $discount);
	}

	protected function _renderCreated() {
		return date('m-d-Y', strtotime($this->_quote->getCreatedAt()));
	}

	protected function _renderExpires() {
		return date('m-d-Y', strtotime($this->_quote->getValidUntil()));
	}

	protected function _renderPrint() {
		$configHelper              = Zend_Controller_Action_HelperBroker::getStaticHelper('config');
		$this->_view->currentTheme = $configHelper->getConfig('currentTheme');
		return $this->_view->render('print.quote.phtml');
	}

	protected function _renderSummary() {
		$summary = array(
			'subTotal' => 0,
			'totalTax' => 0,
			'shipping' => 0,
			'discount' => 0,
			'total'    => 0
		);
		$this->_view->summary = $summary;
		return $this->_view->render('summary.quote.phtml');
	}

	protected function _renderShippinginfo() {
		$this->_view->infoType     = self::INFO_TYPE_SHIPPING;
		$this->_view->customerData = $this->_getInfo(self::INFO_TYPE_SHIPPING);
		return $this->_view->render('info.quote.phtml');
	}

	protected function _renderBillinginfo() {
		$this->_view->infoType     = self::INFO_TYPE_BILLING;
		$this->_view->customerData = $this->_getInfo();
		return $this->_view->render('info.quote.phtml');
	}

	private function _getInfo($infoType = self::INFO_TYPE_BILLING) {
		$cart = $this->_invokeCart();
		return Tools_ShoppingCart::getAddressById(($infoType == self::INFO_TYPE_BILLING) ? $cart->getBillingAddressId() : $cart->getShippingAddressId());
	}

	private function _invokeCart() {
		return Models_Mapper_CartSessionMapper::getInstance()->find($this->_quote->getCartId());
	}

	protected function _renderItem() {
		if(!in_array('quotemspace', $this->_options)) {
			return '';
		}
		unset($this->_options[array_search('quotemspace', $this->_options, true)]);
		$cartKey     = end($this->_options);
		$cart        = Models_Mapper_CartSessionMapper::getInstance()->find($this->_quote->getCartId());
		$cartContent = $cart->getCartContent();
		$cartItem    = $cartContent[$cartKey];
		$product     = Models_Mapper_ProductMapper::getInstance()->find($cartItem['product_id']);
		$cartItem    = array_merge($cartItem, $product->toArray());
		$content     = '';
		if(isset($this->_options[0])) {
			switch($this->_options[0]) {
				case 'photo':
					$folder  = '/product/';
					$content = '<img src="' . $this->_websiteHelper->getUrl() . 'media/' . str_replace('/', $folder, $cartItem['photo']) . '" alt="' . $cartItem['name'] . '" />';
				break;
				case 'price':
					$this->_view->content = (isset($this->_options[1]) && $this->_options[1] == 'unit') ? $cartItem['price'] : $cartItem['price']*$cartItem['qty'];
					$content              = $this->_view->render('price.quote.item.phtml');
				break;
				case 'options':
					$this->_view->quoteItem  = $cartItem;
					$this->_view->weightSign = $this->_shoppingConfig['weightUnit'];
					$content                 = $this->_view->render('options.quote.item.phtml');
				break;
				default:
					$content = (isset($cartItem[$this->_options[0]])) ? $cartItem[$this->_options[0]] : '';
				break;
			}
		}
		return $content;

	}

}
