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
    protected function _initQuote() {
        $requestedUri = isset($this->_toasterOptions['url']) ? $this->_toasterOptions['url'] : Tools_System_Tools::getRequestUri();
        $this->_quote = Quote_Models_Mapper_QuoteMapper::getInstance()->find(
            Zend_Controller_Action_HelperBroker::getStaticHelper('page')->clean($requestedUri)
        );
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
            return (Tools_Security_Acl::isAllowed(Tools_Security_Acl::RESOURCE_ADMINPANEL) ? $swe->getMessage() : '');
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
     * Render created or expires quote dates
     *
     * {$quote:date[:_created_|:expires]}
     * @return mixed
     * @throws Exceptions_SeotoasterWidgetException
     */
    protected function _renderDate() {
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
        $this->_view->status  = $this->_quote->getStatus();
        return $this->_view->render('controls.quote.phtml');
    }

    /**
     * Renderer for the {$quote:}
     *
     * @return string
     * @throws Exceptions_SeotoasterWidgetException
     */
    protected function _renderSearch() {
        // if controls are not available for the current user role - rise exception
        if(!$this->_editAllowed) {
            throw new Exceptions_SeotoasterWidgetException('Quote widget error: Search are available for administrator only.');
        }
        return $this->_view->render('search.quote.phtml');
    }


    /**
     * Renderer for the {$quote:address[:_billing_[:shipping]]}
     *
     * @return string
     */
    protected function _renderAddress() {
        $addressType = isset($this->_options[0]) ? $this->_options[0] : self::ADDRESS_TYPE_BILLING;
        $address     = Tools_ShoppingCart::getAddressById(($addressType == self::ADDRESS_TYPE_BILLING) ? $this->_cart->getBillingAddressId() : $this->_cart->getShippingAddressId());
        if($this->_editAllowed) {
            $this->_view->addressForm = $this->_initAddressForm($addressType, $address);
        }
        $this->_view->addressType = $addressType;
        $this->_view->address     = $address;
        return $this->_view->render('address.quote.phtml');
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
            case Quote_Tools_Calc::TOTAL_TYPE_TAX   : $total = $this->_cart->getTotalTax(); break;
            case Quote_Tools_Calc::TOTAL_TYPE_SUB   : $total = $this->_cart->getSubTotal(); break;
            case Quote_Tools_Calc::TOTAL_TYPE_GRAND : $total = ($this->_cart->getTotal() + $this->_cart->getShippingPrice()); break;
            default                                 : throw new Exceptions_SeotoasterWidgetException('Quote widget error: Total type is invalid');
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
        $this->_view->quoteShipping = ($shippingPrice) ? $shippingPrice : 0;
        return $this->_view->render('shipping.quote.phtml');
    }


    protected function _renderItem() {
        // if this is regular parsing - do nothing
        if(!in_array('quotemspace', $this->_options)) {
            return '';
        }

        if(!isset($this->_options[0])) {
            throw new Exceptions_SeotoasterWidgetException('Quote widget error: Not enough parameters passed.');
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
                $price                  = Tools_ShoppingCart::getInstance()->calculateProductPrice($product, (isset($item['options']) && $item['options']) ? $item['options'] : Quote_Tools_Tools::getProductDefaultOptions($product));
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
                return (Tools_Security_Acl::isAllowed(Tools_Security_Acl::RESOURCE_USERS)) ? '<a data-pid="' . $item['product_id'] . '" class="remove-product" href="javascript:;"><img src="' . $this->_websiteHelper->getUrl() . 'system/images/delete.png" alt="delete"/></a>' : '';
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

    protected function _getProductDefaultOptions(Models_Model_Product $product, $flat = true) {
        $options        = array();
        $defaultOptions = $product->getDefaultOptions();
        if(!is_array($defaultOptions) || empty($defaultOptions)) {
            return null;
        }
        foreach($defaultOptions as $option){
            foreach ($option['selection'] as $item) {
                if($item['isDefault'] == 1) {
                    if(!$flat) {
                        $options[] = $item;
                    } else {
                        $options[$option['id']] = $item['id'];
                    }
                }
            }
        }
        return $options;
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
        $addrKey = $cartStorage->getAddressKey(Models_Model_Customer::ADDRESS_TYPE_BILLING);
        if($addrKey === null) {
            //otherwise trying to get shipping address (if shipping is not pick up)
            $addrKey =  $cartStorage->getAddressKey(Models_Model_Customer::ADDRESS_TYPE_SHIPPING);
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

    /**
     * Provides widget's shortcuts to the toaster
     *
     * @return array
     */
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