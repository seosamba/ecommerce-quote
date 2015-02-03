<?php
/**
 * MAGICSPACE: customersonly
 * {customersonly}{/customersonly} - return content for everyone who not have access to the storemanagement resource
 * Ex: guest, customer, member
 * {customersonly}
 * <div class="quote-info" id="quote-billing-info">
 *  <p class="title">billing address</p>
 *  <p>{$quote:address:billing:firstname} {$quote:address:billing:lastname}</p>
 *  <p>{$quote:address:billing:company}</p>
 *  <p>{$quote:address:billing:address1} {$quote:address:billing:address2}</p>
 *  <p>{$quote:address:billing:city} {$quote:address:billing:state} {$quote:address:billing:zip}</p>
 *  <p>{$quote:address:billing:country}</p>
 *  <p><a href="mailto:{$quote:address:billing:email}">{$quote:address:billing:email}</a></p>
 *  <p>{$quote:address:billing:phone}</p>
 *</div>
 *<div class="quote-info" id="quote-shipping-info">
 *  <p class="title">shipping address</p>
 *  <p>{$quote:address:shipping:firstname} {$quote:address:shipping:lastname}</p>
 *  <p>{$quote:address:shipping:company}</p>
 *  <p>{$quote:address:shipping:address1} {$quote:address:shipping:address2}</p>
 *  <p>{$quote:address:shipping:city} {$quote:address:shipping:state} {$quote:address:shipping:zip}</p>
 *  <p>{$quote:address:shipping:country}</p>
 *  <p><a href="mailto:{$quote:address:shipping:email}">{$quote:address:shipping:email}</a></p>
 *  <p>{$quote:address:shipping:phone}</p>
 *</div>
 * {/customersonly}
 * Class MagicSpaces_Toastercart_Toastercart
 */

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
