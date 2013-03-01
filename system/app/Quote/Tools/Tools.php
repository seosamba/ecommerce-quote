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
                foreach($initialProducts as $product) {
                    if(!$product instanceof Models_Model_Product) {
                        continue;
                    }
                    $cartStorage->add($product);
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

}
