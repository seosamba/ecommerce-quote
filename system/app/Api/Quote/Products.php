<?php
/**
 * Products
 *
 * @author: iamne <eugene@seotoaster.com> Seotoaster core team
 * Date: 9/6/12
 * Time: 5:45 PM
 */
class Api_Quote_Products extends Api_Service_Abstract {

    const UPDATE_TYPE_QTY = 'qty';

    protected $_accessList  = array(
        Tools_Security_Acl::ROLE_SUPERADMIN => array('allow' => array('get', 'post', 'put', 'delete'))
    );

    /**
     * Only for search through the customer
     *
     */
    public function getAction() {}

    public function postAction() {
        $pageHelper  = Zend_Controller_Action_HelperBroker::getStaticHelper('page');
        $quoteMapper = Quote_Models_Mapper_QuoteMapper::getInstance();
        $productId   = filter_var($this->_request->getParam('pid'), FILTER_SANITIZE_NUMBER_INT);
        $quoteId     = $pageHelper->clean(filter_var($this->_request->getParam('qid'), FILTER_SANITIZE_STRING));
        if(!$productId) {
            $this->_error();
        }
        $product       = Models_Mapper_ProductMapper::getInstance()->find($productId);
        $cart          = Quote_Tools_Tools::invokeCart(Quote_Models_Mapper_QuoteMapper::getInstance()->find($quoteId));
        $currentTax    = Tools_Tax_Tax::calculateProductTax($product);
        //$cartContent   = $cart->getCartContent();
        $productExists = false;
        $cartContent   = array_map(function($cartItem) use($productId, &$productExists) {
            if($cartItem['product_id'] == $productId) {
                $cartItem['qty']++;
                $productExists = true;
            }
            return $cartItem;
        }, $cart->getCartContent());

        if(!$productExists) {
            $cartContent[] = array(
                'product_id' => $product->getId(),
                'price'      => $product->getPrice(),
                'options'    => array(), //$this->_proccessOptions($this->_request->getParam('opts', array()), $product),
                'qty'        => $this->_request->getParam('qty', 1),
                'tax'        => $currentTax,
                'tax_price'  => $product->getPrice() + $currentTax
            );
        }
        Models_Mapper_CartSessionMapper::getInstance()->save($cart->setCartContent($cartContent));
        return $quoteMapper->save($quoteMapper->find($quoteId))->toArray();
    }

    public function putAction() {
        $productId  = filter_var($this->_request->getParam('id'), FILTER_SANITIZE_NUMBER_INT);
        $updateType = filter_var($this->_request->getParam('type'), FILTER_SANITIZE_STRING);
        $updateData = Zend_Json::decode($this->_request->getRawBody());
        $result     = null;
        switch($updateType) {
            case self::UPDATE_TYPE_QTY:
                if(!isset($updateData['qty'])) {
                    $this->_error('Cannot update product quantity');
                }
                if(!isset($updateData['qid'])) {
                    $this->_error('Cannot find the quote.', self::REST_STATUS_NOT_FOUND);
                }
                $mapper = Quote_Models_Mapper_QuoteMapper::getInstance();
                $quote  = $mapper->find($updateData['qid']);
                if(!$quote instanceof Quote_Models_Model_Quote) {
                    $this->_error('Cannot find quote', self::REST_STATUS_NOT_FOUND);
                }
                $cart        = Quote_Tools_Tools::invokeCart($quote);
                $cartContent = $cart->getCartContent();
                foreach($cartContent as $key => $cartItem) {
                    if($cartItem['product_id'] == $productId) {
                        $cartContent[$key]['qty'] = $updateData['qty'];
                        break;
                    }
                }
                Models_Mapper_CartSessionMapper::getInstance()->save($cart->setCartContent($cartContent));
                $result = $quote;
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

        $cart        = Quote_Tools_Tools::invokeCart(Quote_Models_Mapper_QuoteMapper::getInstance()->find($data['qid']));
        $cartContent = $cart->getCartContent();

        if(!empty($cartContent)) {
            foreach($cartContent as $key => $cartItem) {
                if(in_array($cartItem['product_id'], $ids)) {
                    unset($cartContent[$key]);
                }
            }
        }
        return Models_Mapper_CartSessionMapper::getInstance()->save($cart->setCartContent($cartContent))->toArray();
    }
}
