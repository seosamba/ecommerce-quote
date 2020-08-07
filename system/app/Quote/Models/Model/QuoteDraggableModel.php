<?php

/**
 * Class Quote_Models_Model_QuoteDraggableModel
 */
class Quote_Models_Model_QuoteDraggableModel extends Application_Model_Models_Abstract
{
    protected $_quoteId = '';

    protected $_data = '';

    /**
     * @return string
     */
    public function getQuoteId()
    {
        return $this->_quoteId;
    }

    /**
     * @param string $quoteId
     */
    public function setQuoteId($quoteId)
    {
        $this->_quoteId = $quoteId;

        return $this;
    }

    /**
     * @return string
     */
    public function getData()
    {
        return $this->_data;
    }

    /**
     * @param string $data
     */
    public function setData($data)
    {
        $this->_data = $data;

        return $this;
    }




}
