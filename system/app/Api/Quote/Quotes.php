<?php
/**
 * Quote
 *
 * @author: iamne <eugene@seotoaster.com> Seotoaster core team
 * Date: 9/5/12
 * Time: 12:59 PM
 * @TODO : refactor this damn thing
 */
class Api_Quote_Quotes extends Api_Service_Abstract {

    /**
     * Instance of quote mapper
     *
     * @var Quote_Models_Mapper_QuoteMapper
     */
    private $_quoteMapper    = null;

    /**
     * E-commerce preferences
     *
     * @var null| array
     */
    private $_shoppingConfig = null;

    /**
     * Session based cart storage. Storage is able to do all calculation process
     *
     * @var null|Tools_ShoppingCart
     */
    private $_cartStorage    = null;

    /**
     * Access list for the API resources
     *
     * @var array
     */
    protected $_accessList   = array(
        Tools_Security_Acl::ROLE_SUPERADMIN => array('allow' => array('get', 'post', 'put', 'delete')),
        Tools_Security_Acl::ROLE_ADMIN      => array('allow' => array('get', 'post', 'put', 'delete')),
        Shopping::ROLE_SALESPERSON          => array('allow' => array('get', 'post', 'put', 'delete')),
        Tools_Security_Acl::ROLE_GUEST      => array('allow' => array('post'))
    );

    public static $_alreadyPaidStatuses = array(
        Models_Model_CartSession::CART_STATUS_COMPLETED,
        Models_Model_CartSession::CART_STATUS_PARTIAL,
        Models_Model_CartSession::CART_STATUS_REFUNDED,
        Models_Model_CartSession::CART_STATUS_NOT_VERIFIED,
        Models_Model_CartSession::CART_STATUS_DELIVERED,
        Models_Model_CartSession::CART_STATUS_SHIPPED
    );

    /**
     * Initialization
     *
     */
    public function init() {
        $this->_quoteMapper    = Quote_Models_Mapper_QuoteMapper::getInstance();
        $this->_shoppingConfig = Models_Mapper_ShoppingConfig::getInstance()->getConfigParams();
        $this->_cartStorage    = Tools_ShoppingCart::getInstance();
    }

    public function getAction() {
        $quoteId = filter_var($this->_request->getParam('id'), FILTER_SANITIZE_STRING);

        if($quoteId) {
            $quote = $this->_quoteMapper->find($quoteId);
            if($quote instanceof Quote_Models_Model_Quote) {
                return $quote->toArray();
            }
            $this->_error(null, self::REST_STATUS_NOT_FOUND);
        }

        //retrieve and validate additional parameters
        $count     = (bool) $this->_request->has('count');
        $offset    = filter_var($this->_request->getParam('offset'), FILTER_SANITIZE_NUMBER_INT);
        $limit     = filter_var($this->_request->getParam('limit'), FILTER_SANITIZE_NUMBER_INT);
        $order     = filter_var($this->_request->getParam('order', 'created_at'), FILTER_SANITIZE_STRING);
        $orderType = filter_var($this->_request->getParam('orderType', 'desc'), FILTER_SANITIZE_STRING);
        $search    = filter_var($this->_request->getParam('search'), FILTER_SANITIZE_STRING);
        $quoteOwnerId    = filter_var($this->_request->getParam('quoteOwnerId'), FILTER_SANITIZE_NUMBER_INT);
        $quoteStatusName    = filter_var($this->_request->getParam('quoteStatusName'), FILTER_SANITIZE_STRING);

        $where = '';
        if(!empty($quoteOwnerId)) {
            $where = $this->_quoteMapper->getDbTable()->getAdapter()->quoteInto('s_q.creator_id = ?', $quoteOwnerId);
        }

        if(!empty($quoteStatusName)) {
            if(!empty($where)) {
                $where .= ' AND ';
            }

            $where .= $this->_quoteMapper->getDbTable()->getAdapter()->quoteInto('s_q.status = ?', $quoteStatusName);
        }

        $quotes    = $this->_quoteMapper->fetchAll(
            ($where)  ? $where : null,
            ($order)  ? array($order . ' ' . strtoupper($orderType)) : array(),
            ($limit)  ? $limit : null,
            ($offset) ? $offset : null,
            ($search) ? $search : null,
            ($count)  ? $count : null
        );

        if($count) {
            return $quotes;
        }
        return array_map(function($quote) {return $quote->toArray();}, $quotes);
    }

    public function postAction() {
        $translator = Zend_Registry::get('Zend_Translate');
        $type          = filter_var($this->_request->getParam('type'), FILTER_SANITIZE_STRING);
        $duplicateQuoteId  = filter_var($this->_request->getParam('duplicateQuoteId'), FILTER_SANITIZE_STRING);
        $quoteTitle  = filter_var($this->_request->getParam('quoteTitle'), FILTER_SANITIZE_STRING);
        $cart          = null;
        $cartMapper    = Models_Mapper_CartSessionMapper::getInstance();
        $responseHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('response');
        $currentUser   = Application_Model_Mappers_UserMapper::getInstance()->find(Zend_Controller_Action_HelperBroker::getStaticHelper('session')->getCurrentUser()->getId());
        if($currentUser instanceof Application_Model_Models_User){
            $editedBy      = $currentUser->getFullName();
            $creatorId     = $currentUser->getId();
        }else{
            $editedBy   = Shopping::ROLE_CUSTOMER;
            $creatorId  = 0;
        }
        $quoteId = 0;
        $oldPageId = 0;

        if (empty($quoteTitle)) {
            $quoteTitle = '';
        }

        switch($type) {
            case Quote::QUOTE_TYPE_GENERATE:
                $form        = new Quote_Forms_Quote();
                $data        = $this->_request->getParams();

                if(!empty($data['formOptions'])) {
                    $sessionFormOptions = $data['formOptions'];
                    $formOptions = Zend_Controller_Action_HelperBroker::getStaticHelper('session')->$sessionFormOptions;
                }

                if($formOptions) {
                    $form = Quote_Tools_Tools::adjustFormFields($form, $formOptions, array('productId' => false, 'productOptions' => false, 'sendQuote' => false));
                }

                $shoppingConfig = Models_Mapper_ShoppingConfig::getInstance()->getConfigParams();
                if (!empty($shoppingConfig['maxProductsInQuote'])) {
                    $inCartContent = $this->_cartStorage->getContent();
                    if (intval($shoppingConfig['maxProductsInQuote']) > 0 && !empty($inCartContent)) {
                        $qtyInCart = 0;
                        foreach ($inCartContent as $content) {
                            $qtyInCart += $content['qty'];
                        }

                        if ($qtyInCart >= $shoppingConfig['maxProductsInQuote']) {
                            $this->_error($translator->translate('The number of products in the cart exceeds the allowed limit!'));
                        }
                    }
                }

                $isAlreadyPayed = Tools_ShoppingCart::verifyIfAlreadyPayed();
                if ($isAlreadyPayed === true) {
                    $cartStorage = Tools_ShoppingCart::getInstance();
                    $cartStorage->clean();
                    $this->_error($translator->translate('Your cart content was changed'));
                }

                if (!Tools_Security_Acl::isAllowed(Shopping::RESOURCE_STORE_MANAGEMENT)) {
                    $googleRecaptcha = new Tools_System_GoogleRecaptcha();
                    if (!$form->isValid($this->_request->getParams()) || empty($data['g-recaptcha-response']) || !$googleRecaptcha->isValid($data['g-recaptcha-response'])) {
                        $this->_error($translator->translate('Sorry, but you didn\'t fill all the required fields or you entered a wrong captcha. Please try again.'));
                    }
                } else {
                    if (!$form->isValid($this->_request->getParams())) {
                        $this->_error($translator->translate('Sorry, but you didn\'t fill all the required fields. Please try again.'));
                    }
                }

                $formData = filter_var_array($form->getValues(), FILTER_SANITIZE_STRING);

                $cartId = $this->_cartStorage->getCartId();
                $quote = null;
                if (!empty($cartId)) {
                    $quote = Quote_Models_Mapper_QuoteMapper::getInstance()->findByCartId($cartId);
                }

                //if we have a product id passed then this is a single product quote request and we should add product to the cart
                $initialProducts = array();
                if (isset($formData['productId']) && $formData['productId']) {
                    $initialProducts[] = array(
                        'product' => Models_Mapper_ProductMapper::getInstance()->find($formData['productId']),
                        'options' => Quote_Tools_Tools::parseOptionsString($formData['productOptions'])
                    );
                    if (!isset($data[md5($formData['productId'])]) || $data[md5($formData['productId'])] !== '') {
                        $responseHelper->success('');
                    }
                } else {
                    $cartId = $this->_cartStorage->getCartId();
                    if (!isset($data[md5($cartId)]) || $data[md5($cartId)] !== '') {
                        $responseHelper->success('');
                    }
                }

                if ($quote instanceof Quote_Models_Model_Quote) {
                    $cart = Quote_Tools_Tools::invokeCart($quote, $initialProducts, true);
                } else {
                    $cart = Quote_Tools_Tools::invokeCart(null, $initialProducts);
                }

                if (!empty($formData['phone'])) {
                    $formData['phone'] = Quote_Tools_Tools::cleanNumber($formData['phone']);
                    if (!empty($formData['phonecountrycode'])) {
                        $mobileCountryPhoneCode = Zend_Locale::getTranslation($formData['phonecountrycode'],
                            'phoneToTerritory');
                        $formData['phone_country_code_value'] = '+' . $mobileCountryPhoneCode;
                    } else {
                        $formData['phone_country_code_value'] = null;
                    }
                }

                if (!empty($formData['mobile'])) {
                    $formData['mobile'] = Quote_Tools_Tools::cleanNumber($formData['mobile']);
                    if (!empty($formData['mobilecountrycode'])) {
                        $mobileCountryPhoneCode = Zend_Locale::getTranslation($formData['mobilecountrycode'],
                            'phoneToTerritory');
                        $formData['mobile_country_code_value'] = '+' . $mobileCountryPhoneCode;
                    } else {
                        $formData['mobile_country_code_value'] = null;
                    }
                }

                if (empty($formData['country'])) {
                    $shoppingConfig = Models_Mapper_ShoppingConfig::getInstance()->getConfigParams();
                    if (!empty($shoppingConfig['country'])) {
                        if (empty($countryCode)) {
                            $formData['country'] = $shoppingConfig['country'];
                        }
                    }
                }

                $configHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('config');
                $userDefaultMobileCountryCode = $configHelper->getConfig('userDefaultPhoneMobileCode');
                if (!empty($userDefaultMobileCountryCode)) {
                    if (empty($formData['mobilecountrycode'])) {
                        $formData['mobilecountrycode'] = $userDefaultMobileCountryCode;
                    }

                    if (empty($formData['phonecountrycode'])) {
                        $formData['phonecountrycode'] = $userDefaultMobileCountryCode;
                    }
                }

                $customer = Shopping::processCustomer($formData);
                if(!$cart) {
                    $this->_error($translator->translate('Server encountered a problem. Unable to create quote'));
                }

                $cart = $cartMapper->save(
                    $cart->setBillingAddressId(Quote_Tools_Tools::addAddress($formData, Models_Model_Customer::ADDRESS_TYPE_BILLING, $customer))
                        ->setShippingAddressId(Quote_Tools_Tools::addAddress($formData, Models_Model_Customer::ADDRESS_TYPE_SHIPPING, $customer))
                        ->setUserId($customer->getId())
                );

                $enableQuoteDefaultType = Models_Mapper_ShoppingConfig::getInstance()->getConfigParam('enableQuoteDefaultType');
                if (!empty($enableQuoteDefaultType)) {
                    $quotePaymentType = Models_Mapper_ShoppingConfig::getInstance()->getConfigParam('quotePaymentType');
                    $quotePartialPercentage = Models_Mapper_ShoppingConfig::getInstance()->getConfigParam('quotePartialPercentage');
                    $quotePartialType = Models_Mapper_ShoppingConfig::getInstance()->getConfigParam('quotePartialType');
                    if (($quotePaymentType === Quote_Models_Model_Quote::PAYMENT_TYPE_PARTIAL_PAYMENT || $quotePaymentType === Quote_Models_Model_Quote::PAYMENT_TYPE_PARTIAL_PAYMENT_SIGNATURE) && !empty($quotePartialPercentage)) {
                        $cart->setIsPartial('1');
                        $cart->setPartialPercentage($quotePartialPercentage);
                        if (!empty($quotePartialType)) {
                            $cart->setPartialType($quotePartialType);
                        } else {
                            $cart->setPartialType(null);
                        }
                    }
                }

                //disable tax if user from another zone
                $cartSession = Tools_ShoppingCart::getInstance();
                $cartSession->setBillingAddressKey($cart->getBillingAddressId());
                $cartSession->setShippingAddressKey($cart->getShippingAddressId());
                $cartSession->calculate(true);
                $cartSession->saveCartSession();

                $shippingServiceInfo = $cartSession->getShippingData();
                $serviceName = '';
                if (!empty($shippingServiceInfo)) {
                    $serviceName = $shippingServiceInfo['service'];
                }

                $shippingServices = Models_Mapper_ShippingConfigMapper::getInstance()->fetchByStatus(
                    Models_Mapper_ShippingConfigMapper::STATUS_ENABLED
                );
                if (!empty($shippingServices) && $serviceName !== 'pickup') {
                    $shippingServices = array_map(
                        function ($shipper) {
                            return in_array($shipper['name'], array(Shopping::SHIPPING_FLATRATE)) ? array(
                                'name' => $shipper['name'],
                                'title' => isset($shipper['config']) && isset($shipper['config']['title']) ? $shipper['config']['title'] : null
                            ) : null;
                        },
                        $shippingServices
                    );
                    $shippingService = array_values(array_filter($shippingServices));
                    if (!empty($shippingService)) {
                        $flatratePlugin = Tools_Factory_PluginFactory::createPlugin(Shopping::SHIPPING_FLATRATE);
                        $result = $flatratePlugin->calculateAction(true);
                        if (!empty($result) && isset($result['price'])) {
                            $cart->setShippingPrice($result['price']);
                        }
                    }
                }

                $cart = $cartMapper->save($cart);

                $customFieldOpt = preg_grep("/^customfields/", $formOptions);

                if(!empty($customFieldOpt)) {
                    $quoteCustomFieldsConfigMapper = Quote_Models_Mapper_QuoteCustomFieldsConfigMapper::getInstance();
                    $customFields = $quoteCustomFieldsConfigMapper->fetchAll(null, null, null, null, true);
                    $customFieldsArray = array();

                    if(!empty($customFields)) {
                        foreach ($customFieldOpt as $customfieldsOptionKey => $opt) {
                            if (!empty($formOptions[$customfieldsOptionKey + 1])) {
                                $customfieldsOptions = array_filter(explode(',', $formOptions[$customfieldsOptionKey + 1]));

                                if (!empty($customfieldsOptions)) {
                                    foreach ($customFields as $key => $field) {
                                        if (in_array($field['param_name'], $customfieldsOptions)) {
                                            $customFieldsArray[$key] = $field;
                                        }
                                    }
                                }
                            }
                        }

                        if(!empty($customFieldsArray)) {
                            $quoteCustomParamsDataMapper = Quote_Models_Mapper_QuoteCustomParamsDataMapper::getInstance();

                            foreach ($customFieldsArray as $field) {
                                if(array_key_exists($field['param_name'], $data) && !empty($data[$field['param_name']])) {

                                    $quoteCustomParamsDataModel = $quoteCustomParamsDataMapper->checkIfParamExists($cartId, $field['id']);

                                    if(!$quoteCustomParamsDataModel instanceof Quote_Models_Model_QuoteCustomParamsDataModel) {
                                        $quoteCustomParamsDataModel = new Quote_Models_Model_QuoteCustomParamsDataModel();

                                        $quoteCustomParamsDataModel->setCartId($cart->getId());
                                        $quoteCustomParamsDataModel->setParamId($field['id']);
                                    }

                                    if($field['param_type'] == Quote_Models_Model_QuoteCustomFieldsConfigModel::CUSTOM_PARAM_TYPE_TEXT) {
                                        $quoteCustomParamsDataModel->setParamValue($data[$field['param_name']]);
                                    } elseif ($field['param_type'] == Quote_Models_Model_QuoteCustomFieldsConfigModel::CUSTOM_PARAM_TYPE_SELECT) {
                                        $quoteCustomParamsDataModel->setParamsOptionId($data[$field['param_name']]);
                                    } elseif ($field['param_type'] == Quote_Models_Model_QuoteCustomFieldsConfigModel::CUSTOM_PARAM_TYPE_RADIO) {
                                        //$quoteCustomParamsDataModel->setParamsOptionId($data[$field['param_name']]);
                                    } elseif ($field['param_type'] == Quote_Models_Model_QuoteCustomFieldsConfigModel::CUSTOM_PARAM_TYPE_TEXTAREA) {
                                        //$quoteCustomParamsDataModel->setParamValue($data[$field['param_name']]);
                                    } elseif ($field['param_type'] == Quote_Models_Model_QuoteCustomFieldsConfigModel::CUSTOM_PARAM_TYPE_CHECKBOX) {
                                        //$quoteCustomParamsDataModel->setParamsOptionId($data[$field['param_name']]);
                                    }

                                    $quoteCustomParamsDataMapper->save($quoteCustomParamsDataModel);
                                }
                            }
                        }
                    }
                }

                if(isset($this->_shoppingConfig['autoQuote']) && $this->_shoppingConfig['autoQuote']) {
                    $editedBy = Quote_Models_Model_Quote::QUOTE_TYPE_AUTO;
                }
            break;
            case Quote::QUOTE_TYPE_BUILD:
                if (Tools_Security_Acl::isAllowed(Shopping::RESOURCE_STORE_MANAGEMENT)) {
                    $cartSessionModel = new Models_Model_CartSession();
                    $cartSessionModel->setDiscountTaxRate(1);

                    $enableAdminQuoteDefaultType = Models_Mapper_ShoppingConfig::getInstance()->getConfigParam('defaultQuoteTypeForAdmin');
                    if (!empty($enableAdminQuoteDefaultType)) {
                        $quotePaymentType = Models_Mapper_ShoppingConfig::getInstance()->getConfigParam('quotePaymentType');
                        $quotePartialPercentage = Models_Mapper_ShoppingConfig::getInstance()->getConfigParam('quotePartialPercentage');
                        $quotePartialType = Models_Mapper_ShoppingConfig::getInstance()->getConfigParam('quotePartialType');
                        if (($quotePaymentType === Quote_Models_Model_Quote::PAYMENT_TYPE_PARTIAL_PAYMENT || $quotePaymentType === Quote_Models_Model_Quote::PAYMENT_TYPE_PARTIAL_PAYMENT_SIGNATURE) && !empty($quotePartialPercentage)) {
                            $cartSessionModel->setIsPartial('1');
                            $cartSessionModel->setPartialPercentage($quotePartialPercentage);
                            if (!empty($quotePartialType)) {
                                $cartSessionModel->setPartialType($quotePartialType);
                            } else {
                                $cartSessionModel->setPartialType(null);
                            }
                        }
                    }

                    $cart = $cartMapper->save($cartSessionModel);
                } else {
                    $this->_error();
                }
            break;
            case Quote::QUOTE_TYPE_CLONE:
                if (Tools_Security_Acl::isAllowed(Shopping::RESOURCE_STORE_MANAGEMENT)) {
                    $quoteId = filter_var($this->_request->getParam('quoteId'), FILTER_SANITIZE_STRING);
                    $oldPageId = filter_var($this->_request->getParam('pageId'), FILTER_SANITIZE_NUMBER_INT);

                    $errMsg = $translator->translate('Can\'t duplicate Quote');
                    if(!empty($quoteId)){
                        $quote = $this->_quoteMapper->find($quoteId);
                        if($quote instanceof Quote_Models_Model_Quote){
                            $errMsg = $translator->translate('Empty cart ID');
                            $cartId = $quote->getCartId();
                            if(!empty($cartId)){
                                $quoteCustomParamsDataMapper = Quote_Models_Mapper_QuoteCustomParamsDataMapper::getInstance();

                                $quoteCustomParamsData = $quoteCustomParamsDataMapper->findByCartId($cartId);

                                $currentCart = $cartMapper->find($cartId);
                                if($currentCart instanceof Models_Model_CartSession){
                                    $errMsg = '';
                                    $currentCart->setId(null);
                                    $currentCart->setStatus(Quote_Models_Model_Quote::STATUS_NEW);
                                    $currentCart->setPartialPaidAmount('0');
                                    $currentCart->setPurchasedOn(null);
                                    $currentCart->setPartialPurchasedOn(null);
                                    $cart =  $cartMapper->save($currentCart);

                                    $newCartId = $cart->getId();
                                    if(!empty($quoteCustomParamsData)) {
                                        foreach ($quoteCustomParamsData as $paramsData) {
                                            $quoteCustomParamsDataModel = new Quote_Models_Model_QuoteCustomParamsDataModel();
                                            $quoteCustomParamsDataModel->setCartId($newCartId);
                                            $quoteCustomParamsDataModel->setParamId($paramsData['param_id']);
                                            $quoteCustomParamsDataModel->setParamValue($paramsData['param_value']);
                                            $quoteCustomParamsDataModel->setParamsOptionId($paramsData['params_option_id']);

                                            $quoteCustomParamsDataMapper->save($quoteCustomParamsDataModel);
                                        }
                                    }
                                }
                            }

                            if(empty($oldPageId)) {
                                $quotePage = Application_Model_Mappers_PageMapper::getInstance()->findByUrl($quoteId . '.html');

                                if($quotePage instanceof Application_Model_Models_Page) {
                                    $oldPageId = $quotePage->getId();
                                }
                            }
                        }
                    }
                } else {
                    $this->_error();
                }
                if(!empty($errMsg)){
                    $this->_error($errMsg);
                }
            break;
            default:
                $this->_error();
            break;
        }
        try {

            $duplicateQuote = false;
            $pageMapper = Application_Model_Mappers_PageMapper::getInstance();
            if (!empty($duplicateQuoteId)) {
                $duplicateQuoteModel = $this->_quoteMapper->find($duplicateQuoteId);
                if (!$duplicateQuoteModel instanceof Quote_Models_Model_Quote) {
                    $this->_error($translator->translate('Quote for duplication not found'));
                }
                $duplicateQuote = true;
            }

            if ($duplicateQuote === true && $duplicateQuoteModel instanceof Quote_Models_Model_Quote) {
                $cartSessionModel = $cartMapper->find($duplicateQuoteModel->getCartId());
                if ($cartSessionModel instanceof Models_Model_CartSession){
                    $cartSessionModel->setId(null);
                    $cartSessionModel->setStatus(Quote_Models_Model_Quote::STATUS_NEW);
                    $cartSessionModel->setPartialPaidAmount('0');
                    $cartSessionModel->setPurchasedOn(null);
                    $cartSessionModel->setPartialPurchasedOn(null);
                    $cart =  $cartMapper->save($cartSessionModel);
                } else {
                    $this->_error($translator->translate('cart not found'));
                }

                $duplicateQuotePage = $pageMapper->findByUrl($duplicateQuoteModel->getId() . '.html');
                $oldPageId = 0;
                if ($duplicateQuotePage instanceof Application_Model_Models_Page) {
                    $oldPageId = $duplicateQuotePage->getId();
                }

                $quote = Quote_Tools_Tools::createQuote($cart,
                    array(
                        'editedBy' => $editedBy,
                        'creatorId' => $creatorId,
                        'disclaimer' => isset($formData['disclaimer']) ? $formData['disclaimer']: '',
                        'oldPageId' => $oldPageId,
                        'oldQuoteId' => $duplicateQuoteModel->getId(),
                        'actionType' => Quote::QUOTE_TYPE_CLONE,
                        'quoteTitle' => $quoteTitle
                    ));
            } else {
                $options = array(
                    'editedBy' => $editedBy,
                    'creatorId' => $creatorId,
                    'disclaimer' => isset($formData['disclaimer']) ? $formData['disclaimer'] : '',
                    'actionType' => $type,
                    'oldQuoteId' => $quoteId,
                    'oldPageId' => $oldPageId,
                    'quoteTitle' => $quoteTitle
                );

                if (!empty($templateName)) {
                    $options['templateName'] = $templateName;
                }

                $quote = Quote_Tools_Tools::createQuote($cart, $options);
            }
        } catch (Exception $e) {
            $this->_error($e->getMessage());
        }

        if($quote instanceof Quote_Models_Model_Quote) {
            $quoteData = $quote->toArray();
            $ownerInfo = Quote_Models_Mapper_QuoteMapper::getInstance()->getOwnerInfo($quoteData['id']);
            if (!empty($ownerInfo)) {
                $quoteData['ownerName'] =  $ownerInfo['ownerName'];
            }

            $enableQuoteDefaultType = Models_Mapper_ShoppingConfig::getInstance()->getConfigParam('enableQuoteDefaultType');
            if (!empty($enableQuoteDefaultType) && $type === Quote::QUOTE_TYPE_GENERATE) {
                $quotePaymentType = Models_Mapper_ShoppingConfig::getInstance()->getConfigParam('quotePaymentType');
                if (!empty($quotePaymentType)) {
                    $quotePaymentTypeName = $quotePaymentType;
                    if ($quotePaymentType === Quote_Models_Model_Quote::PAYMENT_TYPE_PARTIAL_PAYMENT_SIGNATURE) {
                        $quotePaymentTypeName = Quote_Models_Model_Quote::PAYMENT_TYPE_PARTIAL_PAYMENT;
                    }
                    if ($quotePaymentType === Quote_Models_Model_Quote::PAYMENT_TYPE_FULL_SIGNATURE) {
                        $quotePaymentTypeName = Quote_Models_Model_Quote::PAYMENT_TYPE_FULL;
                    }

                    $quote->setPaymentType($quotePaymentTypeName);
                    if ($quotePaymentType === Quote_Models_Model_Quote::PAYMENT_TYPE_ONLY_SIGNATURE || $quotePaymentType === Quote_Models_Model_Quote::PAYMENT_TYPE_PARTIAL_PAYMENT_SIGNATURE || $quotePaymentType === Quote_Models_Model_Quote::PAYMENT_TYPE_FULL_SIGNATURE) {
                        $quote->setIsSignatureRequired('1');
                    } else {
                        $quote->setIsSignatureRequired('0');
                    }
                    $this->_quoteMapper->save($quote);
                }
            }

            $enableAdminQuoteDefaultType = Models_Mapper_ShoppingConfig::getInstance()->getConfigParam('defaultQuoteTypeForAdmin');
            if (!empty($enableAdminQuoteDefaultType) && $type === Quote::QUOTE_TYPE_BUILD) {
                $quotePaymentType = Models_Mapper_ShoppingConfig::getInstance()->getConfigParam('quotePaymentType');
                if (!empty($quotePaymentType)) {
                    $quotePaymentTypeName = $quotePaymentType;
                    if ($quotePaymentType === Quote_Models_Model_Quote::PAYMENT_TYPE_PARTIAL_PAYMENT_SIGNATURE) {
                        $quotePaymentTypeName = Quote_Models_Model_Quote::PAYMENT_TYPE_PARTIAL_PAYMENT;
                    }
                    if ($quotePaymentType === Quote_Models_Model_Quote::PAYMENT_TYPE_FULL_SIGNATURE) {
                        $quotePaymentTypeName = Quote_Models_Model_Quote::PAYMENT_TYPE_FULL;
                    }

                    $quote->setPaymentType($quotePaymentTypeName);
                    if ($quotePaymentType === Quote_Models_Model_Quote::PAYMENT_TYPE_ONLY_SIGNATURE || $quotePaymentType === Quote_Models_Model_Quote::PAYMENT_TYPE_PARTIAL_PAYMENT_SIGNATURE || $quotePaymentType === Quote_Models_Model_Quote::PAYMENT_TYPE_FULL_SIGNATURE) {
                        $quote->setIsSignatureRequired('1');
                    } else {
                        $quote->setIsSignatureRequired('0');
                    }
                    $this->_quoteMapper->save($quote);
                }

            }

            return $quoteData;
        }
        $this->_error();
    }

    public function putAction() {
        $response = Zend_Controller_Action_HelperBroker::getStaticHelper('response');
        $translator = Zend_Registry::get('Zend_Translate');
        $quoteData = Zend_Json::decode($this->_request->getRawBody());
        $eventType = !empty($quoteData['eventType']) ? $quoteData['eventType'] : '';
        $additionalEmailValidate = !empty($quoteData['additionalEmailValidate']) ? $quoteData['additionalEmailValidate'] : '';
        $quoteId   = filter_var($quoteData['qid'], FILTER_SANITIZE_STRING);
        if(!$quoteId) {
            $quoteId = filter_var($quoteData['id'], FILTER_SANITIZE_STRING);
        }

        if(!$quoteId) {
            $this->_error('Not enough parameters', self::REST_STATUS_BAD_REQUEST);
        }

        $emailValidator = new Tools_System_CustomEmailValidator();

        $ccValidEmails = array();
        $ccEmailsArr = array();
        $ccEmails = filter_var($quoteData['ccEmails'], FILTER_SANITIZE_STRING);

        if(!empty($ccEmails)) {
            $ccEmailsArr = array_filter(array_unique(array_map('trim', explode(',', $ccEmails))));
        }

        if (!empty($ccEmailsArr)) {
            foreach ($ccEmailsArr as $ccEmail) {
                if (!$emailValidator->isValid($ccEmail)) {
                    $response->fail($translator->translate('Not valid email address') . ' - ' . $ccEmail);
                }
                $ccValidEmails[] = $ccEmail;
            }
        }

        $quote = $this->_quoteMapper->find($quoteId);

        if(!$quote instanceof Quote_Models_Model_Quote) {
            $this->_error('Quote not found', self:: REST_STATUS_NOT_FOUND);
        }

        $quotePdfTemplate = Quote_Tools_Tools::findPdfTemplateByQuoteUrl($quote->getId().'.html');
        $quoteData['pdfTemplate'] = $quotePdfTemplate;

        $currentUser = Application_Model_Mappers_UserMapper::getInstance()->find(Zend_Controller_Action_HelperBroker::getStaticHelper('session')->getCurrentUser()->getId());
        $quote->setEditedBy($currentUser->getFullName());
        $quote->setEditorId($currentUser->getId());

        $customer          = null;
        $cartSessionMapper = Models_Mapper_CartSessionMapper::getInstance();
        $cart              = $cartSessionMapper->find($quote->getCartId());

        if(!$cart instanceof Models_Model_CartSession) {
            $this->_error('Can\'t find cart assosiated with the current quote.', self::REST_STATUS_NO_CONTENT);
        }

        if (in_array($cart->getStatus(), self::$_alreadyPaidStatuses) && empty($quoteData['skipStatusVerification'])) {
            $response->fail($translator->translate('You can\'t edit the quote which is already paid.'));
        }

        // Update status outdated quote
        if (isset($quoteData['expiresAt']) && $quote->getStatus() == Quote_Models_Model_Quote::STATUS_LOST &&
            $quote->getExpiresAt() != date(Tools_System_Tools::DATE_MYSQL, strtotime($quoteData['expiresAt'])) &&
            date('Ymd', strtotime($quoteData['expiresAt'])) >= date('Ymd')) {
            $quote->setStatus(Quote_Models_Model_Quote::STATUS_NEW);
        }

        if(isset($quoteData['type']) && $quoteData['type']) {
            $value = floatval($quoteData['value']);
            if(!$value) {
                $value = 0;
            }

            switch($quoteData['type']) {
                case 'shipping': $cart->setShippingPrice($value); break;
                case 'discount': $cart->setDiscount($value); break;
                case 'taxrate' : $quote->setDiscountTaxRate($value); $cart->setDiscountTaxRate($value); break;
                case 'delivery': $quote->setDeliveryType($quoteData['value']); break;
                default: $this->_error('Wrong partial option');
            }

            $cartSessionMapper->save($cart);
            $this->_quoteMapper->save($quote);

        } else {
            $quote->setOptions($quoteData);

            // setting up observers
            $quote->registerObserver(new Quote_Tools_Watchdog(array(
                'gateway' => new Quote(array(), array())
            )))
            ->registerObserver(new Quote_Tools_GarbageCollector(array(
                'action' => Tools_System_GarbageCollector::CLEAN_ONUPDATE
            )));

            if($quoteData['sendMail']) {
                $quote->registerObserver(new Tools_Mail_Watchdog(array(
                    'trigger'     => Quote_Tools_QuoteMailWatchdog::TRIGGER_QUOTE_UPDATED,
                    'mailMessage' => $quoteData['mailMessage'],
                    'ccEmails'    => $ccValidEmails
                )));
                $quote->setStatus(Quote_Models_Model_Quote::STATUS_SENT);
            }

            if(isset($quoteData['billing'])) {
                if(!empty($quoteData['errorMessage']) /*&& empty($eventType)*/) {
                    if(empty($additionalEmailValidate)) {
                        $response->fail($translator->translate('Please fill in the required fields'));
                    }
                }

                parse_str($quoteData['billing'], $quoteData['billing']);

                if(!empty($eventType)) {
                    if(!$emailValidator->isValid($quoteData['billing']['email'])) {
                        if(!empty($quoteData['billing']['email'])) {
                            $response->fail($translator->translate('Please enter a valid email address'));
                        }
                        $response->success('');
                    }
                }

                $quoteData['billing']['phone'] = Quote_Tools_Tools::cleanNumber($quoteData['billing']['phone']);
                $quoteData['billing']['mobile'] = Quote_Tools_Tools::cleanNumber($quoteData['billing']['mobile']);
                if (!empty($quoteData['billing']['phonecountrycode'])) {
                    $mobileCountryPhoneCode = Zend_Locale::getTranslation($quoteData['billing']['phonecountrycode'], 'phoneToTerritory');
                    $quoteData['billing']['phone_country_code_value'] = '+'.$mobileCountryPhoneCode;
                } else {
                    $quoteData['billing']['phone_country_code_value'] = null;
                }

                if (!empty($quoteData['billing']['mobilecountrycode'])) {
                    $mobileCountryPhoneCode = Zend_Locale::getTranslation($quoteData['billing']['mobilecountrycode'], 'phoneToTerritory');
                    $quoteData['billing']['mobile_country_code_value'] = '+'.$mobileCountryPhoneCode;
                } else {
                    $quoteData['billing']['mobile_country_code_value'] = null;
                }

                if (!$emailValidator->isValid($quoteData['billing']['email']) && empty($eventType)) {
                    if(!empty($quoteData['billing']['email'])) {
                        $response->fail($translator->translate('Please enter a valid email address'));
                    }
                    $response->success('');
                }

	            if ($quote->getUserId() && empty($quoteData['billing']['overwriteQuoteUserBilling'])){
		            $customer = Models_Mapper_CustomerMapper::getInstance()->find($quote->getUserId());
	            } else {
	                if(!empty($quoteData['billing']['email'])) {
                        $customer = Quote_Tools_Tools::processCustomer($quoteData['billing']);
                        $quote->setUserId($customer->getId());
                    }
	            }

	            if($customer instanceof Models_Model_Customer) {
                    $cart->setBillingAddressId(
                        Models_Mapper_CustomerMapper::getInstance()->addAddress($customer, $quoteData['billing'], Models_Model_Customer::ADDRESS_TYPE_BILLING)
                    );
                }

            }

            if(isset($quoteData['shipping'])) {
                if(!empty($quoteData['errorMessage']) /*&& empty($eventType)*/) {
                    if(empty($additionalEmailValidate)) {
                        $response->fail($translator->translate('Please fill in the required fields'));
                    }
                }
                parse_str($quoteData['shipping'], $quoteData['shipping']);

                if(!empty($eventType)) {
                    if(!$emailValidator->isValid($quoteData['shipping']['email'])) {
                        if(!empty($quoteData['shipping']['email'])) {
                            $response->fail($translator->translate('Please enter a valid email address'));
                        }
                        $response->success('');
                    }
                }

                $quoteData['shipping']['phone'] = Quote_Tools_Tools::cleanNumber($quoteData['shipping']['phone']);
                $quoteData['shipping']['mobile'] = Quote_Tools_Tools::cleanNumber($quoteData['shipping']['mobile']);
                if (!empty($quoteData['shipping']['phonecountrycode'])) {
                    $mobileCountryPhoneCode = Zend_Locale::getTranslation($quoteData['shipping']['phonecountrycode'], 'phoneToTerritory');
                    $quoteData['shipping']['phone_country_code_value'] = '+'.$mobileCountryPhoneCode;
                } else {
                    $quoteData['shipping']['phone_country_code_value'] = null;
                }
                if (!empty($quoteData['shipping']['mobilecountrycode'])) {
                    $mobileCountryPhoneCode = Zend_Locale::getTranslation($quoteData['shipping']['mobilecountrycode'], 'phoneToTerritory');
                    $quoteData['shipping']['mobile_country_code_value'] = '+'.$mobileCountryPhoneCode;
                } else {
                    $quoteData['shipping']['mobile_country_code_value'] = null;
                }

                if (!$emailValidator->isValid($quoteData['shipping']['email']) && empty($eventType)) {
                    if(!empty($quoteData['shipping']['email'])) {
                        $response->fail($translator->translate('Please enter a valid email address'));
                    }
                    $response->success('');
                }

	            if (!$customer || !empty($quoteData['shipping']['overwriteQuoteUserShipping'])){
	                if(!empty($quoteData['shipping']['email'])) {
                        $customer = Quote_Tools_Tools::processCustomer($quoteData['shipping']);
                        $quote->setUserId($customer->getId());
                    }
	            }
                if($customer instanceof Models_Model_Customer) {
                    $cart->setShippingAddressId(
                        Models_Mapper_CustomerMapper::getInstance()->addAddress($customer, $quoteData['shipping'], Models_Model_Customer::ADDRESS_TYPE_SHIPPING)
                    );
                }
            }

            $cartId = $cart->getId();
            $quoteCustomParamsDataMapper = Quote_Models_Mapper_QuoteCustomParamsDataMapper::getInstance();

            $quoteCustomFieldsConfigMapper = Quote_Models_Mapper_QuoteCustomFieldsConfigMapper::getInstance();
            $customFields = $quoteCustomFieldsConfigMapper->fetchAll(null, null, null, null, true);

            if(!empty($customFields)) {
                foreach ($customFields as $field) {
                    if(isset($quoteData['billing'][$field['param_name']]) || isset($quoteData['shipping'][$field['param_name']])) {
                        $type = Widgets_Quote_Quote::ADDRESS_TYPE_BILLING;

                        $quoteCustomParamsDataModel = $quoteCustomParamsDataMapper->checkIfParamExists($cartId, $field['id']);

                        if(!$quoteCustomParamsDataModel instanceof Quote_Models_Model_QuoteCustomParamsDataModel) {
                            $quoteCustomParamsDataModel = new Quote_Models_Model_QuoteCustomParamsDataModel();

                            $quoteCustomParamsDataModel->setCartId($cartId);
                            $quoteCustomParamsDataModel->setParamId($field['id']);
                        }

                        if($field['param_type'] == Quote_Models_Model_QuoteCustomFieldsConfigModel::CUSTOM_PARAM_TYPE_TEXT) {
                            if(isset($quoteData['shipping'][$field['param_name']])) {
                                $type = Widgets_Quote_Quote::ADDRESS_TYPE_SHIPPING;
                            }

                            $quoteCustomParamsDataModel->setParamValue($quoteData[$type][$field['param_name']]);
                        } elseif ($field['param_type'] == Quote_Models_Model_QuoteCustomFieldsConfigModel::CUSTOM_PARAM_TYPE_SELECT) {
                            if(isset($quoteData['shipping'][$field['param_name']])) {
                                $type = Widgets_Quote_Quote::ADDRESS_TYPE_SHIPPING;
                            }

                            $quoteCustomParamsDataModel->setParamsOptionId($quoteData[$type][$field['param_name']]);
                        } elseif ($field['param_type'] == Quote_Models_Model_QuoteCustomFieldsConfigModel::CUSTOM_PARAM_TYPE_RADIO) {

                        } elseif ($field['param_type'] == Quote_Models_Model_QuoteCustomFieldsConfigModel::CUSTOM_PARAM_TYPE_TEXTAREA) {

                        } elseif ($field['param_type'] == Quote_Models_Model_QuoteCustomFieldsConfigModel::CUSTOM_PARAM_TYPE_CHECKBOX) {

                        }

                        $quoteCustomParamsDataMapper->save($quoteCustomParamsDataModel);
                    }
                }
            }

            if (!empty($quoteData['paymentType']) && $quoteData['paymentType'] === Quote_Models_Model_Quote::PAYMENT_TYPE_PARTIAL_PAYMENT) {
                $cart->setPartialPercentage($quoteData['partialPaymentPercentage']);
                $cart->setPartialType($quoteData['partialPaymentType']);
                $cart->setIsPartial('1');
            } else {
                $cart->setIsPartial('0');
                $cart->setPartialPercentage('');
                $cart->setPartialType(null);
            }

            if($customer instanceof Models_Model_Customer) {
                $cart->setUserId($customer->getId());
                Models_Mapper_CartSessionMapper::getInstance()->save($cart);
            }

            $this->_quoteMapper->save($quote);
        }
        $skipGroupPriceRecalculation = false;
        if (Tools_Security_Acl::isAllowed(Shopping::RESOURCE_STORE_MANAGEMENT)) {
            $skipGroupPriceRecalculation = true;
        }

        $quoteParams = Quote_Tools_Tools::calculate(Quote_Tools_Tools::invokeQuoteStorage($quoteId), false, true, $quoteId, $skipGroupPriceRecalculation);

        $allowAutosaveQuote = $this->_shoppingConfig['allowAutosave'];

        $quoteParams['allowAutosave'] = 0;
        $quoteParams['disableAutosaveEmail'] = 0;
        if(!empty($allowAutosaveQuote)) {
            $quoteParams['allowAutosave'] = $allowAutosaveQuote;

            $disableAutosaveEmailConfig = $this->_shoppingConfig['disableAutosaveEmail'];
            if(!empty($disableAutosaveEmailConfig)) {
                $quoteParams['disableAutosaveEmail'] = $disableAutosaveEmailConfig;
            }
        }


        if ($quoteData['sendMail']) {
            $response->success($translator->translate('The email has been sent'));
        }

        return $quoteParams;
    }

    public function deleteAction() {
        $ids = array_filter(filter_var_array(explode(',', $this->_request->getParam('id')), FILTER_SANITIZE_STRING));
        if(empty($ids)) {
            $this->_error();
        }
        $quotes = $this->_quoteMapper->fetchAll('`id` IN (' . join(', ', array_map(function($id) {return "'" . $id . "'"; }, $ids)) . ')');
        if($quotes) {
            $result = array();
            if(is_array($quotes)) {
                foreach($quotes as $quote) {
                    $result[$quote->getId()] = $this->_quoteMapper->delete($quote);
                }
            } else {
                $result[$quotes->getId()] = $this->_quoteMapper->delete($quotes);
            }
            if(!empty($result) && in_array(false, $result)) {
                $this->_error($result);
            }
            return $result;
        }
        $this->_error('Quote not found', self::REST_STATUS_NOT_FOUND);
    }

    protected function _validateAddress($address) {
        if(!is_array($address)) {
            return false;
        }
        $valid = true;
        $excludeFields = array('lastname', 'address2', 'state', 'phone', 'sameForShipping', 'productId', 'productOptions');
        foreach($address as $field => $value) {
            if(in_array($field, $excludeFields)) {
                continue;
            }
            $valid &= (bool)$value;
        }
        return $valid;
    }
}
