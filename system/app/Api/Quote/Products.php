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
            $product->setProductDiscounts(array());
            $cartStorage->setDisableProductDiscounts(true);
            // Skip group price modifiers for quotes.
            $product->setCurrentPrice(floatval($product->getPrice()));
            $cartStorage->add($product, Quote_Tools_Tools::getProductOptions($product), 1, true, false, true);
        }

        $customer = Models_Mapper_CustomerMapper::getInstance()->find($cartStorage->getCustomerId());
        if ($customer === null) {
            $customer = new Models_Model_Customer();
        }

        $cartStorage->setShippingData(array('price'=>$cart->getShippingPrice()));
        $cartStorage->saveCartSession($customer);

        $quoteMapper->save($quote)->toArray();

        $shoppingConfig = Models_Mapper_ShoppingConfig::getInstance()->getConfigParams();
        $quoteDraggableProducts = $shoppingConfig['quoteDraggableProducts'];

        if(!empty($quoteDraggableProducts)) {
            $cart        = Models_Mapper_CartSessionMapper::getInstance()->find($quote->getCartId());
            $quoteDraggableMapper = Quote_Models_Mapper_QuoteDraggableMapper::getInstance();

            $quoteDraggableModel = $quoteDraggableMapper->findByQuoteId($data['qid']);

            $cartContent = $cart->getCartContent();

            $prepareContentSids = array();
            foreach ($cartContent as $key => $content) {
                $product = Models_Mapper_ProductMapper::getInstance()->find($content['product_id']);
                $options = ($content['options']) ? $content['options'] : Quote_Tools_Tools::getProductDefaultOptions($product);
                $prodSid = Quote_Tools_Tools::generateStorageKey($product, $options);
                $prepareContentSids[] = $prodSid;
            }


            if($quoteDraggableModel instanceof Quote_Models_Model_QuoteDraggableModel) {
                $savedGragSids = explode(',', $quoteDraggableModel->getData());
                $data = array_unique(array_merge($savedGragSids, $prepareContentSids));
                $quoteDraggableModel->setData(implode(',', $data));
            } else {
                $quoteDraggableModel = new Quote_Models_Model_QuoteDraggableModel();
                $quoteDraggableModel->setQuoteId($data['qid']);
                $quoteDraggableModel->setData(implode(',', $prepareContentSids));
            }

            $quoteDraggableMapper->save($quoteDraggableModel);
        }

        return;
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
        $cartContentData = array();

        $priceWithoutOptionsTotal = 0;
        $shoppingConfigMapper =  Models_Mapper_ShoppingConfig::getInstance();
        $isTaxable = false;
        $showPriceIncTax = $shoppingConfigMapper->getConfigParam('showPriceIncTax');
        if (!empty($showPriceIncTax)) {
            $isTaxable = true;
        }

        foreach($cartContent as $key => $item) {
            $product = Models_Mapper_ProductMapper::getInstance()->find($item['product_id']);
            $options = ($item['options']) ? $item['options'] : Quote_Tools_Tools::getProductDefaultOptions($product);
            $sid = Quote_Tools_Tools::generateStorageKey($item, $options);

            if($item['product_id'] != $productId || $sid != $data['sid']) {
                $cartContentData[$sid] = $cartContent[$key];
                continue;
            }
            $itemData = $item;
            unset($cartContent[$key]);
        }

        $cartContent = $cartContentData;

        $product   = Models_Mapper_ProductMapper::getInstance()->find($itemData['product_id']);
        $options   = !empty($itemData['options']) ? $itemData['options'] : [];
        $skipOptionRecalculation = true;
        $skipGroupPriceRecalculation = true;

        if ($data['type'] === self::UPDATE_TYPE_QTY) {
            //Calculate price without product options
            $productPrice = $product->getCurrentPrice();
            if (!empty($itemData['qty'])) {
                if (($taxClass = $product->getTaxClass()) != 0 && $isTaxable === true) {
                    $rateMethodName = 'getRate' . $taxClass;

                    $tax = Models_Mapper_Tax::getInstance()->getDefaultRule();

                    if (isset($tax) && $tax !== null) {
                        $productPrice = is_null($product->getCurrentPrice()) ? $product->getPrice() : $product->getCurrentPrice();
                        $itemTax = ($productPrice / 100) * $tax->$rateMethodName();
                    }
                }

                if (!empty($itemTax)) {
                    $priceWithoutOptionsTotal += ($productPrice + $itemTax) * $data['value'];
                }
            }
        }

        switch($data['type']) {
            case self::UPDATE_TYPE_QTY     :
                $product->setPrice(floatval($itemData['price']));
                $product->setCurrentPrice(floatval($product->getPrice()));
                $itemData['qty'] = $data['value'];
                break;
            case self::UPDATE_TYPE_OPTIONS :
                $product->setCurrentPrice(floatval($product->getPrice()));
                $options = $this->_parseOptions($data['value']);
                $skipOptionRecalculation = false;
                break;
            case self::UPDATE_TYPE_PRICE   :
                $product->setPrice(floatval($data['value']));
                $product->setCurrentPrice(floatval($data['value']));
                if(isset($this->_shoppingConfig['showPriceIncTax']) && $this->_shoppingConfig['showPriceIncTax'] === '1'){
                    $shippingAddressKey = $storage->getShippingAddressKey();
                    $destinationAddress = Tools_ShoppingCart::getInstance()->getAddressById($shippingAddressKey);
                    $productTax = Quote_Tools_Tools::getTaxFromProductPrice($product, $destinationAddress);
                    $product->setPrice(floatval($data['value'] - $productTax));
                    $product->setCurrentPrice(floatval($data['value'] - $productTax));
                }break;
            default: $this->_error('Invalid update type.'); break;
        }

        $product->setProductDiscounts(array());
        $storage->setContent($cartContent);
        $storage->setDisableProductDiscounts(true);
        $storage->add($product, $options, $itemData['qty'], true, $skipOptionRecalculation, $skipGroupPriceRecalculation);

        if($data['type'] == self::UPDATE_TYPE_PRICE || $data['type'] == self::UPDATE_TYPE_QTY) {
            $content = $storage->getContent();
            $content[$data['sid']]['options'] = $itemData['options'];
            //$content[$storage->findSidById($product->getId())]['options'] = Quote_Tools_Tools::getProductOptions($product, $itemData['options']);
            $storage->setContent($content);
        }

        $cartSummaryData = Quote_Tools_Tools::calculate($storage, false, true, $data['qid'], $skipGroupPriceRecalculation);

        if (!empty($priceWithoutOptionsTotal)) {
            $cartSummaryData['priceWithoutOptionsTotal'] = $priceWithoutOptionsTotal;
        } else {
            $cartSummaryData['priceWithoutOptionsTotal'] = $cartSummaryData['subTotal'];
        }


        return $cartSummaryData;
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
            $productSids = array();
            foreach($cartContent as $key => $cartItem) {
                $product = Models_Mapper_ProductMapper::getInstance()->find($cartItem['product_id']);
                $options = ($cartItem['options']) ? $cartItem['options'] : Quote_Tools_Tools::getProductDefaultOptions($product);
                $sid = Quote_Tools_Tools::generateStorageKey($product, $options);

                if(isset($cartItem['product_id']) && $cartItem['product_id'] == $id && $data['sid'] ==  $sid) {
                    unset($cartContent[$key]);
                }

                $productSids[$cartItem['product_id']] = $sid;
            }
            $storage->setContent($cartContent);

            $shoppingConfig = Models_Mapper_ShoppingConfig::getInstance()->getConfigParams();
            $quoteDraggableProducts = $shoppingConfig['quoteDraggableProducts'];

            if(!empty($quoteDraggableProducts)) {
                $quoteDraggableMapper = Quote_Models_Mapper_QuoteDraggableMapper::getInstance();

                $quoteDraggableProducts = $quoteDraggableMapper->findByQuoteId($data['qid']);

                if($quoteDraggableProducts instanceof Quote_Models_Model_QuoteDraggableModel) {
                    $savedDragData = explode(',', $quoteDraggableProducts->getData());

                    if(!empty($savedDragData)) {
                        $prodSid = $productSids[$id];

                        if(in_array($prodSid, $savedDragData)) {
                            $searchedParam = array_search($prodSid, $savedDragData);
                            unset($savedDragData[$searchedParam]);
                        }

                        if(!empty($savedDragData)) {
                            $quoteDraggableProducts->setData(implode(',', $savedDragData));
                            $quoteDraggableMapper->save($quoteDraggableProducts);
                        } else{
                            $quoteDraggableMapper->delete($data['qid']);
                        }
                    }
                }
            }
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
