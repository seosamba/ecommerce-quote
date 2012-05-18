<?php
/**
 * Quote mail watchdog
 *
 * @author: iamne <eugene@seotoaster.com> Seotoaster core team
 * Date: 5/15/12
 * Time: 2:14 PM
 */
class Quote_Tools_QuoteMailWatchdog implements Interfaces_Observer {

    const TRIGGER_NEW_QUOTE         = 'new quote';

    const TRIGGER_QUOTE_STATUS_SOLD = 'quote status sold';

    const RECIPIENT_SALESPERSON     = 'sales person';

    const RECIPIENT_CUSTOMER        = 'customer';

    const RECIPIENT_STOREOWNER      = 'store owner';

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
        $this->_initMailer();
        $this->_initMailMessage();
    }

    protected function _initMailMessage() {
        $this->_options['message'] = (isset($this->_options['mailMessage']) ? $this->_options['mailMessage'] : $this->_options['message']);
        unset($this->_options['mailMessage']);
    }

    protected function _initMailer(){
        $config        = $this->_configHelper->getConfig();
        $this->_mailer = new Tools_Mail_Mailer();

        if ((bool)$config['useSmtp']){
            $smtpConfig = array(
                'host'      => $config['smtpHost'],
                'username'  => $config['smtpLogin'],
                'password'  => $config['smtpPassword']
            );
            if ((bool)$config['smtpSsl']){
                $smtpConfig['ssl'] = $config['smtpSsl'];
            }
            if (!empty($config['smtpPort'])){
                $smtpConfig['port'] = $config['smtpPort'];
            }
            $this->_mailer->setSmtpConfig($smtpConfig);
            $this->_mailer->setTransport(Tools_Mail_Mailer::MAIL_TYPE_SMTP);
        } else {
            $this->_mailer->setTransport(Tools_Mail_Mailer::MAIL_TYPE_MAIL);
        }
    }

    public function notify($object) {
        if (!$object){
            return false;
        }
        if (isset($this->_options['trigger'])){
            $methodName = '_send'. str_replace(' ', '', ucwords($this->_options['trigger'])) . 'Mail';
            if (method_exists($this, $methodName)){
                $this->$methodName($object);
            }
        }
    }

    protected function _sendNewQuoteMail(Quote_Models_Model_Quote $quote) {
        $recipient = null;
        switch($this->_options['recipient']) {
            case self::RECIPIENT_CUSTOMER:
                $recipient = Application_Model_Mappers_UserMapper::getInstance()->find($quote->getUserId());
                $this->_mailer->setMailToLabel($recipient->getFullName())
                    ->setMailTo($recipient->getEmail());
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
        $this->_mailer->setMailFrom(!empty($this->_storeConfig['email']) ? $this->_storeConfig['email'] : 'admin@localhost')
            ->setMailFromLabel($this->_storeConfig['company']);
        return ($this->_mailer->send() !== false);
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
