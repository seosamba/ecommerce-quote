<?php
/**
 * Quote's plugn quote widget
 *
 */
class Widgets_Quote_Quote extends Widgets_Abstract {

    /**
     * Quote widget mode preview
     *
     */
    const MODE_PREVIEW = 'preview';

    /**
     * Quote widget mode edit
     *
     */
    const MODE_EDIT = 'edit';

    /**
     * Quote widget user address type shipping
     *
     */
    const ADDRESS_TYPE_SHIPPING = 'shipping';

    /**
     * Quote widget user address type billing
     *
     */
    const ADDRESS_TYPE_BILLING = 'billing';

    /**
     * Quote widget grid rows per page
     *
     */
    const QUOTEGRID_DEFAULTS_PERPAGE = 15;

    /**
     * Quote widget grid default order
     *
     */
    const QUOTEGRID_DEFAULTS_ORDER = 'DESC';

    /**
     * Quote widget date type created
     *
     */
    const DATE_TYPE_CREATED = 'created';

    /**
     * Quote widget date type expires
     *
     */
    const DATE_TYPE_EXPIRES = 'expires';

    /**
     * Total option type tax
     *
     */
    const TOTAL_TYPE_TAX          = 'tax';

    /**
     * Taxt including discount
     *
     */
    const TOTAL_TYPE_TAX_DISCOUNT = 'taxdiscount';

    /**
     * Total option type subtotal
     *
     */
    const TOTAL_TYPE_SUB    = 'sub';

    /**
     * Total option type grand total
     */
    const TOTAL_TYPE_GRAND  = 'grand';

    /**
     * Total option type total without tax
     */
    const TOTAL_TYPE_WOTAX  = 'totalwotax';

    /**
     * Flag that tells toaster cache the widget or not. Should be set to true for production
     *
     * @var bool
     */
    protected $_cacheable = false;

    /**
     * Quote model instance
     *
     * @var null|Quote_Models_Model_Quote
     */
    protected $_quote = null;

    /**
     * Indicates whether the quote is editable (user role has proper permissions and quote is not in a preveiw mode) or not
     *
     * @var bool
     */
    protected $_editAllowed = false;

    /**
     * Instance of the Zend_Currency
     *
     * @var Zend_Currency
     */
    protected $_currency = null;

    /**
     * Instance of the Models_Model_CartSession attached to the quote
     *
     * @var null|Models_Model_CartSession
     */
    protected $_cart = null;

    /**
     * Current store configuration
     *
     * @var null|array
     */
    protected $_shoppingConfig = null;

    /**
     * Toaster website helper
     *
     * @var null|Helpers_Action_Website
     */
    protected $_websiteHelper  = null;

    /**
     * Indicates whether toaster debug mode enabled
     *
     * @var bool
     */
    protected $_debugMode = false;

    /**
     * @var bool
     */
    protected $_userMode = false;

    /**
     * Fields names that should be always present on the quote form
     *
     * @var array
     */
    protected $_formMandatoryFields = array(
        'productId'      => false,
        'productOptions' => false,
        'sendQuote'      => false
    );

    public static $_accessList  = array(
        Tools_Security_Acl::ROLE_SUPERADMIN,
        Tools_Security_Acl::ROLE_ADMIN,
        Shopping::ROLE_SALESPERSON
    );

    protected $_statusesNotLostQuotes = array(
        Models_Model_CartSession::CART_STATUS_PARTIAL,
        Models_Model_CartSession::CART_STATUS_COMPLETED,
        Models_Model_CartSession::CART_STATUS_SHIPPED,
        Models_Model_CartSession::CART_STATUS_DELIVERED
    );

    /**
     * Initialize all helpers, cofigs, etc...
     *
     */
    protected function _init() {
        //views and helpers
        $this->_view = new Zend_View(array('scriptPath' => __DIR__ . '/views'));
        $this->_view->setHelperPath(APPLICATION_PATH . '/views/helpers/');
        $this->_view->addHelperPath('ZendX/JQuery/View/Helper/', 'ZendX_JQuery_View_Helper');

        //website helper
        $this->_websiteHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('website');

        //current currency
        $this->_currency = Zend_Registry::get('Zend_Currency');

        //shopping settings
        $this->_shoppingConfig = Models_Mapper_ShoppingConfig::getInstance()->getConfigParams();

        //website url to view
        $this->_view->websiteUrl  = $this->_websiteHelper->getUrl();

        //debug mode settings
        $this->_debugMode = Tools_System_Tools::debugMode();

        // initialize editing permissions
        $this->_initEditAllowed();

        // initialize quote
        $this->_initQuote();

        // initialize cart attached to the quote
        $this->_initCart();
    }

    /**
     * Can current user edit quote and other quote stuff
     *
     * @return bool
     */
    protected function _initEditAllowed() {
        $previewMode        = (self::MODE_PREVIEW == Zend_Controller_Front::getInstance()->getRequest()->getParam('mode', false));
        $this->_editAllowed = Quote_Tools_Security::isEditAllowed($previewMode);
        //(Tools_Security_Acl::isAllowed(Tools_Security_Acl::RESOURCE_ADMINPANEL) && !$previewMode);

        //edit allowed for view
        $this->_view->editAllowed = $this->_editAllowed;
    }

    /**
     * Initialize quote model via page url (should be a quote page)
     *
     */
    protected function _initQuote()
    {
        $requestedUri = isset($this->_toasterOptions['url']) ? $this->_toasterOptions['url'] : Tools_System_Tools::getRequestUri();
        $mapper = Quote_Models_Mapper_QuoteMapper::getInstance();

        $registry = Zend_Registry::getInstance();
        if (Zend_Registry::isRegistered('processingAutoQuoteId')) {
            $this->_quote = $mapper->find($registry->get('processingAutoQuoteId')
            );
            $this->_userMode = true;
        } else {

            $this->_quote = $mapper->find(
                Zend_Controller_Action_HelperBroker::getStaticHelper('page')->clean($requestedUri)
            );
        }

        if (($this->_quote instanceof Quote_Models_Model_Quote) && Quote_Tools_Tools::checkExpired($this->_quote)) {
            $cartId = $this->_quote->getCartId();
            $cartSessionMapper = Models_Mapper_CartSessionMapper::getInstance();
            $cartSessionModel = $cartSessionMapper->find($cartId);

            if ($this->_quote->getStatus() !== Quote_Models_Model_Quote::STATUS_SOLD && $this->_quote->getStatus() !== Quote_Models_Model_Quote::STATUS_LOST) {
                if ($cartSessionModel instanceof Models_Model_CartSession) {
                    if (!in_array($cartSessionModel->getStatus(), $this->_statusesNotLostQuotes)) {
                        $this->_quote->setStatus(Quote_Models_Model_Quote::STATUS_LOST);
                        $this->_quote = $mapper->save($this->_quote);
                    }
                }
            }
        }
    }

    /**
     * Initialize cart attached to the current quote
     *
     * @throws Exceptions_SeotoasterWidgetException
     */
    protected function _initCart() {
        if(!$this->_quote instanceof Quote_Models_Model_Quote) {
            //throw new Exceptions_SeotoasterWidgetException('Quote widget error: Quote not found. Can\'t initialize the cart.');
            $this->_cart = null;
            return false;
        }
        $this->_cart = Models_Mapper_CartSessionMapper::getInstance()->find($this->_quote->getCartId());
    }

    /**
     * @param $addressType
     * @param array $address
     * @param array $requiredFields
     * @return Forms_Address_Abstract|Quote_Forms_Quote|Quote_Forms_Shipping|Zend_Form|null
     * @throws Exceptions_SeotoasterWidgetException
     * @throws Zend_Form_Exception
     */
    protected function _initAddressForm($addressType, $address = array(), $requiredFields = array()) {
        if(!$addressType) {
            throw new Exceptions_SeotoasterWidgetException('Quote widget error: Invalid address passed to the form init.');
        }
        $addressForm = null;
        switch($addressType) {
            case self::ADDRESS_TYPE_BILLING:
                $addressForm = new Quote_Forms_Quote();
                //remove elements that are not neccessary here (submit button, disclaimer text area)
                $addressForm->removeElement('captcha');
                $addressForm->removeElement('sendQuote');
                $addressForm->removeElement('disclaimer');
            break;
            case self::ADDRESS_TYPE_SHIPPING:
                $addressForm = new Quote_Forms_Shipping();

                $addressForm->getElement('lastname')->setRequired(false)->setAttrib('class','');
                $addressForm->getElement('address1')->setRequired(false)->setAttrib('class','');
                $addressForm->getElement('country')->setRequired(false)->setAttrib('class','');
                $addressForm->getElement('city')->setRequired(false)->setAttrib('class','');
                $addressForm->getElement('zip')->setRequired(false)->setAttrib('class','');

                $addressForm->getElement('phonecountrycode')->setLabel('Phone');
                $addressForm->getElement('phone')->setLabel(null);
                //remove elements that are not neccessary here (submit button, mobile phone field, instructions text area)
                $addressForm->removeElement('calculateAndCheckout');
                $addressForm->removeElement('shippingInstructions');
                $addressForm->removeDisplayGroup('bottom');
            break;
            default:
                throw new Exceptions_SeotoasterWidgetException('Quote widget error: Unrecognized address type');
            break;
        }

        // little modification of address's state output
        if(isset($address['state'])) {
            $state = Tools_Geo::getStateByCode($address['state']);
            if(!is_null($state) && !empty($state)) {
                $address['state'] = $state['id'];
            }
        }

        if(!empty($addressForm) && !empty($requiredFields)) {
            if(!empty($requiredFields)) {
                foreach ($addressForm->getElements() as $element) {
                    if (in_array($element->getName(), $requiredFields)) {
                        if($element->getName() == 'mobile') {
                            $addressForm->getElement('mobilecountrycode')->setRequired(true)->setAttrib('class','required');
                        } elseif ($element->getName() == 'phone') {
                            $addressForm->getElement('phonecountrycode')->setRequired(true)->setAttrib('class','required');
                        }

                        $element->setRequired(true)->setAttrib('class', 'required');
                    }
                }
            }
        }

        //populating the form
        $addressForm->getElement('firstname')->setLabel('First Name');
        $addressForm->getElement('email')->setLabel('E-mail');

        $addressForm = $this->_fixFormCountry($addressForm);

        if (Tools_Security_Acl::isAllowed(Shopping::RESOURCE_STORE_MANAGEMENT)) {
            $addressForm = $this->_addOverwriteUserCheckbox($addressForm, $addressType);
        }

        if (empty($address['state']) && empty($address['country'])) {
            $state = $this->_shoppingConfig['state'];
            $stateCodes = Tools_Geo::getState($this->_shoppingConfig['country'], true);
            if (array_key_exists($state, $stateCodes)) {
                $address['state'] = $state;
                $this->_view->preventRemovingOptions = true;
            }
        }


        $addressForm->getElement('country')->setValue($this->_shoppingConfig['country']);
        $addressForm->setAttrib('action', '#')->populate(($address) ? $address : array());

        $listMasksMapper = Application_Model_Mappers_MasksListMapper::getInstance();
        $this->_view->mobileMasks = $listMasksMapper->getListOfMasksByType(Application_Model_Models_MaskList::MASK_TYPE_MOBILE);
        $this->_view->desktopMasks = $listMasksMapper->getListOfMasksByType(Application_Model_Models_MaskList::MASK_TYPE_DESKTOP);
        return $addressForm;
    }

    /**
     * Add checkbox to the form for overwriting user
     *
     * @param Zend_Form $addressForm form address object
     * @param string $labelSuffix suffix for element
     * @return Zend_Form
     */
    protected function _addOverwriteUserCheckbox(Zend_Form $addressForm, $labelSuffix)
    {
        $translator = Zend_Registry::get('Zend_Translate');
        $addressForm->addElement(new Zend_Form_Element_Checkbox(array(
            'name'  => 'overwriteQuoteUser'.ucfirst($labelSuffix),
            'id'    => 'overwrite-quote-user-'.($labelSuffix),
            'label' => $translator->translate('Assign quote to customer using the email address provided in '.$labelSuffix.' info'),
        )));

        return $addressForm;
    }

    /**
     * Serve widget proccessing
     *
     * @return string
     * @throws Exceptions_SeotoasterWidgetException
     */
    protected function _load() {
        if(empty($this->_options)) {
            if($this->_debugMode) {
                error_log('Quote widget error: Not enough parameters passed to the widget');
            }
            throw new Exceptions_SeotoasterWidgetException('Quote widget error: Not enough parameters passed to the widget');
        }

        // primary option (first option in options list) that will be used for dispatching
        $primaryOption = strtolower(array_shift($this->_options));

        // default widget renderer name
        $renderer      = '_render' . ucfirst($primaryOption);

        //dispatching the render of the option
        try {
            return (!method_exists($this, $renderer)) ? $this->_renderQuoteOption($primaryOption) : $this->$renderer();
        } catch (Exceptions_SeotoasterWidgetException $swe) {
            if($this->_debugMode) {
                error_log($swe->getMessage());
            }
            return (Tools_Security_Acl::isAllowed(Shopping::RESOURCE_STORE_MANAGEMENT) ? $swe->getMessage() : '');
        }
    }

    /**
     * Render a simple quote model option that could be reached with a simple getter
     *
     * @param string $option
     * @return string
     * @throws Exceptions_SeotoasterWidgetException
     */
    protected function _renderQuoteOption($option) {
        // if quote is not initialized rise apropriate exception
        if(!$this->_quote instanceof Quote_Models_Model_Quote) {
            throw new Exceptions_SeotoasterWidgetException('Quote widget error: Quote not found. Rendering of the option ' . $option . ' will be skipped.');
        }

        $getter = 'get' . ucfirst($option);
        if(!method_exists($this->_quote, $getter)) {
            throw new Exceptions_SeotoasterWidgetException('Quote widget error: getter ' . $getter . ' doesn\'t exist.');
        }

        // getting an option value using getter
        $optionValue     = $this->_quote->$getter();

        // option will be rendered through the view scrip if the script exists
        $viewsScriptName = $option . '.quote.phtml';
        if($this->_view->getScriptPath($viewsScriptName)) {
            $this->_view->$option = $optionValue;
            return $this->_view->render($viewsScriptName);
        }
        return $optionValue;
    }

    /**
     * Renderer for {$quote:grid} option
     *
     * @return mixed
     */
    protected function _renderGrid() {
        $this->_view->quotes = Quote_Models_Mapper_QuoteMapper::getInstance()->fetchAll(null, array('created_at ' . self::QUOTEGRID_DEFAULTS_ORDER), self::QUOTEGRID_DEFAULTS_PERPAGE, 0, null, true);
        return $this->_view->render('grid.quote.phtml');
    }

    /**
     * Renderer for {$quote:delivery} option
     *
     * @return string
     */
    protected function _renderDelivery() {
        if (!$this->_quote instanceof Quote_Models_Model_Quote) {
            throw new Exceptions_SeotoasterWidgetException('Quote widget error: Quote not found.');
        }
        $this->_view->delivery = $this->_quote->getDeliveryType();
        return $this->_view->render('delivery.quote.phtml');
    }

    /**
     * Render created or expires quote dates
     *
     * {$quote:date[:_created_|:expires]}
     * @return mixed
     * @throws Exceptions_SeotoasterWidgetException
     */
    protected function _renderDate() {
        if (!$this->_quote instanceof Quote_Models_Model_Quote) {
            throw new Exceptions_SeotoasterWidgetException('Quote widget error: Quote not found.');
        }
        $dateType          = isset($this->_options[0]) ? $this->_options[0] : self::DATE_TYPE_CREATED;
        $this->_view->date = ($dateType == self::DATE_TYPE_CREATED) ? $this->_quote->getCreatedAt() : $this->_quote->getExpiresAt();
        $this->_view->type = $dateType;
        return $this->_view->render('date.quote.phtml');
    }

    /**
     * Render creator name
     *
     * {$quote:creator}
     * @return mixed
     * @throws Exceptions_SeotoasterWidgetException
     */
    protected function _renderCreator() {
        if (!$this->_quote instanceof Quote_Models_Model_Quote) {
            throw new Exceptions_SeotoasterWidgetException('Quote widget error: Quote not found.');
        }

        $creatorId = $this->_quote->getCreatorId();
        $userModel = Application_Model_Mappers_UserMapper::getInstance()->find($creatorId);
        if ($userModel instanceof Application_Model_Models_User) {
            return $userModel->getFullName();
        }

        return '';
    }

    /**
     * Render editor name
     *
     * {$quote:editor}
     * @return mixed
     * @throws Exceptions_SeotoasterWidgetException
     */
    protected function _renderEditor() {
        if (!$this->_quote instanceof Quote_Models_Model_Quote) {
            throw new Exceptions_SeotoasterWidgetException('Quote widget error: Quote not found.');
        }

        $editorId = $this->_quote->getEditorId();
        $userModel = Application_Model_Mappers_UserMapper::getInstance()->find($editorId);
        if ($userModel instanceof Application_Model_Models_User) {
            return $userModel->getFullName();
        }

        return '';
    }

    /**
     * Renderer for the {$quote:controls}
     *
     * @return string
     * @throws Exceptions_SeotoasterWidgetException
     */
    protected function _renderControls() {
        // if controls are not available for the current user role - rise exception
        if(!$this->_editAllowed) {
            //throw new Exceptions_SeotoasterWidgetException('Quote widget error: Controlls are available for administrator only.');
            return '';
        }

        // if quote is not initialized rise apropriate exception
        if(!$this->_quote instanceof Quote_Models_Model_Quote) {
            throw new Exceptions_SeotoasterWidgetException('Quote widget error: Quote not found. Renderer ' . __METHOD__ . ' has no effect');
        }

        $this->_view->quoteId = $this->_quote->getId();
        $this->_view->status  = Quote_Models_Model_Quote::STATUS_SENT; //$this->_quote->getStatus();

        $this->_view->symbol  = $this->_currency->getSymbol();

        $useDraggable = false;
        $quoteDraggableProducts = $this->_shoppingConfig['quoteDraggableProducts'];

        if(!empty($quoteDraggableProducts)) {
            $useDraggable = true;
        }

        $this->_view->quoteDraggableProducts  = $useDraggable;

        $this->_view->usNumericFormat = $this->_shoppingConfig['usNumericFormat'];

        return $this->_view->render('controls.quote.phtml');
    }

    /**
     * Renderer for the {$quote:}
     *
     * @return string
     * @throws Exceptions_SeotoasterWidgetException
     */
    protected function _renderSearch() {
        if($this->_editAllowed) {
            return $this->_view->render('search.quote.phtml');
        }
    }


    /**
     * Renderer for the {$quote:address[:_billing_[:shipping]]}
     *
     * @return string
     */
    protected function _renderAddress() {
        $addressType = isset($this->_options[0]) ? $this->_options[0] : self::ADDRESS_TYPE_BILLING;
        $address = null;
        if ($this->_cart instanceof Models_Model_CartSession) {
            switch ($addressType) {
                case self::ADDRESS_TYPE_BILLING:
                    $address = Tools_ShoppingCart::getAddressById($this->_cart->getBillingAddressId());
                    break;
                case self::ADDRESS_TYPE_SHIPPING:
                    $address = Tools_ShoppingCart::getAddressById($this->_cart->getShippingAddressId());
                    break;
            }
        }
        $this->_view->addressType = $addressType;
        $this->_view->address     = $address;

        if($this->_editAllowed && ($this->_options[1] == 'default' || !array_key_exists($this->_options[1], $address))) {
            $requiredFields = array();
            foreach (preg_grep('/^required-.*$/', $this->_options) as $reqOpt) {
                $fields = explode(',', str_replace('required-', '', $reqOpt));
                $requiredFields = array_merge($requiredFields, $fields);
                unset($reqOpt);
            }

            $addressForm = $this->_initAddressForm($addressType, $address, $requiredFields);
            if (!empty($addressForm->getElement('overwriteQuoteUserBilling'))) {
                $addressForm->getElement('overwriteQuoteUserBilling')
                    ->setAttrib('checked', 'checked')
                    ->setAttrib('class', 'hidden')
                    ->setLabel('');
            }
            $this->_view->addressForm = $addressForm;
            return $this->_view->render('address.quote.phtml');
        } elseif (!$this->_editAllowed && isset($this->_options[1]) && is_array($address)) {
            if (array_key_exists($this->_options[1], $address)) {
                if ($this->_options[1] === 'phone') {
                    return $address['phone_country_code_value'].$address[$this->_options[1]];
                }
                if ($this->_options[1] === 'mobile') {
                    return $address['mobile_country_code_value'].$address[$this->_options[1]];
                }
                if ($this->_options[1] === 'state' && !empty($address['state']) && is_numeric($address['state'])) {
                    $stateData = Tools_Geo::getStateById($address['state']);
                    if (!empty($stateData['state'])) {
                        return $stateData['state'];
                    }
                }

                return $address[$this->_options[1]];
            } elseif ($this->_options[1] == 'default') {
                return $this->_view->render('address.quote.phtml');
            }
        }
    }

    /**
     * Render {$quote:total[:_grand_[:sub|:tax]]}
     *
     * @return string
     * @throws Exceptions_SeotoasterWidgetException
     */
    protected function _renderTotal() {
        if(!$this->_cart instanceof Models_Model_CartSession) {
            throw new Exceptions_SeotoasterWidgetException('Quote widget error: cart in not initialized, no total will be rendered');
        }

        $usNumericFormat = $this->_shoppingConfig['usNumericFormat'];

        if(!empty($usNumericFormat)) {
            $this->_view->currencySymbol = preg_replace('~[\w]~', '', $this->_currency->getSymbol());
            $this->_view->usNumericFormat = $usNumericFormat;
        }

        $totalType = isset($this->_options[0]) ? $this->_options[0] : 'grand';
        if (in_array('clean', $this->_options, true)) {
            $this->_view->clean = '1';
        }
        $total     = 0;
        switch($totalType) {
            case self::TOTAL_TYPE_TAX   : $total = $this->_cart->getTotalTax(); break;
            case self::TOTAL_TYPE_SUB   :
                $subTotal = $this->_cart->getSubTotal();
                $total    = ($this->_shoppingConfig['showPriceIncTax']) ? $subTotal +  $this->_cart->getSubTotalTax() : $subTotal;
                break;
            case self::TOTAL_TYPE_GRAND :
                $total = (($this->_cart->getTotal()));
                break;
            case self::TOTAL_TYPE_WOTAX :
                $total = (($this->_cart->getTotal() - $this->_cart->getTotalTax()));
                break;
            case self::TOTAL_TYPE_TAX_DISCOUNT:
                if (!empty($this->_cart->getDiscountTax())) {
                    $this->_view->taxDiscount = $this->_cart->getDiscountTax();
                } else {
                    $this->_view->taxDiscount = 0;
                }
                return $this->_view->render('taxdiscount.quote.phtml');
                break;
            default : throw new Exceptions_SeotoasterWidgetException('Quote widget error: Total type is invalid');
        }
        $this->_view->totalType = $totalType;
        $this->_view->total     = $total;
        return $this->_view->render('total.quote.phtml');
    }

    /**
     * Render shipping price {$quote:shipping}
     *
     * @return string
     * @throws Exceptions_SeotoasterWidgetException
     */
    protected function _renderShipping() {
        if(!$this->_cart instanceof Models_Model_CartSession) {
            throw new Exceptions_SeotoasterWidgetException('Quote widget error: cart in not initialized, no total will be rendered');
        }
        $shippingPrice              = $this->_cart->getShippingPrice();
        $this->_view->shoppingConfig = $this->_shoppingConfig;
        $this->_view->shippingTax   = $this->_cart->getShippingTax();
        $this->_view->quoteShipping = ($shippingPrice) ? $shippingPrice : 0;

        $usNumericFormat = $this->_shoppingConfig['usNumericFormat'];

        if (in_array('clean', $this->_options, true)) {
            $this->_view->clean = '1';
        }

        if(!empty($usNumericFormat)) {
            $this->_view->currencySymbol = preg_replace('~[\w]~', '', $this->_currency->getSymbol());
            $this->_view->usNumericFormat = $usNumericFormat;
        }

        return $this->_view->render('shipping.quote.phtml');
    }

    /**
     * Render quote discount {$quote:discount}
     *
     * @return string
     * @throws Exceptions_SeotoasterWidgetException
     */
    protected function _renderDiscount() {
        if(!$this->_cart instanceof Models_Model_CartSession) {
            throw new Exceptions_SeotoasterWidgetException('Quote widget error: cart in not initialized, discount cannot be rendered');
        }

        $discount                   = $this->_cart->getDiscount();
        $this->_view->discount      = ($discount) ? $discount : 0;
        $this->_view->rate          = $this->_cart->getDiscountTaxRate();
        $this->_view->discountTax   = $this->_cart->getDiscountTax();
        $this->_view->shoppingConfig = $this->_shoppingConfig;
        $this->_view->taxRates = array(
            '0' => 'Non taxable',
            '1' => 'Default',
            '2' => 'Alternative',
            '3' => 'Alternative 2'
        );

        $usNumericFormat = $this->_shoppingConfig['usNumericFormat'];

        if (in_array('clean', $this->_options, true)) {
            $this->_view->clean = '1';
        }

        if(!empty($usNumericFormat)) {
            $this->_view->currencySymbol = preg_replace('~[\w]~', '', $this->_currency->getSymbol());
            $this->_view->usNumericFormat = $usNumericFormat;
        }

        return $this->_view->render('discount.quote.phtml');
    }


    protected function _renderCreatorId() {
        if(!$this->_quote instanceof Quote_Models_Model_Quote) {
            throw new Exceptions_SeotoasterWidgetException('Quote widget error: Quote not found. Renderer ' . __METHOD__ . ' has no effect');
        }

        return $this->_quote->getCreatorId();
    }

    /**
     * Render all quote item widgets {$quote:item:*}
     *
     * @return bool|string
     * @throws Exceptions_SeotoasterWidgetException
     */
    protected function _renderItem() {
        // if this is regular parsing - do nothing
        if(!in_array('quotemspace', $this->_options)) {
            return '';
        }

        if (!isset($this->_options[0])) {
            throw new Exceptions_SeotoasterWidgetException('Quote widget error: Not enough parameters passed.');
        } elseif (!$this->_quote instanceof Quote_Models_Model_Quote) {
            throw new Exceptions_SeotoasterWidgetException('Quote widget error: Quote not found.');
        }

        // unset a special option for the options list
        unset($this->_options[array_search('quotemspace', $this->_options, true)]);

        // getting the cart content to be able to get an item we need
        $cartContent = $this->_cart->getCartContent();

        $quoteDraggableProducts = $this->_shoppingConfig['quoteDraggableProducts'];

        if(!empty($quoteDraggableProducts)) {
            $quoteId = $this->_quote->getId();

            $quoteDraggableMapper = Quote_Models_Mapper_QuoteDraggableMapper::getInstance();
            $quoteDraggableModel = $quoteDraggableMapper->findByQuoteId($quoteId);

            if($quoteDraggableModel instanceof Quote_Models_Model_QuoteDraggableModel) {
                $dragOrder = $quoteDraggableModel->getData();

                if(!empty($dragOrder)) {
                    $dragOrder = explode(',', $dragOrder);

                    $prepareContentSids = array();
                    foreach ($cartContent as $key => $content) {
                        $product = Models_Mapper_ProductMapper::getInstance()->find($content['product_id']);
                        $options = ($content['options']) ? $content['options'] : Quote_Tools_Tools::getProductDefaultOptions($product);
                        $prodSid = Quote_Tools_Tools::generateStorageKey($product, $options);
                        $prepareContentSids[$prodSid] = $content;
                    }

                    $sortedCartContent = array();
                    foreach ($dragOrder as $productSid) {
                        if(!empty($prepareContentSids[$productSid])) {
                            $sortedCartContent[$productSid] = $prepareContentSids[$productSid];
                        }
                    }
                    $preparedCartContent = array_merge($sortedCartContent, $prepareContentSids);

                    $cartContent = array();

                    foreach ($preparedCartContent as $cContent) {
                        $cartContent[] = $cContent;
                    }
                }
            }
        }

        $itemId      = (end($this->_options));

        // if no such item in the cart - exception
        if(!isset($cartContent[$itemId])) {
            throw new Exceptions_SeotoasterWidgetException('Quote widget error: Can\'t find a product with id ' . $itemId . ' in the cart ' . $this->_cart->getId());
        }

        // Models_Model_Product equivalent of the $item
        $product = Models_Mapper_ProductMapper::getInstance()->find($cartContent[$itemId]['product_id']);
        $product->setPrice(($this->_shoppingConfig['showPriceIncTax']) ? $cartContent[$itemId]['tax_price'] : $cartContent[$itemId]['price']);
        // representation of product item that we store in the cart session
        $item    = array_merge($cartContent[$itemId], $product->toArray());

        $options = ($item['options']) ? $item['options'] : Quote_Tools_Tools::getProductDefaultOptions($product);
        $item['sid'] = Quote_Tools_Tools::generateStorageKey($product, $options);


        $notRender = false;

        $usNumericFormat = $this->_shoppingConfig['usNumericFormat'];

        if(!empty($usNumericFormat)) {
            $this->_view->currencySymbol = preg_replace('~[\w]~', '', $this->_currency->getSymbol());
            $this->_view->usNumericFormat = $usNumericFormat;
        }

        $widgetOption = $this->_options[0];
        if (empty((int)$product->getPrice()) && empty($product->getEnabled()) && $widgetOption !== 'sid') {
            $notRender = true;
        }
        switch($widgetOption) {
            case 'currency':
                if ($notRender === true) {
                   return '';
                }
                return $this->_options[1];
            break;
            case 'photo':
                $img = $product->getPhoto();

                $value               = $item['photo'];
                $this->_view->name   = $item['name'];

                $photoUrl = Tools_Misc::prepareProductImage($value, 'product');

                $this->_view->imageUrl = $photoUrl;

            break;
            case 'price':
                $price                  = ($this->_shoppingConfig['showPriceIncTax']) ? $cartContent[$itemId]['tax_price'] : $cartContent[$itemId]['price']; //Tools_ShoppingCart::getInstance()->calculateProductPrice($product, (isset($item['options']) && $item['options']) ? $item['options'] : Quote_Tools_Tools::getProductDefaultOptions($product));

                if($cartContent[$itemId]['freebies'] === '1'){
                    $this->_view->freebies = true;
                }
                $value                  = (isset($this->_options[1]) && $this->_options[1] === 'unit') ? $price : ($price * $item['qty']);
                $this->_view->unitPrice = (isset($this->_options[1]) && $this->_options[1] === 'unit');
            break;
            case 'options':
                $defaultOptions = $product->getDefaultOptions();
                if(!$defaultOptions || empty($defaultOptions)) {
                    return false;
                }
                $value                   = $options;
                $this->_view->weightSign = $this->_shoppingConfig['weightUnit'];
            break;
            case 'qty':
                $value = $item['qty'];
            break;
            case 'remove':
                return (Tools_Security_Acl::isAllowed(Tools_Security_Acl::RESOURCE_USERS) || Tools_Security_Acl::isAllowed(Shopping::RESOURCE_STORE_MANAGEMENT)) ? '<a data-pid="' . $item['product_id'] . '" data-sid="'. $item['sid'] .'" class="remove-product" href="javascript:;"><img src="' . $this->_websiteHelper->getUrl() . 'system/images/delete.png" alt="delete"/></a>' : '';
            break;
            default:
                if ($notRender === true) {
                    return '';
                }
                return (isset($item[$widgetOption])) ? $item[$widgetOption] : '';
            break;
        }

        if ($notRender === true) {
            return '';
        }

        $this->_view->$widgetOption = $value;
        $this->_view->productId     = $item['product_id'];
        $this->_view->quoteId       = $this->_quote->getId();
        $this->_view->sid           = $item['sid'];

        if (in_array('clean', $this->_options, true)) {
            $this->_view->clean = '1';
        }

        return $this->_view->render('item/' . $widgetOption . '.quote.item.phtml');
    }

    /**
     * Renderer for a quote form {$quote:form}
     *
     * @return Quote_Forms_Quote
     */
    protected function _renderForm() {
        //init quote form and remove elements we don't need
        $quoteForm   = new Quote_Forms_Quote();
        $quoteForm->removeElement('sameForShipping');
        $quoteForm->removeElement('position');

        //check if the automatic quote generation is set up - add extra class to the form
        if(isset($this->_shoppingConfig['autoQuote']) && $this->_shoppingConfig['autoQuote']) {
            $quoteForm->setAttrib('class', '_reload ' . $quoteForm->getAttrib('class'));
        }

        //init cart session storage
        $cartStorage = Tools_ShoppingCart::getInstance();

        //trying to get billng address first, to pre-populate quote form
        $addrKey = $cartStorage->getBillingAddressKey();
        if($addrKey === null) {
            //otherwise trying to get shipping address (if shipping is not pick up)
            $addrKey =  $cartStorage->getShippingAddressKey();
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

        //set store country as default country for the form
        $quoteForm = $this->_fixFormCountry($quoteForm);

        // adjust dynamic quote from fields
        $quoteForm = Quote_Tools_Tools::adjustFormFields($quoteForm, $this->_options, $this->_formMandatoryFields);
        if ($product instanceof Models_Model_Product) {
            $quoteForm->addElement('text', md5($product->getId()), array('style' => 'display:none;', 'aria-label' => 'product id'));
            $quoteForm->getElement(md5($product->getId()))->removeDecorator('HtmlTag');
        } elseif ($cartStorage !== null) {
            $quoteForm->addElement('text', md5($cartStorage->getCartId()), array('style' => 'display:none;', 'aria-label' => 'cart id'));
            $quoteForm->getElement(md5($cartStorage->getCartId()))->removeDecorator('HtmlTag');
        }

        Zend_Controller_Action_HelperBroker::getStaticHelper('session')->formOptions = $this->_options;


        $elements = $quoteForm->getElements();
        if (!empty($elements['captcha'])) {
            $captchaEl = $elements['captcha'];
        }

        if (!empty($elements['sendQuote'])) {
            $sendQuoteEl = $elements['sendQuote'];
        }

        if (!empty($elements['captcha']) && !empty($elements['sendQuote']) && !empty($captchaEl) && !empty($sendQuoteEl)) {
            unset($elements['captcha']);
            unset($elements['sendQuote']);
            $elements['captcha'] = $captchaEl;
            $elements['sendQuote'] = $sendQuoteEl;
            $quoteForm->setElements($elements);
        }

        if (!empty($elements['sendQuote']) && !empty($sendQuoteEl) && empty($elements['captcha'])) {
            unset($elements['sendQuote']);
            $elements['sendQuote'] = $sendQuoteEl;
            $quoteForm->setElements($elements);
        }

        $mobileEl = $quoteForm->getElement('mobile');
        $mobileCountryCodeEl = $quoteForm->getElement('mobilecountrycode');
        $desktopPhoneEl = $quoteForm->getElement('phone');
        $desktopCountryCodeEl = $quoteForm->getElement('phonecountrycode');

        $displayGroups = array();
        $originalQuoteForm = new Quote_Forms_Quote();
        if (!empty($mobileEl) && !empty($mobileCountryCodeEl)) {
            $required = false;
            if(in_array('mobile*', $this->_options)){
                $required = true;
            }
            $quoteForm->getElement('mobile')->setLabel(null);
            $quoteForm->getElement('mobilecountrycode')->setLabel('Mobile');
            $position = array_search('mobilecountrycode', array_keys($quoteForm->getElements()));
            $mobilesBlockGroup = $originalQuoteForm->getDisplayGroups()['mobilesBlock'];
            $mobilesBlockGroup->setOrder($position);
            $mobilesBlockGroup->getElement('mobile')->setAttribs(array('class' => ($required) ? 'quote-required required' : '', 'aria-label' => 'Mobile'))->setValue($mobileEl->getValue());
            $mobilesBlockGroup->getElement('mobilecountrycode')->setRequired($required)->setValue($mobileCountryCodeEl->getValue());
            $displayGroups[]  = $mobilesBlockGroup;
        }

        if (!empty($desktopPhoneEl) && !empty($desktopCountryCodeEl)) {
            $required = false;
            if(in_array('phone*', $this->_options)){
                $required = true;
            }
            $quoteForm->getElement('phone')->setLabel(null);
            $quoteForm->getElement('phonecountrycode')->setLabel('Phone');
            $position = array_search('phonecountrycode', array_keys($quoteForm->getElements()));
            $phonesBlockGroup = $originalQuoteForm->getDisplayGroups()['phonesBlock'];
            $phonesBlockGroup->setOrder($position);
            $phonesBlockGroup->getElement('phone')->setAttribs(array('class' => ($required) ? 'quote-required required' : '', 'aria-label' => 'Phone'))->setValue($desktopPhoneEl->getValue());
            $phonesBlockGroup->getElement('phonecountrycode')->setRequired($required)->setValue($desktopCountryCodeEl->getValue());
            $displayGroups[]  = $phonesBlockGroup;
        }

        if (!empty($displayGroups)) {
            $quoteForm->addDisplayGroups($displayGroups);
        }

        $quoteForm->removeDisplayGroup('sameForShippingGroup');
        $this->_view->form = $quoteForm->setAction($this->_websiteHelper->getUrl() . 'api/quote/quotes/type/' . Quote::QUOTE_TYPE_GENERATE);
        $listMasksMapper = Application_Model_Mappers_MasksListMapper::getInstance();
        $this->_view->mobileMasks = $listMasksMapper->getListOfMasksByType(Application_Model_Models_MaskList::MASK_TYPE_MOBILE);
        $this->_view->desktopMasks = $listMasksMapper->getListOfMasksByType(Application_Model_Models_MaskList::MASK_TYPE_DESKTOP);
        return $this->_view->render('form.quote.phtml');
    }

    /**
     * Set default country and states if needed to the address form
     *
     * @param $form
     * @return Forms_Address_Abstract
     */
    private function _fixFormCountry($form) {
        $form->getElement('country')->setValue($this->_shoppingConfig['country']);
        $states = Tools_Geo::getState($this->_shoppingConfig['country']);
        if(is_array($states) && !empty($states)) {
            $statePairs = array();
            foreach($states as $state) {
                $statePairs[$state['state']] = $state['name'];
            }
            $currentState = Tools_Geo::getStateById($this->_shoppingConfig['state']);
            $form->getElement('state')->setMultiOptions($statePairs)
                ->setValue($currentState['state']);
        } else {
            $form->getElement('state')->setMultiOptions(array());
        }
        return $form;
    }

    /**
     * Provides widget's shortcuts to the toaster
     *
     * @return array
     */
    public static function getAllowedOptions() {
        $translator = Zend_Registry::get('Zend_Translate');
        return array(
            array(
                'group'  => $translator->translate('Shopping Shortcuts'),
                'alias'  => $translator->translate('Store Quote Form - Generates instantly a quote request form'),
                'option' => 'quote:form'
            )
        );
    }

    /**
     * Renderer for a quote fild {$quote:internalnote}
     *
     * @return string
     */
    protected function _renderInternalnote()
    {
        $currentRole = Zend_Controller_Action_HelperBroker::getStaticHelper('Session')->getCurrentUser()->getRoleId();
        $accessList  = array(
            Tools_Security_Acl::ROLE_SUPERADMIN,
            Tools_Security_Acl::ROLE_ADMIN,
            Shopping::ROLE_SALESPERSON
        );
        if (in_array($currentRole, $accessList)) {
            $this->_view->content = $this->_quote->getInternalNote();
            $this->_view->id      = $this->_quote->getId();

            return $this->_view->render('internalnote.quote.phtml');
        }

        return '';
    }

    protected function _renderSignature()
    {
        if ($this->_cart instanceof Models_Model_CartSession && $this->_quote instanceof Quote_Models_Model_Quote) {
            $this->_view->accessAllowed = $this->_editAllowed;

            $quoteId = $this->_quote->getId();
            $isQuoteSigned = $this->_quote->getIsQuoteSigned();
            $signature = $this->_quote->getSignature();

            $this->_view->isQuoteSigned = $isQuoteSigned;
            $this->_view->signature = $signature;
            $this->_view->quoteId = $quoteId;

            $isSignatureRequired = $this->_quote->getIsSignatureRequired();

            if ($this->_userMode === true) {
                if (empty($isSignatureRequired)) {
                    return '';
                }

                if (empty($signature)) {
                    return '';
                }

                if (in_array('src', $this->_options)) {
                    return $signature;
                }
                
                return $this->_view->render('signature-signed.phtml');
            }

            $this->_view->signatureClass = '';
            if (empty($isSignatureRequired)) {
                $this->_view->signatureClass = 'hidden';
            }

            return $this->_view->render('signature.phtml');
        }
    }

    protected function _renderQuotetypeinfo()
    {
        if ($this->_cart instanceof Models_Model_CartSession && $this->_quote instanceof Quote_Models_Model_Quote) {
            $currentCartStatus = $this->_cart->getStatus();
            if ($currentCartStatus === Models_Model_CartSession::CART_STATUS_COMPLETED || $currentCartStatus === Models_Model_CartSession::CART_STATUS_SHIPPED || $currentCartStatus === Models_Model_CartSession::CART_STATUS_DELIVERED || $currentCartStatus === Models_Model_CartSession::CART_STATUS_REFUNDED) {
                return '';
            }

            $cartStatuses = array(
                Models_Model_CartSession::CART_STATUS_COMPLETED,
                Models_Model_CartSession::CART_STATUS_REFUNDED,
                Models_Model_CartSession::CART_STATUS_DELIVERED,
                Models_Model_CartSession::CART_STATUS_SHIPPED,
                Models_Model_CartSession::CART_STATUS_CANCELED
            );

            $paymentType = $this->_quote->getPaymentType();
            if (empty($paymentType)) {
                $paymentType = Quote_Models_Model_Quote::PAYMENT_TYPE_FULL;
            }

            $leftAmountToPaid = 0;
            if ($paymentType === Quote_Models_Model_Quote::PAYMENT_TYPE_PARTIAL_PAYMENT) {
                $partialPercentage = $this->_cart->getPartialPercentage();
                $this->_view->partialToPayAmount = $this->_currency->toCurrency(($partialPercentage * $this->_cart->getTotal()/100));
                $this->_view->partialPercentage = $partialPercentage;
                $this->_view->partialAmountPaid  = $this->_cart->getPartialPaidAmount();
                $isPartialPaid = false;
                if ((int) $this->_cart->getPartialPaidAmount() > 0 && !in_array($this->_cart->getStatus(), $cartStatuses) && $this->_cart->getPartialPaidAmount() < $this->_cart->getTotal()) {
                    $isPartialPaid = true;
                    $leftAmountToPaid = round(($this->_cart->getTotal() -($this->_cart->getTotal()*$this->_cart->getPartialPercentage())/100), 2);
                }
            }

            $isAdmin = false;
            if (Tools_Security_Acl::isAllowed(Tools_Security_Acl::RESOURCE_PLUGINS)) {
                $isAdmin = true;
            }

            $this->_view->partialPaidDate = date('Y-m-d', strtotime($this->_cart->getPartialPurchasedOn()));
            $isSignatureRequired = $this->_quote->getIsSignatureRequired();
            $this->_view->paymentType = $paymentType;
            $this->_view->quoteTotal = $this->_cart->getTotal();
            $this->_view->isSignatureRequired = $isSignatureRequired;
            $this->_view->isAdmin = $isAdmin;
            $this->_view->isPartialPaid = $isPartialPaid;
            $this->_view->leftAmountToPaid = $leftAmountToPaid;

            return $this->_view->render('quote-type-info.phtml');
        }

    }


    protected function _renderPaymenttypeconfig()
    {
        if ($this->_cart instanceof Models_Model_CartSession && $this->_quote instanceof Quote_Models_Model_Quote) {
            $this->_view->accessAllowed = $this->_editAllowed;
            $templateMapper = Application_Model_Mappers_TemplateMapper::getInstance();
            $pdfTemplates = $templateMapper->findByType('typepdfquote');
            $paymentType = $this->_quote->getPaymentType();
            if (empty($paymentType)) {
                $paymentType = Quote_Models_Model_Quote::PAYMENT_TYPE_FULL;
            }

            $pdfTemplate = $this->_quote->getPdfTemplate();
            if (empty($pdfTemplate)) {
                $pdfTemplate = '';
            }

            $isSignatureRequired = $this->_quote->getIsSignatureRequired();
            $quoteStatus = $this->_quote->getStatus();

            $partialPaymentAllowed = false;
            if (!empty($this->_shoppingConfig['enabledPartialPayment'])) {
                $partialPaymentAllowed = true;
            }

            $this->_view->quoteId = $this->_quote->getId();
            $this->_view->quoteStatus = $quoteStatus;
            $this->_view->paymentType = $paymentType;
            $this->_view->isSignatureRequired = $isSignatureRequired;
            $this->_view->pdfTemplate = $pdfTemplate;
            $this->_view->pdfTemplates = $pdfTemplates;
            $this->_view->partialPaymentAllowed = $partialPaymentAllowed;
            return $this->_view->render('payment-type-config.phtml');
        }
    }

    protected function _renderQuotesigneddate()
    {
        $translator = Zend_Registry::get('Zend_Translate');
        if ($this->_cart instanceof Models_Model_CartSession && $this->_quote instanceof Quote_Models_Model_Quote) {
            if (!empty($this->_quote->getIsQuoteSigned())) {
                return date('d-M-Y', strtotime($this->_quote->getQuoteSignedAt()));
            }

            if ($this->_editAllowed === true) {
                return $translator->translate('Not yet accepted');
            }

        }
    }

    protected function _renderDownloadpdf()
    {

        if ($this->_cart instanceof Models_Model_CartSession && $this->_quote instanceof Quote_Models_Model_Quote && !$this->_editAllowed) {
            if (!Tools_Security_Acl::isAllowed(Shopping::RESOURCE_STORE_MANAGEMENT)) {
                if (empty($this->_quote->getIsQuoteSigned())) {
                    $this->_view->quoteId = $this->_quote->getId();

                    return $this->_view->render('download-preview-button.phtml');
                }

                $translator = Zend_Registry::get('Zend_Translate');

                $userId = $this->_quote->getUserId();
                $userModel = Application_Model_Mappers_UserMapper::getInstance()->find($userId);
                $userEmail = '';
                if ($userModel instanceof Application_Model_Models_User) {
                    $userEmail = $userModel->getEmail();
                }

                return '<p>'.$translator->translate('Please find a copy of this signed proposal in your inbox at').' '.$userEmail.'</p>';
            }
        }

        return '';

    }

    protected function _renderQuoteowner()
    {
        if ($this->_quote instanceof Quote_Models_Model_Quote) {
            $creatorId = $this->_quote->getCreatorId();
            if (!empty($creatorId)) {
                $registry = Zend_Registry::getInstance();
                if (Zend_Registry::isRegistered('quoteCreatorModel')) {
                    $quoteCreatorModel = $registry->get('quoteCreatorModel');
                } else {
                    $quoteCreatorModel = Application_Model_Mappers_UserMapper::getInstance()->find($creatorId);
                    $registry->set('quoteCreatorModel', $quoteCreatorModel);
                }

                if ($quoteCreatorModel instanceof Application_Model_Models_User) {
                    if ($this->_options[0] === 'fullname') {
                        return $quoteCreatorModel->getFullName();
                    }
                    if ($this->_options[0] === 'mobile') {
                        return $quoteCreatorModel->getMobileCountryCodeValue().$quoteCreatorModel->getMobilePhone();
                    }
                    if ($this->_options[0] === 'desktop') {
                        return $quoteCreatorModel->getDesktopCountryCodeValue().$quoteCreatorModel->getDesktopPhone();
                    }
                    if ($this->_options[0] === 'email') {
                        return $quoteCreatorModel->getEmail();
                    }
                    if ($this->_options[0] === 'signature') {
                        return $quoteCreatorModel->getSignature();
                    }
                    if ($this->_options[0] === 'voip') {
                        return $quoteCreatorModel->getVoipPhone();
                    }

                }

            }
        }
        return '';
    }

}
