<?php
/**
 * Builder
 * @author: iamne <eugene@seotoaster.com> Seotoaster core team
 * Date: 9/5/12
 * Time: 1:24 PM
 */
class Quote_Tools_Tools {

    const TITLE_PREFIX = 'New quote: ';

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

        $quote->registerObserver(new Quote_Tools_Watchdog(array(
            'gateway' => new Quote(array(), array())
        )))
        ->registerObserver(new Tools_Mail_Watchdog(array(
            'trigger' => Quote_Tools_QuoteMailWatchdog::TRIGGER_QUOTE_CREATED
        )));

        $expirationDelay = Models_Mapper_ShoppingConfig::getInstance()->getConfigParam('expirationDelay');
        if(!$expirationDelay) {
            $expirationDelay = 1;
        }

        $quote = Quote_Models_Mapper_QuoteMapper::getInstance()->save(
            $quote->setId($quoteId)
                ->setStatus(Quote_Models_Model_Quote::STATUS_NEW)
                ->setTitle($quoteId)
                ->setCartId($cart->getId())
                ->setCreatedAt($date)
                ->setUpdatedAt($date)
                ->setExpiresAt(date(Tools_System_Tools::DATE_MYSQL, strtotime('+' . (($expirationDelay == 1) ? '1 day' : $expirationDelay . ' days'))))
                ->setUserId($cart->getUserId())
                ->setEditedBy($options['editedBy'])
                ->setDisclaimer($options['disclaimer'])
                ->setCreatorId($options['creatorId'])
        );
        Tools_ShoppingCart::getInstance()->clean();
        return $quote;
    }

    /**
     * Invoke cart session
     *
     * @param Quote_Models_Model_Quote $quote
     * @param array $initialProducts
     * @return Models_Model_CartSession
     */
    public static function invokeCart($quote = null, $initialProducts = array()) {
        $cart   = null;
        $mapper = Models_Mapper_CartSessionMapper::getInstance();
        if(!$quote instanceof Quote_Models_Model_Quote) {
            $cartStorage = Tools_ShoppingCart::getInstance();

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
                    if ($option['type'] == Models_Model_Option::TYPE_TEXT || $option['type'] == Models_Model_Option::TYPE_DATE) {
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

    public static function calculate($storage, $currency = true, $forceSave = false, $quoteId = null) {
        $cart             = Models_Mapper_CartSessionMapper::getInstance()->find($storage->getCartId());
        $shippingPrice    = $cart->getShippingPrice();
        $storage->setDiscount($cart->getDiscount());
        $storage->setShippingData(array('price'=>$shippingPrice));
        $storage->setDiscountTaxRate($cart->getDiscountTaxRate());
        $data             = $storage->calculate(true);

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

    public static function getValidCaptchaService() {
        $config = Zend_Controller_Action_HelperBroker::getStaticHelper('config')->getConfig();
        if (!empty($config[Tools_System_Tools::RECAPTCHA_PUBLIC_KEY]) && !empty($config[Tools_System_Tools::RECAPTCHA_PRIVATE_KEY])) {
            return Quote_Forms_Quote::CAPTCHA_SERVICE_RECAPTCHA;
        }
        return Quote_Forms_Quote::CAPTCHA_SERVICE_CAPTCHA;
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
}
