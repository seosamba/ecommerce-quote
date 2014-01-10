<?php
/**
 * Customers
 * @author: iamne <eugene@seotoaster.com> Seotoaster core team
 * Date: 9/6/12
 * Time: 5:45 PM
 */
class Api_Quote_Customers extends Api_Service_Abstract {

    protected $_accessList  = array(
        Tools_Security_Acl::ROLE_SUPERADMIN => array('allow' => array('get', 'post', 'put', 'delete')),
        Tools_Security_Acl::ROLE_ADMIN      => array('allow' => array('get', 'post', 'put', 'delete')),
        Shopping::ROLE_SALESPERSON          => array('allow' => array('get', 'post', 'put', 'delete'))
    );

    /**
     * Only for search through the customer
     *
     */
    public function getAction() {
        $search = filter_var($this->_request->getParam('search'), FILTER_SANITIZE_STRING);
        $addressTable = new Quote_Models_DbTable_ShoppingCustomerAddress();
        return $addressTable->searchAddress($search);
    }

    public function postAction() {}

    public function putAction() {}

    public function deleteAction() {}
}
