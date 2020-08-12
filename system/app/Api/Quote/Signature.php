<?php

class Api_Quote_Signature extends Api_Service_Abstract
{

    protected $_accessList = array(
        Tools_Security_Acl::ROLE_SUPERADMIN => array('allow' => array('get', 'post', 'put', 'delete')),
        Tools_Security_Acl::ROLE_ADMIN => array('allow' => array('get', 'post', 'put', 'delete')),
        Shopping::ROLE_SALESPERSON => array('allow' => array('get', 'post', 'put', 'delete')),
        Tools_Security_Acl::ROLE_GUEST => array('allow' => array('get', 'post', 'put', 'delete')),
        Tools_Security_Acl::ROLE_MEMBER => array('allow' => array('get', 'post', 'put', 'delete')),
        Tools_Security_Acl::ROLE_USER => array('allow' => array('get', 'post', 'put', 'delete'))
    );

    public function init()
    {
        parent::init();
        $this->_responseHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('response');
    }

    public function getAction()
    {
    }

    public function postAction()
    {
        $quoteMapper = Quote_Models_Mapper_QuoteMapper::getInstance();
        $quoteId = filter_var($this->_request->getParam('quoteId'), FILTER_SANITIZE_STRING);
        $signature = filter_var($this->_request->getParam('signature'), FILTER_SANITIZE_STRING);

        $translator = Zend_Registry::get('Zend_Translate');
        if (empty($quoteId)) {
            $this->_error($translator->translate('Quote id is missing'));
        }

        $quote = $quoteMapper->find($quoteId);
        if (!$quote instanceof Quote_Models_Model_Quote) {
            $this->_error($translator->translate('Quote not found'));
        }

        $isQuoteSigned = $quote->getIsQuoteSigned();
        if (!empty($isQuoteSigned)) {
            $this->_error($translator->translate('This quote already signed'));
        }

        $quote->setIsQuoteSigned('1');
        $quote->setSignature($signature);
        $quote->setQuoteSignedAt(Tools_System_Tools::convertDateFromTimezone('now'));

        $pdfTemplate = $quote->getPdfTemplate();
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

                $session = Zend_Controller_Action_HelperBroker::getExistingHelper('session');
                $session->storeCartSessionConversionKey = $quote->getCartId();

                $pdfFileName = 'Quote_' . md5($quote->getId() . microtime()) . '.pdf';

                $storedData = Tools_LeadDocumentsTools::generateStoredName(array('name' => $pdfFileName));

                $leadsMapper = Leads_Mapper_LeadsMapper::getInstance();
                $leadModel = $leadsMapper->findByUserId($quote->getUserId());
                if (!$leadModel instanceof Leads_Model_LeadsModel) {
                    $userModel = Application_Model_Mappers_UserMapper::getInstance()->find($quote->getUserId());
                    if ($userModel instanceof Application_Model_Models_User) {
                        $leadModel = $leadsMapper->findByEmail($userModel->getEmail());
                    }

                }

                if ($leadModel instanceof Leads_Model_LeadsModel) {
                    $savePath = Tools_LeadDocumentsTools::getFilePath($storedData['fileStoredName']);
                    $leadId = $leadModel->getId();
                    $leadsDocumentsMapper = Leads_Mapper_LeadsDocumentsMapper::getInstance();
                    $leadsDocumentsModel = new Leads_Model_LeadsDocumentsModel();
                    $leadsDocumentsModel->setLeadId($leadId);
                    $leadsDocumentsModel->setFileStoredName($storedData['fileStoredName']);
                    $leadsDocumentsModel->setFileHash($storedData['fileHash']);
                    $leadsDocumentsModel->setOriginalFileName($storedData['fileName'] . '.' . $storedData['fileExtension']);
                    $leadsDocumentsModel->setDisplayFileName($storedData['fileName']);
                    $leadsDocumentsModel->setUploadedAt(date(Tools_System_Tools::DATE_MYSQL));

                    $leadsDocumentsMapper->save($leadsDocumentsModel);
                    $pdfFile->Output($savePath, 'F');
                }

            }
        }

        if ($quote->getPaymentType() === Quote_Models_Model_Quote::PAYMENT_TYPE_ONLY_SIGNATURE) {
            $quote->setStatus(Quote_Models_Model_Quote::STATUS_SOLD);
            $quote->registerObserver(new Quote_Tools_Watchdog(array(
                'gateway' => new Quote(array(), array())
            )))->registerObserver(new Quote_Tools_GarbageCollector(array(
                'action' => Tools_System_GarbageCollector::CLEAN_ONUPDATE
            )));
        }

        $quoteMapper->save($quote);

        $this->_responseHelper->success($translator->translate('Quote has been signed'));

    }

    public function putAction()
    {
    }

    public function deleteAction()
    {
    }
}
