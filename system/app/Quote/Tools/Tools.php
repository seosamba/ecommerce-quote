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
            'gateway' => new Tools_PaymentGateway(array(), array())
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
                ->setTitle(self::TITLE_PREFIX . $quoteId)
                ->setCartId($cart->getId())
                ->setCreatedAt($date)
                ->setUpdatedAt($date)
                ->setExpiresAt(date(Tools_System_Tools::DATE_MYSQL, strtotime('+' . (($expirationDelay == 1) ? '1 day' : $expirationDelay . ' days'))))
                ->setUserId($cart->getUserId())
                ->setEditedBy($options['editedBy'])
                ->setDisclaimer($options['disclaimer'])
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
            return null;
        }

        foreach($optionSelectionPairs as $optionId => $selectionId) {
            foreach($allOptions as $option) {
                if($option['id'] == $optionId) {
                    if($option['type'] == Models_Model_Option::TYPE_TEXT || $option['type'] == Models_Model_Option::TYPE_DATE) {
                        $option['selection'] = $selectionId;
                        $options[] = $option;
                    } else {
                        $selection = array_filter($option['selection'], function($selection) use ($selectionId) {
                            if($selection['id'] == $selectionId) {
                                return $selection;
                            }
                        });
                        if(!is_array($selection) || empty($selection)) {
                            continue;
                        }
                        $options[] = array_shift($selection);
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
            return null;
        }
        foreach($defaultOptions as $option){
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
        return $options;
    }

    public static function parseOptionsString($optionsString) {
        $options = array();
        $parsed = array();
        parse_str($optionsString, $options);
        foreach($options as $keyString => $option) {
            $key          = preg_replace('~product-[0-9]*\-option\-([0-9])*~', '$1', $keyString);
            $parsed[$key] = $option;
        }
        return $parsed;
    }

    public static function calculate($storage, $currency = true, $forceSave = false, $quoteId = null) {
        $cart             = Models_Mapper_CartSessionMapper::getInstance()->find($storage->getCartId());
        $shippingPrice    = $cart->getShippingPrice();
        $data             = $storage->calculate(true);
        $data['discount'] = $cart->getDiscount();

        if($forceSave) {
            $storage->setDiscount($cart->getDiscount());
            $storage->saveCartSession();
        }

        unset($data['showPriceIncTax']);

        $data['total']       = ($data['total'] - $data['discount']) + $shippingPrice;
        $data['shipping']    = ($shippingPrice) ? $shippingPrice : 0;
        $data['discountTax'] = ($quoteId) ? self::calculateDiscountTax(Quote_Models_Mapper_QuoteMapper::getInstance()->find($quoteId)) : $data['totalTax'];


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
        $cart    = Models_Mapper_CartSessionMapper::getInstance()->find($quote->getCartId());
        $taxRate = $quote->getDiscountTaxRate();
        $tax     = null;
        if(!$taxRate) {
            return $cart->getTotalTax();
        }
        $rateGetter = 'getRate' . $taxRate;
        $address    = $cart->getShippingAddressId();
        if(!$address) {
            $address = $cart->getBillingAddressId();
        }
        if($address) {
            $zoneId = Tools_Tax_Tax::getZone(Tools_ShoppingCart::getAddressById($address));
            if($zoneId) {
                $tax = Models_Mapper_Tax::getInstance()->findByZoneId($zoneId);
            }
        } else {
            $tax = Models_Mapper_Tax::getInstance()->getDefaultRule();
        }

        if($tax) {
            return $cart->getTotalTax() - (($cart->getDiscount() / 100) * $tax->$rateGetter());
        }
        return $cart->getTotalTax();
    }
}
