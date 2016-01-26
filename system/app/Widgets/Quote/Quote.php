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
     * Fields names that should be always present on the quote form
     *
     * @var array
     */
    protected $_formMandatoryFields = array(
        'productId'      => false,
        'productOptions' => false,
        'sendQuote'      => false
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
        $this->_quote = $mapper->find(
            Zend_Controller_Action_HelperBroker::getStaticHelper('page')->clean($requestedUri)
        );

        if (($this->_quote instanceof Quote_Models_Model_Quote) && Quote_Tools_Tools::checkExpired($this->_quote)) {
            $this->_quote->setStatus(Quote_Models_Model_Quote::STATUS_LOST);
            $this->_quote = $mapper->save($this->_quote);
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

    protected function _initAddressForm($addressType, $address = array()) {
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
                $addressForm = new Forms_Checkout_Shipping();
                //remove elements that are not neccessary here (submit button, mobile phone field, instructions text area)
                $addressForm->removeElement('calculateAndCheckout');
                $addressForm->removeElement('mobile');
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

        //populating the form
        $addressForm->getElement('firstname')->setLabel('First Name');
        $addressForm->getElement('email')->setLabel('E-mail');

        $addressForm = $this->_fixFormCountry($addressForm);

        $addressForm->getElement('country')->setValue($this->_shoppingConfig['country']);
        $addressForm->setAttrib('action', '#')->populate(($address) ? $address : array());
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
        if ($this->_editAllowed && (!isset($this->_options[1]) || (isset($this->_options[1]) && $this->_options[1] == 'default'))) {
            $this->_view->addressForm = $this->_initAddressForm($addressType, $address);
            return $this->_view->render('address.quote.phtml');
        } elseif (!$this->_editAllowed && isset($this->_options[1]) && is_array($address)) {
            if (array_key_exists($this->_options[1], $address)) {
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
        $totalType = isset($this->_options[0]) ? $this->_options[0] : 'grand';
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
                $this->_view->taxDiscount = ($this->_shoppingConfig['showPriceIncTax']) ? $this->_cart->getDiscount() + $this->_cart->getDiscountTax():$this->_cart->getDiscount();
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

        $widgetOption = $this->_options[0];
        switch($widgetOption) {
            case 'photo':
                $value               = $item['photo'];
                $this->_view->folder = (isset($this->_options[1]) && $this->_options[1] && is_dir($this->_websiteHelper->getMedia() . $this->_options[1])) ?  ('/' . $this->_options[1] . '/') : '/product/';
                $this->_view->name   = $item['name'];
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
                $value                   = Quote_Tools_Tools::getProductOptions($product, $item['options']);
                $this->_view->weightSign = $this->_shoppingConfig['weightUnit'];
            break;
            case 'qty':
                $value = $item['qty'];
            break;
            case 'remove':
                return (Tools_Security_Acl::isAllowed(Tools_Security_Acl::RESOURCE_USERS) || Tools_Security_Acl::isAllowed(Shopping::RESOURCE_STORE_MANAGEMENT)) ? '<a data-pid="' . $item['product_id'] . '" class="remove-product" href="javascript:;"><img src="' . $this->_websiteHelper->getUrl() . 'system/images/delete.png" alt="delete"/></a>' : '';
            break;
            default:
                return (isset($item[$widgetOption])) ? $item[$widgetOption] : '';
            break;
        }
        $this->_view->$widgetOption = $value;
        $this->_view->productId     = $item['product_id'];
        $this->_view->quoteId       = $this->_quote->getId();
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
            $quoteForm->addElement('text', md5($product->getId()), array('style' => 'display:none;'));
            $quoteForm->getElement(md5($product->getId()))->removeDecorator('HtmlTag');
        } elseif ($cartStorage !== null) {
            $quoteForm->addElement('text', md5($cartStorage->getCartId()), array('style' => 'display:none;'));
            $quoteForm->getElement(md5($cartStorage->getCartId()))->removeDecorator('HtmlTag');
        }

        Zend_Controller_Action_HelperBroker::getStaticHelper('session')->formOptions = $this->_options;

        $this->_view->form = $quoteForm->setAction($this->_websiteHelper->getUrl() . 'api/quote/quotes/type/' . Quote::QUOTE_TYPE_GENERATE);
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
}
