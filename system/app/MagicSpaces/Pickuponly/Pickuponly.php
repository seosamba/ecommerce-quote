<?php

class MagicSpaces_Pickuponly_Pickuponly extends Tools_MagicSpaces_Abstract {

    protected function _run() {
        $pageHelper  = Zend_Controller_Action_HelperBroker::getStaticHelper('page');
        $quote       = Quote_Models_Mapper_QuoteMapper::getInstance()->find($pageHelper->clean($this->_toasterData['url']));

        $previewMode        = (Widgets_Quote_Quote::MODE_PREVIEW == Zend_Controller_Front::getInstance()->getRequest()->getParam('mode', false));

        if ($quote instanceof Quote_Models_Model_Quote){
            $cartSessionMapper = Models_Mapper_CartSessionMapper::getInstance();
            $cartSessionModel = $cartSessionMapper->find($quote->getCartId());
            if ($cartSessionModel instanceof Models_Model_CartSession) {
                $shippingType = $cartSessionModel->getShippingService();
                if (!empty($this->_params[0]) && $this->_params[0] === 'not') {
                    if ($shippingType !== 'pickup') {
                        return $this->_spaceContent;
                    } else {
                        return '';
                    }

                } elseif ($shippingType === 'pickup') {
                    return $this->_spaceContent;
                }
            }
        }

        return '';
    }

}
