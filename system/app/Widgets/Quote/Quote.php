<?php
/**
 *
 */
class Widgets_Quote_Quote extends Widgets_Abstract {

	const MODE_PREVIEW        = 'preview';

	const INFO_TYPE_SHIPPING  = 'shipping';

	const INFO_TYPE_BILLING   = 'billing';

	const DATE_TYPE_CREATED   = 'created';

	const DATE_TYPE_EXPIRES   = 'expires';

    const NEWSLIST_DEFAULTS_PERPAGE = 15;

    const NEWSLIST_DEFAULTS_ORDER   = 'DESC';

	protected $_quote         = null;

	protected $_previewMode   = false;

	protected $_currency      = null;

	protected $_cart          = null;

	protected $_websiteHelper = null;

	protected $_pageHelper    = null;

    protected $_cacheable     = false;

    protected $_shoppingConfig = null;

    protected $_cartStorage    = null;

	protected function _init() {
		//views and helpers
		$this->_view = new Zend_View(array('scriptPath' => __DIR__ . '/views'));
		$this->_view->setHelperPath(APPLICATION_PATH . '/views/helpers/');
		$this->_view->addHelperPath('ZendX/JQuery/View/Helper/', 'ZendX_JQuery_View_Helper');

		//website helper
		$this->_websiteHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('website');

		//page helper to clean quote url to get a quote id
		$this->_pageHelper    = Zend_Controller_Action_HelperBroker::getStaticHelper('page');

		//current currency
		$this->_currency = Zend_Registry::get('Zend_Currency');

        //shopping settings
        $this->_shoppingConfig = Models_Mapper_ShoppingConfig::getInstance()->getConfigParams();
        $this->_cartStorage    = Tools_ShoppingCart::getInstance();

		//current quote
		$this->_quote = $this->_findQuote();

        if(!$this->_quote) {
            return null;
        }

		//current cart
		$this->_cart  = Models_Mapper_CartSessionMapper::getInstance()->find($this->_quote->getCartId());

		//in preview mode?
		$this->_previewMode = (self::MODE_PREVIEW == Zend_Controller_Front::getInstance()->getRequest()->getParam('mode', false));

		//edit allowed for view
		$this->_view->editAllowed = $this->_editAllowed();

		//website url to view
		$this->_view->websiteUrl  = $this->_websiteHelper->getUrl();
	}

	protected function _load() {
		if(empty($this->_options)) {
			throw new Exceptions_SeotoasterWidgetException('Not enough parameters');
		}
		$rendererName = '_render' . ucfirst(array_shift($this->_options));
		if(method_exists($this, $rendererName)) {
			return $this->$rendererName();
		}
		return '<!-- can not find renderer ' . $rendererName . ' -->';
	}

	/**
	 * Can current user edit quote and other quote stuff
	 *
	 * @return bool
	 */
	protected function _editAllowed() {
		return (Tools_Security_Acl::isAllowed(Tools_Security_Acl::RESOURCE_ADMINPANEL) && !$this->_previewMode);
	}

	/**
	 * Find quote using it's url
	 *
	 * @return mixed
	 */
	protected function _findQuote() {
		$pageHelper   = Zend_Controller_Action_HelperBroker::getStaticHelper('page');
		$requestedUri = isset($this->_toasterOptions['url']) ? $this->_toasterOptions['url'] : Tools_System_Tools::getRequestUri();
		return Quote_Models_Mapper_QuoteMapper::getInstance()->find($pageHelper->clean($requestedUri));
	}

	/*
	 * Render quote title (editable for an admin)
	 *
	 */
	protected function _renderTitle() {
		$this->_view->title = $this->_quote->getTitle();
		return $this->_view->render('title.quote.phtml');
	}

	/**
	 * Render shipping price
	 *
	 * @return mixed
	 */
	protected function _renderShipping() {
        $shippingPrice              = $this->_cart->getShippingPrice();
		$this->_view->quoteShipping = ($shippingPrice) ? $shippingPrice : 0;
        return $this->_view->render('shipping.quote.phtml');
	}

	/**
	 * Render discount amount
	 *
	 * @return mixed
	 */
	protected function _renderDiscount() {
		return $this->_currency->toCurrency(0);
	}

	/**
	 * Render billing or shipping info
	 *
	 * @return mixed
	 * @throws Exceptions_SeotoasterWidgetException
	 */
	protected function _renderInfo() {
		if(!isset($this->_options[0])) {
			throw new Exceptions_SeotoasterWidgetException('Info type not specified. Use: "billing" or "shiping"');
		}
		$infoType              = $this->_options[0];
		$address               = Tools_ShoppingCart::getAddressById(($infoType == self::INFO_TYPE_BILLING) ? $this->_cart->getBillingAddressId() : $this->_cart->getShippingAddressId());
		$this->_view->infoType = $infoType;
		if($this->_editAllowed()) {
			if($infoType == self::INFO_TYPE_BILLING) {
				$addressForm = new Quote_Forms_Quote();
				$addressForm->removeElement('sendQuote');
			} else {
				$addressForm = new Forms_Checkout_Shipping();
				$addressForm->removeElement('calculateAndCheckout');
				$addressForm->removeElement('mobile');
				$addressForm->removeElement('shippingInstructions');
				$addressForm->removeDisplayGroup('bottom');
			}
			$addressForm->setAttrib('action', '#')
				->populate(($address) ? $address : array());
			$this->_view->addressForm = $addressForm;
		}
		$this->_view->customerData = $address;
		return $this->_view->render('info.quote.phtml');
	}

	/**
	 * Render total tax, grand total and subtotal
	 * @return mixed
	 */
	protected function _renderTotal() {
		$totalType = isset($this->_options[0]) ? $this->_options[0] : 'sub';
		$cartContent = $this->_cart->getCartContent();
		if(!$cartContent || !is_array($cartContent)) {
			return $this->_currency->toCurrency(0);
		}

		$totalTax = array_reduce($cartContent, function($result, $item) {
			return ($result += $item['tax']);
		}, 0);

		$subTotal = array_reduce($cartContent, function($result, $item) {
			$product        = Models_Mapper_ProductMapper::getInstance()->find($item['product_id']);
			$defaultOptions = $product->getDefaultOptions();
			foreach($item['options'] as $optionId => $selectionId) {
				foreach($defaultOptions as $defaultOption) {
					if($optionId != $defaultOption['id']) {
						continue;
					}
				}
				$selections = array_filter($defaultOption['selection'], function($selection) use($selectionId) {
					if($selectionId == $selection['id']) {
						return $selection;
					}
				});
			}
			if(!empty($selection)) {
				foreach($selections as $selection) {
					if($selection['priceType'] == 'unit') {
						$modifier = $selection['priceValue'];
					} else {
						$modifier = ($item['tax_price'] / 100) * $selection['priceValue'];
					}
					$item['tax_price'] = ($selection['priceSign'] == '+') ? $item['tax_price'] + $modifier : $item['tax_price'] - $modifier;
				}
			}
			return ($result += ($item['tax_price'] * $item['qty']));
		}, 0);

		if($totalType == 'tax') {
			return $this->_currency->toCurrency($totalTax);
		}
		if($totalType == 'sub') {
			return '<span class="quote-sub-total-val">' . $this->_currency->toCurrency($subTotal) . '</span>';
		}
		$shippingPrice = $this->_cart->getShippingPrice();
		//@todo Probably we will have to change this, because discount will be moved to the cart
		$discount      = $this->_quote->getDiscount();
		return '<span class="quote-grand-total-val">' . $this->_currency->toCurrency($totalTax + $subTotal + $shippingPrice + $discount) . '</span>';
	}

	/**
	 * Render print quote button with print css
	 *
     * @deprecated Will be removed in next iterations
	 * @return mixed
	 */
	protected function _renderPrint() {
        return 'this widget is deprecated. Please use media type in your css.';
	}

	/**
	 * Render created or epires quote dates
	 *
	 * @return mixed
	 * @throws Exceptions_SeotoasterWidgetException
	 */
	protected function _renderDate() {
		if(!isset($this->_options[0])) {
			throw new Exceptions_SeotoasterWidgetException('Date type not specified. Use: "created" or "expires"');
		}
		$dateType          = $this->_options[0];
		$this->_view->date = ($dateType == self::DATE_TYPE_CREATED) ? $this->_quote->getCreatedAt() : $this->_quote->getExpiresAt();
		$this->_view->type = $dateType;
		return $this->_view->render('date.quote.phtml');
	}

	/**
	 * Render quote controls panel
	 *
	 * @return string
	 */
	protected function _renderControls() {
		if(!$this->_editAllowed()) {
			return '<!-- controls available only for administrator -->';
		}
		$this->_view->quoteId = $this->_pageHelper->clean($this->_toasterOptions['url']);
		return $this->_view->render('controls.quote.phtml');
	}

	/**
	 * Render quote search panel
	 *
	 * @return string
	 */
	protected function _renderSearch() {
		if(!$this->_editAllowed()) {
			return '<!-- search available only for administrator -->';
		}
		return $this->_view->render('search.quote.phtml');
	}

	protected function _renderItem() {
		/**
		 * If it is regular parsing - do nothing
		 */
		if(!in_array('quotemspace', $this->_options)) {
			return '';
		}
		unset($this->_options[array_search('quotemspace', $this->_options, true)]);
		$shoppingConfig = Models_Mapper_ShoppingConfig::getInstance()->getConfigParams();
		$content        = '';
		$cartContent    = $this->_cart->getCartContent();
		$cartItem       = $cartContent[end($this->_options)];
		$currentProduct = Models_Mapper_ProductMapper::getInstance()->find($cartItem['product_id']);
		$cartItem       = array_merge($cartItem, $currentProduct->toArray());
		$currentOptions = $this->_getOptions($cartItem['product_id'], $cartItem['options']);
		if(!isset($this->_options[0])) {
			throw new Exceptions_SeotoasterWidgetException('Wrong options count');
		}
		switch($this->_options[0]) {
			case 'photo':
				$this->_view->folder = (isset($this->_options[1]) && $this->_options[1] && is_dir($this->_websiteHelper->getMedia() . $this->_options[1])) ?  ('/' . $this->_options[1] . '/') : '/product/';
				$this->_view->photo  = $cartItem['photo'];
				$this->_view->name   = $cartItem['name'];
				$content             = $this->_view->render('photo.quote.item.phtml');
			break;
			case 'options':
                if(!isset($cartItem['defaultOptions']) || !is_array($cartItem['defaultOptions']) || empty($cartItem['defaultOptions'])) {
                    break;
                }
				$this->_view->options    = $currentOptions;
				$this->_view->weightSign = $shoppingConfig['weightUnit'];
				$this->_view->pid        = $cartItem['product_id'];
                $this->_view->qid        = $this->_quote->getId();
				$content                 = $this->_view->render('options.quote.item.phtml');
			break;
			case 'price':
				if(!empty($currentOptions)) {
					foreach($currentOptions as $optionData) {
						if($optionData['priceType'] == 'unit') {
							$cartItem['price'] = ($optionData['priceSign'] == '+') ? $cartItem['price'] + $optionData['priceValue'] : $cartItem['price'] - $optionData['priceValue'];
						}
					}
				}
				$this->_view->content   = (isset($this->_options[1]) && $this->_options[1] === 'unit') ? $cartItem['price'] : ($cartItem['price']*$cartItem['qty']);
				$this->_view->unitPrice = (isset($this->_options[1]) && $this->_options[1] === 'unit');
				$content                = $this->_view->render('price.quote.item.phtml');
			break;
			case 'qty':
				$this->_view->qty = $cartItem['qty'];
				$this->_view->pid = $cartItem['id'];
				$content = $this->_view->render('qty.quote.phtml');
			break;
            case 'remove':
                return (Tools_Security_Acl::isAllowed(Tools_Security_Acl::RESOURCE_USERS)) ? '<a data-pid="' . $cartItem['id'] . '" class="remove-product" href="javascript:;"><img src="' . $this->_websiteHelper->getUrl() . 'system/images/delete.png" alt="delete"/></a>' : '';
            break;
			default:
				$content = (isset($cartItem[$this->_options[0]])) ? $cartItem[$this->_options[0]] : '';
			break;
		}
		return $content;
	}

	protected function _getOptions($productId, $options) {
		$actualOptions  = array();
		$product        = Models_Mapper_ProductMapper::getInstance()->find($productId);
		$defaultOptions = $product->getDefaultOptions();
		foreach($options as $optionId => $selectionId) {
			foreach($defaultOptions as $defaultOption) {
				if($optionId != $defaultOption['id']) {
					continue;
				}
				$actualOptions = array_filter($defaultOption['selection'], function($selection) use($selectionId) {
					if($selectionId == $selection['id']) {
						return $selection;
					}
				});
			}
		}
		return $actualOptions;
	}

    protected function _renderGrid() {
        $this->_view->quotes     = Quote_Models_Mapper_QuoteMapper::getInstance()->fetchAll(null, array('created_at ' . self::NEWSLIST_DEFAULTS_ORDER), self::NEWSLIST_DEFAULTS_PERPAGE, 0, null, true);
        $this->_view->websiteUrl = $this->_websiteHelper->getUrl();
        return $this->_view->render('grid.quote.phtml');
    }

    /**
     * Render a quote form
     *
     * @return Quote_Forms_Quote
     */
    protected function _renderForm() {
        $quoteForm = new Quote_Forms_Quote();
        $quoteForm->removeElement('sameForShipping');
        //check if the automatic quote generation is set up - add extra class to the form
        if(isset($this->_shoppingConfig['autoQuote']) && $this->_shoppingConfig['autoQuote']) {
            $quoteForm->setAttrib('class', '_reload ' . $quoteForm->getAttrib('class'));
        }
        //trying to get billng address first, to pre-populate quote form
        $addrKey = $this->_cartStorage->getAddressKey(Models_Model_Customer::ADDRESS_TYPE_BILLING);
        if($addrKey === null) {
            //otherwise trying to get shipping address (if shipping is not pick up)
            $addrKey =  $this->_cartStorage->getAddressKey(Models_Model_Customer::ADDRESS_TYPE_SHIPPING);
        }
        //if we have any address key -> getting an address
        if($addrKey !== null) {
            $address = Tools_ShoppingCart::getAddressById($addrKey);
            if(is_array($address) && !empty($address)) {
                $quoteForm->populate($address);
            }
        }

        //trying to get a product if this page is product page
        $product = Models_Mapper_ProductMapper::getInstance()->findByPageId($this->_toasterOptions['id']);
        if($product instanceof Models_Model_Product) {
            $quoteForm->getElement('productId')->setValue($product->getId());
        }

        $this->_view->form = $quoteForm->setAction($this->_websiteHelper->getUrl() . 'api/quote/quotes/type/' . Quote::QUOTE_TYPE_GENERATE);
        return $this->_view->render('form.quote.phtml');
    }

    public static function getAllowedOptions() {
        $translator = Zend_Registry::get('Zend_Translate');
        return array(
            array(
                'alias'  => $translator->translate('Store Quote Form - Generates instantly a quote request form'),
                'option' => 'quote:form'
            )
        );
    }
}