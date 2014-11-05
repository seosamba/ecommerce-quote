<?php


class MagicSpaces_Customersonly_Customersonly extends Tools_MagicSpaces_Abstract {

	protected function _run() {
        $modePreview = Zend_Controller_Action_HelperBroker::getStaticHelper('response')->getRequest()->getParam('mode');
        if(isset($modePreview) && $modePreview == 'preview' && Tools_Security_Acl::isAllowed(Shopping::RESOURCE_STORE_MANAGEMENT)){
            return $this->_spaceContent;
        }elseif(!Tools_Security_Acl::isAllowed(Shopping::RESOURCE_STORE_MANAGEMENT)){
            return $this->_spaceContent;
        }else{
            return '';
        }

    }


}
