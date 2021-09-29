<?php

class MagicSpaces_Quotediscount_Quotediscount extends Tools_MagicSpaces_Abstract {

    protected function _run() {
        $pageHelper  = Zend_Controller_Action_HelperBroker::getStaticHelper('page');
        $quote       = Quote_Models_Mapper_QuoteMapper::getInstance()->find($pageHelper->clean($this->_toasterData['url']));

        if($quote instanceof Quote_Models_Model_Quote){
            if (Tools_Security_Acl::isAllowed(Shopping::RESOURCE_STORE_MANAGEMENT)) {
                return $this->_spaceContent;
            } else {
                $cartSessionMapper = Models_Mapper_CartSessionMapper::getInstance();
                $cartSessionModel = $cartSessionMapper->find($quote->getCartId());
                if ($cartSessionModel instanceof Models_Model_CartSession) {
                    $discount = $cartSessionModel->getDiscount();

                    if($discount > 0) {
                        return $this->_spaceContent;
                    }
                }
            }
        }

        return '';
    }

}
