<?php
/**
 * Quote mail watchdog
 *
 * @author: iamne <eugene@seotoaster.com> Seotoaster core team
 * Date: 5/15/12
 * Time: 2:14 PM
 */
class Quote_Tools_QuoteMailWatchdog implements Interfaces_Observer {

    const TRIGGER_NEW_QUOTE         = 'quote_newquote';

    const TRIGGER_QUOTE_UPDATED     = 'quote_updated';

    const TRIGGER_QUOTE_STATUS_SOLD = 'quotestatussold';

    const TRIGGER_QUOTE_STATUS_SENT = 'quotestatussent';

    const TRIGGER_QUOTE_STATUS_LOST = 'quotestatuslost';

    const RECIPIENT_SALESPERSON     = 'sales person';

    const RECIPIENT_CUSTOMER        = 'customer';

    const RECIPIENT_MEMBER          = 'member';

    const RECIPIENT_STOREOWNER      = 'storeowner';

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

    protected $_configHelper        = null;

    protected $_websiteHelper       = null;

    protected $_storeConfig         = null;

    public function __construct($options = array()) {
        $this->_options       = $options;
        $this->_storeConfig   = Models_Mapper_ShoppingConfig::getInstance()->getConfigParams();
        $this->_entityParser  = new Tools_Content_EntityParser();
        $this->_configHelper  = Zend_Controller_Action_HelperBroker::getStaticHelper('config');
        $this->_websiteHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('website');
        $this->_mailer        = Tools_Mail_Tools::initMailer();
        $this->_initMailMessage();
    }

    protected function _initMailMessage() {
        $this->_options['message'] = (isset($this->_options['mailMessage']) ? $this->_options['mailMessage'] : $this->_options['message']);
        unset($this->_options['mailMessage']);
    }

    public function notify($object) {
        if (!$object){
            return false;
        }
        if (isset($this->_options['trigger'])){
            $methodName = '_send'. str_replace(array(' ', '_'), '', ucwords($this->_options['trigger'])) . 'Mail';
            if (method_exists($this, $methodName)){
                $this->$methodName($object);
            }
        }
    }

    protected function _sendQuotenewquoteMail(Quote_Models_Model_Quote $quote) {
        $recipient = null;
        switch($this->_options['recipient']) {
            case self::RECIPIENT_CUSTOMER:
            case self::RECIPIENT_MEMBER:
                $quoteUserId = $quote->getUserId();
                if($quoteUserId) {
                    $recipient = Application_Model_Mappers_UserMapper::getInstance()->find($quoteUserId);
                    $this->_mailer->setMailToLabel($recipient->getFullName())
                        ->setMailTo($recipient->getEmail());
                }
            break;
            case self::RECIPIENT_STOREOWNER:
                $this->_mailer->setMailToLabel($this->_storeConfig['company'])
                    ->setMailTo($this->_storeConfig['email']);
            break;
            default:
                error_log('Unsupported recipient '.$this->_options['recipient'].' given');
                return false;
            break;
        }

        if (false === ($body = $this->_prepareEmailBody($quote))) {
            return false;
        }

        $this->_entityParser->objectToDictionary($quote);
        $this->_mailer->setBody($this->_entityParser->parse($body));
        $this->_mailer->setMailFrom((!isset($this->_options['from']) || !$this->_options['from']) ? $this->_storeConfig['email'] : $this->_options['from'])
            ->setMailFromLabel($this->_storeConfig['company'])
            ->setSubject(isset($mailActionTrigger['subject']) ? $mailActionTrigger['subject'] : 'New quote is generated for you!');
        return ($this->_mailer->send() !== false);
    }

    /**
     * Sending updated quote email
     *
     * For now supports only customer and store owner recipients
     *
     * @param Quote_Models_Model_Quote $quote
     * @return boolean
     */
    protected function _sendQuoteupdatedMail(Quote_Models_Model_Quote $quote) {
        $recipient = null;
        switch($this->_options['recipient']) {
            case self::RECIPIENT_CUSTOMER:
                $recipient = Application_Model_Mappers_UserMapper::getInstance()->find($quote->getUserId());

                //send updated quote to the shipping and billing e-mails
                $cart = Models_Mapper_CartSessionMapper::getInstance()->find($quote->getCartId());
                if(!$cart instanceof Models_Model_CartSession) {
                    if(Tools_System_Tools::debugMode()) {
                        error_log('Cannot find cart with id: ' . $quote->getCartId() . ' for the quote with id: ' . $quote->getId());
                    }
                    return;
                }

                $shippingAddress = Tools_ShoppingCart::getAddressById($cart->getShippingAddressId());
                $billingAddress  = Tools_ShoppingCart::getAddressById($cart->getBillingAddressId());

                $this->_mailer->setMailToLabel($recipient->getFullName());

                $recipientEmails = array();

                if($billingAddress && isset($billingAddress['email'])) {
                    if(isset($billingAddress['firstname']) && isset($billingAddress['lastname'])) {
                        $fullName = $billingAddress['firstname'] . ' ' . $billingAddress['lastname'];
                    }
                    $recipientEmails[][(isset($fullName) ? $fullName : $recipient->getFullName())] = $billingAddress['email'];
                }
                if($shippingAddress && isset($shippingAddress['email'])) {
                    if(isset($shippingAddress['firstname']) && isset($shippingAddress['lastname'])) {
                        $fullName = $shippingAddress['firstname'] . ' ' . $shippingAddress['lastname'];
                    }
                    $recipientEmails[][(isset($fullName) ? $fullName : $recipient->getFullName())] = $shippingAddress['email'];
                }

                $this->_mailer->setMailTo($recipientEmails);
            break;
            case self::RECIPIENT_STOREOWNER:
                $this->_mailer->setMailToLabel($this->_storeConfig['company'])
                    ->setMailTo($this->_storeConfig['email']);
            break;
        }

        if (false === ($body = $this->_prepareEmailBody($quote))) {
            return false;
        }

        $this->_entityParser->objectToDictionary($quote);
        $this->_mailer->setBody($this->_entityParser->parse($body));
        $this->_mailer->setMailFrom((!isset($this->_options['from']) || !$this->_options['from']) ? $this->_storeConfig['email'] : $this->_options['from'])
            ->setMailFromLabel($this->_storeConfig['company'])
            ->setSubject(isset($mailActionTrigger['subject']) ? $mailActionTrigger['subject'] : 'Quote has been updated');
        return $this->_mailer->send();
    }

    protected function _prepareEmailBody(Quote_Models_Model_Quote $quote) {
        $tmplName     = $this->_options['template'];
        $tmplMessage  = $this->_options['message'];
        $mailTemplate = Application_Model_Mappers_TemplateMapper::getInstance()->find($tmplName);

        if (!empty($mailTemplate)){
            $this->_entityParser->setDictionary(array(
                'emailmessage' => !empty($tmplMessage) ? $tmplMessage : ''
            ));
            //pushing message template to email template and cleaning dictionary
            $mailTemplate = $this->_entityParser->parse($mailTemplate->getContent());
            $this->_entityParser->setDictionary(array());

            $mailTemplate = $this->_entityParser->parse($mailTemplate);

            $themeData = Zend_Registry::get('theme');
            $extConfig = Zend_Registry::get('extConfig');
            $parserOptions = array(
                'websiteUrl'   => $this->_websiteHelper->getUrl(),
                'websitePath'  => $this->_websiteHelper->getPath(),
                'currentTheme' => $extConfig['currentTheme'],
                'themePath'    => $themeData['path'],
            );
            $parser = new Tools_Content_Parser($mailTemplate, Application_Model_Mappers_PageMapper::getInstance()->findByUrl($quote->getId() . '.html')->toArray(), $parserOptions);
            return $parser->parseSimple();
        }

        return false;
    }
}
