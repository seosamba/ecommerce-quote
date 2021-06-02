<?php

/**
 * Class Api_Quote_Quotecustomfieldsconfig
 */
class Api_Quote_Quotecustomfieldsconfig extends Api_Service_Abstract
{

    /*
     * Quote custom field select
     */
    const QUOTE_CUSTOM_FIELD_TYPE_SELECT = 'select';

    /*
     * Quote custom field text
     */
    const QUOTE_CUSTOM_FIELD_TYPE_TEXT = 'text';

    /**
     * Mandatory fields
     *
     * @var array
     */
    protected $_mandatoryParams = array();

    /**
     * System response helper
     *
     * @var null
     */
    protected $_responseHelper = null;

    /**
     * @var array Access Control List
     */
    protected $_accessList = array(
        Tools_Security_Acl::ROLE_SUPERADMIN => array(
            'allow' => array('get', 'post', 'put', 'delete')
        ),
        Tools_Security_Acl::ROLE_ADMIN => array(
            'allow' => array('get', 'post', 'put', 'delete')
        )
    );


    public function init()
    {
        parent::init();
        $this->_responseHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('response');
    }


    /**
     *
     * Resource:
     * : /api/quote/Quotecustomfieldsconfig/
     *
     * HttpMethod:
     * : GET
     *
     * @return JSON
     */
    public function getAction()
    {
        $limit = filter_var($this->_request->getParam('limit'), FILTER_SANITIZE_NUMBER_INT);
        $offset = filter_var($this->_request->getParam('offset'), FILTER_SANITIZE_NUMBER_INT);
        $sortOrder = filter_var($this->_request->getParam('order', 'qcfc.param_name'), FILTER_SANITIZE_STRING);
        $id = filter_var($this->_request->getParam('id'), FILTER_SANITIZE_NUMBER_INT);

        $quoteCustomFieldsConfigMapper = Quote_Models_Mapper_QuoteCustomFieldsConfigMapper::getInstance();
        if ($id) {
            $where = $quoteCustomFieldsConfigMapper->getDbTable()->getAdapter()->quoteInto('qcfc.id = ?', $id);
            $data = $quoteCustomFieldsConfigMapper->fetchAll($where);
        } else {
            $data = $quoteCustomFieldsConfigMapper->fetchAll(null, $sortOrder, $limit, $offset);
        }

        return $data;
    }

    /**
     * Create new quote custom param config
     *
     * Resource:
     * : /api/quote/Quotecustomfieldsconfig/
     *
     * HttpMethod:
     * : POST
     *
     * @return JSON
     */
    public function postAction()
    {
        $data = $this->getRequest()->getParams();
        $translator = Zend_Registry::get('Zend_Translate');

        $fieldDataMissing = array_filter($this->_mandatoryParams, function ($param) use ($data) {
            if (!array_key_exists($param, $data) || empty($data[$param])) {
                return $param;
            }
        });

        if (!empty($fieldDataMissing)) {
            return array('status' => 'error', 'message' => $translator->translate('Missing mandatory params'));
        }

        $secureToken = $this->getRequest()->getParam(Tools_System_Tools::CSRF_SECURE_TOKEN, false);
        $tokenValid = Tools_System_Tools::validateToken($secureToken, Quote::QUOTE_SECURE_TOKEN);
        if (!$tokenValid) {
            $websiteHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('website');
            $websiteUrl = $websiteHelper->getUrl();
            return array('status' => 'error', 'message' => $translator->translate('Your session has timed-out. Please Log back in '.'<a href="'.$websiteUrl.'go">here</a>'));
        }

        if(preg_match('~[^\w-]~ui', $data['param_name'])) {
            return array('status' => 'error', 'message' => $translator->translate('Invalid param name. You can use only alphabet and digits. You can also use "-". White Spaces not allowed'));
        }

        $quoteCustomFieldsConfigMapper = Quote_Models_Mapper_QuoteCustomFieldsConfigMapper::getInstance();
        $quoteCustomFieldsConfigModel = $quoteCustomFieldsConfigMapper->getByName($data['param_name']);
        if ($quoteCustomFieldsConfigModel instanceof Quote_Models_Model_QuoteCustomFieldsConfigModel) {
            return array('status' => 'error', 'message' => $translator->translate('Custom param with such name already exists'));
        }

        $quoteCustomFieldsConfigModel = new Quote_Models_Model_QuoteCustomFieldsConfigModel();

        $quoteCustomFieldsConfigModel->setOptions($data);
        $quoteCustomFieldsConfigMapper->save($quoteCustomFieldsConfigModel);

        $quoteCustomFieldsOptionsDataMapper = Quote_Models_Mapper_QuoteCustomFieldsOptionsDataMapper::getInstance();

        $customFieldParamId = $quoteCustomFieldsConfigModel->getId();

        if ($data['param_type'] == self::QUOTE_CUSTOM_FIELD_TYPE_SELECT) {
            $quoteCustomFieldsOptionsData = $quoteCustomFieldsOptionsDataMapper->findByCustomParamId($customFieldParamId);

            if(empty($quoteCustomFieldsOptionsData)) {
                foreach ($data['dropdownParams'] as $key => $params) {
                    $quoteCustomFieldsOptionsDataModel = new Quote_Models_Model_QuoteCustomFieldsOptionsDataModel();

                    $quoteCustomFieldsOptionsDataModel->setCustomParamId($customFieldParamId);
                    $quoteCustomFieldsOptionsDataModel->setOptionValue($params['value']);

                    $quoteCustomFieldsOptionsDataMapper->save($quoteCustomFieldsOptionsDataModel);
                }
            } else {
                return array('status' => 'error', 'message' => $translator->translate('Custom param with such name already exists'));
            }
        }

        return array('status' => 'ok', 'message' => $translator->translate('Custom param has been created'));

    }

    /**
     * Update quote custom param config
     *
     * Resource:
     * : /api/quote/Quotecustomfieldsconfig/
     *
     * HttpMethod:
     * : PUT
     *
     * ## Parameters:
     * id (type integer)
     * : quote custom param id to update
     *
     * @return JSON
     */
    public function putAction()
    {
        $data = json_decode($this->_request->getRawBody(), true);
        if (!empty($data['id']) && !empty($data[Tools_System_Tools::CSRF_SECURE_TOKEN])) {
            $translator = Zend_Registry::get('Zend_Translate');
            $secureToken = $data[Tools_System_Tools::CSRF_SECURE_TOKEN];
            $tokenValid = Tools_System_Tools::validateToken($secureToken, Quote::QUOTE_SECURE_TOKEN);
            if (!$tokenValid) {
                $websiteHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('website');
                $websiteUrl = $websiteHelper->getUrl();
                return array('status' => 'error', 'message' => $translator->translate('Your session has timed-out. Please Log back in '.'<a href="'.$websiteUrl.'go">here</a>'));

            }
            $customParamId = filter_var($data['id'], FILTER_SANITIZE_NUMBER_INT);

            $quoteCustomFieldsConfigMapper = Quote_Models_Mapper_QuoteCustomFieldsConfigMapper::getInstance();
            $quoteCustomFieldsConfigModel = $quoteCustomFieldsConfigMapper->find($customParamId);

            if (!$quoteCustomFieldsConfigModel instanceof Quote_Models_Model_QuoteCustomFieldsConfigModel) {
                return array('status' => 'error', 'message' => $translator->translate('Config doesn\'t exists'));
            }

            $oldParamName = $quoteCustomFieldsConfigModel->getParamName();

            if(preg_match('~[^\w-]~ui', $data['param_name'])) {
                return array('status' => 'error', 'message' => $translator->translate('Invalid param name. You can use only alphabet and digits. You can also use "-". White Spaces not allowed'));
            }
            $currentParamName = $data['param_name'];
            if ($oldParamName !== $currentParamName) {
                $validateTypeExists = new Zend_Validate_Db_RecordExists(array(
                    'table' => 'quote_custom_fields_config',
                    'field' => 'param_name'
                ));
                if ($validateTypeExists->isValid($currentParamName)) {
                    return array('status' => 'error', 'message' => $translator->translate('You have another custom param with such name'));
                }
            }

            $quoteCustomFieldsConfigModel->setOptions($data);
            $quoteCustomFieldsConfigMapper->save($quoteCustomFieldsConfigModel);

            $quoteCustomFieldsOptionsDataMapper = Quote_Models_Mapper_QuoteCustomFieldsOptionsDataMapper::getInstance();

            if ($data['param_type'] == self::QUOTE_CUSTOM_FIELD_TYPE_SELECT) {
                $newDropdownParams = $data['dropdownParams'];
                $quoteCustomFieldsOptionsData = $quoteCustomFieldsOptionsDataMapper->findByCustomParamId($customParamId);

                if(!empty($quoteCustomFieldsOptionsData) && !empty($newDropdownParams)) {
                    foreach ($quoteCustomFieldsOptionsData as $key => $customFieldsOption) {
                        foreach ($newDropdownParams as $newDropdown) {
                            $savedDropId = $customFieldsOption->getId();
                            if($savedDropId == $newDropdown['id']) {
                                $customFieldsOption->setOptionValue($newDropdown['value']);
                                $quoteCustomFieldsOptionsDataMapper->save($customFieldsOption);

                                unset($quoteCustomFieldsOptionsData[$key]);
                            }
                        }
                    }

                    if(!empty($quoteCustomFieldsOptionsData)) {
                        foreach ($quoteCustomFieldsOptionsData as $customFieldsOption) {
                            $quoteCustomFieldsOptionsDataMapper->delete($customFieldsOption->getId());
                        }
                    }

                    foreach ($newDropdownParams as $newDropdown) {
                        if(empty($newDropdown['id'])) {
                            $quoteCustomFieldsOptionsDataModel = new Quote_Models_Model_QuoteCustomFieldsOptionsDataModel();
                            $quoteCustomFieldsOptionsDataModel->setCustomParamId($customParamId);
                            $quoteCustomFieldsOptionsDataModel->setOptionValue($newDropdown['value']);

                            $quoteCustomFieldsOptionsDataMapper->save($quoteCustomFieldsOptionsDataModel);
                        }
                    }

                    return array('status' => 'ok', 'message' => $translator->translate('Options were successfully updated'));
                } else {
                    return array('status' => 'error', 'message' => $translator->translate('Custom param with such name already exists'));
                }
            }
            return array('status' => 'ok', 'message' => $translator->translate('Custom param has been updated'));
        }

    }

    /**
     * Delete quote custom param config
     *
     * Resource:
     * : /api/quote/Quotecustomfieldsconfig/
     *
     * HttpMethod:
     * : DELETE
     *
     * ## Parameters:
     * id (type integer)
     * : quote custom param id to delete
     *
     * @return JSON
     */
    public function deleteAction()
    {
        $translator = Zend_Registry::get('Zend_Translate');
        $id = filter_var($this->_request->getParam('id'), FILTER_SANITIZE_NUMBER_INT);

        if (!$id) {
            return array('status' => 'error', 'message' => $translator->translate('error'));
        }

        $quoteCustomFieldsConfigMapper = Quote_Models_Mapper_QuoteCustomFieldsConfigMapper::getInstance();
        $quoteCustomFieldsConfigModel = $quoteCustomFieldsConfigMapper->find($id);
        if ($quoteCustomFieldsConfigModel instanceof Quote_Models_Model_QuoteCustomFieldsConfigModel) {
            $quoteCustomFieldsConfigMapper->delete($id);

            return array('status' => 'ok', 'message' => $translator->translate('Quote custom field has been deleted'));
        } else {
            return array('status' => 'error', 'message' => $translator->translate('error'));
        }
    }

}
