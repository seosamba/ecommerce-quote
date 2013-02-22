<?php
/**
 * Quote
 * @author: iamne <eugene@seotoaster.com> Seotoaster core team
 * Date: 9/5/12
 * Time: 12:59 PM
 */
class Api_Quote_Quotes extends Api_Service_Abstract {

    private $_quoteMapper    = null;

    private $_shoppingConfig = null;

    protected $_accessList  = array(
        Tools_Security_Acl::ROLE_SUPERADMIN => array('allow' => array('get', 'post', 'put', 'delete')),
        Tools_Security_Acl::ROLE_GUEST      => array('allow' => array('get', 'post'))
    );

    public function init() {
        $this->_quoteMapper    = Quote_Models_Mapper_QuoteMapper::getInstance();
        $this->_shoppingConfig = Models_Mapper_ShoppingConfig::getInstance()->getConfigParams();
    }

    public function getAction() {
        $quoteId = filter_var($this->_request->getParam('id'), FILTER_SANITIZE_STRING);
        $count   = (bool) $this->_request->has('count');
        if($quoteId) {
            $quote = $this->_quoteMapper->find($quoteId);
            if($quote instanceof Quote_Models_Model_Quote) {
                return $quote->toArray();
            }
            $this->_error(null, self::REST_STATUS_NOT_FOUND);
        }
        //retrieve and validate additional parameters
        $offset    = filter_var($this->_request->getParam('offset'), FILTER_SANITIZE_NUMBER_INT);
        $limit     = filter_var($this->_request->getParam('limit'), FILTER_SANITIZE_NUMBER_INT);
        $order     = filter_var($this->_request->getParam('order', 'created_at'), FILTER_SANITIZE_STRING);
        $orderType = filter_var($this->_request->getParam('orderType', 'desc'), FILTER_SANITIZE_STRING);
        $search    = filter_var($this->_request->getParam('search'), FILTER_SANITIZE_STRING);
        $quotes    = $this->_quoteMapper->fetchAll(
            null,
            ($order)  ? array($order . ' ' . strtoupper($orderType)) : array(),
            ($limit)  ? $limit : null,
            ($offset) ? $offset : null,
            ($search) ? $search : null,
            ($count)  ? $count : null
        );
        if($count) {
            return $quotes;
        }
        return array_map(function($quote) {return $quote->toArray();}, $quotes);
    }

    public function postAction() {
        $type          = filter_var($this->_request->getParam('type'), FILTER_SANITIZE_STRING);
        $cart          = null;
        $cartMapper    = Models_Mapper_CartSessionMapper::getInstance();
        $editedBy      = '';
        switch($type) {
            case Quote::QUOTE_TYPE_GENERATE:
                $form = new Quote_Forms_Quote();
                if(!$form->isValid($this->_request->getParams())) {
                    $this->_error('Parameters are invalid');
                }
                $formData = $form->getValues();
                $cart     = Quote_Tools_Tools::invokeCart();
                $customer = Shopping::processCustomer($formData);
                if(!$cart) {
                    $this->_error('Server encountered a problem. Unable to create quote');
                }
                $cart = $cartMapper->save(
                    $cart->setBillingAddressId(Quote_Tools_Tools::addAddress($form->getValues(), Models_Model_Customer::ADDRESS_TYPE_BILLING, $customer))
                        ->setUserId($customer->getId())
                );
                if(isset($this->_shoppingConfig['autoQuote']) && $this->_shoppingConfig['autoQuote']) {
                    $editedBy = Quote_Models_Model_Quote::QUOTE_TYPE_AUTO;
                }
            break;
            case Quote::QUOTE_TYPE_BUILD:
                $cart = $cartMapper->save(new Models_Model_CartSession());
            break;
            default:
                $this->_error();
            break;
        }
        try {
            $quote = Quote_Tools_Tools::createQuote($cart, array('editedBy' => $editedBy));
        } catch (Exception $e) {
            $this->_error($e->getMessage());
        }

        if($quote instanceof Quote_Models_Model_Quote) {
            return $quote->toArray();
        }
        $this->_error();
    }

    public function putAction() {
        $quoteData = Zend_Json::decode($this->_request->getRawBody());
        if(is_array($quoteData)) {
            $customer = null;
            $quote    = $this->_quoteMapper->find($quoteData['id']);
            if($quote->getId()) {
                $quote->setOptions($quoteData);

                $cart = Quote_Tools_Tools::invokeCart($quote);

                $quote->registerObserver(new Quote_Tools_Watchdog(array(
                    'gateway' => new Tools_PaymentGateway(array(), array())
                )))
                ->registerObserver(new Quote_Tools_GarbageCollector(array(
                    'action' => Tools_System_GarbageCollector::CLEAN_ONUPDATE
                )));

                if($quoteData['sendMail']) {
                    $quote->registerObserver(new Tools_Mail_Watchdog(array(
                        'trigger' => Quote_Tools_QuoteMailWatchdog::TRIGGER_NEW_QUOTE
                    )));
                }

                if(isset($quoteData['billing']) && !empty($quoteData['billing'])) {
                    parse_str($quoteData['billing'], $quoteData['billing']);
                    $customer = Shopping::processCustomer($quoteData['billing']);
                    $cart->setBillingAddressId(Quote_Tools_Tools::addAddress($quoteData['billing'], Models_Model_Customer::ADDRESS_TYPE_BILLING, $customer));
                    $quote->setUserId($customer->getId())
                        ->setCartId($cart->getId());
                }

                if(isset($quoteData['shipping']) && !empty($quoteData['shipping'])) {
                    parse_str($quoteData['shipping'], $quoteData['shipping']);
                    $cart->setShippingAddressId(Quote_Tools_Tools::addAddress($quoteData['shipping'], Models_Model_Customer::ADDRESS_TYPE_SHIPPING));
                }
                if($customer) {
                    Models_Mapper_CartSessionMapper::getInstance()->save($cart->setUserId($customer->getId()));
                }
                $this->_quoteMapper->save($quote);
            }
        }
    }

    public function deleteAction() {
        $ids = array_filter(filter_var_array(explode(',', $this->_request->getParam('id')), FILTER_SANITIZE_STRING));
        if(empty($ids)) {
            $this->_error();
        }
        $quotes = $this->_quoteMapper->fetchAll('`id` IN (' . join(', ', array_map(function($id) {return "'" . $id . "'"; }, $ids)) . ')');
        if($quotes) {
            $result = array();
            if(is_array($quotes)) {
                foreach($quotes as $quote) {
                    $result[$quote->getId()] = $this->_quoteMapper->delete($quote);
                }
            } else {
                $result[$quotes->getId()] = $this->_quoteMapper->delete($quotes);
            }
            if(!empty($result) && in_array(false, $result)) {
                $this->_error($result);
            }
            return $result;
        }
        $this->_error('Quote not found', self::REST_STATUS_NOT_FOUND);
    }
}
