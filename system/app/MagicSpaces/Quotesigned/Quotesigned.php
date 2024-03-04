<?php
/**
 * MAGICSPACE: quotesigned - displays content if quote is signed
 * {quotesigned}{/quotesigned}
 * Class MagicSpaces_Quotesigned_Quotesigned
 */

class MagicSpaces_Quotesigned_Quotesigned extends Tools_MagicSpaces_Abstract
{

    protected function _run()
    {
        $pageHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('page');
        $quote = Quote_Models_Mapper_QuoteMapper::getInstance()->find($pageHelper->clean($this->_toasterData['url']));
        if ($quote instanceof Quote_Models_Model_Quote && !empty($quote->getIsSignatureRequired())) {
            if (empty($quote->getIsQuoteSigned())) {
                return '';
            }

            return $this->_spaceContent;

        }
        return '';

    }


}
