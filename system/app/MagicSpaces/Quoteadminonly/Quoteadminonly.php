<?php
/**
 * MAGICSPACE: quoteadminonly
 * {quoteadminonly}{/quoteadminonly} - return content for everyone who have access to the storemanagement resource
 * or mode=preview
 * Class MagicSpaces_Toastercart_Toastercart
 */

class MagicSpaces_Quoteadminonly_Quoteadminonly extends Tools_MagicSpaces_Abstract
{

    protected function _run()
    {
        $modePreview = Zend_Controller_Action_HelperBroker::getStaticHelper('response')->getRequest()->getParam('mode');
        if (!isset($modePreview) && Tools_Security_Acl::isAllowed(Shopping::RESOURCE_STORE_MANAGEMENT)) {
            return $this->_spaceContent;
        } elseif (isset($modePreview) && $modePreview == 'preview' && Tools_Security_Acl::isAllowed(Shopping::RESOURCE_STORE_MANAGEMENT)) {
            return '';
        } else {
            return '';
        }

    }


}
