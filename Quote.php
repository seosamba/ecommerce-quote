<?php
/**
 *
 * @author Eugene I. Nezhuta <eugene@seotoaster.com>
 */

class Quote extends Tools_PaymentGateway {

	/**
	 * Parent id for quote pages
	 *
	 */
	const QUOTE_CATEGORY_ID     = -5;

	/**
	 * Quote generating method canstant, show that quote are building by the admin
	 *
	 */
	const QUOTE_METHOD_BUILD    = 'build';

	/**
	 * Quote generating method canstant, show that quote are generating by the system
	 *
	 */
	const QUOTE_METHOD_GENERATE = 'generate';

	/**
	 * Quote prefix, will be used in title, etc...
	 */
	const QUOTE_PREFIX          = 'Quote';

	/**
	 * JSON helper for sending well-formated json response
	 *
	 * @var Zend_Controller_Action_Helper_Json
	 */
	protected $_jsonHelper     = null;

	/**
	 * Shopping config data
	 *
	 * @var array
	 */
	protected $_shoppingConfig = null;

	/**
	 * Seotoaster page action helper
	 *
	 * @var Helpers_Action_Page
	 */
	protected $_pageHelper     = null;

	/**
	 * Quote mapper allows to save, update, create and search quotes
	 *
	 * @var Quote_Models_Mapper_QuoteMapper
	 */
	protected $_quoteMapper    = null;

	protected $_layout         = null;

	protected $_securedActions = array(
		Tools_Security_Acl::ROLE_SUPERADMIN => array(
            'settings',
			'build',
			'quotes',
			'qty'
        )
	);

	protected function _init() {
		$this->_layout = new Zend_Layout();
		$this->_layout->setLayoutPath(__DIR__ . '/system/views/');

		if ($viewScriptPath = Zend_Layout::getMvcInstance()->getView()->getScriptPaths()){
			$this->_view->setScriptPath($viewScriptPath);
		}
		$this->_view->addScriptPath(__DIR__ . '/system/views/');

		$this->_jsonHelper        = Zend_Controller_Action_HelperBroker::getStaticHelper('json');
		$this->_pageHelper        = Zend_Controller_Action_HelperBroker::getStaticHelper('page');
		$this->_shoppingConfig    = Models_Mapper_ShoppingConfig::getInstance()->getConfigParams();
		$this->_previewMode       = ('preview' == Zend_Controller_Front::getInstance()->getRequest()->getParam('mode', false));
		$this->_quoteMapper       = Quote_Models_Mapper_QuoteMapper::getInstance();
		$this->_view->editAllowed = $this->_editAllowed();
	}

	public function run($requestedParams = array()) {
		$dispatched = parent::run($requestedParams);
		return ($dispatched) ? $dispatched : '';
	}

	/**
	 * Quote configuration action
	 *
	 */
	public function settingsAction() {
		$mapper = Models_Mapper_ShoppingConfig::getInstance();
		$config = $mapper->getConfigParams();
		$form   = new Quote_Forms_Settings();
		if($this->_request->isPost()) {
			if($form->isValid($this->_request->getParams())) {
				$mapper->save($form->getValues());
				$this->_responseHelper->success($this->_translator->translate('Configuration updated'));
                return true;
			} else {
				$this->_jsonHelper->direct($form->getMessages());
			}
		}
		$form->populate($config);
		$this->_view->form = $form;
		echo $this->_view->render('settings.quote.phtml');
	}

    public static function getEcommerceConfigTab() {
        $translator = Zend_Controller_Action_HelperBroker::getStaticHelper('language');
        return array(
            'title'      => $translator->translate('Quote'),
            'contentUrl' =>  Zend_Controller_Action_HelperBroker::getStaticHelper('website')->getUrl() . 'plugin/quote/run/settings/'
        );
    }

	/**
     * Single quote administration action
     *
     * @throws Exceptions_SeotoasterPluginException
     */
	public function buildAction() {
		if(!$this->_request->isPost()) {
			throw new Exceptions_SeotoasterPluginException('Direct access is not allowed');
		}

    	$quote = $this->_quoteMapper->find($this->_request->getParam('quoteId', 0));
		if(!$quote instanceof Quote_Models_Model_Quote) {
			throw new Exceptions_SeotoasterPluginException('Cannot load quote.');
		}
		$quote->registerObserver(new Quote_Tools_Watchdog(array(
			'gateway' => $this
		)));
		$quoteTitle       = $this->_request->getParam('quoteTitle', '');
		$createdAt        = $this->_request->getParam('createdDate', '');
		$expiresAt        = $this->_request->getParam('expiresDate', '');
		$billingFormData  = array();
		$shippingFormData = array();
		if($quoteTitle) {
			$quote->setTitle($quoteTitle);
		}
		if($createdAt) {
			$quote->setCreatedAt(date(DATE_ATOM, strtotime($createdAt)));
			$quote->setValidUntil(date(DATE_ATOM, strtotime($expiresAt)));
		}

		parse_str($this->_request->getParam('billing'), $billingFormData);
		parse_str($this->_request->getParam('shipping'), $shippingFormData);

		if(isset($billingFormData['email'])) {
			if(null === ($customer = $this->_invokeCustomer($billingFormData['email']))) {
				$customer = new Models_Model_Customer();
				$customer->setRoleId(Shopping::ROLE_CUSTOMER)
					->setEmail($billingFormData['email'])
					->setFullName($billingFormData['firstname'] . ' ' . $billingFormData['lastname'])
					->setIpaddress($this->_request->getClientIp())
					->setPassword(md5(uniqid('customer_' . time())));
				$result = Models_Mapper_CustomerMapper::getInstance()->save($customer);
				if ($result) {
					$customer->setId($result);
				}
			}
			$billingAddressId  = $this->_addAddress($billingFormData);
			if(isset($billingFormData['sameForShipping']) && $billingFormData['sameForShipping']) {
				$shippingFormData = $billingFormData;
			}
			$shippingAddressId = $this->_addAddress($shippingFormData, Models_Model_Customer::ADDRESS_TYPE_SHIPPING);
		}

		$quote->setUserId(($customer !== null) ? $customer->getId() : null);
		$this->_quoteMapper->save($quote);

		if($customer) {
			$cart = Models_Mapper_CartSessionMapper::getInstance()->find($quote->getCartId());
			Models_Mapper_CartSessionMapper::getInstance()->save(
				$cart->setBillingAddressId($billingAddressId)
					->setShippingAddressId($shippingAddressId)
					->setUserId($customer->getId())
			);
		}

		if($this->_request->getParam('sendMail', false)) {
			//@todo send mail to the client
		}

		$this->_responseHelper->success($this->_translator->translate('Saved.'));
	}

	/**
	 * Quote item(product) options managment
	 *
	 */
	public function optionsAction() {
		$productId = $this->_request->getParam('pid');
		if($this->_request->isPost()) {
			$quoteId = $this->_pageHelper->clean($this->_request->getParam('qid'));
			$cart    = $this->_invokeCart($this->_quoteMapper->find($quoteId));
			$options = $this->_proccessOptions($this->_request->getParam('options', array()));
			$cartContent = array_map(function($product) use($productId, $options) {
				if($product['product_id'] == $productId) {
					$product['options'] = $options;
				}
				return $product;
			}, $cart->getCartContent());
			Models_Mapper_CartSessionMapper::getInstance()->save($cart->setCartContent($cartContent));
			$this->_responseHelper->success($this->_translator->translate('Saved'));
		}
		$this->_view->productId = $productId;
		echo $this->_view->render('manage.options.quote.phtml');
	}

	public function loadselectionsAction() {
		$quoteId = $this->_pageHelper->clean($this->_request->getParam('qid'));
  	    $cart    = $this->_invokeCart($this->_quoteMapper->find($quoteId));
	}

	/**
	 * Render product add popup and add product to the quote
	 *
	 */
	public function productsAction() {
		if($this->_request->isPost()) {
			$product       = Models_Mapper_ProductMapper::getInstance()->find($this->_request->getParam('pid'));
			$quoteId       = $this->_pageHelper->clean($this->_request->getParam('qid'));
			$cart          = $this->_invokeCart($this->_quoteMapper->find($quoteId));
			$currentTax    = Tools_Tax_Tax::calculateProductTax($product);
			$cartContent   = $cart->getCartContent();
			$cartContent[] = array(
				'product_id' => $product->getId(),
				'price'      => $product->getPrice(),
				'options'    => $this->_proccessOptions($this->_request->getParam('opts', array()), $product),
				'qty'        => $this->_request->getParam('qty', 1),
				'tax'        => $currentTax,
				'tax_price'  => $product->getPrice() + $currentTax
			);
			Models_Mapper_CartSessionMapper::getInstance()->save($cart->setCartContent($cartContent));
			$this->_quoteMapper->save($this->_quoteMapper->find($quoteId));
			$this->_responseHelper->success($this->_translator->translate('Added.'));
		}
		echo $this->_view->render('add.products.quote.phtml');
	}

	/**
	 * Manage list of quotes.
	 *
	 */
	public function quotesAction() {
		$data          = array();
		$requestMethod = $this->_request->getMethod();
		switch($requestMethod) {
			case 'GET':
				//if type parameter specified and eq 'list' render quote list view
				$type = $this->_request->getParam('type', false);
				if($type == 'list') {
					echo $this->_view->render('list.quote.phtml');
					return;
				}
				$quoteId = $this->_request->getParam('qid', 0);
				if($quoteId) {
					$quote = $this->_quoteMapper->find($quoteId);
					$data  = $quote->toArray();
				} else {
					$order   = filter_var($this->_request->getParam('order', false), FILTER_SANITIZE_STRING);
					$limit   = filter_var($this->_request->getParam('limit', false), FILTER_SANITIZE_NUMBER_INT);
					$offset  = filter_var($this->_request->getParam('offset', false), FILTER_SANITIZE_NUMBER_INT);
					$search  = filter_var($this->_request->getParam('search', null), FILTER_SANITIZE_SPECIAL_CHARS);
					if(!$order && !$limit && !$offset) {
						$data = $this->_quoteMapper->fetchAll(null, array('created_at DESC', 'title ASC'));
					} else {
						$data = $this->_quoteMapper->fetchAll(null, array($order), $limit, $offset, $search);
					}
					if(!empty($data)) {
						$data = array_map(function($quote) { return $quote->toArray();}, $data);
					}
				}
			break;
			case 'POST':
				//creating a quote
				$mapper       = Models_Mapper_CartSessionMapper::getInstance();
				$generateType = $this->_request->getParam('type', self::QUOTE_METHOD_GENERATE);
				$editedBy     = $this->_sessionHelper->getCurrentUser()->getFullName();
				switch($generateType) {
					case self::QUOTE_METHOD_GENERATE:
						$billingForm = new Quote_Forms_Address();
						if($billingForm->isValid($this->_request->getParams())) {
							$addressId = $this->_addAddress($billingForm->getValues());
							$cart      = $this->_invokeCart();
							$cart->setBillingAddressId($addressId);
							$mapper->save($cart);
							if(isset($this->_shoppingConfig['autoQuote']) && $this->_shoppingConfig['autoQuote']) {
								$editedBy = 'auto';
							}
						} else {
							$this->_responseHelper->fail(Tools_Content_Tools::proccessFormMessagesIntoHtml($billingForm->getMessages(), get_class($billingForm)));
						}
					break;
					case self::QUOTE_METHOD_BUILD:
						$cart = $mapper->save(new Models_Model_CartSession());
					break;
					default:

					break;
				}
				$quoteId = $this->_makeQuote($cart->getId(), $cart->getUserId(), $editedBy);
				if(Tools_Security_Acl::isAllowed(Tools_Security_Acl::RESOURCE_ADMINPANEL) ||
				   isset($this->_shoppingConfig['autoQuote']) && $this->_shoppingConfig['autoQuote']) {
					$this->_responseHelper->success(array('redirectTo' => $quoteId . '.html'));
				}
				$this->_responseHelper->success($this->_translator->translate('Quote generated'));
			break;
			case 'PUT':
				$quoteParams = json_decode($this->_request->getRawBody(), true);
				if(!is_array($quoteParams) || empty($quoteParams)) {
					throw new Exceptions_SeotoasterPluginException('Quote parameters not passed');
				}
				$quote = new Quote_Models_Model_Quote($quoteParams);
				$quote->registerObserver(new Quote_Tools_Watchdog(array(
					'gateway' => $this
				)));
				if($this->_quoteMapper->save($quote)) {
					$data = array('error' => false, 'responseText' => $this->_translator->translate('Quote updated'));
				} else {
					$data = array('error' => true, 'code' => 409, 'responseText' => $this->_translator->translate('Can not update quote'));
				}
			break;
			case 'DELETE':
				$quoteParams = explode('/', $this->_request->getRequestUri());
				if(!is_array($quoteParams) || empty($quoteParams)) {
					throw new Exceptions_SeotoasterPluginException('Quote parameters not passed');
				}
				$quote = $this->_quoteMapper->find(end($quoteParams));
				if(!$quote instanceof Quote_Models_Model_Quote) {
					$data = array('error' => true, 'responseText' => $this->_translator->translate('Cannot find quote'));
				}
				if($this->_quoteMapper->delete($quote)) {
					$data = array('error' => false, 'responseText' => $this->_translator->translate('Quote successfuly removed'));
				} else {
					$data = array('error' => true, 'responseText' => $this->_translator->translate('Cannot remove quote'));
				}
			break;
			default:
				throw new Exceptions_SeotoasterPluginException('Unknown query type');
			break;
		}
		$this->_jsonHelper->sendJson($data);
	}

	public function qtyAction() {
		if(!$this->_request->isPost()) {
			throw new Exceptions_SeotoasterPluginException('Direct access is not allowed');
		}
		$quoteId   = filter_var($this->_request->getParam('qid'), FILTER_SANITIZE_STRING);
		$productId = filter_var($this->_request->getParam('pid'), FILTER_SANITIZE_STRING);
		$qty       = $this->_request->getParam('qty', 1);
		$cart      = $this->_invokeCart($this->_quoteMapper->find($quoteId));
		$content   = $cart->getCartContent();
		if(!empty($content)) {
			$currency   = Zend_Registry::get('Zend_Currency');
			$totalPrice = 0;
			$subTotal   = 0;
			$total      = 0;
			$content    = array_map(function($productData) use($productId, $qty, &$totalPrice, &$subTotal, &$total) {
				if($productData['product_id'] == $productId) {
					$productData['qty'] = $qty;
					$totalPrice         = $qty * $productData['tax_price'];
				}
				$subTotal += $productData['qty'] * $productData['tax_price'];
				return $productData;
			}, $content);
			$cart->setSubTotal($subTotal);
			$total = $subTotal + $cart->getTotalTax() + $cart->getShippingPrice();
			$cart->setCartContent($content);
			$cart->setTotal($total);
			Models_Mapper_CartSessionMapper::getInstance()->save($cart);
			$this->_responseHelper->success(array(
				'totalPrice' => $currency->toCurrency($totalPrice),
				'subTotal'   => $currency->toCurrency($subTotal),
				'total'      => $currency->toCurrency($total)
			));
		}
	}

	public function searchaddressAction() {
		$searchTerm = $this->_request->getParam('term', false);
		$data       = array();
		if($searchTerm) {
			$addressTable = new Quote_Models_DbTable_ShoppingCustomerAddress();
				$data = $addressTable->searchAddress($searchTerm);
		}
		echo $this->_jsonHelper->direct($data);
	}

	/***************************
	 * options
	 **************************/
	/**
	 * Generating quote form
	 *
	 * @return string
	 */
	protected function _makeOptionQuote() {
		$form = new Quote_Forms_Address();
		if(isset($this->_shoppingConfig['autoQuote']) && $this->_shoppingConfig['autoQuote']) {
			$form->setAttrib('class', '_reload ' . $form->getAttrib('class'));
		}

		//trying to get billng address first, to pre-populate quote form
		$addrKey = Tools_ShoppingCart::getInstance()->getAddressKey(Models_Model_Customer::ADDRESS_TYPE_BILLING);
		if($addrKey === null && $this->_shoppingConfig['shippingType'] != Tools_Shipping_Shipping::SHIPPING_TYPE_PICKUP) {
			//otherwise trying to get shipping address (if shipping is not pick up)
			$addrKey =  Tools_ShoppingCart::getInstance()->getAddressKey(Models_Model_Customer::ADDRESS_TYPE_SHIPPING);
		}

		//if we have any address key -> getting an address
		if($addrKey !== null) {
			$address = Tools_ShoppingCart::getAddressById($addrKey);
			if(is_array($address) && !empty($address)) {
				$form->populate($address);
			}
		}

		$this->_view->form = $form;
		return $this->_view->render('option.quote.phtml');
	}

	protected function _makeOptionQuotes() {
		if(!$this->_editAllowed()) {
			return '<!-- list available only for administrator -->';
		}
		$this->_view->noLayout = true;
		return $this->_view->render('list.quote.dash.phtml');
	}

	/*****************************
	 * all helpers methods
	 ****************************/

	/**
	 * Making quote
	 *
	 * @param integer $cartId
	 * @param integer $userId
	 * @param string $editedBy
	 * @return bool|string
	 */
	protected function _makeQuote($cartId, $userId, $editedBy) {
		$quoteTitle = uniqid($this->_translator->translate(self::QUOTE_PREFIX) . ' ');
		$quoteId    = substr(md5($quoteTitle . time(true)), 0, 15);
		$quoteUrl   = $quoteId . '.html';
		$this->_makeQuotePage(array(
			'quoteTitle' => $quoteTitle,
			'quoteUrl'   => $quoteUrl)
		);
		$quote = new Quote_Models_Model_Quote();
		$quote->setId($quoteId)
			->setStatus(Quote_Models_Model_Quote::STATUS_NEW)
			->setTitle($quoteTitle)
			->setCartId($cartId)
			->setCreatedAt(date(DATE_ATOM))
			->setUpdatedAt(date(DATE_ATOM))
			->setValidUntil(date(DATE_ATOM, strtotime('+1 day', strtotime(date(DATE_ATOM)))))
			->setUserId($userId)
			->setEditedBy($editedBy);
		if($this->_quoteMapper->save($quote)) {
			$this->updateCartStatus($cartId, Models_Model_CartSession::CART_STATUS_PENDING);
			Tools_ShoppingCart::getInstance()->clean();
			return $quoteId;
		}
		return false;
	}

	protected function _makeQuotePage($params) {
		$templateMapper = Application_Model_Mappers_TemplateMapper::getInstance();
		// getting quote template from shopping config
		if(!isset($this->_shoppingConfig['quoteTemplate']) || !$this->_shoppingConfig['quoteTemplate']) {
			//if quote template not secified in configs, get first template by type 'quote'
			$quoteTemplates = $templateMapper->findByType(Application_Model_Models_Template::TYPE_QUOTE);
			$quoteTemplate  = reset($quoteTemplates);
			unset($quoteTemplates);
		} else {
			$quoteTemplate = $templateMapper->find($this->_shoppingConfig['quoteTemplate']);
		}
		if(!$quoteTemplate instanceof Application_Model_Models_Template) {
			throw new Exceptions_SeotoasterPluginException('Quote parameters not passed');
		}
		$page = new Application_Model_Models_Page();
		$page->setH1($params['quoteTitle'])
			->setNavName($params['quoteTitle'])
			->setTemplateId($quoteTemplate->getName())
			->setUrl($params['quoteUrl'])
			->setParentId(self::QUOTE_CATEGORY_ID)
			->setSystem(true)
			->setLastUpdate(date(DATE_ATOM))
			->setShowInMenu(Application_Model_Models_Page::IN_NOMENU)
			->setHeaderTitle($params['quoteTitle']);
		return Application_Model_Mappers_PageMapper::getInstance()->save($page);
	}

	protected function _invokeCustomer($parameter) {
		$customer = null;
		$mapper   = Models_Mapper_CustomerMapper::getInstance();
		if(is_integer($parameter)) {
			$customer = $mapper->find($parameter);
		} elseif($parameter instanceof Quote_Models_Model_Quote) {
			$customer = $mapper->find($parameter->getUserId());
		} elseif(is_string($parameter)) {
			$customer = $mapper->findByEmail($parameter);
		} else {
			throw new Exceptions_SeotoasterPluginException('Wrong parameter type. e-mail, quote or integer expected');
		}
		return $customer;
	}

	/**
	 * Invoke cart session
	 *
	 * @param Quote_Models_Model_Quote $quote
	 * @return Models_Model_CartSession
	 */
	protected function _invokeCart($quote = null) {
		$cart   = null;
		$mapper = Models_Mapper_CartSessionMapper::getInstance();
		if(!$quote instanceof Quote_Models_Model_Quote) {
			$cartStorage = Tools_ShoppingCart::getInstance();
			$cart        = $mapper->find($cartStorage->getCartId());
		} else {
			$cart = $mapper->find($quote->getCartId());
		}
		return $cart;
	}

	/**
	 * Add new address for customer and returns new address id
	 *
	 * @param array $data Asosiative array, should contain 'email' key
	 * @param string $type One of the Models_Model_Customer address type constants
	 * @return mixed
	 */
	protected function _addAddress($data, $type = Models_Model_Customer::ADDRESS_TYPE_BILLING) {
		return Models_Mapper_CustomerMapper::getInstance()->addAddress($this->_invokeCustomer($data['email']), $data, $type);
	}

	protected function _editAllowed() {
		return (Tools_Security_Acl::isAllowed(Tools_Security_Acl::RESOURCE_ADMINPANEL) && !$this->_previewMode);
	}

	protected function _proccessOptions($options = array(), $product = null) {
		if(!empty($options)) {
			parse_str($options, $options);
			$options = array($options['option'] => $options['selection']);
		} else {
			if($product !== null) {
				$options = Tools_Misc::getDefaultProductOptions($product);
			}
		}
		return $options;
	}
}