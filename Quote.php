<?php
/**
 *
 * @author Eugene I. Nezhuta <eugene@seotoaster.com>
 */

class Quote extends Tools_PaymentGateway {

	/**
	 * JSON helper for sending well-formated json response
	 *
	 * @var Zend_Controller_Action_Helper_Json
	 */
	protected $_jsonHelper     = null;

	protected $_cartStorage    = null;

	protected $_configHelper   = null;

	protected $_shoppingConfig = null;

	protected function _init() {
		$this->_jsonHelper     = Zend_Controller_Action_HelperBroker::getStaticHelper('json');
		$this->_configHelper   = Zend_Controller_Action_HelperBroker::getStaticHelper('config');
		$this->_shoppingConfig = Models_Mapper_ShoppingConfig::getInstance()->getConfigParams();
		$this->_cartStorage    = Tools_ShoppingCart::getInstance();
		$this->_view->setScriptPath(__DIR__ . '/system/views/');
	}

	public function run($requestedParams = array()) {
		$dispatched = parent::run($requestedParams);
		return ($dispatched) ? $dispatched : '';
	}

	/**
	 * Admin quote list interface action
	 *
	 */
	public function manageAction() {
		echo $this->_view->render('manage.quote.phtml');
	}

	/**
	 * Adimin screen (full-size) where admin able to create new quote or update existing one
	 *
	 */
	public function buildAction() {
		$this->_view->shippingType = $this->_shoppingConfig['shippingType'];
		$this->_view->lastEditedBy = $this->_sessionHelper->getCurrentUser()->getFullName();
		echo $this->_view->render('build.quote.phtml');
	}

	public function additemAction() {
		if($this->_request->isPost()) {
			$params = $this->_request->getParams();
			$this->_responseHelper->success($this->_translator->translate('Added.'));
		}
		echo $this->_view->render('add.products.quote.phtml');
	}

	/**
	 * REST action for the quote managment
	 *
	 */
	public function quoteAction() {
		$data   = array();
		$method = $this->_request->getMethod();
		switch($method) {
			case 'GET':
				$qid = $this->_request->getParam('qid', 0);
				if($qid) {
					$quote = Quote_Models_Mapper_QuoteMapper::getInstance()->find($qid);
					echo $quote->getContent(); die();
				}
				$data = Quote_Models_Mapper_QuoteMapper::getInstance()->fetchAll();
				if(!empty($data)) {
					$data = array_map(function($item) { return $item->toArray();}, $data);
				}
			break;
			case 'POST':
				$data = $this->_saveQuote();
			break;
			case 'PUT':
			   $data = $this->_updateQuote();
			break;
			case 'DELETE':
			   $data = $this->_removeQuote();
			break;
			default:
				throw new Exceptions_SeotoasterPluginException('Unknown query type');
			break;
		}
		$this->_jsonHelper->direct($data);
	}

	/**
	 * Generating quote form
	 *
	 * @return string
	 */
	protected function _makeOptionQuote() {
		$form = new Quote_Forms_Address();
		if(isset($this->_shoppingConfig['autoQuote']) && $this->_shoppingConfig['autoQuote']) {
			//$form->addAttribs(array('class' => '_fajax _reload'));
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

	private function _saveQuote() {
		$form = new Quote_Forms_Address();
		if($form->isValid($this->_request->getParams())) {

			//adding a billing address for current customer
			$formData       = $form->getValues();
			$customer       = Models_Mapper_CustomerMapper::getInstance()->findByEmail($formData['email']);
			$quoteAddressId = Models_Mapper_CustomerMapper::getInstance()->addAddress($customer, $formData, Models_Model_Customer::ADDRESS_TYPE_BILLING);

			//getting current cart and making quote
			$cart = Models_Mapper_CartSessionMapper::getInstance()->find($this->_cartStorage->getCartId());
			if(!$cart instanceof Models_Model_CartSession) {
				if(APPLICATION_ENV == 'development') {
					error_log('Cant generate quote => shopping cart is null');
				}
				$this->_responseHelper->fail('Cannot generate quote. Please, try again later.');
			}
			$cart->setBillingAddressId($quoteAddressId);
			Models_Mapper_CartSessionMapper::getInstance()->save($cart);


			$quoteTitle = uniqid('Quote ');
			$quoteId    = substr(md5($quoteTitle . time(true)), 0, 15);
			$quoteUrl   = $quoteId . '.html';
			$this->_createQuotePage(array(
				'quoteTitle' => $quoteTitle,
				'quoteUrl'   => $quoteUrl
			));
			$quote = new Quote_Models_Model_Quote();
			$quote->setId($quoteId)
			    ->setStatus(Quote_Models_Model_Quote::STATUS_NEW)
				->setTitle($quoteTitle)
				->setCartId($cart->getId())
				->setCreatedAt(date(DATE_ATOM))
				->setUpdatedAt(date(DATE_ATOM))
				->setValidUntil(date(DATE_ATOM, strtotime('+1 day', strtotime(date(DATE_ATOM)))))
				->setUserId($cart->getUserId());
			if(isset($this->_shoppingConfig['autoQuote']) && $this->_shoppingConfig['autoQuote']) {
				$quote->setEditedBy('auto');
			}

			if(Quote_Models_Mapper_QuoteMapper::getInstance()->save($quote)) {

				$this->updateCartStatus($cart->getId(), Models_Model_CartSession::CART_STATUS_PENDING);
				$this->_cartStorage->clean();

				if(isset($this->_shoppingConfig['autoQuote']) && $this->_shoppingConfig['autoQuote']) {
					$this->_responseHelper->success(array('redirectTo' => $quoteUrl));
				}
				$this->_responseHelper->success($this->_translator->translate('Quote generated'));
			}
			$this->_responseHelper->fail($this->_translator->translate('Problem to save quote'));
		} else {
			$this->_responseHelper->fail(Tools_Content_Tools::proccessFormMessagesIntoHtml($form->getMessages(), get_class($form)));
		}
	}

	protected function _updateQuote() {
		$quoteParams = json_decode($this->_request->getRawBody(), true);
		if(!is_array($quoteParams) || empty($quoteParams)) {
			throw new Exceptions_SeotoasterPluginException('Quote parameters not passed');
		}
		$quote = new Quote_Models_Model_Quote($quoteParams);
		if(Quote_Models_Mapper_QuoteMapper::getInstance()->save($quote)) {
			//$this->_responseHelper->success($this->_translator->translate('Quote updated'));
			return array('error' => false, 'responseText' => $this->_translator->translate('Quote updated'));
		}
		return array('error' => true, 'code' => 409, 'responseText' => $this->_translator->translate('Can not update quote'));
		//$this->_responseHelper->fail('Can not update quote');
	}

	protected function _removeQuote() {
		$quoteParams = explode('/', $this->_request->getRequestUri());
		if(!is_array($quoteParams) || empty($quoteParams)) {
			throw new Exceptions_SeotoasterPluginException('Quote parameters not passed');
		}
		$quote = Quote_Models_Mapper_QuoteMapper::getInstance()->find(end($quoteParams));
		if(!$quote instanceof Quote_Models_Model_Quote) {
			//$this->_responseHelper->fail($this->_translator->translate('Cannot find quote'));
			return array('error' => true, 'responseText' => $this->_translator->translate('Cannot find quote'));
		}
		if(Quote_Models_Mapper_QuoteMapper::getInstance()->delete($quote)) {
			//$this->_responseHelper->success($this->_translator->translate('Quote successfuly removed'));
			return array('error' => false, 'responseText' => $this->_translator->translate('Quote successfuly removed'));
		}
		//$this->_responseHelper->fail($this->_translator->translate('Cannot remove quote'));
		return array('error' => true, 'responseText' => $this->_translator->translate('Cannot remove quote'));
	}

	protected function _createQuotePage($params) {
		$templateMapper = Application_Model_Mappers_TemplateMapper::getInstance();

		// getting quote template from shopping config
		if(!isset($this->_shoppingConfig['quoteTemplate']) || !$this->_shoppingConfig['quoteTemplate']) {
			//if quote template not secified in configs, get first template by type 'quote'
			$quoteTemplates = $templateMapper->findByType(Application_Model_Models_Template::TYPE_QUOTE);
			$quoteTemplate  = reset($quoteTemplates);
			unset($quoteTemplates);
		} else {
			$quoteTemplate = $templateMapper->findByName($this->_shoppingConfig['quoteTemplate']);
		}

		if(!$quoteTemplate instanceof Application_Model_Models_Template) {
			//throw exception
		}

		$page = new Application_Model_Models_Page();
		$page->setH1($params['quoteTitle'])
			->setNavName($params['quoteTitle'])
			->setTemplateId($quoteTemplate->getName())
			->setUrl($params['quoteUrl'])
			->setHeaderTitle($params['quoteTitle']);
		return Application_Model_Mappers_PageMapper::getInstance()->save($page);
	}

	protected function _getParamsFromRawHttp() {
		parse_str($this->_request->getRawBody(), $this->_requestedParams);
	}
}
