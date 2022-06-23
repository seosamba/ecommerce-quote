<?php
/*
 * MAGICSPACE: postpurchasequotecode
 * {postpurchasequotecode} ... {/postpurchasequotecode} - postpurchasequotecode magic space is used to specify place where to display
 * information about purchase
 */

class MagicSpaces_Postpurchasequotecode_Postpurchasequotecode extends Tools_MagicSpaces_Abstract
{

    protected function _run()
    {
        $registry = Zend_Registry::getInstance();
        if ($registry->isRegistered('postPurchaseCart')) {
            $cartSession = $registry->get('postPurchaseCart');
            if ($cartSession instanceof Models_Model_CartSession) {
                $quoteModel = Quote_Models_Mapper_QuoteMapper::getInstance()->findByCartId($cartSession->getId());
                if ($quoteModel instanceof Quote_Models_Model_Quote) {
                    return $this->_spaceContent;
                }
            }

        }
        return '';
    }

}
