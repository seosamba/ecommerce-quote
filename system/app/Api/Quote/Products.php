<?php
/**
 * Products
 *
 * @author: iamne <eugene@seotoaster.com> Seotoaster core team
 * Date: 9/6/12
 * Time: 5:45 PM
 */
class Api_Quote_Products extends Api_Service_Abstract {

    const UPDATE_TYPE_QTY     = 'qty';

    const UPDATE_TYPE_OPTIONS = 'options';

    protected $_accessList  = array(
        Tools_Security_Acl::ROLE_SUPERADMIN => array('allow' => array('get', 'post', 'put', 'delete'))
    );

    protected $_mapper = null;

    public function init() {
        $this->_mapper = Quote_Models_Mapper_QuoteMapper::getInstance();
    }

    public function getAction() {}

    public function postAction() {
        $quoteMapper = Quote_Models_Mapper_QuoteMapper::getInstance();
        $ids         = array_filter(filter_var_array(explode(',', $this->_request->getParam('id')), FILTER_SANITIZE_NUMBER_INT));
        $data        = Zend_Json::decode($this->_request->getRawBody());

        if(!$ids) {
            $this->_error();
        }

        $products = array();
        $product  = Models_Mapper_ProductMapper::getInstance()->find($ids);
        if(!is_array($product)) {
            $products[] = $product;
        } else {
            $products = $product;
        }

        $quote       = $quoteMapper->find($data['qid']);
        $cartStorage = Tools_ShoppingCart::getInstance();
        $cart        = Models_Mapper_CartSessionMapper::getInstance()->find($quote->getCartId());
        $cartStorage->restoreCartSession($quote->getCartId());
        $cartStorage->setCustomerId($quote->getUserId())
            ->setAddressKey(Models_Model_Customer::ADDRESS_TYPE_BILLING, $cart->getBillingAddressId())
            ->setAddressKey(Models_Model_Customer::ADDRESS_TYPE_SHIPPING, $cart->getShippingAddressId());

        foreach($products as $product)  {
            $cartStorage->add($product, Quote_Tools_Tools::getProductDefaultOptions($product));
        }

        $cartStorage->saveCartSession();
        return $quoteMapper->save($quote)->toArray();
    }

    public function putAction() {
        $productId   = filter_var($this->_request->getParam('id'), FILTER_SANITIZE_NUMBER_INT);
        $updateType  = filter_var($this->_request->getParam('type'), FILTER_SANITIZE_STRING);
        $updateData  = Zend_Json::decode($this->_request->getRawBody());
        $cartStorage = Tools_ShoppingCart::getInstance();
        $result      = null;

        switch($updateType) {
            case self::UPDATE_TYPE_QTY:
                if(!isset($updateData['qty'])) {
                    $this->_error('Cannot update product quantity');
                }
                if(!isset($updateData['qid'])) {
                    $this->_error('Cannot find the quote.', self::REST_STATUS_NOT_FOUND);
                }

                $quote  = $this->_mapper->find($updateData['qid']);

                if(!$quote instanceof Quote_Models_Model_Quote) {
                    $this->_error('Cannot find quote', self::REST_STATUS_NOT_FOUND);
                }

                $cart = Models_Mapper_CartSessionMapper::getInstance()->find($quote->getCartId());
                $cartStorage->restoreCartSession($quote->getCartId());
                $cartStorage->setCustomerId($quote->getUserId())
                    ->setAddressKey(Models_Model_Customer::ADDRESS_TYPE_BILLING, $cart->getBillingAddressId())
                    ->setAddressKey(Models_Model_Customer::ADDRESS_TYPE_SHIPPING, $cart->getShippingAddressId());

                $cartContent = $cartStorage->getContent();

                $tmpItem = array();
                foreach($cartContent as $key => $cartItem) {
                    if($cartItem['product_id'] == $productId) {
                        $tmpItem = $cartItem;
                        unset($cartContent[$key]);
                        break;
                    }

                    //$cartContent[$key]['qty'] = $updateData['qty'];
                }
                $cartStorage->setContent($cartContent);
                $cartStorage->add(Models_Mapper_ProductMapper::getInstance()->find($tmpItem['product_id']), $tmpItem['options'], $updateData['qty']);
                $cartStorage->saveCartSession();


                return $this->_sendResponse($cartStorage);
            break;
            case self::UPDATE_TYPE_OPTIONS:
                if(isset($updateData['options'])) {
                    $updateData['options'] = $this->_parseOptions($updateData['options']);
                }

                $quote  = $this->_mapper->find($updateData['qid']);
                if(!$quote instanceof Quote_Models_Model_Quote) {
                    $this->_error('Cannot find quote', self::REST_STATUS_NOT_FOUND);
                }
                $cart = Models_Mapper_CartSessionMapper::getInstance()->find($quote->getCartId());

                $storage = Tools_ShoppingCart::getInstance();
                $storage->restoreCartSession($quote->getCartId());
                $storage->setCustomerId($quote->getUserId())
                    ->setAddressKey(Models_Model_Customer::ADDRESS_TYPE_BILLING, $cart->getBillingAddressId())
                    ->setAddressKey(Models_Model_Customer::ADDRESS_TYPE_SHIPPING, $cart->getShippingAddressId());

                $cartContent = $storage->getContent();
                foreach($cartContent as $key => $cartItem) {
                    if($cartItem['product_id'] == $productId) {
                        //$cartContent[$key]['options'] = $updateData['options'];
                        unset($cartContent[$key]);
                        break;
                    }
                }

                $storage->setContent($cartContent);

                $storage->add(Models_Mapper_ProductMapper::getInstance()->find($productId), $updateData['options']);

                //Models_Mapper_CartSessionMapper::getInstance()->save($cart->setCartContent($cartContent));
                $storage->saveCartSession();
                return $this->_sendResponse($cartStorage);
            break;
        }
        return (array)$result;
    }

    public function deleteAction() {
        $ids  = array_filter(filter_var_array(explode(',', $this->_request->getParam('id')), FILTER_VALIDATE_INT));
        $data = Zend_Json::decode($this->_request->getRawBody());

        if(empty($ids)) {
            $this->_error();
        }

        if(!isset($data['qid'])) {
            $this->_error('Cannot find a quote', self::REST_STATUS_NOT_FOUND);
        }

        $quote       = Quote_Models_Mapper_QuoteMapper::getInstance()->find($data['qid']);
        $cartStorage = Tools_ShoppingCart::getInstance();
        $cart        = Models_Mapper_CartSessionMapper::getInstance()->find($quote->getCartId());

        $cartStorage->restoreCartSession($quote->getCartId());
        $cartStorage->setCustomerId($quote->getUserId())
            ->setAddressKey(Models_Model_Customer::ADDRESS_TYPE_BILLING, $cart->getBillingAddressId())
            ->setAddressKey(Models_Model_Customer::ADDRESS_TYPE_SHIPPING, $cart->getShippingAddressId());

        $cartStorage->restoreCartSession($quote->getCartId());
        $cartStorage->setCustomerId($quote->getUserId());

        $cartContent = $cartStorage->getContent();

        foreach($ids as $id) {
            if(sizeof($cartContent) > 1) {
                foreach($cartContent as $key => $cartItem) {
                    if(isset($cartItem['product_id']) && $cartItem['product_id'] == $id) {
                        unset($cartContent[$key]);
                    }
                }
                $cartStorage->setContent($cartContent);
            } else {
                $cartStorage->setContent(null);
            }
        }
        $cartStorage->saveCartSession();
        return $this->_sendResponse($cartStorage);
    }

    /**
     *
     *
     * @param $storage
     * @param bool $currency
     * @return mixed
     */
    protected function _sendResponse($storage, $currency = true) {
        $cart          = Models_Mapper_CartSessionMapper::getInstance()->find($storage->getCartId());
        $shippingPrice = $cart->getShippingPrice();
        $data          = $storage->calculate();
        unset($data['showPriceIncTax']);
        $data['total'] += $shippingPrice;
        if(!$currency) {
            return $data;
        }
        $currency = Zend_Registry::get('Zend_Currency');
        foreach($data as $key => $value) {
            $data[$key] = $currency->toCurrency($value);
        }
        return $data;
    }

    private function _parseOptions($options) {
        parse_str($options, $options);
        $parsed = array();
        foreach($options as $keyString => $option) {
            $key          = preg_replace('~product-[0-9]*\-option\-([0-9])*~', '$1', $keyString);
            $parsed[$key] = $option;
        }
        return $parsed;
    }
}
