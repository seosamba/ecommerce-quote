<?php
/**
 * Prices
 * @author: iamne <eugene@seotoaster.com> Seotoaster core team
 * Date: 9/12/12
 * Time: 3:07 PM
 */
class Api_Quote_Prices extends Api_Service_Abstract {

    protected $_accessList  = array(
        Tools_Security_Acl::ROLE_SUPERADMIN => array('allow' => array('get', 'post', 'put', 'delete'))
    );

    protected $_format   = false;

    /**
     * @var Zend_Currency
     */
    protected $_currency = null;

    protected $_cart     = null;

    protected $_quoteId  = 0;

    public function init() {
        //current currency
        if(($this->_format = (boolean)filter_var($this->_request->getParam('currency', 0), FILTER_SANITIZE_NUMBER_INT)) !== false) {
            $this->_currency = Zend_Registry::get('Zend_Currency');
        }
        $this->_quoteId  = filter_var($this->_request->getParam('id', 0), FILTER_SANITIZE_STRING);
        if(!$this->_quoteId) {
            $this->_error();
        }
    }

    public function getAction() {
        $type = filter_var($this->_request->getParam('type'), FILTER_SANITIZE_STRING);
        if(!$type) {
            $this->_error();
        }

        $quote = Quote_Models_Mapper_QuoteMapper::getInstance()->find($this->_quoteId);
        if(!$quote instanceof Quote_Models_Model_Quote) {
            $this->_error('Canonot find quote', self::REST_STATUS_NOT_FOUND);
        }

        $total = Quote_Tools_Calc::getInstance()->init($quote)->calculate($type);
        return (array)(($this->_format) ? $this->_currency->toCurrency($total) : $total);
    }

    /**
     * The post action handles POST requests; it should accept and digest a
     * POSTed resource representation and persist the resource state.
     */
    public function postAction()
    {
        // TODO: Implement postAction() method.
    }

    /**
     * The put action handles PUT requests and receives an 'id' parameter; it
     * should update the server resource state of the resource identified by
     * the 'id' value.
     */
    public function putAction()
    {
        // TODO: Implement putAction() method.
    }

    /**
     * The delete action handles DELETE requests and receives an 'id'
     * parameter; it should update the server resource state of the resource
     * identified by the 'id' value.
     */
    public function deleteAction()
    {
        // TODO: Implement deleteAction() method.
    }


}
