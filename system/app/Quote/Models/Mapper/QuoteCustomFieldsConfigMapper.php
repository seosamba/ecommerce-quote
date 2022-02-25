<?php

class Quote_Models_Mapper_QuoteCustomFieldsConfigMapper extends Application_Model_Mappers_Abstract
{

    protected $_model = 'Quote_Models_Model_QuoteCustomFieldsConfigModel';

    protected $_dbTable = 'Quote_Models_DbTable_QuoteCustomFieldsConfigDbTable';

    /**
     * Save quote custom params config model to DB
     * @param $model Quote_Models_Model_QuoteCustomFieldsConfigModel
     * @return Quote_Models_Model_QuoteCustomFieldsConfigModel
     */
    public function save($model)
    {
        if (!$model instanceof $this->_model) {
            $model = new $this->_model($model);
        }

        $data = array(
            'param_type' => $model->getParamType(),
            'param_name' => $model->getParamName(),
            'label' => $model->getLabel()
        );

        $id = $model->getId();
        if (!empty($id)) {
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
     * Get quote custom param config by param type and name
     *
     * @param string $paramType param type
     * @param string $paramName param name
     * @return null
     */
    public function getByTypeName($paramType, $paramName)
    {
        $where = $this->getDbTable()->getAdapter()->quoteInto('param_type = ?', $paramType);
        $where .= ' AND ' . $this->getDbTable()->getAdapter()->quoteInto('param_name = ?', $paramName);

        return $this->_findWhere($where);
    }

    /**
     * Get quote custom param config by param name
     *
     * @param string $paramName param name
     * @return null
     */
    public function getByName($paramName)
    {
        $where = $this->getDbTable()->getAdapter()->quoteInto('param_name = ?', $paramName);

        return $this->_findWhere($where);
    }


    public function fetchAll(
        $where = null,
        $order = null,
        $limit = null,
        $offset = null,
        $withoutCount = false,
        $singleRecord = false
    ) {
        $select = $this->getDbTable()->getAdapter()->select()
            ->from(array('qcfc' => 'quote_custom_fields_config'),
                array(
                    'qcfc.id',
                    'qcfc.param_name',
                    'qcfc.param_type',
                    'qcfc.label',
                    'option_values' => new Zend_Db_Expr('GROUP_CONCAT(qcpod.option_value)'),
                    'option_ids' => new Zend_Db_Expr('GROUP_CONCAT(qcpod.id)')
                )
            )
            ->joinLeft(array('qcpod' => 'quote_custom_params_options_data'),
                'qcpod.custom_param_id = qcfc.id', array());
        if (!empty($order)) {
            $select->order($order);
        }

        if (!empty($where)) {
            $select->where($where);
        }

        $select->limit($limit, $offset);

        if ($singleRecord) {
            $data = $this->getDbTable()->getAdapter()->fetchRow($select);
        } else {
            $data = $this->getDbTable()->getAdapter()->fetchAll($select);
        }

        if ($withoutCount === false) {
            $select->reset(Zend_Db_Select::COLUMNS);
            $select->reset(Zend_Db_Select::FROM);
            $select->reset(Zend_Db_Select::LIMIT_OFFSET);
            $select->reset(Zend_Db_Select::GROUP);

            $select->from(array('qcfc' => 'quote_custom_fields_config'),
                array('count' => 'COUNT(qcfc.id)'));
            $count = $this->getDbTable()->getAdapter()->fetchRow($select);

            return array(
                'totalRecords' => $count['count'],
                'data' => $data,
                'offset' => $offset,
                'limit' => $limit
            );
        } else {
            return $data;
        }
    }


    /**
     * Get all custom params
     *
     * @return array
     */
    public function getCustomParamsConfig()
    {
        $select = $this->getDbTable()->getAdapter()->select()->from('quote_custom_fields_config',
            array('id', 'param_type', 'param_name', 'label'));

        return $this->getDbTable()->getAdapter()->fetchAssoc($select);
    }

    /**
     * Get all custom params
     *
     * @return array
     */
    public function getCustomParamsPairs()
    {
        $select = $this->getDbTable()->getAdapter()->select()->from('quote_custom_fields_config',
            array('id', 'label'));

        return $this->getDbTable()->getAdapter()->fetchPairs($select);
    }


    /**
     * Delete lead record
     *
     * @param int $id lead id
     * @return mixed
     * @throws Exception
     */
    public function delete($id)
    {
        $where = $this->getDbTable()->getAdapter()->quoteInto('id = ?', $id);

        return $this->getDbTable()->getAdapter()->delete('quote_custom_fields_config', $where);

    }

}
