<?php

class Api_Quote_Signature extends Api_Service_Abstract
{

    protected $_accessList = array(
        Tools_Security_Acl::ROLE_SUPERADMIN => array('allow' => array('get', 'post', 'put', 'delete')),
        Tools_Security_Acl::ROLE_ADMIN => array('allow' => array('get', 'post', 'put', 'delete')),
        Shopping::ROLE_SALESPERSON => array('allow' => array('get', 'post', 'put', 'delete')),
        Tools_Security_Acl::ROLE_GUEST => array('allow' => array('get', 'post', 'put', 'delete')),
        Tools_Security_Acl::ROLE_MEMBER => array('allow' => array('get', 'post', 'put', 'delete')),
        Tools_Security_Acl::ROLE_USER => array('allow' => array('get', 'post', 'put', 'delete'))
    );

    public function init()
    {
        parent::init();
        $this->_responseHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('response');
    }

    public function getAction()
    {
    }

    public function postAction()
    {
        $quoteMapper = Quote_Models_Mapper_QuoteMapper::getInstance();
        $quoteId = filter_var($this->_request->getParam('quoteId'), FILTER_SANITIZE_STRING);
        $signature = filter_var($this->_request->getParam('signature'), FILTER_SANITIZE_STRING);

        $translator = Zend_Registry::get('Zend_Translate');
        if (empty($quoteId)) {
            $this->_error($translator->translate('Quote id is missing'));
        }

        $quote = $quoteMapper->find($quoteId);
        if (!$quote instanceof Quote_Models_Model_Quote) {
            $this->_error($translator->translate('Quote not found'));
        }

        $isQuoteSigned = $quote->getIsQuoteSigned();
        if (!empty($isQuoteSigned)) {
            $this->_error($translator->translate('This quote already signed'));
        }

        $quote->setIsQuoteSigned('1');
        $quote->setSignature($signature);
        $quote->setQuoteSignedAt(Tools_System_Tools::convertDateFromTimezone('now'));

        if ($quote->getPaymentType() === Quote_Models_Model_Quote::PAYMENT_TYPE_ONLY_SIGNATURE) {
            $quote->setStatus(Quote_Models_Model_Quote::STATUS_SOLD);
            $quote->registerObserver(new Quote_Tools_Watchdog(array(
                'gateway' => new Quote(array(), array())
            )))->registerObserver(new Quote_Tools_GarbageCollector(array(
                'action' => Tools_System_GarbageCollector::CLEAN_ONUPDATE
            )));
        }
        $quoteMapper->save($quote);

        $this->_responseHelper->success($translator->translate('Quote has been signed'));

    }

    public function putAction()
    {
    }

    public function deleteAction()
    {
    }
}
