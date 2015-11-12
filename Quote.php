<?php

/**
 * Quote system plugin for the Seotoaster CMS v.2
 *
 * @author: iamne <eugene@seotoaster.com> Seotoaster core team
 * Date: 9/6/12
 * Time: 11:12 AM
 */
class Quote extends Tools_PaymentGateway
{

    /**
     * Postfix for the views scripts (e.g.: settings.quote.phtml)
     *
     */
    const VIEWS_POSTFIX = '.quote.phtml';

    /**
     * Quote generation type, shows quote is building by the admin
     *
     */
    const QUOTE_TYPE_BUILD = 'build';

    /**
     * Quote generation type, shows quote is generating by the system
     *
     */
    const QUOTE_TYPE_GENERATE = 'generate';

    /**
     * Quote template type id
     *
     */
    const QUOTE_TEPMPLATE_TYPE = 'typequote';

    /**
     * Category id for the quote pages
     *
     */
    const QUOTE_CATEGORY_ID = -5;

    /**
     * Secure token
     */
    const QUOTE_SECURE_TOKEN = 'QuoteToken';

    /**
     * Quote page type
     */
    const QUOTE_PAGE_TYPE = 4;

    /**
     * Layout instance
     *
     * @var Zend_Layout
     */
    private $_layout = null;

    /**
     * Store common configuration and settings
     *
     * @var array
     */
    private $_shoppingConfig = array();

    /**
     * @var array
     */
    protected $_securedActions = array(
        Tools_Security_Acl::ROLE_SUPERADMIN => array(
            'settings'
        )
    );

    /**
     * Initialize layout, views, etc...
     */
    protected function _init()
    {
        $this->_layout = new Zend_Layout();
        $this->_layout->setLayoutPath(Zend_Layout::getMvcInstance()->getLayoutPath());

        if (($scriptPaths = Zend_Layout::getMvcInstance()->getView()->getScriptPaths()) !== false) {
            $this->_view->setScriptPath($scriptPaths);
        }
        $this->_view->addScriptPath(__DIR__ . '/system/views/');

        $this->_shoppingConfig = Models_Mapper_ShoppingConfig::getInstance()->getConfigParams();

        // shows if the quote is in the preview mode (editing is disabled)
        $this->_view->previewMode = Quote_Tools_Security::isEditAllowed(('preview' == Zend_Controller_Front::getInstance()->getRequest()->getParam('mode',
                false)));
    }

    /**
     * Generate tab for the general store config
     *
     * @static
     * @return array
     */
    public static function getEcommerceConfigTab()
    {
        $translator = Zend_Controller_Action_HelperBroker::getStaticHelper('language');
        return array(
            'title' => $translator->translate('Quote'),
            'contentUrl' => Zend_Controller_Action_HelperBroker::getStaticHelper('website')->getUrl() . 'plugin/quote/run/settings/'
        );
    }

    /**
     * Show and process quote system settings
     *
     */
    public function settingsAction()
    {
        $form = new Quote_Forms_Settings();
        if ($this->_request->isPost()) {
            $secureToken = $this->_request->getParam(Tools_System_Tools::CSRF_SECURE_TOKEN, false);
            $tokenValid = Tools_System_Tools::validateToken($secureToken, self::QUOTE_SECURE_TOKEN);
            if (!$tokenValid) {
                $this->_responseHelper->fail('');
            }
            if ($form->isValid($this->_request->getParams())) {
                if (Models_Mapper_ShoppingConfig::getInstance()->save($this->_request->getParams())) {
                    $this->_responseHelper->success($this->_translator->translate('Configuration updated'));
                }
                $this->_responseHelper->fail($this->_translator->translate('Cannot update quote configuration.'));
            }
            $this->_responseHelper->fail(join('<br />', $form->getMessages()));
        }
        $form->populate($this->_shoppingConfig);
        $this->_view->form = $form;
        $this->_show(null, true);
    }

    /**
     * Show "add new product" to the quote
     *
     */
    public function productAction()
    {
        $this->_show();
    }

    /**
     * Show manage product options screen
     *
     */
    public function optionsAction()
    {
        $this->_view->quoteId = $this->_request->getParam('qid');
        $this->_view->weightSign = $this->_shoppingConfig['weightUnit'];

        $currentOptions = array();
        parse_str($this->_request->getParam('co'), $currentOptions);
        $this->_view->currOptions = $currentOptions;

        $this->_show();
    }

    public function internalnoteSaveAction()
    {
        $accessList = array(
            Tools_Security_Acl::ROLE_SUPERADMIN,
            Tools_Security_Acl::ROLE_ADMIN,
            Shopping::ROLE_SALESPERSON
        );
        if (in_array($this->_sessionHelper->getCurrentUser()->getRoleId(), $accessList)) {
            $content = filter_var($this->_request->getParam('content'), FILTER_SANITIZE_STRING);
            $quoteMapper = Quote_Models_Mapper_QuoteMapper::getInstance();
            $data = $quoteMapper->find(filter_var($this->_request->getParam('id'), FILTER_SANITIZE_STRING));
            if ($data instanceof Quote_Models_Model_Quote && $data->getInternalNote() != $content) {
                $quoteMapper->save($data->setInternalNote($content));
            }
        }
    }

    /**
     * Render a proper view script
     *
     * If $viewScript not passed, generates view script file name automatically using the action name and VIEWS_POSTFIX
     * @param string $viewScript
     * @param boolean $disableLayout
     * @return boolean
     */
    private function _show($viewScript = null, $disableLayout = false)
    {
        if (!$viewScript) {
            $trace = debug_backtrace(false);
            $viewScript = str_ireplace('Action', self::VIEWS_POSTFIX, $trace[1]['function']);
        }
        if ($disableLayout) {
            echo $this->_view->render($viewScript);
            return true;
        }
        $this->_layout->content = $this->_view->render($viewScript);
        echo $this->_layout->render();
        return true;
    }

}
