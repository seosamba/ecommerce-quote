<?php

/**
 * Class Quote_Models_Mapper_QuoteConversionsMapper
 */
class Quote_Models_Mapper_QuoteConversionsMapper extends Application_Model_Mappers_Abstract
{
    protected $_model = 'Quote_Models_Model_QuoteConversionsModel';

    protected $_dbTable = 'Quote_Models_DbTable_QuoteConversionsDbTable';

    /**
     * @param $model
     * @return mixed
     * @throws Exceptions_SeotoasterPluginException
     */
    public function save($model)
    {
        if (!$model instanceof $this->_model) {
            throw new Exceptions_SeotoasterPluginException('Wrong model type given.');
        }

        $data = array(
            'cart_id' => $model->getCartId(),
            'created_at' => $model->getCreatedAt()
        );

        $recordExists = $this->findByCartId($data['cart_id']);

        if ($recordExists instanceof Quote_Models_Model_QuoteConversionsModel) {
            $where = $this->getDbTable()->getAdapter()->quoteInto('cart_id = ?', $model->getQuoteId());
            $this->getDbTable()->update($data, $where);
        } else {
            $this->getDbTable()->insert($data);
        }

        return $model;
    }

    /**
     * @param $cartId
     * @return mixed|null
     * @throws Exception
     */
    public function findByCartId($cartId)
    {
        $where = $this->getDbTable()->getAdapter()->quoteInto('cart_id = ?', $cartId);
        return $this->_findWhere($where);
    }

}
