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

    const UPDATE_TYPE_PRICE   = 'price';

    protected $_debugMode     = false;

    protected $_accessList    = array(
        Tools_Security_Acl::ROLE_SUPERADMIN => array('allow' => array('get', 'post', 'put', 'delete')),
        Tools_Security_Acl::ROLE_ADMIN      => array('allow' => array('get', 'post', 'put', 'delete')),
        Shopping::ROLE_SALESPERSON          => array('allow' => array('get', 'post', 'put', 'delete'))
    );

    /**
     * Instance of the Quote_Models_Mapper_QuoteMapper. Using to invoke quote's cart storage
     *
     * @var Quote_Models_Mapper_QuoteMapper
     */
    protected $_mapper = null;

    public function init() {
        $this->_debugMode = Tools_System_Tools::debugMode();
        $this->_mapper    = Quote_Models_Mapper_QuoteMapper::getInstance();
        $this->_shoppingConfig = Models_Mapper_ShoppingConfig::getInstance()->getConfigParams();
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
            $cartStorage->add($product, Quote_Tools_Tools::getProductOptions($product));
        }
        
        $cartStorage->setShippingData(array('price'=>$cart->getShippingPrice()));
        $cartStorage->saveCartSession();
        return $quoteMapper->save($quote)->toArray();
    }

    public function putAction() {
        $productId   = filter_var($this->_request->getParam('id'), FILTER_SANITIZE_NUMBER_INT);
        $data        = Zend_Json::decode($this->_request->getRawBody());

        if(!isset($data['type'])) {
            $this->_error();
        }

        if(!isset($data['value']) || !$data['value']) {
            $this->_error('Cannot perform an update');
        }

        $storage     = $this->_invokeQuoteStorage($data['qid']);
        $cartContent = $storage->getContent();
        $itemData    = null;

        foreach($cartContent as $key => $item) {
            if($item['product_id'] != $productId) {
                continue;
            }
            $itemData = $item;
            unset($cartContent[$key]);
        }

        $product   = Models_Mapper_ProductMapper::getInstance()->find($itemData['product_id']);
        $basePrice = $product->getCurrentPrice();
        $basePrice = ($basePrice === null) ? $product->getPrice() : $basePrice;
        $options   = Quote_Tools_Tools::getProductOptions($product);

        $product->setPrice($itemData['price']);

        switch($data['type']) {
            case self::UPDATE_TYPE_QTY     : $itemData['qty'] = $data['value']; break;
            case self::UPDATE_TYPE_OPTIONS :
                $product->setPrice($basePrice);
                $product->setCurrentPrice(floatval($basePrice));
                $options = $this->_parseOptions($data['value']); break;
            case self::UPDATE_TYPE_PRICE   :
                $product->setPrice(floatval($data['value']));
                $product->setCurrentPrice(floatval($data['value']));
                if(isset($this->_shoppingConfig['showPriceIncTax']) && $this->_shoppingConfig['showPriceIncTax'] === '1'){
                    $shippingAddressKey = $storage->getShippingAddressKey();
                    $destinationAddress = Tools_ShoppingCart::getInstance()->getAddressById($shippingAddressKey);
                    $productTax = Quote_Tools_Tools::getTaxFromProductPrice($product, $destinationAddress);
                    $product->setPrice(floatval($data['value'] - $productTax));
                    $product->setCurrentPrice(floatval($data['value'] - $productTax));
                }
                $options = array();
            break;
            default: $this->_error('Invalid update type.'); break;
        }

        $storage->setContent($cartContent);
        $storage->add($product, $options, $itemData['qty']);

        if($data['type'] == self::UPDATE_TYPE_PRICE) {
            $content = $storage->getContent();
            $content[$storage->findSidById($product->getId())]['options'] = Quote_Tools_Tools::getProductOptions($product, $itemData['options']);
            $storage->setContent($content);
        }

        return Quote_Tools_Tools::calculate($storage, false, true, $data['qid']);
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

        $storage     = $this->_invokeQuoteStorage($data['qid']);
        $cartContent = $storage->getContent();

        foreach($ids as $id) {
//            if(sizeof($cartContent) > 1) {
                foreach($cartContent as $key => $cartItem) {
                    if(isset($cartItem['product_id']) && $cartItem['product_id'] == $id) {
                        unset($cartContent[$key]);
                    }
                }
                $storage->setContent($cartContent);
//            } else {
//                $storage->setContent(null);
//            }
        }
        return Quote_Tools_Tools::calculate($storage, false, true, $data['qid']);
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
        $data          = $storage->calculate(true);
        $storage->saveCartSession();
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

    /**
     * Invoke quote's cart session storage
     *
     * @param integer $id quote id
     * @return Tools_ShoppingCart
     */
    protected function _invokeQuoteStorage($id) {
        $quote = $this->_mapper->find($id);
        if(!$quote instanceof Quote_Models_Model_Quote) {
            $this->_error('Quote cannot be found');
        }
        $cart    = Models_Mapper_CartSessionMapper::getInstance()->find($quote->getCartId());
        if(!$cart instanceof Models_Model_CartSession) {
            $this->_error('Requested quote has no cart');
        }
        $storage = Tools_ShoppingCart::getInstance();
        $storage->restoreCartSession($quote->getCartId());
        $storage->setCustomerId($quote->getUserId())
            ->setAddressKey(Models_Model_Customer::ADDRESS_TYPE_BILLING, $cart->getBillingAddressId())
            ->setAddressKey(Models_Model_Customer::ADDRESS_TYPE_SHIPPING, $cart->getShippingAddressId());
        return $storage;
    }

    /**
     * Overriding base error method to add error message to the error log file if debug mode is on
     *
     * @param null $message
     * @param int $statusCode
     */
    protected function _error($message = null, $statusCode = self::REST_STATUS_BAD_REQUEST) {
        if($this->_debugMode && $message) {
            error_log('Quote products api error:' . $message);
        }
        parent::_error($message, $statusCode);
    }

    private function _parseOptions($options) {
        parse_str($options, $options);
        $parsed = array();
        foreach($options as $keyString => $option) {
            $key          = preg_replace('~product-[0-9]*\-option\-([^0-9*])*~', '$1', $keyString);
            $parsed[$key] = $option;
        }
        return $parsed;
    }
}
