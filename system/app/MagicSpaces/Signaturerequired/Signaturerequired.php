<?php
/**
 *
 * MAGICSPACE: signaturerequired
 * {signaturerequired}{/signaturerequired}
 *
 * Class MagicSpaces_Signaturerequired_Signaturerequired
 */

class MagicSpaces_Signaturerequired_Signaturerequired extends Tools_MagicSpaces_Abstract {


	protected function _run() {
       $pageHelper  = Zend_Controller_Action_HelperBroker::getStaticHelper('page');
       $quote       = Quote_Models_Mapper_QuoteMapper::getInstance()->find($pageHelper->clean($this->_toasterData['url']));
       if($quote instanceof Quote_Models_Model_Quote){
           $quoteSignatureRequired = $quote->getIsSignatureRequired();
           if (!empty($quoteSignatureRequired)) {
               return $this->_spaceContent;
           }

       }
       return '';

    }


}
