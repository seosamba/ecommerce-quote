<?php
/**
 *
 * MAGICSPACE: onlysignaturerequired
 * {onlysignaturerequired}{/onlysignaturerequired}
 *
 * Class MagicSpaces_Onlysignaturerequired_Onlysignaturerequired
 */

class MagicSpaces_Onlysignaturerequired_Onlysignaturerequired extends Tools_MagicSpaces_Abstract {


	protected function _run() {
       $pageHelper  = Zend_Controller_Action_HelperBroker::getStaticHelper('page');
       $quote       = Quote_Models_Mapper_QuoteMapper::getInstance()->find($pageHelper->clean($this->_toasterData['url']));
       if($quote instanceof Quote_Models_Model_Quote){
           $paymentType = $quote->getPaymentType();
           if ($paymentType === Quote_Models_Model_Quote::PAYMENT_TYPE_ONLY_SIGNATURE) {
               return '';
           }

           return $this->_spaceContent;

       }
       return '';

    }


}
