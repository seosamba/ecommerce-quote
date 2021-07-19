<?php

class Quote_Models_Mapper_QuoteCustomParamsDataMapper extends Application_Model_Mappers_Abstract
{

    protected $_model = 'Quote_Models_Model_QuoteCustomParamsDataModel';

    protected $_dbTable = 'Quote_Models_DbTable_QuoteCustomParamsDataDbTable';

    /**
     * Save quote custom params config model to DB
     *
     * @param $model Quote_Models_Model_QuoteCustomParamsDataModel
     * @return Quote_Models_Model_QuoteCustomParamsDataModel
     * @throws Exception
     */
    public function save($model)
    {
        if (!$model instanceof $this->_model) {
            $model = new $this->_model($model);
        }

        $data = array(
            'cart_id' => $model->getCartId(),
            'param_id' => $model->getParamId(),
            'param_value' => $model->getParamValue(),
            'params_option_id' => $model->getParamsOptionId()
        );

        $paramExists = $this->checkIfParamExists($data['cart_id'], $data['param_id']);
        if ($paramExists instanceof Quote_Models_Model_QuoteCustomParamsDataModel) {
            $id = $paramExists->getId();
            $where = $this->getDbTable()->getAdapter()->quoteInto('id = ?', $id);
            $this->getDbTable()->update($data, $where);
        } else {
            $id = $this->getDbTable()->insert($data);
            $model->setId($id);
        }

        return $model;
    }

    /**
     * @param $id
     * @return null
     */
    public function findById($id)
    {
        $where = $this->getDbTable()->getAdapter()->quoteInto('id = ?', $id);

        return $this->_findWhere($where);
    }

    /**
     * Check if param already exists
     *
     * @param int $cartId cart id
     * @param int $paramId custom param id
     * @return Quote_Models_Model_QuoteCustomParamsDataModel
     * @throws Exception
     */
    public function checkIfParamExists($cartId, $paramId)
    {
        $where = $this->getDbTable()->getAdapter()->quoteInto('cart_id = ?', $cartId);
        $where .= ' AND ' . $this->getDbTable()->getAdapter()->quoteInto('param_id = ?', $paramId);

        return $this->_findWhere($where);
    }

    /**
     * Find cart custom params by id
     *
     * @param int $cartId cart id
     * @return mixed
     * @throws Exception
     */
    public function findByCartId($cartId)
    {
        $where = $this->getDbTable()->getAdapter()->quoteInto('cart_id = ?', $cartId);
        $select = $this->getDbTable()->getAdapter()->select()->from(array('qcpd' => 'quote_custom_params_data'),
            array(
                'qcpd.id',
                'qcpd.param_id',
                'qcpd.cart_id',
                'qcpd.param_value',
                'qcpd.params_option_id',
                'qcfc.param_type',
                'qcfc.param_name',
                'option_val' => 'qcpod.option_value'
            ))
            ->join(array('qcfc' => 'quote_custom_fields_config'),
                'qcfc.id=qcpd.param_id', array())
            ->joinLeft(array('qcpod' => 'quote_custom_params_options_data'),
                'qcpod.id=qcpd.params_option_id', array());

        $select->where($where);

        return $this->getDbTable()->getAdapter()->fetchAssoc($select);
    }

    /**
     * Delete record
     *
     * @param int $id id
     * @return mixed
     * @throws Exception
     */
    public function delete($id)
    {
        $where = $this->getDbTable()->getAdapter()->quoteInto('id = ?', $id);

        return $this->getDbTable()->getAdapter()->delete('quote_custom_params_data', $where);

    }

}
