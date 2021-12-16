<?php
/**
 * MAGICSPACE: quoterestrictedcontrol
 * {quoterestrictedcontrol}{/quoterestrictedcontrol}
 * Class MagicSpaces_Quoterestrictedcontrol_Quoterestrictedcontrol
 */

class MagicSpaces_Quoterestrictedcontrol_Quoterestrictedcontrol extends Tools_MagicSpaces_Abstract
{

    protected function _run()
    {
        $pageHelper  = Zend_Controller_Action_HelperBroker::getStaticHelper('page');
        $quote       = Quote_Models_Mapper_QuoteMapper::getInstance()->find($pageHelper->clean($this->_toasterData['url']));
        if($quote instanceof Quote_Models_Model_Quote){
            $isQuoteRestrictedControl = $quote->getIsQuoteRestrictedControl();
            if (empty($isQuoteRestrictedControl)) {
                return '';
            }

            return $this->_spaceContent;

        }
        return '';

    }


}
