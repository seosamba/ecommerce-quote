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
     * Quote clone type. Clone already created quote
     */
    const QUOTE_TYPE_CLONE = 'clone';

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

        $this->_view->addScriptPath(__DIR__ . '/system/app/Widgets/Quote/views/');
        $this->_view->addScriptPath(__DIR__ . '/system/views/');

        $this->_shoppingConfig = Models_Mapper_ShoppingConfig::getInstance()->getConfigParams();

        // shows if the quote is in the preview mode (editing is disabled)
        $this->_view->previewMode = Quote_Tools_Security::isEditAllowed(('preview' == Zend_Controller_Front::getInstance()->getRequest()->getParam('mode',
                false)));
    }

    public static function extendPermission()
    {
        $sessionHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('session');
        $currentUser = $sessionHelper->getCurrentUser();

        if ($currentUser->getRoleId() === Shopping::ROLE_SALESPERSON) {

            $pageMapper = Application_Model_Mappers_PageMapper::getInstance();
            $requestedUri = Tools_System_Tools::getRequestUri();

            $front = Zend_Controller_Front::getInstance();
            $data = $front->getRequest()->getParams();
            if (!empty($data['page'])) {
                $pageModel = $pageMapper->findByUrl($data['page']);
                if ($pageModel instanceof Application_Model_Models_Page) {
                    self::extendRole($pageModel);
                }
            } elseif ((isset($data['name']) && isset($data['pageId']) && $data['name'] === 'webbuilder') ||
                (isset($data['plugin']) && isset($data['pageId']) && $data['plugin'] === 'webbuilder') ||
                (isset($data['caller']) && isset($data['pageId']) && $data['caller'] === 'media') ||
                (isset($data['name']) && $data['name'] === 'webbuilder' && isset($data['pid']))
            ) {
                $pageId = 0;
                if (!empty($data['pageId'])) {
                    $pageId = $data['pageId'];
                }

                if (empty($pageId) && !empty($data['pid'])) {
                    $pageId = $data['pid'];
                }

                if (!empty($data['run']) && $data['run'] === 'imageonly' && empty($data['pid'])) {
                    self::allowAdditionalAccess();
                }

                $pageModel = $pageMapper->find($pageId);
                if ($pageModel instanceof Application_Model_Models_Page) {
                    self::extendRole($pageModel);
                }
            } elseif (preg_match('~\.html~', $requestedUri)) {
                $pageModel = Application_Model_Mappers_PageMapper::getInstance()->findByUrl($requestedUri);
                if ($pageModel instanceof Application_Model_Models_Page) {
                    self::extendRole($pageModel);
                }
            } elseif (isset($data['controller']) && isset($data['action']) && $data['controller'] === 'backend_content' && ($data['action'] === 'edit' || $data['action'] === 'add')) {
                if (!empty($data['id'])) {
                    $containerModel = Application_Model_Mappers_ContainerMapper::getInstance()->find($data['id']);
                    if ($containerModel instanceof Application_Model_Models_Container) {
                        $pageId = $containerModel->getPageId();
                        $pageModel = Application_Model_Mappers_PageMapper::getInstance()->find($pageId);
                        if ($pageModel instanceof Application_Model_Models_Page) {
                            self::extendRole($pageModel);
                        }

                        if ($containerModel->getContainerType() == Application_Model_Models_Container::TYPE_STATICCONTENT || $containerModel->getContainerType() == Application_Model_Models_Container::TYPE_STATICHEADER) {
                            self::allowAdditionalAccess();
                        }
                    }
                } elseif(!empty($data['pageId'])) {
                    $pageModel = Application_Model_Mappers_PageMapper::getInstance()->find($data['pageId']);
                    if ($pageModel instanceof Application_Model_Models_Page) {
                        self::extendRole($pageModel);
                    }
                }

            } elseif(isset($data['controller']) && isset($data['action']) && ($data['action'] === 'loadwidgets' || $data['action'] === 'loadwidgetmaker')) {
                self::allowAdditionalAccess();
            } elseif(!empty($data['service']) && $data['service'] == 'io') {
                self::allowAdditionalAccess();
            }
        }
    }

    public static function extendRole($pageModel)
    {
        $quoteModel = Quote_Models_Mapper_QuoteMapper::getInstance()->find(str_replace('.html', '',
            $pageModel->getUrl()));
        if ($quoteModel instanceof Quote_Models_Model_Quote) {
            self::allowAdditionalAccess();
        }
    }

    public static function allowAdditionalAccess()
    {
        $acl = Zend_Registry::get('acl');
        if (!$acl->hasRole(Shopping::ROLE_SALESPERSON)) {
            $acl->addRole(new Zend_Acl_Role(Shopping::ROLE_SALESPERSON), Tools_Security_Acl::ROLE_MEMBER);
        }
        $acl->allow(Shopping::ROLE_SALESPERSON, Tools_Security_Acl::RESOURCE_PLUGINS);
        $acl->allow(Shopping::ROLE_SALESPERSON, Tools_Security_Acl::RESOURCE_ADMINPANEL);
        $acl->allow(Shopping::ROLE_SALESPERSON, Tools_Security_Acl::RESOURCE_CONTENT);
        $acl->allow(Shopping::ROLE_SALESPERSON, Tools_Security_Acl::RESOURCE_MEDIA);
        $acl->allow(Shopping::ROLE_SALESPERSON, Tools_Security_Acl::RESOURCE_PAGES);
        $acl->allow(Shopping::ROLE_SALESPERSON, Tools_Security_Acl::RESOURCE_THEMES);
        $accessList = array(
            Widgets_Videolink_Videolink::VIDEOLINK_RESOURCE,
            Widgets_Directupload_Directupload::DIRECTUPLOAD_RESOURCE,
            'Webbuilder-textonly',
            'Webbuilder-imageonly',
            'Webbuilder-galleryonly',
            'Webbuilder-featuredonly',
            'api_webbuilder_du_post',
            'api_webbuilder_du_delete',
            'api_webbuilder_uf_get',
            'api_webbuilder_uf_post',
            'api_webbuilder_uf_delete',
            'api_webbuilder_io_get',
            'api_webbuilder_io_post',
            'api_webbuilder_io_delete',
            'api_webbuilder_to_get',
            'api_webbuilder_to_post',
            'api_webbuilder_to_delete',
            'api_webbuilder_vi_get',
            'api_webbuilder_vi_post',
            'api_webbuilder_vi_delete',
            'api_webbuilder_go_get',
            'api_webbuilder_go_post',
            'api_webbuilder_go_delete'
        );
        foreach ($accessList as $accessElement) {
            if (!$acl->has($accessElement)) {
                $acl->addResource($accessElement);
            }
            $acl->allow(Shopping::ROLE_SALESPERSON, $accessElement);
        }

        Zend_Registry::set('acl', $acl);
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
        $this->_view->sid = $this->_request->getParam('sid');

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

    /**
     * @return array
     * @throws Zend_Exception
     */
    public static function systemUserDeleteErrorMessage()
    {
        $translator = Zend_Registry::get('Zend_Translate');
        $systemUserDeleteErrorMessage = $translator->translate('This user can\'t be deleted. User is used in quote.');
        return $systemUserDeleteErrorMessage;
    }


    public function getPaymenttypeinfoAction()
    {
        $accessList = array(
            Tools_Security_Acl::ROLE_SUPERADMIN,
            Tools_Security_Acl::ROLE_ADMIN,
            Shopping::ROLE_SALESPERSON
        );

        if (in_array($this->_sessionHelper->getCurrentUser()->getRoleId(), $accessList)) {
            $paymentType = filter_var($this->_request->getParam('paymentType'), FILTER_SANITIZE_STRING);
            $quoteId = filter_var($this->_request->getParam('quoteId'), FILTER_SANITIZE_STRING);
            $isSignatureRequired = filter_var($this->_request->getParam('isSignatureRequired'),
                FILTER_SANITIZE_NUMBER_INT);
            $quoteMapper = Quote_Models_Mapper_QuoteMapper::getInstance();
            $quoteModel = $quoteMapper->find($quoteId);
            if ($quoteModel instanceof Quote_Models_Model_Quote) {
                $cart = Models_Mapper_CartSessionMapper::getInstance()->find($quoteModel->getCartId());
                $currency = Zend_Registry::get('Zend_Currency');
                $quoteTotal = $currency->toCurrency($cart->getTotal());
                $message = '';
                if ($paymentType === Quote_Models_Model_Quote::PAYMENT_TYPE_FULL) {
                    if (!empty($isSignatureRequired)) {
                        $message = $this->_translator->translate('Please sign and validate your signature first, then to get things started, please make a full payment') . ': ' . $quoteTotal .' '. $this->_translator->translate('using the credit card form below');
                    } else {
                        $message = $this->_translator->translate('To get things started please make payment of') . ' <span>' . $quoteTotal .'</span> '. $this->_translator->translate('using the credit card form below');
                    }
                }

                if ($paymentType === Quote_Models_Model_Quote::PAYMENT_TYPE_PARTIAL_PAYMENT) {
                    $currency = Zend_Registry::get('Zend_Currency');
                    $partialPercentage = (int) $cart->getPartialPercentage();
                    $this->_view->partialPercentage = $partialPercentage;
                    $this->_view->partialToPayAmount = $currency->toCurrency(($partialPercentage * $cart->getTotal()/100));
                    $message = $this->_view->render('partial-payment-select-info.phtml');
                }

                $this->_responseHelper->success($message);
            }

            $this->_responseHelper->fail('');
        }
    }

    public function checkquoteExpiredAction()
    {
        if (Tools_Security_Acl::isAllowed(Shopping::RESOURCE_STORE_MANAGEMENT)) {
            $quoteId = filter_var($this->_request->getParam('quoteId'), FILTER_SANITIZE_STRING);
            if (!empty($quoteId)) {
                $quoteModel = Quote_Models_Mapper_QuoteMapper::getInstance()->find($quoteId);
                if ($quoteModel instanceof Quote_Models_Model_Quote) {
                    if ($quoteModel->getStatus() == Quote_Models_Model_Quote::STATUS_LOST || strtotime($quoteModel->getExpiresAt()) <= strtotime('now')) {
                        $this->_responseHelper->fail($this->_translator->translate('You can\'t send expired quote.'));
                    }
                    $this->_responseHelper->success('');
                }
            }
        }
    }


    /**
     * Save draggable quote products in selected order
     */
    public function saveDragListOrderAction()
    {
        $currentRole = $this->_sessionHelper->getCurrentUser()->getRoleId();
        if (($currentRole === Tools_Security_Acl::ROLE_SUPERADMIN || $currentRole === Tools_Security_Acl::ROLE_ADMIN || $currentRole === Shopping::ROLE_SALESPERSON) && $this->_request->isPost()) {
            $draggData = filter_var_array($this->_request->getParams(), FILTER_SANITIZE_STRING);

            if(!empty($draggData['quoteId'])) {
                $quoteDraggableMapper = Quote_Models_Mapper_QuoteDraggableMapper::getInstance();

                $quoteId = $draggData['quoteId'];
                $data = $draggData['data'];

                $quoteDraggableModel = $quoteDraggableMapper->findByQuoteId($quoteId);

                if($quoteDraggableModel instanceof Quote_Models_Model_QuoteDraggableModel) {
                    $quoteDraggableModel->setData(implode(',', $data));
                } else {
                    $quoteDraggableModel = new Quote_Models_Model_QuoteDraggableModel();
                    $quoteDraggableModel->setQuoteId($quoteId);
                    $quoteDraggableModel->setData(implode(',', $data));
                }

                $quoteDraggableMapper->save($quoteDraggableModel);

                $this->_responseHelper->success($this->_translator->translate('Order has been updated'));
            }
        }

        $this->_responseHelper->fail($this->_translator->translate('Cannot save quote draggable configuration.'));
    }

    public function downloadquotepdfAction()
    {
        $quoteId = filter_var($this->_request->getParam('quoteId'), FILTER_SANITIZE_STRING);
        $quoteMapper = Quote_Models_Mapper_QuoteMapper::getInstance();

        $translator = Zend_Registry::get('Zend_Translate');
        if (empty($quoteId)) {
            $this->_error($translator->translate('Quote id is missing'));
        }

        $quote = $quoteMapper->find($quoteId);
        if (!$quote instanceof Quote_Models_Model_Quote) {
            $this->_error($translator->translate('Quote not found'));
        }

        $pdfTemplate = Quote_Tools_Tools::findPdfTemplateByQuoteUrl($quote->getId() . '.html');
        if (!empty($pdfTemplate)) {
            $pdfTemplate = Application_Model_Mappers_TemplateMapper::getInstance()->find($pdfTemplate);
            if ($pdfTemplate instanceof Application_Model_Models_Template) {
                $websiteConfig = Zend_Registry::get('website');
                $pdfPath = $websiteConfig['path'] . 'plugins' . DIRECTORY_SEPARATOR . 'invoicetopdf' . DIRECTORY_SEPARATOR . 'invoices' . DIRECTORY_SEPARATOR;
                if (!defined('_MPDF_TEMP_PATH')) {
                    define('_MPDF_TEMP_PATH', $pdfPath);
                }

                require_once($websiteConfig['path'] . 'plugins' . DIRECTORY_SEPARATOR . 'invoicetopdf' . DIRECTORY_SEPARATOR . 'system/library/mpdf/mpdf.php');
                $pageMapper = Application_Model_Mappers_PageMapper::getInstance();
                $websiteHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('website');
                $themeData = Zend_Registry::get('theme');

                $session = Zend_Controller_Action_HelperBroker::getExistingHelper('session');
                $session->storeCartSessionConversionKey = $quote->getCartId();
                $registry = Zend_Registry::getInstance();
                $registry->set('processingAutoQuoteId', $quote->getId());

                $parserOptions = array(
                    'websiteUrl' => $websiteHelper->getUrl(),
                    'websitePath' => $websiteHelper->getPath(),
                    'currentTheme' => $websiteHelper->getConfig('currentTheme'),
                    'themePath' => $themeData['path'],
                );
                $page = $pageMapper->findByUrl($quote->getId() . '.html');
                $page = $page->toArray();

                $parser = new Tools_Content_Parser($pdfTemplate->getContent(), $page, $parserOptions);
                $content = $parser->parse();

                $pdfFile = new mPDF('utf-8', 'A4');
                $pdfFile->WriteHTML($content);

                $fileName = 'Proposal-quote-' . $quote->getTitle();
                $pdfFileName = $fileName . '.pdf';

                $filePath = $websiteHelper->getPath() . 'plugins' . DIRECTORY_SEPARATOR . 'quote' . DIRECTORY_SEPARATOR . 'quotePdf'
                    . DIRECTORY_SEPARATOR . $pdfFileName;
                $pdfFile->Output($filePath, 'F');

                if (file_exists($filePath)) {
                    $response = Zend_Controller_Front::getInstance()->getResponse();
                    $response->setHeader('Content-Disposition',
                        'attachment; filename=' . $fileName . '.' . pathinfo($pdfFileName,
                            PATHINFO_EXTENSION))
                        ->setHeader('Content-type', 'application/force-download');
                    readfile($filePath);
                    $response->sendResponse();
                    exit;
                }

            }
        }
    }

    public function getQuoteNamesAction()
    {
        if (Tools_Security_Acl::isAllowed(Tools_Security_Acl::RESOURCE_PLUGINS)) {
            if ($this->_request->isGet()) {
                $quoteMapper = Quote_Models_Mapper_QuoteMapper::getInstance();
                $searchTerm = filter_var($this->_request->getParam('searchTerm'), FILTER_SANITIZE_STRING);
                $where = $quoteMapper->getDbTable()->getAdapter()->quoteInto('sq.title LIKE ?',
                    $searchTerm . '%');
                $data = $quoteMapper->searchQuotes($where, null, null, null, true);

                echo json_encode($data);
            }
        }
    }

}
