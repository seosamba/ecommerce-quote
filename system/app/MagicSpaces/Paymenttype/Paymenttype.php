<?php

/**
 * MAGICSPACE: paymenttype
 * {paymenttype} ... {/paymenttype} - Orderstatus magicspace displays content for customers
 *
 * Class MagicSpaces_Paymenttype_Paymenttype
 */
class MagicSpaces_Paymenttype_Paymenttype extends Tools_MagicSpaces_Abstract
{
    /**
     * Payment type Magic Space
     * {paymenttype[:full_payment|full_payment_signature|partial_payment|partial_payment_signature|only_signature]]}
     * Here you can put content that will be displayed
     * {/paymenttype}
     * @return string
     */
    protected function _run()
    {

        $result = $this->_spaceContent;
        $allowedPaymentTypes = array(
            Quote_Models_Model_Quote::PAYMENT_TYPE_PARTIAL_PAYMENT_SIGNATURE,
            Quote_Models_Model_Quote::PAYMENT_TYPE_PARTIAL_PAYMENT,
            Quote_Models_Model_Quote::PAYMENT_TYPE_FULL,
            Quote_Models_Model_Quote::PAYMENT_TYPE_FULL_SIGNATURE,
            Quote_Models_Model_Quote::PAYMENT_TYPE_ONLY_SIGNATURE
        );

        $paymentType = filter_var($this->_params[0], FILTER_SANITIZE_STRING);

        if (empty($paymentType)) {
            return '';
        }

        if (!in_array($paymentType, $allowedPaymentTypes)) {
            return '';
        }


        $originalPaymentType = Quote_Models_Model_Quote::PAYMENT_TYPE_ONLY_SIGNATURE;
        if ($paymentType === Quote_Models_Model_Quote::PAYMENT_TYPE_PARTIAL_PAYMENT_SIGNATURE || $paymentType === Quote_Models_Model_Quote::PAYMENT_TYPE_PARTIAL_PAYMENT) {
            $originalPaymentType = Quote_Models_Model_Quote::PAYMENT_TYPE_PARTIAL_PAYMENT;
        }

        if ($paymentType === Quote_Models_Model_Quote::PAYMENT_TYPE_FULL || $paymentType === Quote_Models_Model_Quote::PAYMENT_TYPE_FULL_SIGNATURE) {
            $originalPaymentType = Quote_Models_Model_Quote::PAYMENT_TYPE_FULL;
        }

        $mapper = Quote_Models_Mapper_QuoteMapper::getInstance();
        $requestedUri = Tools_System_Tools::getRequestUri();
        $quote = $mapper->find(
            Zend_Controller_Action_HelperBroker::getStaticHelper('page')->clean($requestedUri)
        );

        if ($quote instanceof Quote_Models_Model_Quote) {
            $cartSessionMapper = Models_Mapper_CartSessionMapper::getInstance();
            $cartSessionModel = $cartSessionMapper->find($quote->getCartId());
            if ($cartSessionModel instanceof Models_Model_CartSession && $originalPaymentType === $quote->getPaymentType()) {
                if ($paymentType === Quote_Models_Model_Quote::PAYMENT_TYPE_FULL && !$quote->getIsSignatureRequired()) {
                    return $result;
                }

                $partialPaymentType = $cartSessionModel->getPartialType();

                if ($paymentType === Quote_Models_Model_Quote::PAYMENT_TYPE_FULL_SIGNATURE && $quote->getIsSignatureRequired()) {
                    return $result;
                }

                if ($paymentType === Quote_Models_Model_Quote::PAYMENT_TYPE_PARTIAL_PAYMENT && !$quote->getIsSignatureRequired()) {
                    if (!empty($this->_params[1])) {
                        if ($partialPaymentType === $this->_params[1]) {
                            return $result;
                        } else {
                            return '';
                        }
                    }

                    return $result;
                }

                if ($paymentType === Quote_Models_Model_Quote::PAYMENT_TYPE_PARTIAL_PAYMENT_SIGNATURE && $quote->getIsSignatureRequired()) {
                    if (!empty($this->_params[1])) {
                        if ($partialPaymentType === $this->_params[1]) {
                            return $result;
                        } else {
                            return '';
                        }
                    }

                    return $result;
                }

                if ($paymentType === Quote_Models_Model_Quote::PAYMENT_TYPE_ONLY_SIGNATURE) {
                    return $result;
                }
            }
        }

        return '';
    }

}
