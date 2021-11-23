<?php


class Quote_Tools_Tools {

    /**
     * Quote pdf template prefix
     */
    const QUOTE_PDF_TEMPLATE_PREFIX = 'pdf-';

    /**
     * Quote pdf template type
     */
    const QUOTE_PDF_TEMPLATE_TYPE = 'typepdfquote';

    const TITLE_PREFIX = 'New quote: ';

    /**
     * General files folder
     */
    const FILES_FOLDER = 'quotePdf';

    /**
     * Defined user prefixes
     * @var array
     */
    public static $userPrefixes = array(
        'Mr',
        'Mrs',
        'Ms',
        'Miss',
        'Dr'
    );

    /**
     * Create quote
     *
     * @static
     * @param Models_Model_CartSession $cart
     * @param array $options
     * @return bool|string
     */
    public static function createQuote($cart, $options = array()) {
        $quoteId = substr(md5(uniqid(time(true)) . time(true)), 0, 15);
        $date    = date(Tools_System_Tools::DATE_MYSQL);
        $quote   = new Quote_Models_Model_Quote();

        $oldPageId = 0;
        $oldQuoteId = '';
        $templateName = '';
        if (!empty($options['templateName'])) {
            $templateName = $options['templateName'];
        }

        if($options['actionType'] == Quote::QUOTE_TYPE_CLONE && !empty($options['oldPageId'] && !empty($options['oldQuoteId']))) {
            $oldPageId = $options['oldPageId'];
            $oldQuoteId = $options['oldQuoteId'];
        }

        if ($options['actionType'] == Quote::QUOTE_TYPE_CLONE) {
            $quote->registerObserver(new Quote_Tools_Watchdog(array(
                'gateway' => new Quote(array(), array()),
                'oldPageId' => $oldPageId,
                'oldQuoteId' => $oldQuoteId

            )));
        } else {
            $quote->registerObserver(new Quote_Tools_Watchdog(array(
                'gateway' => new Quote(array(), array()),
                'oldPageId' => $oldPageId,
                'oldQuoteId' => $oldQuoteId,
                'templateName' => $templateName

            )))->registerObserver(new Tools_Mail_Watchdog(array(
                    'trigger' => Quote_Tools_QuoteMailWatchdog::TRIGGER_QUOTE_CREATED
                )));
        }

        $expirationDelay = Models_Mapper_ShoppingConfig::getInstance()->getConfigParam('expirationDelay');
        if(!$expirationDelay) {
            $expirationDelay = 1;
        }

        $quoteTitle = $quoteId;
        if (!empty($options['quoteTitle'])) {
            $quoteTitle = $options['quoteTitle'];
        }

        $quote = Quote_Models_Mapper_QuoteMapper::getInstance()->save(
            $quote->setId($quoteId)
                ->setStatus(Quote_Models_Model_Quote::STATUS_NEW)
                ->setTitle($quoteTitle)
                ->setCartId($cart->getId())
                ->setCreatedAt($date)
                ->setUpdatedAt($date)
                ->setExpiresAt(date(Tools_System_Tools::DATE_MYSQL, strtotime('+' . (($expirationDelay == 1) ? '1 day' : $expirationDelay . ' days'))))
                ->setUserId($cart->getUserId())
                ->setEditedBy($options['editedBy'])
                ->setDisclaimer($options['disclaimer'])
                ->setCreatorId($options['creatorId'])
                ->setIsSignatureRequired('0')
                ->setIsQuoteSigned('0')
                ->setPaymentType(Quote_Models_Model_Quote::PAYMENT_TYPE_FULL)
        );
        Tools_ShoppingCart::getInstance()->clean();
        return $quote;
    }

    /**
     * Invoke cart session
     *
     * @param Quote_Models_Model_Quote $quote
     * @param array $initialProducts
     * @param bool $forceCreateNewCart force to add products to the cart
     * @return Models_Model_CartSession
     */
    public static function invokeCart($quote = null, $initialProducts = array(), $forceCreateNewCart = false) {
        $cart   = null;
        $mapper = Models_Mapper_CartSessionMapper::getInstance();
        if(!$quote instanceof Quote_Models_Model_Quote || $forceCreateNewCart === true) {
            $cartStorage = Tools_ShoppingCart::getInstance();
            if ($forceCreateNewCart === true) {
                $cartStorage->setContent(array());
                $cartStorage->setCartId(null);
            }

            if(is_array($initialProducts) && !empty($initialProducts)) {
                foreach($initialProducts as $initialData) {
                    if(!$initialData['product'] instanceof Models_Model_Product) {
                        continue;
                    }
                    $cartStorage->add($initialData['product'], $initialData['options']);
                }
            }

            $cartStorage->saveCartSession();
            $cart        = $mapper->find($cartStorage->getCartId());
        } else {
            $cart = $mapper->find($quote->getCartId());
        }
        return ($cart === null) ? new Models_Model_CartSession() : $cart;
    }

    /**
     * Invoke customer
     *
     * @deprecated
     * @static
     * @param integer|Quote_Models_Model_Quote|string $option Could be either an user id or instance of the quote of user's e-mail
     * @return null|Models_Model_Customer
     * @throws Exceptions_SeotoasterPluginException
     */
    public static function invokeCustomer($option) {
        $customer = null;
        $mapper   = Models_Mapper_CustomerMapper::getInstance();
        if(is_integer($option)) {
            $customer = $mapper->find($option);
        } elseif($option instanceof Quote_Models_Model_Quote) {
            $customer = $mapper->find($option->getUserId());
        } elseif(is_string($option)) {
            $customer = $mapper->findByEmail($option);
        } else {
            throw new Exceptions_SeotoasterPluginException('Wrong option type. e-mail, quote or integer expected');
        }
        return $customer;
    }

    public static function addAddress($addressData, $type = Models_Model_Customer::ADDRESS_TYPE_BILLING, $customer = null) {
        if($customer === null) {
            $customer = Shopping::processCustomer($addressData);
        }
        return Models_Mapper_CustomerMapper::getInstance()->addAddress($customer, $addressData, $type);
    }

    public static function getProductOptions(Models_Model_Product $product, $optionSelectionPairs = array()) {
        if(empty($optionSelectionPairs)) {
            return self::getProductDefaultOptions($product);
        }

        $options    = array();

        // get all available product options
        $allOptions = $product->getDefaultOptions();

        if(empty($allOptions)) {
            return $options;
        }

        foreach($optionSelectionPairs as $selectionId) {
            foreach($allOptions as $option) {
                if ($option['id'] == $selectionId['option_id']) {
                    if ($option['type'] == Models_Model_Option::TYPE_TEXT || $option['type'] == Models_Model_Option::TYPE_DATE || $option['type'] == Models_Model_Option::TYPE_TEXTAREA) {
                        $option['selection'] = $selectionId;
                        $options[] = $option;
                    } else {
                        $selection = array_filter($option['selection'], function($selection) use ($selectionId) {
                            if ($selection['id'] == $selectionId['id']) {
                                return $selection;
                            }
                        });
                        if(!is_array($selection) || empty($selection)) {
                            continue;
                        }
                        $options[$option['title']] = array_shift($selection);
                    }
                }
            }
        }
        return $options;
    }

    public static function getProductDefaultOptions(Models_Model_Product $product, $flat = true) {
        $options        = array();
        $defaultOptions = $product->getDefaultOptions();
        if(!is_array($defaultOptions) || empty($defaultOptions)) {
            return $options;
        }
        foreach($defaultOptions as $option){
            if(!empty($option['selection'])){
                foreach ($option['selection'] as $item) {
                    if($item['isDefault'] == 1) {
                        if(!$flat) {
                            $options[] = $item;
                        } else {
                            $options[$option['id']] = $item['id'];
                        }
                    }
                }
            }
        }
        return $options;
    }

    public static function parseOptionsString($optionsString) {
        $options = array();
        $parsed = array();
        parse_str($optionsString, $options);
        foreach($options as $keyString => $option) {
            $key          = preg_replace('~product-[0-9]*\-option\-([^0-9]*)*~', '$1', $keyString);
            $parsed[$key] = $option;
        }
        return $parsed;
    }

    public static function calculate($storage, $currency = true, $forceSave = false, $quoteId = null, $skipGroupPriceRecalculation = false) {
        $cart             = Models_Mapper_CartSessionMapper::getInstance()->find($storage->getCartId());
        $shippingPrice    = $cart->getShippingPrice();
        $shippingService  = $cart->getShippingService();
        $shippingType     = $cart->getShippingType();
        $storage->setDiscount($cart->getDiscount());

        $storage->setShippingData(array(
            'price'   => $shippingPrice,
            'service' => $shippingService,
            'type'    => $shippingType
        ));
        $storage->setDiscountTaxRate($cart->getDiscountTaxRate());
        $data             = $storage->calculate(true, $skipGroupPriceRecalculation);

        if($forceSave) {
            $storage->saveCartSession(Models_Mapper_CustomerMapper::getInstance()->find($cart->getUserId()));
        }

        unset($data['showPriceIncTax']);
        $shoppingConfig = Models_Mapper_ShoppingConfig::getInstance()->getConfigParams();
        if(isset($shoppingConfig['showPriceIncTax']) && $shoppingConfig['showPriceIncTax'] === '1'){
            $data['subTotal']    = $data['subTotal'] + $data['subTotalTax'];
        }
        $data['discountWithTax'] = $data['discount'] + $data['discountTax'];
        $data['shippingWithTax'] = $data['shipping'] + $data['shippingTax'];
        if(!$currency) {
            return $data;
        }
        $currency = Zend_Registry::get('Zend_Currency');
        foreach($data as $key => $value) {
            $data[$key] = $currency->toCurrency($value);
        }
        return $data;
    }

    public static function invokeQuoteStorage($quoteId) {
        $mapper = Quote_Models_Mapper_QuoteMapper::getInstance();
        $quote  = $mapper->find($quoteId);
        if(!$quote instanceof Quote_Models_Model_Quote) {
            throw new Exceptions_NewslogException('Quote cannot be found');
        }
        $cart = Models_Mapper_CartSessionMapper::getInstance()->find($quote->getCartId());
        if(!$cart instanceof Models_Model_CartSession) {
            throw new Exceptions_NewslogException('Requested quote has no cart');
        }
        $storage = Tools_ShoppingCart::getInstance();
        $storage->restoreCartSession($quote->getCartId());
        $storage->setCustomerId($quote->getUserId())
            ->setAddressKey(Models_Model_Customer::ADDRESS_TYPE_BILLING, $cart->getBillingAddressId())
            ->setAddressKey(Models_Model_Customer::ADDRESS_TYPE_SHIPPING, $cart->getShippingAddressId());
        return $storage;
    }

    public static function calculateDiscountTax(Quote_Models_Model_Quote $quote) {
        $cart     = Models_Mapper_CartSessionMapper::getInstance()->find($quote->getCartId());
        $taxRate  = $quote->getDiscountTaxRate();
        $tax      = null;
        $totalTax = $cart->getTotalTax();
        if(!$taxRate) {
            return $totalTax;
        }
        $rateGetter = 'getRate' . $taxRate;
        $address    = $cart->getShippingAddressId();
        if(!$address) {
            $address = $cart->getBillingAddressId();
            if(!$address) {
                $address = Models_Mapper_ShoppingConfig::getInstance()->getConfigParams();
            }
        }
        if($address) {
            $zoneId = Tools_Tax_Tax::getZone(!is_array($address) ? Tools_ShoppingCart::getAddressById($address) : $address);
            if($zoneId) {
                $tax = Models_Mapper_Tax::getInstance()->findByZoneId($zoneId);
            }
        } else {
            $tax = Models_Mapper_Tax::getInstance()->getDefaultRule();
        }

        if($tax) {
            return ($totalTax == 0) ? $totalTax : ($cart->getTotalTax() - (($cart->getDiscount() / 100) * $tax->$rateGetter()));
        }
        return $cart->getTotalTax();
    }

    public static function getEmailData(array $roles) {
        $where = null;
        if(!empty($roles)) {
           $where = implode(' OR ', array_map(function($role) { return "`role_id` = '" . $role . "'"; }, $roles));
        }
        $sql        = "SELECT `full_name`, `email` FROM `user` WHERE (" . $where . ");";
        $usersTable = new Application_Model_DbTable_User();
        $data       = $usersTable->getAdapter()->fetchPairs($sql);
        return is_array($data) ? $data : array();
    }

    public static function adjustFormFields(Quote_Forms_Quote $form, $options = array(), $mandatoryFields = array()) {
        if(empty($options)) {
            return $form;
        }

        $currentElements = $form->getElements();

        // fields that should stay
        $fields = array();
        foreach($options as $field) {
            $required = false;
            if(substr($field, strlen($field)-1) == '*') {
                $required = true;
                $field    = str_replace('*', '', $field);
            }
            $fields[$field] = $required;
        }

        foreach($currentElements as $element) {
            $form->removeElement($element->getName());
        }

        $fields = array_merge($fields, $mandatoryFields);
        foreach($fields as $name => $required) {
            if(!array_key_exists($name, $currentElements)) {
                continue;
            }
            $currentElements[$name]->setAttribs(array(
                'class' => ($required) ? 'quote-required required' : 'quote-optional optional'
            ))->setRequired($required);
            $form->addElement($currentElements[$name]);
        }

        $displayGroups = $form->getDisplayGroups();
        array_walk($displayGroups, function($dGroup) use($form) {
            $form->removeDisplayGroup($dGroup->getName());
        });

        if (!array_key_exists('mobilecountrycode', $fields) && array_key_exists('mobile', $fields)) {
            $form->getElement('mobile')->setLabel('Mobile');
        }

        if (!array_key_exists('phonecountrycode', $fields) && array_key_exists('phone', $fields)) {
            $form->getElement('phone')->setLabel('Phone');
        }

        return $form;
    }

    public static function getTaxFromProductPrice(Models_Model_Product $product, $destinationAddress){
        if(($taxClass = $product->getTaxClass()) != 0) {
            $rateMethodName = 'getRate' . $taxClass;

            if (null !== $destinationAddress){
                $zoneId = Tools_Tax_Tax::getZone($destinationAddress);
                if ($zoneId) {
                    $tax = Models_Mapper_Tax::getInstance()->findByZoneId($zoneId);
                }
            } else {
                $tax = Models_Mapper_Tax::getInstance()->getDefaultRule();
            }

            if (isset($tax) && $tax !== null) {
                $productPrice = $product->getPrice();
                return ($productPrice - $productPrice /(1 + ($tax->$rateMethodName()/100)));
            }
        }
        return 0;
    }

    /**
     * @param Quote_Models_Model_Quote $quote
     * @return bool
     */
    public static function checkExpired(Quote_Models_Model_Quote $quote) {
        $today   = new DateTime(date('Y-m-d'));
        $expDate = new DateTime($quote->getExpiresAt());
        return ($expDate < $today);
    }

    /**
     * Create customer if not exists. Based on user info array data.
     *
     * @param array $data user info data
     * @return Models_Model_Customer
     */
     public static function processCustomer(array $data)
     {
         if (null === ($customer = Models_Mapper_CustomerMapper::getInstance()->findByEmail($data['email']))) {
             $fullName = isset($data['firstname']) ? $data['firstname'] : '';
             $fullName .= isset($data['lastname']) ? ' ' . $data['lastname'] : '';
             $mobilePhone = isset($data['mobile']) ? $data['mobile'] : '';
             $desktopPhone = isset($data['phone']) ? $data['phone'] : '';
             $mobileCountryCode = isset($data['mobilecountrycode']) ? $data['mobilecountrycode'] : '';
             $mobileCountryCodeValue = isset($data['mobile_country_code_value']) ? $data['mobile_country_code_value'] : null;
             $phoneCountryCode = isset($data['phonecountrycode']) ? $data['phonecountrycode'] : '';
             $phoneCountryCodeValue = isset($data['phone_country_code_value']) ? $data['phone_country_code_value'] : null;

             $configHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('config');
             $userDefaultTimezone = $configHelper->getConfig('userDefaultTimezone');

             $password = md5(uniqid('customer_' . time()));
             $customer = new Models_Model_Customer();
             if (empty($data['timezone']) && !empty($userDefaultTimezone)) {
                 $customer->setTimezone($userDefaultTimezone);
             }

             $customer->setRoleId(Shopping::ROLE_CUSTOMER)
                 ->setEmail($data['email'])
                 ->setFullName($fullName)
                 ->setIpaddress($_SERVER['REMOTE_ADDR'])
                 ->setMobilePhone($mobilePhone)
                 ->setMobileCountryCode($mobileCountryCode)
                 ->setMobileCountryCodeValue($mobileCountryCodeValue)
                 ->setDesktopPhone($desktopPhone)
                 ->setDesktopCountryCode($phoneCountryCode)
                 ->setDesktopCountryCodeValue($phoneCountryCodeValue)
                 ->setPassword($password);
             $newCustomerId = Models_Mapper_CustomerMapper::getInstance()->save($customer);
             if ($newCustomerId) {
                 $customer->setId($newCustomerId);
             }
         }

         return $customer;
     }

    /**
     * Remove all non digits
     *
     * @param string $number
     * @return mixed
     */
    public static function cleanNumber($number)
    {
        return preg_replace('~[^\d]~ui', '', $number);
    }

    /**
     * @param $item
     * @param array $options
     * @return bool|string
     */
    public static function generateStorageKey($item, $options = array()) {
        if($item instanceof Models_Model_Product){
            return substr(md5($item->getName() . $item->getSku() . http_build_query($options)), 0, 10);
        }else{
            return substr(md5($item['name'] . $item['sku'] . http_build_query($options)), 0, 10);
        }


    }

    /**
     * Get quote pdf template by quote page url
     *
     * @param string $quotePageUrl quote page url
     * @return string
     */
    public static function findPdfTemplateByQuoteUrl($quotePageUrl)
    {
        $pageModel = Application_Model_Mappers_PageMapper::getInstance()->findByUrl($quotePageUrl);
        if ($pageModel instanceof Application_Model_Models_Page) {
            $quotePdfTemplate = Quote_Tools_Tools::findQuotePdfTemplate($pageModel->getTemplateId());
            return $quotePdfTemplate;
        }

        return '';
    }

    /**
     * find quote pdf template
     *
     * @param string $templateName quote template
     * @return string
     */
    public static function findQuotePdfTemplate($templateName)
    {
        $quotePdfTemplateName = self::QUOTE_PDF_TEMPLATE_PREFIX.$templateName;
        $quotePdfTemplate = Application_Model_Mappers_TemplateMapper::getInstance()->find($quotePdfTemplateName);
        if ($quotePdfTemplate instanceof Application_Model_Models_Template) {
            if ($quotePdfTemplate->getType() === self::QUOTE_PDF_TEMPLATE_TYPE) {
                return $quotePdfTemplate->getName();
            }
        }

        return '';
    }

    /**
     * @param Zend_Form $form
     * @param $fieldsParams
     * @return Zend_Form
     * @throws Zend_Exception
     * @throws Zend_Form_Exception
     */
    public static function addFormFields (Zend_Form $form, $fieldsParams) {
        $translator = Zend_Registry::get('Zend_Translate');

        if(!empty($fieldsParams)) {
            switch ($fieldsParams['param_type']) {
                case 'text':
                    $form->addElement(new Zend_Form_Element_Text(array(
                        'name'  => $fieldsParams['param_name'],
                        'id'    => $fieldsParams['param_name'],
                        'label' => $translator->translate($fieldsParams['label']),
                        //'placeholder' => $translator->translate($fieldsParams['label']),
                        'value' => !empty($fieldsParams['value']) ? $fieldsParams['value'] : '',
                        'decorators' => array(
                            'ViewHelper',
                            array('Label'),
                            array('HtmlTag', array('tag' => 'p'))
                        )
                    )));

                    break;
                case 'select':
                    $optionValues = array();
                    if(!empty($fieldsParams['option_values']) && !empty($fieldsParams['option_ids'])) {
                        $optionValues = explode(',', $fieldsParams['option_values']);
                        $optionIds = explode(',', $fieldsParams['option_ids']);

                        $optionValues = array_combine($optionIds, $optionValues);
                    }

                    $form->addElement(new Zend_Form_Element_Select(array(
                        'name'         => $fieldsParams['param_name'],
                        'id'           => $fieldsParams['param_name'],
                        'label'        => $translator->translate($fieldsParams['label']),
                        'multiOptions' => array('' => $translator->translate('Select')) + $optionValues,
                        'value' => !empty($fieldsParams['value']) ? $fieldsParams['value'] : '',
                        'decorators' => array(
                            'ViewHelper',
                            array('Label'),
                            array('HtmlTag', array('tag' => 'p'))
                        )
                    )));

                    break;
                case 'radio':
                    $optionValues = array();
                    if(!empty($fieldsParams['option_values'])) {
                        $optionValues = explode(',', $fieldsParams['option_values']);
                    }

                    $form->addElement(new Zend_Form_Element_Radio(array(
                        'name'  => $fieldsParams['param_name'],
                        'id'    => $fieldsParams['param_name'],
                        'label' => $translator->translate($fieldsParams['label']),
                        //'value' => !empty($fieldsParams['value']) ? $fieldsParams['value'] : '',
                        'multiOptions' => $optionValues,
                        'decorators' => array(
                            'ViewHelper',
                            array('Label'),
                            array('HtmlTag', array('tag' => 'p'))
                        )
                    )));

                    break;
                case 'textarea':
                    $form->addElement(new Zend_Form_Element_Textarea(array(
                        'name'  => $fieldsParams['param_name'],
                        'id'    => $fieldsParams['param_name'],
                        'label' => $translator->translate($fieldsParams['label']),
                        'cols'  => '15',
                        'rows'  => '4',
                        //'value' => !empty($fieldsParams['value']) ? $fieldsParams['value'] : '',
                        'decorators' => array(
                            'ViewHelper',
                            array('Label'),
                            array('HtmlTag', array('tag' => 'p'))
                        )
                    )));

                    break;
                case 'checkbox':
                    $form->addElement(new Zend_Form_Element_Checkbox(array(
                        'name'  => $fieldsParams['param_name'],
                        'id'    => $fieldsParams['param_name'],
                        'label' => $translator->translate($fieldsParams['label']),
                        //'value' => !empty($fieldsParams['value']) ? $fieldsParams['value'] : '',
                        'decorators' => array(
                            'ViewHelper',
                            array('Label'),
                            array('HtmlTag', array('tag' => 'p'))
                        )
                    )));

                    break;
                default:
                    return $form;
            }
        }

        return $form;
    }

    /**
     * Get path to file
     *
     * @param string $fileStoredName file stored name
     * @return string
     */
    public static function getFilePath($fileStoredName)
    {
        $websiteHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('website');

        return $websiteHelper->getPath() . 'plugins' . DIRECTORY_SEPARATOR . 'quote' .
            DIRECTORY_SEPARATOR . self::FILES_FOLDER
            . DIRECTORY_SEPARATOR . $fileStoredName;

    }

    /**
     * Generate stored and hash
     *
     * @param array $file file info
     * @param bool $uniqueHash unique hash flag
     * @return array
     * @throws Zend_Exception
     */
    public static function generateStoredName($file, $uniqueHash = true)
    {
        $translator = Zend_Registry::get('Zend_Translate');
        $storedData = array();

        preg_match('~[^\x00-\x1F"<>\|:\*\?/]+\.[\w\d]{2,8}$~iU', $file['name'], $match);
        if (!$match) {
            $storedData['error'] = array('result' => $translator->translate('Corrupted filename'), 'error' => 1);
        }

        $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fileName = pathinfo($file['name'], PATHINFO_FILENAME);

        $storedData['fileExtension'] = $fileExtension;
        $storedData['fileName'] = $fileName;
        if ($uniqueHash === true) {
            $storedData['fileStoredName'] = self::generateUniqueFileStoredName($fileName, $fileExtension);
            $storedData['fileHash'] = self::generateUniqueHash($fileName);
        } else {
            $storedData['fileStoredName'] = self::generateFileStoredName($fileName, $fileExtension);
            $storedData['fileHash'] = self::generateHash($fileName);
        }

        return $storedData;
    }


    /**
     * Generate unique file hash name
     *
     * @param string $fileName file name
     * @return string
     */
    public static function generateUniqueHash($fileName)
    {
        return sha1($fileName . uniqid(microtime()));
    }

    /**
     * Generate file hash name
     *
     * @param string $fileName file name
     * @return string
     */
    public static function generateHash($fileName)
    {
        return sha1($fileName);
    }

    /**
     * Generate unique file stored hash name
     *
     * @param string $fileName file name
     * @param string $fileExtension file extension
     * @return string
     */
    public static function generateUniqueFileStoredName($fileName, $fileExtension)
    {
        return self::generateUniqueHash($fileName) . '.' . $fileExtension;
    }

    /**
     * Generate  file stored hash name
     *
     * @param string $fileName file name
     * @param string $fileExtension file extension
     * @return string
     */
    public static function generateFileStoredName($fileName, $fileExtension)
    {
        return self::generateHash($fileName) . '.' . $fileExtension;
    }

    /**
     * Copy existing quote
     *
     * @param string $quoteId quote it
     * @return array
     * @throws Exceptions_SeotoasterPluginException
     * @throws Zend_Exception
     */
    public static function copyQuote($quoteId)
    {
        $translator = Zend_Registry::get('Zend_Translate');
        $quoteMapper = Quote_Models_Mapper_QuoteMapper::getInstance();
        $cartMapper = Models_Mapper_CartSessionMapper::getInstance();
        $shoppingConfigMapper = Models_Mapper_ShoppingConfig::getInstance();
        $quoteTitle = '';
        $type = 'clone';

        if (!empty($quoteId)) {
            $quote = $quoteMapper->find($quoteId);
            if ($quote instanceof Quote_Models_Model_Quote) {
                $cartId = $quote->getCartId();
                if (!empty($cartId)) {
                    $quoteCustomParamsDataMapper = Quote_Models_Mapper_QuoteCustomParamsDataMapper::getInstance();

                    $quoteCustomParamsData = $quoteCustomParamsDataMapper->findByCartId($cartId);

                    $currentCart = $cartMapper->find($cartId);
                    if ($currentCart instanceof Models_Model_CartSession) {
                        $currentCart->setId(null);
                        $currentCart->setStatus(Quote_Models_Model_Quote::STATUS_NEW);
                        $currentCart->setPartialPaidAmount('0');
                        $currentCart->setPurchasedOn(null);
                        $currentCart->setPartialPurchasedOn(null);
                        $cart = $cartMapper->save($currentCart);

                        $newCartId = $cart->getId();
                        if (!empty($quoteCustomParamsData)) {
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


                $quotePage = Application_Model_Mappers_PageMapper::getInstance()->findByUrl($quoteId . '.html');

                if ($quotePage instanceof Application_Model_Models_Page) {
                    $oldPageId = $quotePage->getId();
                }

            }
        }

        $currentUser = Application_Model_Mappers_UserMapper::getInstance()->find(Zend_Controller_Action_HelperBroker::getStaticHelper('session')->getCurrentUser()->getId());

        if ($currentUser instanceof Application_Model_Models_User) {
            $editedBy = $currentUser->getFullName();
            $creatorId = $currentUser->getId();
        } else {
            $editedBy = Shopping::ROLE_CUSTOMER;
            $creatorId = 0;
        }

        $options = array(
            'editedBy' => $editedBy,
            'creatorId' => $creatorId,
            'disclaimer' => '',
            'actionType' => $type,
            'oldQuoteId' => $quoteId,
            'oldPageId' => $oldPageId,
            'quoteTitle' => $quoteTitle
        );

        if (!empty($templateName)) {
            $options['templateName'] = $templateName;
        }

        $quote = Quote_Tools_Tools::createQuote($cart, $options);

        if ($quote instanceof Quote_Models_Model_Quote) {
            $quoteData = $quote->toArray();
            $ownerInfo = Quote_Models_Mapper_QuoteMapper::getInstance()->getOwnerInfo($quoteData['id']);
            if (!empty($ownerInfo)) {
                $quoteData['ownerName'] = $ownerInfo['ownerName'];
            }

            $enableQuoteDefaultType = $shoppingConfigMapper->getConfigParam('enableQuoteDefaultType');
            if (!empty($enableQuoteDefaultType) && $type === Quote::QUOTE_TYPE_GENERATE) {
                $quotePaymentType = $shoppingConfigMapper->getConfigParam('quotePaymentType');
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
                    $quoteMapper->save($quote);
                }
            }

            return $quoteData;
        }
    }


    public static function replaceQuote($fromQuoteId, $toQuoteId)
    {
        $translator = Zend_Registry::get('Zend_Translate');
        $quoteMapper = Quote_Models_Mapper_QuoteMapper::getInstance();
        $fromQuoteModel = $quoteMapper->find($fromQuoteId);
        if (!$fromQuoteModel instanceof Quote_Models_Model_Quote) {
            return array('error' => '1', 'message' => $translator->translate('Original quote not found'));
        }

        $quoteToModel = $quoteMapper->find($toQuoteId);
        if (!$quoteToModel instanceof Quote_Models_Model_Quote) {
            return array('error' => '1', 'message' => $translator->translate('Quote to not found'));
        }

        $fromCartId = $fromQuoteModel->getCartId();
        $quoteToCartId = $quoteToModel->getCartId();

        $cartMapper = Models_Mapper_CartSessionMapper::getInstance();
        $fromCartModel = $cartMapper->find($fromCartId);
        if (!$fromCartModel instanceof Models_Model_CartSession) {
            return array('error' => '1', 'message' => $translator->translate('Original cart not found'));
        }

        $cartToModel = $cartMapper->find($quoteToCartId);
        if (!$cartToModel instanceof Models_Model_CartSession) {
            return array('error' => '1', 'message' => $translator->translate('Cart to not found'));
        }

        $cartToStatus = $cartToModel->getStatus();
        if ($cartToStatus !== Models_Model_CartSession::CART_STATUS_PARTIAL) {
            return array('error' => '1', 'message' => $translator->translate('You can replace only partially paid quotes!'));
        }

        //Copy cart content and current total price
        $fromCartContent = $fromCartModel->getCartContent();
        $fromTotal = $fromCartModel->getTotal();
        $fromDiscount = $fromCartModel->getDiscount();
        $fromSubTotal = $fromCartModel->getSubTotal();
        $fromTotalTax = $fromCartModel->getTotalTax();
        $fromSubTotalTax = $fromCartModel->getSubTotalTax();
        $fromDiscountTax = $fromCartModel->getDiscountTax();
        $fromDiscountTaxRate = $fromCartModel->getDiscountTaxRate();

        $cartToModel->setCartContent($fromCartContent);
        $cartToModel->setTotal($fromTotal);
        $cartToModel->setDiscount($fromDiscount);
        $cartToModel->setSubTotal($fromSubTotal);
        $cartToModel->setTotalTax($fromTotalTax);
        $cartToModel->setSubTotalTax($fromSubTotalTax);
        $cartToModel->setDiscountTax($fromDiscountTax);
        $cartToModel->setDiscountTaxRate($fromDiscountTaxRate);

        //prepare partially payed founds
        //set always to payment type "amount"
        $cartToModel->setPartialType(Models_Model_CartSession::CART_PARTIAL_PAYMENT_TYPE_AMOUNT);
        $cartToModel->setPartialPercentage($cartToModel->getPartialPaidAmount());
        $cartMapper->save($cartToModel);

        $quoteToModel->setUpdatedAt(date(Tools_System_Tools::DATE_MYSQL));

        return array('error' => '0');
    }
}
