<?php
/**
 * Quote mail watchdog
 *
 * @author: iamne <eugene@seotoaster.com> Seotoaster core team
 * Date: 5/15/12
 * Time: 2:14 PM
 */
class Quote_Tools_QuoteMailWatchdog implements Interfaces_Observer {

    /**
     * New quote trigger.
     *
     */
    const TRIGGER_QUOTE_CREATED     = 'quote_created';

    /**
     * Quote update trigger
     *
     */
    const TRIGGER_QUOTE_UPDATED     = 'quote_updated';

    /**
     * Quote mail recipient 'sales person'
     *
     */
    const RECIPIENT_SALESPERSON     = 'sales person';

    /**
     * Quote mail recipient 'admin'
     */
    const RECIPIENT_ADMIN           = 'admin';

    /**
     * Quote mail recipient 'customer'
     *
     */
    const RECIPIENT_CUSTOMER        = 'customer';

    /**
     * Quote mail recipient 'member'
     *
     */
    const RECIPIENT_MEMBER          = 'member';

    /**
     * Quote mail recipient 'storeowner'
     *
     */
    const RECIPIENT_STOREOWNER      = 'storeowner';

    /**
     * Sold quote status
     *
     */
    const TRIGGER_QUOTE_STATUS_SOLD = 'quotestatussold';

    /**
     * Sent quote status
     *
     */
    const TRIGGER_QUOTE_STATUS_SENT = 'quotestatussent';

    /**
     * Lost quote status
     *
     */
    const TRIGGER_QUOTE_STATUS_LOST = 'quotestatuslost';


    /**
     * Options passed from the toaster system mail watchdog
     *
     * @var array
     */
    protected $_options             = array();

    /**
     * Toaster mailer
     *
     * @var Tools_Mail_Mailer Toaster mailer instance
     */
    protected $_mailer              = null;

    /**
     * Toaster entity parser
     *
     * @var Tools_Content_EntityParser
     */
    protected $_entityParser        = null;

    /**
     * Toaster db config helper
     *
     * @var null|Helpers_Action_Config
     */
    protected $_configHelper        = null;

    /**
     * Toaster website helper
     *
     * @var null|Helpers_Action_Website
     */
    protected $_websiteHelper       = null;

    /**
     * Shopping configuration
     *
     * @var array|null
     */
    protected $_storeConfig         = null;

    /**
     * Seotoaster translator instance
     *
     * @var Helpers_Action_Language
     */
    protected $_translator          = null;

    /**
     * Quote model instance
     *
     * @var null|Quote_Models_Model_Quote
     */
    protected $_quote               = null;

    /**
     * Flag to see if we are in the debug mode
     *
     * @var bool
     */
    protected $_debugEnabled       = false;

    /**
     * Init all necessary helpers and assign correct mail message
     *
     * @param array $options
     */
    public function __construct($options = array()) {
        // get global options
        $this->_options       = $options;

        // initialize helpers
        $this->_storeConfig   = Models_Mapper_ShoppingConfig::getInstance()->getConfigParams();
        $this->_entityParser  = new Tools_Content_EntityParser();
        $this->_configHelper  = Zend_Controller_Action_HelperBroker::getStaticHelper('config');
        $this->_websiteHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('website');
        $this->_translator    = Zend_Controller_Action_HelperBroker::getStaticHelper('language');

        // get current debug mode state
        $this->_debugEnabled  = Tools_System_Tools::debugMode();

        // initialize mailer and set correct message
        $this->_mailer        = Tools_Mail_Tools::initMailer();
        $this->_initMailMessage();
    }

    /**
     * Mail watchdog ectry point. Everything begins here
     *
     * @param Quote_Models_Model_Quote $object
     * @return bool
     */
    public function notify($object) {
        // we are expecting quote here
        if (!$object instanceof Quote_Models_Model_Quote) {
            if($this->_debugEnabled) {
                error_log('Quote Mail Watchdog report: Quote instance expected, but ' . gettype($object) . ' passed');
            }
            return false;
        }

        // assign $object to the _quote property
        $this->_quote = $object;

        // generate sender method for the specific trigger and execute it if exists
        if (isset($this->_options['trigger'])) {
            $methodName = '_send'. str_replace(' ', '', ucwords(str_replace('_', ' ', $this->_options['trigger']))) . 'Mail';
            if (method_exists($this, $methodName)){
                $this->$methodName();
            }
        }
    }

    /**
     * Initialize mail message.
     *
     * If there is a 'mailMessage' key in the _options array - it will be used as a mail message
     * Otherwise 'message' key will be used
     *
     */
    protected function _initMailMessage() {
        $this->_options['message'] = (isset($this->_options['mailMessage']) ? $this->_options['mailMessage'] : $this->_options['message']);
        unset($this->_options['mailMessage']);
    }


    /**
     * Sender method for the 'quote_created' trigger
     *
     * Can send mails to the customer, sales person and store owner
     * @return bool
     */
    protected function _sendQuoteCreatedMail() {
        // switch through the recipients and init proper mailer values for them
        switch($this->_options['recipient']) {
            case self::RECIPIENT_CUSTOMER:
            case self::RECIPIENT_MEMBER:
                $recipient = $this->_getCustomerRecipient();
                if(!$recipient) {
                    return false;
                }
                $this->_mailer->setMailToLabel($recipient->getFullName())->setMailTo($recipient->getEmail());
            break;
            case self::RECIPIENT_SALESPERSON:
                // store owner
                $emails[$this->_storeConfig['company']] = $this->_storeConfig['email'];
                // all other sales persons
                $emails = array_merge($emails, Quote_Tools_Tools::getEmailData(array(
                        self::RECIPIENT_SALESPERSON
                    )));
                $this->_mailer->setMailToLabel($this->_storeConfig['company'])->setMailTo($emails);
            break;
            case self::RECIPIENT_STOREOWNER:
            case self::RECIPIENT_ADMIN:
                // all admins
                $emails = Quote_Tools_Tools::getEmailData(array(
                    self::RECIPIENT_ADMIN
                ));
                if (!empty($emails)) {
                    $this->_mailer->setMailToLabel($this->_storeConfig['company'])->setMailTo($emails);
                }
            break;
            default:
                if($this->_debugEnabled) {
                    error_log('Quote Mail Watchdog report: Unsupported recipient '.$this->_options['recipient'].' given');
                }
                return false;
            break;
        }

        //changing quote status to send
        $this->_quote->removeObserver(new Tools_Mail_Watchdog());
        if($this->_quote->getEditedBy() == 'auto'){
            Quote_Models_Mapper_QuoteMapper::getInstance()->save($this->_quote->setStatus(Quote_Models_Model_Quote::STATUS_SENT));
        }else{
            Quote_Models_Mapper_QuoteMapper::getInstance()->save($this->_quote->setStatus(Quote_Models_Model_Quote::STATUS_NEW));
        }

        return $this->_send(array('subject' => $this->_translator->translate($this->_storeConfig['company'] . ' Hello! We created a new quote for you')));
    }

    /**
     * Sending updated quote email
     *
     * For now supports only customer and store owner recipients
     *
     * @return boolean
     */
    protected function _sendQuoteupdatedMail() {
        switch($this->_options['recipient']) {
            case self::RECIPIENT_CUSTOMER:
                $recipient = $this->_getCustomerRecipient();
                $this->_mailer->setMailToLabel($recipient->getFullName());

                // restore the cart
                $cart = Models_Mapper_CartSessionMapper::getInstance()->find($this->_quote->getCartId());
                if(!$cart instanceof Models_Model_CartSession) {
                    if($this->_debugEnabled) {
                        error_log('Quote Mail Watchdog report: Cannot find cart with id: ' . $this->_quote->getCartId() . ' for the quote with id: ' . $this->_quote->getId());
                    }
                    return false;
                }

                // get the name => email for the customer
                $recipientEmails = $this->_getCustomerEmails(array(
                    Tools_ShoppingCart::getAddressById($cart->getShippingAddressId()),
                    Tools_ShoppingCart::getAddressById($cart->getBillingAddressId())
                ), array('mailTo' => $recipient->getFullName()));

                if(empty($recipientEmails)) {
                    if($this->_debugEnabled) {
                        error_log('Quote Mail Watchdog report: Can\'t find any address for the recipient with id: ' . $recipient->getId());
                    }
                }

                $this->_mailer->setMailTo($recipientEmails);
            break;
            case self::RECIPIENT_SALESPERSON:
                // store owner
                $emails[$this->_storeConfig['company']] = $this->_storeConfig['email'];
                // all other recipients
                $emails = array_merge($emails, Quote_Tools_Tools::getEmailData(array(
                    self::RECIPIENT_SALESPERSON
                )));
                $this->_mailer->setMailToLabel($this->_storeConfig['company'])->setMailTo($emails);
            break;
            case self::RECIPIENT_STOREOWNER:
            case self::RECIPIENT_ADMIN:
                // all admins
                $emails = Quote_Tools_Tools::getEmailData(array(
                    self::RECIPIENT_ADMIN
                ));
                $this->_mailer->setMailToLabel($this->_storeConfig['company'])->setMailTo($emails);
            break;
        }
        return $this->_send(array('subject' => $this->_translator->translate($this->_storeConfig['company'] . ' Hello! Your quote has been updated')));
    }

    /**
     * Prepare mail body using mail template form the trigger
     *
     * Mail template will be parsed for the:
     * 1. {emailmessage} instance
     * 2. all {quote:quote_model_property} instances
     * 3. standart toaster widgets
     * @return null
     */
    protected function _prepareEmailBody() {
        // getting quote mail template for the mail body
        $mailTemplate = Application_Model_Mappers_TemplateMapper::getInstance()->find($this->_options['template']);

        if(!$mailTemplate || empty($mailTemplate)) {
            if($this->_debugEnabled) {
                error_log('Quote Mail Watchdog report: can\'t find quote mail template. Looks like it doesn\'t exist');
            }
	        return false;
        }

        // init entity parser dictionary with proper message
        $mailTemplate = $this->_entityParser->setDictionary(array(
            'emailmessage' => !empty($this->_options['message']) ? $this->_options['message'] : ''
        ))->parse($mailTemplate->getContent());
        // parse mail template for the {quote:quote_model_property} occurences
        $cartId = $this->_quote->getCartId();
        $orderMapper = Models_Mapper_OrdersMapper::getInstance();
        $where = $orderMapper->getDbTable()->getAdapter()->quoteInto('oc.cart_id=?', $cartId);
        $currentOrder = Models_Mapper_OrdersMapper::getInstance()->fetchAll($where);
        if(!empty($currentOrder)){
           foreach($currentOrder[0] as $orderKey=>$value){
                $orderDictionary['customer:'.$orderKey] = $value;
           }
           $this->_entityParser->addToDictionary($orderDictionary);
        }
        $mailTemplate = $this->_entityParser->objectToDictionary($this->_quote)->parse($mailTemplate);

        // gethering options for the toaster parser
        $themeData = Zend_Registry::get('theme');
        $extConfig = Zend_Registry::get('extConfig');
        $parserOptions = array(
            'websiteUrl'   => $this->_websiteHelper->getUrl(),
            'websitePath'  => $this->_websiteHelper->getPath(),
            'currentTheme' => $extConfig['currentTheme'],
            'themePath'    => $themeData['path'],
        );

        // init toaster parser, parse mail template for the standart toaster widgets and return the result
        $parser = new Tools_Content_Parser($mailTemplate, Application_Model_Mappers_PageMapper::getInstance()->findByUrl($this->_quote->getId() . '.html')->toArray(), $parserOptions);
        return Tools_Content_Tools::stripEditLinks($parser->parseSimple());
    }

    /**
     * Prepare body, set from parameters and send a mail
     *
     * @param array $defaults
     * @return bool
     */
    protected function _send($defaults = array()) {
        // something wrong happend during we were preparing the mail
        if (false === ($body = $this->_prepareEmailBody())) {
            if($this->_debugEnabled) {
                error_log('Quote Mail Watchdog report: Can\'t prepare email body');
            }
            return false;
        }
        // adding quote model to the entity parser dictionary to be able to parse {quote:quote_property_here} instances
        $this->_entityParser->objectToDictionary($this->_quote);
        $this->_mailer->setBody($this->_entityParser->parse($body));

        $this->_mailer->setMailFrom((!isset($this->_options['from']) || !$this->_options['from']) ? $this->_storeConfig['email'] : $this->_options['from'])
            ->setMailFromLabel($this->_storeConfig['company'])
            ->setSubject(isset($this->_options['subject']) ? $this->_entityParser->parse($this->_options['subject']) : $defaults['subject']);
        return $this->_mailer->send();

    }

    /**
     * Get quote user who's representing a customer recipient
     *
     * @return Application_Model_Models_User
     */
    protected function _getCustomerRecipient() {
        $userId = $this->_quote->getUserId();
        if(!$userId) {
            if($this->_debugEnabled) {
                error_log('Quote Mail Watchdog report: Quote ' . $this->_quote->getId() . ' is missing user id');
            }
            return null;
        }
        return Application_Model_Mappers_UserMapper::getInstance()->find($userId);
    }

    /**
     * Get an array of customer's billing and shipping name => email pairs: 'John Doe' => 'johndoe@example.com'
     *
     * @param array $addresses
     * @param array $defaults
     * @return array
     */
    protected function _getCustomerEmails($addresses = array(), $defaults = array()) {
        $emails = array();
        if(empty($addresses)) {
            return $emails;
        }
        foreach($addresses as $address) {
            if(!$address || !isset($address['email'])) {
                continue;
            }
            if(isset($address['firstname']) && isset($address['lastname'])) {
                $fullName = $address['firstname'] . ' ' . $address['lastname'];
            }
            $emails[][(isset($fullName) ? $fullName : $defaults['mailTo'])] = $address['email'];
        }
        return $emails;
    }
}
