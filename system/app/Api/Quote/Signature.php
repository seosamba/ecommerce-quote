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

        if (strlen(base64_decode($signature)) < 2691) {
            $this->_error($translator->translate('Please sign the quote'));
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
        $quoteMapper->save($quote);

        $attachment = '';

        $pdfTemplate = Quote_Tools_Tools::findPdfTemplateByQuoteUrl($quote->getId().'.html');
        $quote->setPdfTemplate($pdfTemplate);

        if (!empty($pdfTemplate)) {
            $pdfTemplate = Application_Model_Mappers_TemplateMapper::getInstance()->find($pdfTemplate);
            if ($pdfTemplate instanceof Application_Model_Models_Template) {
                $websiteConfig = Zend_Registry::get('website');
                $pdfTmpPath = $websiteConfig['path'] . 'plugins' . DIRECTORY_SEPARATOR . 'invoicetopdf' . DIRECTORY_SEPARATOR . 'invoices' . DIRECTORY_SEPARATOR;
//                if (!defined('_MPDF_TEMP_PATH')) {
//                    define('_MPDF_TEMP_PATH', $pdfPath);
//                }

                require_once($websiteConfig['path'] . 'plugins' . DIRECTORY_SEPARATOR . 'invoicetopdf' . DIRECTORY_SEPARATOR.'system/library/mpdflatest/vendor/autoload.php');

//                require_once($websiteConfig['path'] . 'plugins' . DIRECTORY_SEPARATOR . 'invoicetopdf' . DIRECTORY_SEPARATOR . 'system/library/mpdf/mpdf.php');
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

                //$pdfFile = new mPDF('utf-8', 'A4');
                $pdfFile = new \Mpdf\Mpdf([
                    'mode' => 'utf-8',
                    'format' => 'A4',
                    'tempDir' => $pdfTmpPath
                ]);
                $pdfFile->WriteHTML($content);

                $quoteProposalQuote = Models_Mapper_ShoppingConfig::getInstance()->getConfigParam('quoteDownloadLabel');

                if (!empty($quoteProposalQuote)) {
                    $pdfFileName = $quoteProposalQuote.'-' . $quote->getTitle() . '.pdf';
                    $pdfFileNameLocally = $quoteProposalQuote.'-' . $quote->getId() . '.pdf';
                } else {
                    $pdfFileName = 'Proposal-quote-' . $quote->getTitle() . '.pdf';
                    $pdfFileNameLocally = 'Proposal-quote-' . $quote->getId() . '.pdf';
                }

                $storedData = Tools_LeadDocumentsTools::generateStoredName(array('name' => $pdfFileName));
                $storedDataLocally = Quote_Tools_Tools::generateStoredName(array('name' => $pdfFileNameLocally), false);

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
                    $quoteSaveLocallyPath = Quote_Tools_Tools::getFilePath($storedDataLocally['fileStoredName']);
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
                    $pdfFile->Output($quoteSaveLocallyPath, 'F');

                    $attachment = new Zend_Mime_Part(file_get_contents($savePath));
                    $attachment->type = 'application/pdf';
                    $attachment->disposition = Zend_Mime::DISPOSITION_ATTACHMENT;
                    $attachment->encoding = Zend_Mime::ENCODING_BASE64;
                    $attachment->filename = $storedData['fileName'];
                }

            }
        }

        $observableModel = '';
        if (!empty($leadModel) && $leadModel instanceof Leads_Model_LeadsModel) {
            $observableModel = $leadModel;
        } else {
            $observableModel = $quote;
        }

        $quote->registerObserver(new Tools_Mail_Watchdog(array(
            'trigger'     => Quote_Tools_QuoteMailWatchdog::TRIGGER_QUOTE_SIGNED,
            'attachment'  =>  $attachment,
            'observableModel' => $observableModel
        )));


        $message = 'Thank you! A confirmation email with a copy of this agreement has been sent to you. Now to effectively place this order, please make a payment as instructed.';

        $reloadPage = false;
        if ($quote->getPaymentType() === Quote_Models_Model_Quote::PAYMENT_TYPE_ONLY_SIGNATURE) {
            $quote->setStatus(Quote_Models_Model_Quote::STATUS_SIGNATURE_ONLY_SIGNED);
//            $quote->registerObserver(new Quote_Tools_Watchdog(array(
//                'gateway' => new Quote(array(), array())
//            )))->registerObserver(new Quote_Tools_GarbageCollector(array(
//                'action' => Tools_System_GarbageCollector::CLEAN_ONUPDATE
//            )));
            $cartSessionMapper = Models_Mapper_CartSessionMapper::getInstance();
            $cart = $cartSessionMapper->find($quote->getCartId());
            if ($cart instanceof Models_Model_CartSession) {
                $cart->setStatus(Models_Model_CartSession::CART_STATUS_NOT_VERIFIED);
                $cartSessionMapper->save($cart);
            }

            $message = 'Thank you! A confirmation email with a copy of this agreement has been sent to you.';
            $reloadPage = true;
        }

        $quoteMapper->save($quote);
        if ($reloadPage === false) {
            $this->_responseHelper->success($translator->translate($message));
        } else {
            $this->_responseHelper->success(array('reload' => '1', 'message' => $translator->translate($message)));
        }

    }

    public function putAction()
    {
    }

    public function deleteAction()
    {
    }
}
