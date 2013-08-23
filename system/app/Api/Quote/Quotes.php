<?php
/**
 * Quote
 *
 * @author: iamne <eugene@seotoaster.com> Seotoaster core team
 * Date: 9/5/12
 * Time: 12:59 PM
 * @TODO : refactor this damn thing
 */
class Api_Quote_Quotes extends Api_Service_Abstract {

    /**
     * Instance of quote mapper
     *
     * @var Quote_Models_Mapper_QuoteMapper
     */
    private $_quoteMapper    = null;

    /**
     * E-commerce preferences
     *
     * @var null| array
     */
    private $_shoppingConfig = null;

    /**
     * Session based cart storage. Storage is able to do all calculation process
     *
     * @var null|Tools_ShoppingCart
     */
    private $_cartStorage    = null;

    /**
     * Access list for the API resources
     *
     * @var array
     */
    protected $_accessList   = array(
        Tools_Security_Acl::ROLE_SUPERADMIN => array('allow' => array('get', 'post', 'put', 'delete')),
        Tools_Security_Acl::ROLE_ADMIN      => array('allow' => array('get', 'post', 'put', 'delete')),
        Tools_Security_Acl::ROLE_GUEST      => array('allow' => array('get', 'post'))
    );

    /**
     * Initialization
     *
     */
    public function init() {
        $this->_quoteMapper    = Quote_Models_Mapper_QuoteMapper::getInstance();
        $this->_shoppingConfig = Models_Mapper_ShoppingConfig::getInstance()->getConfigParams();
        $this->_cartStorage    = Tools_ShoppingCart::getInstance();
    }

    public function getAction() {
        $quoteId = filter_var($this->_request->getParam('id'), FILTER_SANITIZE_STRING);

        if($quoteId) {
            $quote = $this->_quoteMapper->find($quoteId);
            if($quote instanceof Quote_Models_Model_Quote) {
                return $quote->toArray();
            }
            $this->_error(null, self::REST_STATUS_NOT_FOUND);
        }

        //retrieve and validate additional parameters
        $count     = (bool) $this->_request->has('count');
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
                $formOptions = Zend_Controller_Action_HelperBroker::getStaticHelper('session')->formOptions;
                $form        = new Quote_Forms_Quote();
                if($formOptions) {
                    $form = Quote_Tools_Tools::adjustFormFields($form, $formOptions, array('productId' => false, 'productOptions' => false, 'sendQuote' => false));
                }

                if(!$form->isValid($this->_request->getParams())) {
                    $this->_error('Sorry, but you didn\'t feel all the required fields or you entered a wrong captcha. Please try again.');
                }
                $formData = $form->getValues();

                //if we have a product id passed then this is a single product quote request and we should add product to the cart
                $initialProducts = array();
                if(isset($formData['productId']) && $formData['productId']) {
                    $initialProducts[] = array(
                        'product' => Models_Mapper_ProductMapper::getInstance()->find($formData['productId']),
                        'options' => Quote_Tools_Tools::parseOptionsString($formData['productOptions'])
                    );
                }

                $cart     = Quote_Tools_Tools::invokeCart(null, $initialProducts);
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
            $quote = Quote_Tools_Tools::createQuote($cart, array('editedBy' => $editedBy, 'disclaimer' => isset($formData['disclaimer']) ? $formData['disclaimer']: '' ));
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
        $quoteId   = filter_var($quoteData['qid'], FILTER_SANITIZE_STRING);
        if(!$quoteId) {
            $quoteId = filter_var($quoteData['id'], FILTER_SANITIZE_STRING);
        }

        if(!$quoteId) {
            $this->_error('Not enough parameters', self::REST_STATUS_BAD_REQUEST);
        }

        $quote = $this->_quoteMapper->find($quoteId);

        if(!$quote instanceof Quote_Models_Model_Quote) {
            $this->_error('Quote not found', self:: REST_STATUS_NOT_FOUND);
        }

        $customer          = null;
        $cartSessionMapper = Models_Mapper_CartSessionMapper::getInstance();
        $cart              = $cartSessionMapper->find($quote->getCartId());

        if(!$cart instanceof Models_Model_CartSession) {
            $this->_error('Can\'t find cart assosiated with the current quote.', self::REST_STATUS_NO_CONTENT);
        }

        if(isset($quoteData['type']) && $quoteData['type']) {
            $value = floatval($quoteData['value']);
            if(!$value) {
                $value = 0;
            }

            switch($quoteData['type']) {
                case 'shipping': $cart->setShippingPrice($value); break;
                case 'discount': $cart->setDiscount($value); break;
                case 'taxrate' :  $quote->setDiscountTaxRate($value); break;
                case 'delivery': $quote->setDeliveryType($quoteData['value']); break;
                default: $this->_error('Wrong partial option');
            }

            $cartSessionMapper->save($cart);
            $this->_quoteMapper->save($quote);

        } else {
            $quote->setOptions($quoteData);

            // setting up observers
            $quote->registerObserver(new Quote_Tools_Watchdog(array(
                'gateway' => new Quote(array(), array())
            )))
            ->registerObserver(new Quote_Tools_GarbageCollector(array(
                'action' => Tools_System_GarbageCollector::CLEAN_ONUPDATE
            )));

            if($quoteData['sendMail']) {
                $quote->registerObserver(new Tools_Mail_Watchdog(array(
                    'trigger'     => Quote_Tools_QuoteMailWatchdog::TRIGGER_QUOTE_UPDATED,
                    'mailMessage' => $quoteData['mailMessage']
                )));
                $quote->setStatus(Quote_Models_Model_Quote::STATUS_SENT);
            }

            if(isset($quoteData['billing'])) {
                parse_str($quoteData['billing'], $quoteData['billing']);

	            if ($quote->getUserId()){
		            $customer = Models_Mapper_CustomerMapper::getInstance()->find($quote->getUserId());
	            } else {
		            $customer = Shopping::processCustomer($quoteData['billing']);
		            $quote->setUserId($customer->getId());
	            }

	            $cart->setBillingAddressId(
		            Models_Mapper_CustomerMapper::getInstance()->addAddress($customer, $quoteData['billing'], Models_Model_Customer::ADDRESS_TYPE_BILLING)
	            );

            }

            if(isset($quoteData['shipping'])) {
                parse_str($quoteData['shipping'], $quoteData['shipping']);
	            if (!$customer){
                    $customer = Shopping::processCustomer($quoteData['shipping']);
	            }
	            $cart->setShippingAddressId(
		            Models_Mapper_CustomerMapper::getInstance()->addAddress($customer, $quoteData['shipping'], Models_Model_Customer::ADDRESS_TYPE_SHIPPING)
	            );

            }

            if($customer) {
                $cart->setUserId($customer->getId());
                Models_Mapper_CartSessionMapper::getInstance()->save($cart);
            }

            $this->_quoteMapper->save($quote);
        }
	    // @todo: check why it was using 'forceSave' parameter???
        return Quote_Tools_Tools::calculate(Quote_Tools_Tools::invokeQuoteStorage($quoteId), false, true, $quoteId);
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

    protected function _validateAddress($address) {
        if(!is_array($address)) {
            return false;
        }
        $valid = true;
        $excludeFields = array('lastname', 'address2', 'state', 'phone', 'sameForShipping', 'productId', 'productOptions');
        foreach($address as $field => $value) {
            if(in_array($field, $excludeFields)) {
                continue;
            }
            $valid &= (bool)$value;
        }
        return $valid;
    }
}
