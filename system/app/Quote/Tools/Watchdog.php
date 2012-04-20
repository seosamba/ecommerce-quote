<?php
/**
 * Render and save new quote's content
 *
 * @author iamne Eugene I. Nezhuta <eugene@seotoaster.com>
 */

class Quote_Tools_Watchdog implements Interfaces_Observer {

	/**
	 * @var Quote_Models_Model_Quote
	 */
	private $_quote   = null;

	/**
	 * @var array Watchdog options
	 */
	private $_options = array();

	public function __construct($options = array()) {
		$this->_options = $options;
	}

	public function notify($object) {
		if(!$object instanceof Quote_Models_Model_Quote) {
			throw new Exceptions_SeotoasterPluginException('Instance of Quote_Models_Model_Quote expected.');
		}
		$this->_quote = $object;
		$this->_updateCartStatus();
		$this->_recalculate();
	}

	/**
	 * Update quote-related shopping cart status
	 *
	 */
	protected function _updateCartStatus() {
		if(!isset($this->_options['gateway'])) {
			throw new Exceptions_SeotoasterPluginException('Gateway not passed.');
		}
		$gateway = $this->_options['gateway'];
		switch($this->_quote->getStatus()) {
			case Quote_Models_Model_Quote::STATUS_NEW:
				$gateway->updateCartStatus($this->_quote->getCartId(), Models_Model_CartSession::CART_STATUS_PENDING);
			break;
			case Quote_Models_Model_Quote::STATUS_SOLD:
				$gateway->updateCartStatus($this->_quote->getCartId(), Models_Model_CartSession::CART_STATUS_COMPLETED);
			break;
			case Quote_Models_Model_Quote::STATUS_SENT:
				$gateway->updateCartStatus($this->_quote->getCartId(), Models_Model_CartSession::CART_STATUS_PROCESSING);
			break;
			case Quote_Models_Model_Quote::STATUS_LOST:
				$gateway->updateCartStatus($this->_quote->getCartId(), Models_Model_CartSession::CART_STATUS_CANCELED);
			break;
			default:
				$gateway->updateCartStatus($this->_quote->getCartId(), Models_Model_CartSession::CART_STATUS_ERROR);
			break;
		}
	}

	protected function _recalculate() {
		$mapper      = Models_Mapper_CartSessionMapper::getInstance();
		$cart        = $mapper->find($this->_quote->getCartId());
		$cart->setSubTotal(0)
			->setTotalTax(0)
			->setTotal(0);
		$cartContent = $cart->getCartContent();
		if(is_array($cartContent) && !empty($cartContent)) {
			array_walk($cartContent, function($product) use($cart) {
				$cart->setSubTotal($cart->getSubTotal() + $product['tax_price'] * $product['qty']);
				$cart->setTotalTax($cart->getTotalTax() + $product['tax']);
			});
			$cart->setTotal($cart->getSubTotal() + $cart->getTotalTax() + $cart->getShippingPrice());
			$mapper->save($cart);
		}
	}
}
