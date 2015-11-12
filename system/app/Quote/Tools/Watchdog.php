<?php
/**
 * Render and save new quote's content
 *
 * @author iamne Eugene I. Nezhuta <eugene@seotoaster.com>
 */

class Quote_Tools_Watchdog implements Interfaces_Observer {

    const EXTERNAL_TOASTER_URL = 'http://www.seotoaster.com/web-site-quote-system-software-tool.html';

	/**
	 * @var Quote_Models_Model_Quote
	 */
	private $_quote   = null;

	/**
	 * @var array Watchdog options
	 */
	private $_options = array();

	public function  __construct($options = array()) {
		$this->_options = $options;
	}

	public function notify($object) {
		if(!$object instanceof Quote_Models_Model_Quote) {
			throw new Exceptions_SeotoasterPluginException('Instance of Quote_Models_Model_Quote expected.');
		}
		$this->_quote = $object;
		$this->_updateCartStatus()
            ->_updateQuotePage();
	}

    protected function _updateQuotePage() {
        $shoppingConfig = Models_Mapper_ShoppingConfig::getInstance()->getConfigParams();
        $templateMapper = Application_Model_Mappers_TemplateMapper::getInstance();

        if(!isset($shoppingConfig['quoteTemplate']) || !$shoppingConfig['quoteTemplate']) {
            $templates     = $templateMapper->findByType(Quote::QUOTE_TEPMPLATE_TYPE);
            $quoteTemplate = array_shift($templates);
        } else {
            $quoteTemplate = $templateMapper->find($shoppingConfig['quoteTemplate']);
        }

        if(!$quoteTemplate instanceof Application_Model_Models_Template) {
            if(Tools_Security_Acl::isAllowed(Shopping::RESOURCE_STORE_MANAGEMENT)) {
                throw new Exceptions_SeotoasterPluginException('To use this feature, you first need to create a quote template.');
            }
            throw new Exceptions_SeotoasterPluginException('Sorry, we can\'t generate a quote for you right now, please try again later.');
        }

        $pageHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('page');
        $page       = Application_Model_Mappers_PageMapper::getInstance()->findByUrl($pageHelper->filterUrl($this->_quote->getId()));
        if(!$page instanceof Application_Model_Models_Page) {
            $page = new Application_Model_Models_Page();
        }

        $page = Application_Model_Mappers_PageMapper::getInstance()->save(
            $page->setH1($this->_quote->getTitle())
                ->setNavName($this->_quote->getTitle())
                ->setHeaderTitle($this->_quote->getTitle())
                ->setTargetedKeyPhrase($this->_quote->getTitle())
                ->setTemplateId($quoteTemplate->getName())
                ->setUrl($pageHelper->filterUrl($this->_quote->getId()))
                ->setParentId(Quote::QUOTE_CATEGORY_ID)
                ->setSystem(true)
                ->setDraft(false)
                ->setLastUpdate(date(Tools_System_Tools::DATE_MYSQL))
                ->setShowInMenu(Application_Model_Models_Page::IN_NOMENU)
                ->setPageType(Quote::QUOTE_PAGE_TYPE)
        );

        // save special container for the quote page with a disclaimer

        $disclaimer          = $this->_quote->getDisclaimer();

        $containerMapper     = Application_Model_Mappers_ContainerMapper::getInstance();
        $containerName       = $this->_quote->getId() . '-disclaimer';
        $disclaimerContainer = $containerMapper->findByName($containerName);

        if(!$disclaimerContainer instanceof Application_Model_Models_Container) {
            $disclaimerContainer = new Application_Model_Models_Container();
            $disclaimerContainer->setName($containerName)
                ->setContainerType(Application_Model_Models_Container::TYPE_REGULARCONTENT)
                ->setPageId($page->getId());
        }

        $disclaimerContainer->setContent($disclaimer ? $disclaimer : '');
        $containerMapper->save($disclaimerContainer);

        return $this;
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
        return $this;
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
        return $this;
	}
}
