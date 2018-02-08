<?php

class Quote_Tools_PurchaseWatchdog implements Interfaces_Observer
{

    /**
     * Allowed statuses
     *
     * @var array
     */
    public static $_allowedStatuses = array(
        Models_Model_CartSession::CART_STATUS_COMPLETED,
        Models_Model_CartSession::CART_STATUS_SHIPPED,
        Models_Model_CartSession::CART_STATUS_DELIVERED,
        Models_Model_CartSession::CART_STATUS_REFUNDED,
        Models_Model_CartSession::CART_STATUS_CANCELED
    );

    /**
     * Cart statuses for the lost quote
     *
     * @var array
     */
    public static $_cartQuoteLostStatuses = array(
        Models_Model_CartSession::CART_STATUS_REFUNDED,
        Models_Model_CartSession::CART_STATUS_CANCELED
    );

    /**
     * Cart statuses for the sold quote
     *
     * @var array
     */
    public static $_cartQuoteSoldStatuses = array(
        Models_Model_CartSession::CART_STATUS_COMPLETED,
        Models_Model_CartSession::CART_STATUS_SHIPPED,
        Models_Model_CartSession::CART_STATUS_DELIVERED
    );


    /**
     * Change quote status based on purchase status
     *
     * @param Models_Model_CartSession $cartSessionModel cart session model
     * @return string
     */
    public function notify($cartSessionModel)
    {
        if ($cartSessionModel instanceof Models_Model_CartSession) {
            $cartStatus = $cartSessionModel->getStatus();
            $cartId = $cartSessionModel->getId();
            if (in_array($cartStatus, self::$_allowedStatuses, true)) {
                $quoteMapper = Quote_Models_Mapper_QuoteMapper::getInstance();
                $quoteModel = $quoteMapper->findByCartId($cartId);
                if ($quoteModel instanceof Quote_Models_Model_Quote) {
                    $currentQuoteModelStatus = $quoteModel->getStatus();
                    if (in_array($cartStatus, self::$_cartQuoteLostStatuses,
                            true) && $currentQuoteModelStatus !== Quote_Models_Model_Quote::STATUS_LOST) {
                        if ($cartStatus === Models_Model_CartSession::CART_STATUS_REFUNDED) {
                            $total = $cartSessionModel->getTotal();
                            if ($total !== '0.00') {
                                return '';
                            }
                        }
                        $quoteModel->setStatus(Quote_Models_Model_Quote::STATUS_LOST);
                        $quoteModel->setUpdatedAt(date(Tools_System_Tools::DATE_MYSQL));
                        $quoteMapper->save($quoteModel);
                    }

                    if (in_array($cartStatus, self::$_cartQuoteSoldStatuses,
                            true) && $currentQuoteModelStatus !== Quote_Models_Model_Quote::STATUS_SOLD) {
                        $quoteModel->setStatus(Quote_Models_Model_Quote::STATUS_SOLD);
                        $quoteModel->setUpdatedAt(date(Tools_System_Tools::DATE_MYSQL));
                        $quoteMapper->save($quoteModel);
                    }
                }

            }

        }

    }
}

