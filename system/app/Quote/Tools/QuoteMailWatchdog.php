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
     * Quote update trigger
     *
     */
    const TRIGGER_QUOTE_SIGNED     = 'quote_signed';

    /**
     * Quote expiration at trigger
     *
     */
    const TRIGGER_QUOTE_NOTIFYEXPIRYQUOTE = 'quote_notifyexpiryquote';

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

    protected $_observableModel = null;

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
        $data = $this->_options['params'];
        if ($this->_options['service'] === 'sms') {
            return $this->_sendSms($data);
        } else {
            switch ($this->_options['recipient']) {
                case self::RECIPIENT_CUSTOMER:
                case self::RECIPIENT_MEMBER:
                    $recipient = $this->_getCustomerRecipient();
                    if (!$recipient) {
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
                    if ($this->_debugEnabled) {
                        error_log('Quote Mail Watchdog report: Unsupported recipient ' . $this->_options['recipient'] . ' given');
                    }
                    return false;
                    break;
            }

            //changing quote status to send
            $this->_quote->removeObserver(new Tools_Mail_Watchdog());
            if ($this->_quote->getEditedBy() == 'auto') {
                Quote_Models_Mapper_QuoteMapper::getInstance()->save($this->_quote->setStatus(Quote_Models_Model_Quote::STATUS_SENT));
            } else {
                Quote_Models_Mapper_QuoteMapper::getInstance()->save($this->_quote->setStatus(Quote_Models_Model_Quote::STATUS_NEW));
            }

            return $this->_send(array('subject' => $this->_storeConfig['company'] . $this->_translator->translate(' Hello! We created a new quote for you')));
        }
    }

    /**
     * Sending updated quote email
     *
     * For now supports only customer and store owner recipients
     *
     * @return boolean
     */
    protected function _sendQuoteupdatedMail() {
        $data = $this->_options['params'];
        if ($this->_options['service'] === 'sms') {
            return $this->_sendSms($data);
        } else {
            switch ($this->_options['recipient']) {
                case self::RECIPIENT_CUSTOMER:
                    $recipient = $this->_getCustomerRecipient();
                    $this->_mailer->setMailToLabel($recipient->getFullName());

                    // restore the cart
                    $cart = Models_Mapper_CartSessionMapper::getInstance()->find($this->_quote->getCartId());
                    if (!$cart instanceof Models_Model_CartSession) {
                        if ($this->_debugEnabled) {
                            error_log('Quote Mail Watchdog report: Cannot find cart with id: ' . $this->_quote->getCartId() . ' for the quote with id: ' . $this->_quote->getId());
                        }
                        return false;
                    }

                    $defaultsMail = array('mailTo' => $recipient->getFullName());

                    // get the name => email for the customer
                    $recipientEmails = $this->_getCustomerEmails(array(
                        Tools_ShoppingCart::getAddressById($cart->getShippingAddressId()),
                        Tools_ShoppingCart::getAddressById($cart->getBillingAddressId())
                    ), $defaultsMail);

                    if (empty($recipientEmails)) {
                        if ($this->_debugEnabled) {
                            error_log('Quote Mail Watchdog report: Can\'t find any address for the recipient with id: ' . $recipient->getId());
                        }
                    }

                    $ccEmails = $this->_options['ccEmails'];

                    if (!empty($ccEmails)) {
                        $additionalEmails = array();
                        foreach ($ccEmails as $email) {
                            $additionalEmails[][$defaultsMail['mailTo']] = $email;
                        }

                        $recipientEmails = array_merge($recipientEmails, $additionalEmails);
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
            return $this->_send(array('subject' => $this->_storeConfig['company'] . $this->_translator->translate(' Hello! Your quote has been updated')));
        }
    }


    /**
     * Sending quote signed email
     *
     * @return boolean
     */
    protected function _sendQuotesignedMail() {

        $attachment = $this->_options['attachment'];
        $data = $this->_options['params'];
        if ($this->_options['service'] === 'sms') {
            return $this->_sendSms($data);
        } else {
            switch ($this->_options['recipient']) {
                case self::RECIPIENT_CUSTOMER:
                    $recipient = $this->_getCustomerRecipient();
                    $this->_mailer->setMailToLabel($recipient->getFullName());

                    // restore the cart
                    $cart = Models_Mapper_CartSessionMapper::getInstance()->find($this->_quote->getCartId());
                    if (!$cart instanceof Models_Model_CartSession) {
                        if ($this->_debugEnabled) {
                            error_log('Quote Mail Watchdog report: Cannot find cart with id: ' . $this->_quote->getCartId() . ' for the quote with id: ' . $this->_quote->getId());
                        }
                        return false;
                    }

                    $defaultsMail = array('mailTo' => $recipient->getFullName());

                    // get the name => email for the customer
                    $recipientEmails = $this->_getCustomerEmails(array(
                        Tools_ShoppingCart::getAddressById($cart->getShippingAddressId()),
                        Tools_ShoppingCart::getAddressById($cart->getBillingAddressId())
                    ), $defaultsMail);

                    if (empty($recipientEmails)) {
                        if ($this->_debugEnabled) {
                            error_log('Quote Mail Watchdog report: Can\'t find any address for the recipient with id: ' . $recipient->getId());
                        }
                    }

                    $recipientEmails = array_unique($recipientEmails);

                    $ccEmails = $this->_options['ccEmails'];

                    if (!empty($ccEmails)) {
                        $additionalEmails = array();
                        foreach ($ccEmails as $email) {
                            $additionalEmails[][$defaultsMail['mailTo']] = $email;
                        }

                        $recipientEmails = array_merge($recipientEmails, $additionalEmails);
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

            if (!empty($attachment)) {
                $this->_mailer->addAttachment($attachment);
            }

            $this->_observableModel = $this->_options['observableModel'];

            return $this->_send(array('subject' => $this->_storeConfig['company'] . $this->_translator->translate(' Hello! Your quote has been updated')));
        }
    }

    protected function _sendQuoteNotifyexpiryquoteMail()
    {
        $data = $this->_options['params'];
        $adminEmail = !empty($this->_configHelper->getConfig('adminEmail')) ? $this->_configHelper->getConfig('adminEmail') : 'admin@localhost';
        $bccArray = array();
        $userMapper = Application_Model_Mappers_UserMapper::getInstance();

        if ($this->_options['service'] === 'sms') {
            if($this->_options['recipient'] == self::RECIPIENT_CUSTOMER || $this->_options['recipient'] == self::RECIPIENT_MEMBER) {
                $smsPhoneNumber = $this->_prepareMobilePhone($data);

                if(!empty($smsPhoneNumber)) {
                    $customerFieldData = $this->_prepareCustomerFieldData($data);

                    $message = strip_tags($this->_options['message']);
                    $message = Quote_Tools_Tools::addDictionarySmsFields($message, $data, $customerFieldData, $this->_websiteHelper->getUrl());

                    $subscriber['subscriber']['user'] = array(
                        'phone' => array($smsPhoneNumber),
                        'message' => $message,
                        'owner_type' => Apps::SMS_OWNER_TYPE_USER,
                        'custom_params' => array(),
                        'sms_from_type' => 'info',
                    );

                    $response = Apps::apiCall('POST', 'apps', array('twilioSms'), $subscriber);

                    return true;
                }
            } elseif ($this->_options['recipient'] == self::RECIPIENT_SALESPERSON || $this->_options['recipient'] == self::RECIPIENT_ADMIN || $this->_options['recipient'] == Tools_Security_Acl::ROLE_SUPERADMIN) {
                if($this->_options['recipient'] == Tools_Security_Acl::ROLE_SUPERADMIN) {
                    $adminPhone = !empty($this->_configHelper->getConfig('phone')) ? $this->_configHelper->getConfig('phone') : '';
                    if(!empty($adminPhone)) {
                        //$userMapper = Application_Model_Mappers_UserMapper::getInstance();
                        //$user = $userMapper->findByRole(Tools_Security_Acl::ROLE_SUPERADMIN);

                        $customerFieldData = $this->_prepareCustomerFieldData($data);

                        $smsNumber = Apps_Tools_Twilio::normalizePhoneNumberToE164($adminPhone);
                        $message = strip_tags($this->_options['message']);
                        $message = Quote_Tools_Tools::addDictionarySmsFields($message, $data, $customerFieldData/*$user*/, $this->_websiteHelper->getUrl());

                        if (!empty($smsNumber)) {
                            $subscriber['subscriber']['user'] = array(
                                'phone' => array($smsNumber),
                                'message' => $message,
                                'owner_type' => Apps::SMS_OWNER_TYPE_ADMIN,
                                'custom_params' => array(),
                                'sms_from_type' => 'info',
                            );

                            $response = Apps::apiCall('POST', 'apps', array('twilioSms'), $subscriber);
                        }
                    }
                } else {
                    $where = $userMapper->getDbTable()->getAdapter()->quoteInto("role_id = ?", $this->_options['recipient']);
                    $allUsers = $userMapper->fetchAll($where);
                    if (!empty($allUsers)) {
                        $customerFieldData = $this->_prepareCustomerFieldData($data);

                        foreach ($allUsers as $user) {
                            $smsNumber = '';

                            if(!empty($user->getMobileCountryCodeValue()) && !empty($user->getMobilePhone())) {
                                $smsNumber = Apps_Tools_Twilio::normalizePhoneNumberToE164($user->getMobileCountryCodeValue() . $user->getMobilePhone());
                            } elseif (!empty($user->getDesktopCountryCodeValue()) && !empty($user->getDesktopPhone())) {
                                $smsNumber = Apps_Tools_Twilio::normalizePhoneNumberToE164($user->getDesktopCountryCodeValue() . $user->getDesktopPhone());
                            }

                            $message = strip_tags($this->_options['message']);

                            $message = Quote_Tools_Tools::addDictionarySmsFields($message, $data, $customerFieldData/*$user*/, $this->_websiteHelper->getUrl());

                            if (!empty($smsNumber)) {
                                $subscriber['subscriber']['user'] = array(
                                    'phone' => array($smsNumber),
                                    'message' => $message,
                                    'owner_type' => Apps::SMS_OWNER_TYPE_ADMIN,
                                    'custom_params' => array(),
                                    'sms_from_type' => 'info',
                                );

                                $response = Apps::apiCall('POST', 'apps', array('twilioSms'), $subscriber);
                            }
                        }
                    }
                }
            }
        } else {
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
                    $where = $userMapper->getDbTable()->getAdapter()->quoteInto("role_id = ?",
                        self::RECIPIENT_SALESPERSON);
                    $salesUsers = $userMapper->fetchAll($where);
                    //store owner

                    $bccArray[] = $this->_storeConfig['email'];

                    if (!empty($salesUsers)) {
                        foreach ($salesUsers as $sales) {
                            array_push($bccArray, $sales->getEmail());
                        }
                        if (!empty($bccArray)) {
                            $this->_mailer->setMailBcc($bccArray);
                        }
                    }

                    $this->_mailer->setMailToLabel($this->_storeConfig['company']);
                    break;
                case self::RECIPIENT_ADMIN:
                    // all admins
                    $this->_mailer->setMailToLabel('Admin')
                        ->setMailTo($adminEmail);
                    $where = $userMapper->getDbTable()->getAdapter()->quoteInto("role_id = ?",
                        Tools_Security_Acl::ROLE_ADMIN);
                    $adminUsers = $userMapper->fetchAll($where);
                    if (!empty($adminUsers)) {
                        foreach ($adminUsers as $admin) {
                            array_push($bccArray, $admin->getEmail());
                        }
                        if (!empty($bccArray)) {
                            $this->_mailer->setMailBcc($bccArray);
                        }
                    }

                    break;
                default:
                    if($this->_debugEnabled) {
                        error_log('Quote Mail Watchdog report: Unsupported recipient '.$this->_options['recipient'].' given');
                    }
                    return false;
                    break;
            }

            return ($this->_send() !== false);
        }
    }

    protected function _prepareMobilePhone($data) {
        $smsPhoneNumber = '';

        if(!empty($data)) {
            if(!empty($data['userMobileCountryCode']) && !empty($data['userMobilePhone'])) {
                $smsPhoneNumber = Apps_Tools_Twilio::normalizePhoneNumberToE164($data['userMobileCountryCode'] . $data['userMobilePhone']);
            } elseif (!empty($data['userDesctopCountryCode']) && !empty($data['userDesctopPhone'])) {
                $smsPhoneNumber = Apps_Tools_Twilio::normalizePhoneNumberToE164($data['userDesctopCountryCode'] . $data['userDesctopPhone']);
            } elseif (!empty($data['billingAddressId'])) {
                if(!empty($data['billing_phone_country_code_value']) && !empty($data['billing_phone'])) {
                    $smsPhoneNumber = Apps_Tools_Twilio::normalizePhoneNumberToE164($data['billing_phone_country_code_value'] . $data['billing_phone']);
                } elseif (!empty($data['billing_mobile_country_code_value']) && !empty($data['billing_mobile'])) {
                    $smsPhoneNumber = Apps_Tools_Twilio::normalizePhoneNumberToE164($data['billing_mobile_country_code_value'] . $data['billing_mobile']);
                }
            } elseif (!empty($data['shippingAddressId'])) {
                if(!empty($data['shipping_phone_country_code_value']) && !empty($data['shipping_phone'])) {
                    $smsPhoneNumber = Apps_Tools_Twilio::normalizePhoneNumberToE164($data['shipping_phone_country_code_value'] . $data['shipping_phone']);
                } elseif (!empty($data['shipping_mobile_country_code_value']) && !empty($data['shipping_mobile'])) {
                    $smsPhoneNumber = Apps_Tools_Twilio::normalizePhoneNumberToE164($data['shipping_mobile_country_code_value'] . $data['shipping_mobile']);
                }
            }

        }

        return $smsPhoneNumber;
    }

    /**
     * Customer email and Customer full name
     * @param $data
     * @return array
     */
    protected function _prepareCustomerFieldData($data) {
        $customerEmail = '';
        $customerFullName = '';

        if(!empty($data)) {
            if(!empty($data['userEmail']) && !empty($data['userFullName'])) {
                $customerEmail = $data['userEmail'];
                $customerFullName = $data['userFullName'];
            } elseif (!empty($data['billingAddressId'])) {
                if(!empty($data['billing_email']) && (!empty($data['billing_firstname']) || !empty($data['billing_lastname']))) {
                    $customerEmail = $data['billing_email'];
                    $customerFullName = $data['billing_firstname'] . ' ' . $data['billing_lastname'];
                }
            } elseif (!empty($data['shippingAddressId'])) {
                if(!empty($data['shipping_email']) && (!empty($data['shipping_firstname']) || !empty($data['shipping_lastname']))) {
                    $customerEmail = $data['shipping_email'];
                    $customerFullName = $data['shipping_firstname'] . ' ' . $data['shipping_lastname'];
                }
            }
        }

        return array('customerEmail' => $customerEmail, 'customerFullName' => $customerFullName);
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

        if (!empty($this->_observableModel)){
            $parserOptions['observableModel'] = $this->_observableModel;
        }

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

        //{quoteleadorganizationlogo} - This lexem return quote lead organization logo
        //{quoteleadorganizationlogo:src} - This lexem return quote lead organization logo src
        $userId = $this->_quote->getUserId();

        if(!empty($userId)) {
            $userModel = Application_Model_Mappers_UserMapper::getInstance()->find($userId);

            if ($userModel instanceof Application_Model_Models_User) {
                $leadsPlugin = Application_Model_Mappers_PluginMapper::getInstance()->findByName('leads');

                if ($leadsPlugin instanceof Application_Model_Models_Plugin) {
                    $leadsPluginStatus = $leadsPlugin->getStatus();

                    if ($leadsPluginStatus === 'enabled') {
                        $organizationDocumentData = Tools_LeadTools::getOrganizationLogo($userId);

                        if(!empty($organizationDocumentData)){
                            $this->_entityParser->addToDictionary(array(
                                'quoteleadorganizationlogo' => '<img src="'. $this->_websiteHelper->getUrl() . Leads::ORGANIZATION_LOGOS_IMAGES_PATH . DIRECTORY_SEPARATOR . $organizationDocumentData['file_stored_name'] .'" alt="'. $organizationDocumentData['display_file_name'] .'">',
                                'quoteleadorganizationlogo:src' => $this->_websiteHelper->getUrl() . Leads::ORGANIZATION_LOGOS_IMAGES_PATH . DIRECTORY_SEPARATOR . $organizationDocumentData['file_stored_name']
                            ));
                        }
                    }
                }
            }
        } else {
            $this->_entityParser->addToDictionary(array(
                'customer:full_name' => '',
                'customer:email' => ''
            ));
        }

        $cartId = $this->_quote->getCartId();

        $quoteCustomParamsDataMapper = Quote_Models_Mapper_QuoteCustomParamsDataMapper::getInstance();
        $quoteCustomParamsData = $quoteCustomParamsDataMapper->findByCartId($cartId);

        $quoteCustomFieldsConfigMapper = Quote_Models_Mapper_QuoteCustomFieldsConfigMapper::getInstance();
        $customFields = $quoteCustomFieldsConfigMapper->fetchAll(null, null, null, null, true);

        if(!empty($customFields)) {
            foreach ($customFields as $field) {
                $paramValue = '';

                if(!empty($quoteCustomParamsData)) {
                    foreach ($quoteCustomParamsData as $paramsData) {
                        if($field['param_name'] == $paramsData['param_name']) {
                            if($paramsData['param_type'] == Quote_Models_Model_QuoteCustomFieldsConfigModel::CUSTOM_PARAM_TYPE_TEXT) {
                                if(!empty($paramsData['param_value'])) {
                                    $paramValue = $paramsData['param_value'];
                                }
                            } elseif ($paramsData['param_type'] == Quote_Models_Model_QuoteCustomFieldsConfigModel::CUSTOM_PARAM_TYPE_SELECT) {
                                if(!empty($paramsData['params_option_id'])) {
                                    $paramValue = $paramsData['option_val'];
                                }
                            } elseif ($paramsData['param_type'] == Quote_Models_Model_QuoteCustomFieldsConfigModel::CUSTOM_PARAM_TYPE_RADIO) {

                            } elseif ($paramsData['param_type'] == Quote_Models_Model_QuoteCustomFieldsConfigModel::CUSTOM_PARAM_TYPE_TEXTAREA) {

                            } elseif ($paramsData['param_type'] == Quote_Models_Model_QuoteCustomFieldsConfigModel::CUSTOM_PARAM_TYPE_CHECKBOX) {

                            }
                        }
                    }
                }

                $this->_entityParser->addToDictionary(array('quotecustomfields:'.$field['param_name'] => $paramValue));
            }
        }

        $createdId = $this->_quote->getCreatorId();
        if (!empty($createdId)) {
            $userModel = Application_Model_Mappers_UserMapper::getInstance()->find($createdId);
            if ($userModel instanceof Application_Model_Models_User) {
                $this->_entityParser->addToDictionary(array('quoteowner:email' => $userModel->getEmail()));
            }
        } else {
            $this->_entityParser->addToDictionary(array('quoteowner:email' => $this->_storeConfig['email']));
        }

        $wicEmail = $this->_configHelper->getConfig('wicEmail');
        $this->_entityParser->addToDictionary(array('widcard:BizEmail' => !empty($wicEmail) ? $wicEmail : $this->_configHelper->getConfig('adminEmail')));

        $this->_mailer->setBody($this->_entityParser->parse($body));

        $this->_options['from'] = $this->_parseMailFrom($this->_entityParser->parse($this->_options['from']));

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

    protected function _parseMailFrom($mailFrom)
    {
        $themeData = Zend_Registry::get('theme');
        $extConfig = Zend_Registry::get('extConfig');
        $parserOptions = array(
            'websiteUrl' => $this->_websiteHelper->getUrl(),
            'websitePath' => $this->_websiteHelper->getPath(),
            'currentTheme' => $extConfig['currentTheme'],
            'themePath' => $themeData['path'],
        );
        $parser = new Tools_Content_Parser($mailFrom, array(), $parserOptions);

        return Tools_Content_Tools::stripEditLinks($parser->parseSimple());
    }

    /**
     * @param array $data additional data
     * @return bool
     * @throws Exceptions_SeotoasterPluginException
     */
    protected function _sendSms($data)
    {
        $orderId = $this->_quote->getCartId();
        $userMapper = Application_Model_Mappers_UserMapper::getInstance();
        $cartSessionMapper = Models_Mapper_CartSessionMapper::getInstance();
        $orderModel = $cartSessionMapper->find($orderId);
        $websiteUrl = $this->_websiteHelper->getUrl();
        $orderMapper = Models_Mapper_OrdersMapper::getInstance();
        $where = $orderMapper->getDbTable()->getAdapter()->quoteInto('oc.cart_id=?', $orderId);
        $currentOrder = $orderMapper->fetchAll($where);
        $dictionary = array();
        if (!empty($currentOrder)) {
            foreach ($currentOrder[0] as $orderKey => $value) {
                $dictionary['customer:' . $orderKey] = $value;
            }
        }

        foreach ($this->_quote->toArray() as $orderKey => $value) {
            $dictionary['quote:' . strtolower($orderKey)] = $value;
        }

        $message = strip_tags($this->_options['message']);
        $dictionary['$website:url'] = $websiteUrl;

        $entityParser = new Tools_Content_EntityParser();

        $entityParser->addToDictionary($dictionary);

        $message = $entityParser->parse($message);

        if ($orderModel instanceof Models_Model_CartSession) {
            if ($this->_options['recipient'] == Tools_Security_Acl::ROLE_GUEST || $this->_options['recipient'] == Tools_StoreMailWatchdog::RECIPIENT_CUSTOMER || $this->_options['recipient'] == Tools_Security_Acl::ROLE_MEMBER) {
                $smsPhoneNumber = $this->_prepareCustomerPhoneNumber();

                $smsPhoneNumber = Apps_Tools_Twilio::normalizePhoneNumberToE164($smsPhoneNumber);

                if (!empty($smsPhoneNumber)) {
                    $subscriber['subscriber']['user'] = array(
                        'phone' => array($smsPhoneNumber),
                        'message' => $message,
                        'owner_type' => Apps::SMS_OWNER_TYPE_USER,
                        'custom_params' => array(),
                        'sms_from_type' => 'info'
                    );

                    $response = Apps::apiCall('POST', 'apps', array('twilioSms'), $subscriber);
                }

            } elseif ($this->_options['recipient'] == Tools_StoreMailWatchdog::RECIPIENT_SALESPERSON || $this->_options['recipient'] == Tools_StoreMailWatchdog::RECIPIENT_ADMIN || $this->_options['recipient'] == Tools_Security_Acl::ROLE_SUPERADMIN) {
                if ($this->_options['recipient'] == Tools_Security_Acl::ROLE_SUPERADMIN) {
                    $adminPhone = !empty($this->_configHelper->getConfig('phone')) ? $this->_configHelper->getConfig('phone') : '';
                    if (!empty($adminPhone)) {
                        $smsNumber = Apps_Tools_Twilio::normalizePhoneNumberToE164($adminPhone);

                        if (!empty($smsNumber)) {
                            $subscriber['subscriber']['user'] = array(
                                'phone' => array($smsNumber),
                                'message' => $message,
                                'owner_type' => Apps::SMS_OWNER_TYPE_ADMIN,
                                'custom_params' => array(),
                                'sms_from_type' => 'info',
                            );

                            $response = Apps::apiCall('POST', 'apps', array('twilioSms'), $subscriber);
                        }
                    }
                } else {
                    $where = $userMapper->getDbTable()->getAdapter()->quoteInto("role_id = ?", $this->_options['recipient']);
                    $allUsers = $userMapper->fetchAll($where);
                    if (!empty($allUsers)) {
                        foreach ($allUsers as $user) {
                            $smsNumber = '';

                            if (!empty($user->getMobileCountryCodeValue()) && !empty($user->getMobilePhone())) {
                                $smsNumber = Apps_Tools_Twilio::normalizePhoneNumberToE164($user->getMobileCountryCodeValue() . $user->getMobilePhone());
                            } elseif (!empty($user->getDesktopCountryCodeValue()) && !empty($user->getDesktopPhone())) {
                                $smsNumber = Apps_Tools_Twilio::normalizePhoneNumberToE164($user->getDesktopCountryCodeValue() . $user->getDesktopPhone());
                            }

                            if (!empty($smsNumber)) {
                                $subscriber['subscriber']['user'] = array(
                                    'phone' => array($smsNumber),
                                    'message' => $message,
                                    'owner_type' => Apps::SMS_OWNER_TYPE_ADMIN,
                                    'custom_params' => array(),
                                    'sms_from_type' => 'info',
                                );

                                $response = Apps::apiCall('POST', 'apps', array('twilioSms'), $subscriber);
                            }
                        }
                    }
                }
            }
        }

        return true;
    }

    protected function _prepareCustomerPhoneNumber()
    {
        $orderId = $this->_quote->getCartId();
        $userId = $this->_quote->getUserId();
        $userMapper = Application_Model_Mappers_UserMapper::getInstance();
        $cartSessionMapper = Models_Mapper_CartSessionMapper::getInstance();
        $orderModel = $cartSessionMapper->find($orderId);
        $userModel = $userMapper->find($userId);
        $smsPhoneNumber = '';

        if ($orderModel instanceof Models_Model_CartSession) {
            if ($userModel instanceof Application_Model_Models_User) {
                if (!empty($userModel->getMobilePhone())) {
                    $smsPhoneNumber = Apps_Tools_Twilio::normalizePhoneNumberToE164($userModel->getMobileCountryCodeValue() . $userModel->getMobilePhone());
                } else {
                    $smsPhoneNumber = Apps_Tools_Twilio::normalizePhoneNumberToE164($userModel->getDesktopCountryCodeValue() . $userModel->getDesktopPhone());
                }
            }
            if (empty($smsPhoneNumber)) {
                if(!empty($orderModel->getBillingAddressId())) {
                    $billingAddressData = Tools_ShoppingCart::getAddressById($orderModel->getBillingAddressId());
                    if (!empty($billingAddressData['mobile_country_code_value']) && !empty($billingAddressData['mobile'])) {
                        $smsPhoneNumber = Apps_Tools_Twilio::normalizePhoneNumberToE164($billingAddressData['mobile_country_code_value'] . $billingAddressData['mobile']);
                    } elseif (!empty($billingAddressData['phone_country_code_value']) && !empty($billingAddressData['phone'])) {
                        $smsPhoneNumber = Apps_Tools_Twilio::normalizePhoneNumberToE164($billingAddressData['phone_country_code_value'] . $billingAddressData['phone']);
                    }
                }
                if (empty($smsPhoneNumber)) {
                    if(!empty($orderModel->getShippingAddressId())) {
                        $shippingAddressData = Tools_ShoppingCart::getAddressById($orderModel->getShippingAddressId());
                        if (!empty($shippingAddressData['mobile_country_code_value']) && !empty($shippingAddressData['mobile'])) {
                            $smsPhoneNumber = Apps_Tools_Twilio::normalizePhoneNumberToE164($shippingAddressData['mobile_country_code_value'] . $shippingAddressData['mobile']);
                        } elseif (!empty($shippingAddressData['phone_country_code_value']) && !empty($shippingAddressData['phone'])) {
                            $smsPhoneNumber = Apps_Tools_Twilio::normalizePhoneNumberToE164($shippingAddressData['phone_country_code_value'] . $shippingAddressData['phone']);
                        }
                    }
                }

            }
        }

        return $smsPhoneNumber;
    }
}
