<?php

/**
 * Class Quote_Models_Model_QuoteConversionsModel
 */
class Quote_Models_Model_QuoteConversionsModel extends Application_Model_Models_Abstract
{
    protected $_cartId = '';

    protected $_createdAt = '';

    /**
     * @return string
     */
    public function getCartId()
    {
        return $this->_cartId;
    }

    /**
     * @param string $cartId
     */
    public function setCartId($cartId)
    {
        $this->_cartId = $cartId;
    }

    /**
     * @return string
     */
    public function getCreatedAt()
    {
        return $this->_createdAt;
    }

    /**
     * @param string $createdAt
     */
    public function setCreatedAt($createdAt)
    {
        $this->_createdAt = $createdAt;
    }

}
