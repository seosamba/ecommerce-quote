<?php

/**
 * Class Quote_Models_Mapper_QuoteDraggableMapper
 */
class Quote_Models_Mapper_QuoteDraggableMapper extends Application_Model_Mappers_Abstract
{
    protected $_model = 'Quote_Models_Model_QuoteDraggableModel';

    protected $_dbTable = 'Quote_Models_DbTable_QuoteDraggableDbTable';

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
            'quoteId' => $model->getQuoteId(),
            'data' => $model->getData()
        );

        $recordExists = $this->find($data['quoteId']);

        if ($recordExists instanceof Quote_Models_Model_QuoteDraggableModel) {
            $where = $this->getDbTable()->getAdapter()->quoteInto('quoteId = ?', $model->getQuoteId());
            $this->getDbTable()->update($data, $where);
        } else {
            $this->getDbTable()->insert($data);
        }

        return $model;
    }

    /**
     * @param $quoteId
     * @return mixed|null
     * @throws Exception
     */
    public function findByQuoteId($quoteId)
    {
        $where = $this->getDbTable()->getAdapter()->quoteInto('quoteId = ?', $quoteId);
        return $this->_findWhere($where);
    }

    /**
     * @param $id
     * @return mixed
     * @throws Exception
     */
    public function delete($id)
    {
        $where = $this->getDbTable()->getAdapter()->quoteInto('quoteId = ?', $id);
        return $this->getDbTable()->getAdapter()->delete('shopping_quote_draggable', $where);

    }

}
