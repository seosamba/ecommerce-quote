<?php

class Api_Quote_Partialpayment extends Api_Service_Abstract
{

    protected $_responseHelper = null;

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
        $partialPercentage = filter_var($this->_request->getParam('partialPercentage'), FILTER_SANITIZE_STRING);

        $translator = Zend_Registry::get('Zend_Translate');
        if (empty($quoteId)) {
            $this->_error($translator->translate('Quote id is missing'));
        }

        $quote = $quoteMapper->find($quoteId);
        if (!$quote instanceof Quote_Models_Model_Quote) {
            $this->_error($translator->translate('Quote not found'));
        }

        if (empty($partialPercentage)) {
            $this->_error($translator->translate('Please specify partial payment percentage'));
        }

        $cartId = $quote->getCartId();
        $cart = Models_Mapper_CartSessionMapper::getInstance()->find($cartId);
        if (empty($cart->getIsPartial())) {
            $this->_error($translator->translate('Wrong quote payment type'));
        }

        $partialPaidAmount = $cart->getPartialPaidAmount();
        $cart->setIsPartial('1');
        if (!empty($partialPaidAmount) && $partialPaidAmount !== '0.00' && !empty((int)$partialPaidAmount)) {
            $cart->setPartialPaidAmount($cart->getTotal());
            $amountToPayPartial = round($cart->getTotal() - round(($cart->getTotal() * $cart->getPartialPercentage()) / 100,
                    2), 2);
            $updatePaymentStatus = Models_Model_CartSession::CART_STATUS_COMPLETED;
        } else {
            $amountToPayPartial = round(($cart->getTotal() * $cart->getPartialPercentage()) / 100, 2);
            $cart->setPartialPaidAmount($amountToPayPartial);
            $updatePaymentStatus = Models_Model_CartSession::CART_STATUS_PARTIAL;
        }


        if ($updatePaymentStatus !== Models_Model_CartSession::CART_STATUS_PARTIAL) {
            $this->_error($translator->translate('Wrong payment type'));
        }

        $cartSession = Models_Mapper_CartSessionMapper::getInstance()->find($cartId);
        $cartSession->registerObserver(new Tools_Mail_Watchdog(array(
            'trigger' => Tools_StoreMailWatchdog::TRIGGER_STORE_PARTIALPAYMENT
        )));

        $currency = Zend_Registry::get('Zend_Currency');
        $message = $currency->toCurrency($amountToPayPartial);

        $paymentStatus = $updatePaymentStatus;
        $cart->setPurchasedOn(date(Tools_System_Tools::DATE_MYSQL));
        $cart->setPartialPurchasedOn(date(Tools_System_Tools::DATE_MYSQL));
        $cart->setStatus($paymentStatus);
        Models_Mapper_CartSessionMapper::getInstance()->save($cart);
        $sessionHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('session');

        $sessionHelper->storeCartSessionKey = $cartId;
        $sessionHelper->storeCartSessionConversionKey = $cartId;

        $cartSession->notifyObservers();

        $this->_responseHelper->success(array(
            'error' => 0,
            'generalSuccess' => $translator->translate('Thank you for your payment of ') . $message
        ));
    }

    public function putAction()
    {
    }

    public function deleteAction()
    {
    }
}
